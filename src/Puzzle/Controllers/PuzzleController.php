<?php

namespace Lambda\Puzzle\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lambda\Dataform\Dataform;
use Lambda\Datagrid\Datagrid;
use Lambda\DataSource\DataSource;
use Lambda\Puzzle\Puzzle;
use Illuminate\Support\Facades\Config;

define('MAX_FILE_LIMIT', 1024 * 1024 * 2);

class PuzzleController extends Controller
{
    public function index()
    {
        $dbSchema = Puzzle::getDBSchema();
        $gridList = DB::table('vb_schemas')->where('type', 'grid')->get();
        $config = Config::get('lambda');
        $user_fields = $config['user_data_fields'];

        return view('puzzle::index', compact('dbSchema', 'gridList', 'user_fields'));
    }

    public function builder()
    {
        return view('puzzle::builder');
    }

    function sanitizeFileName($file)
    {
        //sanitize, remove double dot .. and remove get parameters if any
//        $file = __DIR__ . '/' . preg_replace('@\?.*$@' , '', preg_replace('@\.{2,}@' , '', preg_replace('@[^\/\\a-zA-Z0-9\-\._]@', '', $file)));
        $file = preg_replace('@\?.*$@' , '', preg_replace('@\.{2,}@' , '', preg_replace('@[^\/\\a-zA-Z0-9\-\._]@', '', $file)));
        return public_path('/' . $file);
    }

    function savePage(){
        $html = "";
        if (isset($_POST['startTemplateUrl']) && !empty($_POST['startTemplateUrl']))
        {
            $startTemplateUrl = $this->sanitizeFileName($_POST['startTemplateUrl']);
            $html = file_get_contents($startTemplateUrl);
        } else if (isset($_POST['html']))
        {
            $html = substr($_POST['html'], 0, MAX_FILE_LIMIT);
        }

        $file = $this->sanitizeFileName($_POST['file']);

        if (file_put_contents($file, $html)) {
            echo "<strong>Амжилттай хадгалагдлаа</strong> <br/> $file";
        } else {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
            echo "Error saving file  $file\nPossible causes are missing write permission or incorrect file path!";
        }

    }

    public function embed()
    {
        $dbSchema = Puzzle::getDBSchema();
        $gridList = DB::table('vb_schemas')->where('type', 'grid')->get();

        return view('puzzle::embed', compact('dbSchema', 'gridList'));
    }

    public function dbSchema($table = false)
    {
        return $table == false ? VB::tables() : VB::tableMeta($table);
    }

    //Chart function

    //Visual builder
    public function getVB($type, $id = false, $condition = null)
    {
        $qr = DB::table('vb_schemas')->where('type', $type);

        if (strpos($id, '_') === false) {
            $data = $id === false ? $qr->orderBy('created_at', 'desc')->get() : $qr->where('id', $id)->first();
        } else {
            $qr = DB::table('vb_schemas_admin')->where('type', $type);
            $data = $qr->where('id', $id)->first();
        }

        $user_condition = [];
        //Filling option data
        if ($type == 'form' && $id != false) {
            $user = null;
            if ($condition) {
                if (Auth::user()) {
                    $user = Auth::user();
                    $user = $user->toArray();
                    $condition = json_decode($condition, true);
                    if ($user && $condition) {
                        foreach ($condition as $u_condition) {
                            $user_condition[$u_condition['form_field']] = $user[$u_condition['user_field']];
                        }
                        $schema = json_decode($data->schema);

                        if ($condition != 'builder') {
                            foreach ($schema->schema as &$s) {
                                foreach ($user_condition as $key => $value) {
                                    if ($s->model == $key) {
                                        $s->default = $value;
                                        $s->disabled = true;
                                    }
                                }
                            }
                        }

                        $schema->ui->schema = $this->setUserCondition($schema->ui->schema, $user_condition);
                        $data->schema = json_encode($schema);
                    }
                } else {
                    return redirect('auth/login');
                }
            }
        }

        if ($data) {
            return response()->json(['status' => true, 'data' => $data]);
        }
        return response()->json(['status' => false]);
    }

    public function getOptions()
    {
        $relations = request()->relations;

        $f = new Dataform();
        $data = [];
        foreach ($relations as $key => $relation) {
            $data[$key] = $f->options((object)$relation);
        }

        return $data;
    }

    public function setUserCondition($schema_ui, $use_condition)
    {
        foreach ($schema_ui as &$ui) {
            if ($ui->type == 'form') {
                foreach ($use_condition as $key => $value) {
                    if ($ui->model == $key) {
                        $ui->default = $value;
                        $ui->disabled = true;
                    }
                }
            }

            if (isset($ui->children)) {
                $ui->children = $this->setUserCondition($ui->children, $use_condition);
            }
        }

        return $schema_ui;
    }

    public function saveVB($type, $id = false)
    {
        $qr = DB::table('vb_schemas');
        if (strpos($id, '_') !== false) {
            $qr = DB::table('vb_schemas_admin');
        }
        $data = [
            'name' => request()->name,
            'type' => $type,
            'schema' => request()->schema,
        ];
        $action = $id ? 'update' : 'insert';

        $this->beforeAction($action, $data, $id);

        if ($id == false) {
            $r = $qr->insert($data);

            $id = DB::getPdo()->lastInsertId();
        } else {
            $r = $qr->where('id', $id)->update($data);

            $r >= 0 ? $r = true : $r = false;
        }

        if ($r) {
            $this->afterAction($action, $data, $id);

            return response()->json(['status' => true]);
        }

        return response()->json(['status' => false]);
    }

    public function deleteVB($table, $type, $id)
    {
        $this->beforeAction('delete', ['type' => $type], $id);
        $r = DB::table($table)->delete($id);
        if ($r) {
            $this->afterAction('delete', ['type' => $type], $id);

            return response()->json(['status' => true]);
        }

        return response()->json(['status' => false]);
    }

    public function formVB($action, $schemaID)
    {
        return Dataform::exec($schemaID, $action, null);
    }

    public function gridVB($action, $schemaID)
    {
        return Datagrid::exec($action, $schemaID);
    }

    public function fileUpload()
    {
        return Dataform::upload();
    }

    public function afterAction($action, $data, $id)
    {
        if ($data['type'] == 'datasource') {
            DataSource::viewHandler('after', $action, $data, $id);
        }
    }

    public function beforeAction($action, $data, $id)
    {
        if ($data['type'] == 'datasource') {
            DataSource::viewHandler('before', $action, $data, $id);
        }
    }

    function getKrud($id)
    {
        $krud = DB::table('krud')->where('id', $id)->first();
        return response()->json($krud);
    }
}
