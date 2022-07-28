<?php

namespace Lambda\Dataform;

use DB;
use Illuminate\Support\Facades\Facade;
use Auth;

class Dataform extends Facade
{
    private $dbSchema;
    private $schema;
    private $meta;

    use FileManager;
    use Validate;
    use Utils;
    use CustomUtils;

    public function __construct()
    {
        $this->schema = [];
        $this->meta = [];
    }

    public function buildSchema($schemaID)
    {
        $this->dbSchema = DB::table('vb_schemas')->where('id', (int)$schemaID)->first();
        if (!$this->dbSchema) {
            $this->dbSchema = DB::table('vb_schemas_admin')->where('type', 'form')->where('id', $schemaID)->first();
        }
        $this->dbSchema = json_decode($this->dbSchema->schema);
        $this->schema = $this->dbSchema->schema;
    }


    public static function exec($schemaID, $action, $dataID)
    {

        $f = new self();
        $f->buildSchema($schemaID);
        switch ($action) {
            case 'store':
                $data = $f->validateFormRequest();
                if (!$data['status']) {
                    return response()->json($data);
                }
                return $f->store($data['data'], $data['subforms']);

            case 'update':
                $data = $f->validateFormRequest($action);
                if (!$data['status']) {
                    return response()->json($data);
                }

                return $f->update($dataID, $data['data'], $data['subforms']);

            case 'edit':
                $subforms = [];

                foreach ($f->schema as $sch) {
                    if (isset($sch->formType)) {
                        if ($sch->formType == 'SubForm') {
                            $item = new \stdClass();
                            $item->model = $sch->model;
                            $item->parent = $sch->parent;
                            $subforms[] = $item;
                        }
                    }
                }
                return $f->edit($f->dbSchema->model, $dataID, $subforms);

            case 'options':
                return $f->options();

            case 'unique':
                return $f->checkUnique();

            case 'checkCurrentPassword':
                return $f->checkCurrentPassword();

            default:
                break;
        }

        return response()->json(['status' => false]);
    }

    public function storeSubs($subforms, $parentID, $status)
    {
        if (count($subforms) > 0) {
            foreach ($subforms as $sf) {

                //$data = $f->validateFormRequest();
                //Custom trigger
                //$this->customCallTrigger('beforeInsertDeleteOld', $sf, null, $parentID, $status);
                DB::table($sf->model)->where($sf->parent, $parentID)->delete();

                $subqr = DB::table($sf->model);
                if ($sf->data && count($sf->data) > 0) {
                    foreach ($sf->data as $key => $sd) {
                        $sd[$sf->parent] = $parentID;
                        if ($sf->generateID) {
                            $sd[$sf->identity] = (string)Uuid::generate();
                        } else {
                            unset($sd['id']);
                        }
                        //form subform
                        $subSubForms = isset($sf->subForms) ? $sf->subForms : [];
                        foreach ($subSubForms as $sForm) {
                            $sForm->data = $sd[$sForm->model];
                        }
                        foreach (array_keys($sd) as $key) {
                            if (is_array($sd[$key])) {
                                $array_name = null;
                                unset($sd[$key]);
                            };
                        }
                        $insert_id = $subqr->insertGetId($sd);

                        if (count($subSubForms) > 0) {
                            foreach ($subSubForms as $sForm) {
                                if(count($sForm->data)>0)
                                {
                                    if (isset($sForm->generateID) && $sForm->generateID) {
                                        $sForm->{$sForm->identity} = (string)Uuid::generate();
                                    } else {
                                        if(isset($sForm->id))
                                            unset($sForm->id);
                                    }
                                    foreach ($sForm->data as $sFormData)
                                    {
                                        $sFormData[$sForm->parent] = $insert_id;
                                        $SFormSubQr = DB::table($sForm->model);
                                        $SFormSubQr->insert($sFormData);
                                    }
                                }
                            }
                        }
                    }
                }

            }
        }
    }

    public function store($data, $subforms)
    {
        $data = $this->callTrigger('beforeInsert', $data);
        if (isset($data->ignore_exec)) {
            return $data->response;
        }

        $qr = DB::table($this->dbSchema->model);

//        $r = isset($data['id']) ? $qr->insert($data) : $qr->insertGetId($data);
        if (array_key_exists('id', $data) && $data['id'] == null) {
            unset($data['id']);
        }
        // dd($qr->toSql());
        $r = $qr->insert($data);
        if ($r) {
            isset($data['id']) ? $id = $data['id'] : $id = $data['id'] = DB::getPdo()->lastInsertId();
            $this->storeSubs($subforms, $id, 'store');
            $data[$this->dbSchema->identity] = $id;
            $data = $this->callTrigger('afterInsert', $data, $id);

            $response_data = ['status' => true, 'data' => $data];
            $response_data[$this->dbSchema->identity] = $id;
            return response()->json($response_data);
        }

        return response()->json(['status' => false]);
    }

    public function updateSubs($subforms, $parentID, $status)
    {
        if (count($subforms) > 0) {
            foreach ($subforms as $sf) {
                $oldSubData = DB::table($sf->model)
                    ->where($sf->parent, $parentID)
                    ->pluck('id as val', 'id');
                foreach ($sf->data as $sd) {
                    if (isset($sd['id'])) {
                        $old = DB::table($sf->model)
                            ->where('id', $sd['id'])
                            ->first();

                        unset($oldSubData[$old->id]);
                        unset($sd['id']);
                        DB::table($sf->model)
                            ->where('id', $old->id)
                            ->update($sd);
                    } else {
                        $sd[$sf->parent] = $parentID;
                        if ($sf->generateID) {
                            $sd[$sf->identity] = (string)Uuid::generate();
                        } else {
                            if (env('DB_CONNECTION') == 'sqlsrv') {
                                unset($sd['id']);
                            }
                        }
                        DB::table($sf->model)->insert($sd);
                    }
                }
                foreach ($oldSubData as $key => $value) {
                    DB::table($sf->model)->where('id', $key)->delete();
                }
            }
        }
    }

    public function update($id, $data, $subforms)
    {
        unset($data[$this->dbSchema->identity]);

        $data = $this->callTrigger('beforeUpdate', $data, $id);
        $r = DB::table($this->dbSchema->model)
            ->where($this->dbSchema->identity, $id)
            ->update($data);
        $data[$this->dbSchema->identity] = $id;

        $this->updateSubs($subforms, $id, 'update');
        $data = $this->callTrigger('afterUpdate', $data, $id);
        $response_data = ['status' => true, 'data' => $data];
        $response_data[$this->dbSchema->identity] = $id;
        return response()->json($response_data);
    }

    public function edit($table, $id, $submodels = [])
    {
        $r = DB::table($table)->where($this->dbSchema->identity, $id)->first();
        //dd($r);
        if ($r) {
            foreach ($submodels as $sub) {
                $r->{$sub->model} = DB::table($sub->model)->where($sub->parent, $r->{$this->dbSchema->identity})->get();
            }
            return response()->json(['status' => true, 'data' => $r]);
        }
        return response()->json(['status' => false]);
    }

    public function options($relObj = false)
    {
        $table = $relObj == false ? request()->table : $relObj->table;
        $value = $relObj == false ? request()->key : $relObj->key;
        $labels = $relObj == false ? request()->fields : $relObj->fields;

        if ($relObj == false) {
            $filter = request()->filter;
        } else {
            $filter = isset($relObj->filter) ? $relObj->filter : false;
        }

        $filterWithUser = null;

        if (isset($relObj->filterWithUser)) {
            $user = Auth::user()->toArray();
            foreach ($relObj->filterWithUser as $userFilter) {
                if ($user[$userFilter["userField"]]) {
                    if ($filterWithUser) {
                        $filterWithUser = " and " . $userFilter['tableField'] . " = '" . $user[$userFilter["userField"]] . "'";
                    } else {
                        $filterWithUser = "" . $userFilter['tableField'] . " = '" . $user[$userFilter["userField"]] . "'";
                    }
                }
            }
        }

        $parentFieldOfTable = false;
        if ($relObj == false) {
            if (request()->has('parentFieldOfTable')) {
                $parentFieldOfTable = request()->parentFieldOfTable;
            }
        } else {
            $parentFieldOfTable = isset($relObj->parentFieldOfTable) ? $relObj->parentFieldOfTable : false;
        }

        if (!$table) {
            return [];
        }

        //Sorting
        $sortField = $relObj == false ? request()->sortField : $relObj->sortField;
        $sortOrder = $relObj == false ? request()->sortOrder : $relObj->sortOrder;

        $qr = DB::table($table)->select($value . ' as value');
        if (is_array($labels)) {
            $label_column = join(",', ',", $labels);
            if (env('DB_CONNECTION') == 'sqlsrv') {
                if (count($labels) >= 2) {
                    $pdo = DB::connection()->getPdo();
                    $db_server_v = $pdo->getAttribute(constant('PDO::ATTR_SERVER_VERSION'));
                    if ($db_server_v >= '11.0.2100.60') {
                        $label_column = 'concat(' . $label_column . ')';
                    } else {
                        $label_column = '(' . $label_column . ')';
                    }
                } else {
                    $label_column = '(' . $label_column . ')';
                }
            } elseif (env('DB_CONNECTION') == 'oracle') {
                $label_column = '(' . $label_column . ')';
            } else {
                $label_column = 'concat(' . $label_column . ')';
            }
        } else {
            $label_column = $labels;
        }

        $qr->addSelect(DB::raw("$label_column as label"));

        if ($parentFieldOfTable) {
            $qr->addSelect($parentFieldOfTable . ' as parent_value');
        }

        if ($sortField) {
            $qr->orderBy($sortField, $sortOrder);
        }

        if ($filter) {
            $qr->whereRaw($filter);
        }

        if ($filterWithUser) {
            $qr->whereRaw($filterWithUser);
        }

        $options = $qr->get();
        if ($relObj == false) {
            return response()->json($options);
        }

        return $options;
    }
}
