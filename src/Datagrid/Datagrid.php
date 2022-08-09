<?php

namespace Lambda\Datagrid;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Maatwebsite\Excel\Facades\Excel;
use Auth;

class Datagrid extends Facade
{
    protected $qr;
    protected $schema;
    private $dbSchema;
    protected $title;
    protected $excelHeader;

    use Trigger;

    public function __construct()
    {
        $this->schema = [];
        $this->excelHeader = [];
    }

    public function buildSchema($schemaID)
    {
        $this->dbSchema = DB::table('vb_schemas')->where('id', (int)$schemaID)->first();
        if (!$this->dbSchema) {
            $this->dbSchema = DB::table('vb_schemas_admin')->where('type', 'grid')->where('id', $schemaID)->first();
        }

        $this->title = $this->dbSchema->name;
        $this->dbSchema = json_decode($this->dbSchema->schema);
        $this->schema = $this->dbSchema->schema;
        $this->qr = DB::table($this->dbSchema->model);

        foreach ($this->schema as $s) {
            if (((!isset($s->relation) || !property_exists($s->relation, 'table') || $s->relation->table == null)) || (isset($this->dbSchema->identity) && $s->model == $this->dbSchema->identity)) {
                $this->qr->addSelect($this->dbSchema->model . '.' . $s->model);
                $this->setExcelHeader($s);
            } else {
                if(isset($s->gridType)) {
                    if (($s->gridType == 'Tag') && property_exists($s->relation, 'table') && property_exists($s->relation, 'fields') && $s->relation->table !== null) {
                        $sql = '(SELECT group_concat(' . $s->relation->fields . ') FROM ' . $s->relation->table . ' WHERE ' . $s->relation->key . ' IN (SELECT (SUBSTRING_INDEX(SUBSTRING_INDEX(B.' . $s->model . ", ',', NS.n), ',', -1)) AS tag FROM (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10) NS INNER JOIN " . $this->dbSchema->model . ' B ON NS.n <= CHAR_LENGTH(B.' . $s->model . ') - CHAR_LENGTH(REPLACE(B.' . $s->model . ", ',', '')) + 1 WHERE B." . $s->relation->key . "=" . $this->dbSchema->model . '.' . $s->relation->key . ')) as ' . $s->model;
                        $this->qr->addSelect(DB::raw($sql));
                        $this->setExcelHeader($s);
                    }

                    if (($s->gridType == 'Select') && property_exists($s->relation, 'table') && property_exists($s->relation, 'fields') && $s->relation->table !== null) {
                        $sql = '(SELECT ' . $s->relation->fields . ' FROM ' . $s->relation->table . ' WHERE ' . $s->relation->key . ' IN (' . $s->model . ') limit 1) as ' . $s->model;
                        $this->qr->addSelect(DB::raw($sql));
                        $this->setExcelHeader($s);
                    }
                }
            }
        }
    }

    public static function exec($action, $schemaID, $id = false)
    {
        $g = new self();
        if ($action == 'aggergation') {
            $g->buildAggergation($schemaID);
        } else {
            $g->buildSchema($schemaID);
        }

        switch ($action) {
            case 'data':
                return $g->fetchData();
            case 'aggergation':
                return $g->aggergation();
            case 'excel':
                return $g->exportExcel();
            case 'custom-data':
                return $g->cusTomData($schemaID);
            case 'print':
                return $g->printData($schemaID);
            case 'delete':
                return $g->deleteData($id);
                break;
            case 'update-row':
                return $g->updateRow($schemaID);
                break;
            default:
                break;
        }

        return response()->json(['status' => false]);
    }

    public function fetchData()
    {
        //Soft delete
        if (isset($this->dbSchema->softDelete) && $this->dbSchema->softDelete) {
            $this->qr = $this->qr->where('deleted_at', null);
        }

        $this->sort(request()->get('sort'), request()->get('order'));
        //Tag select
        $this->filter();
//        return $this->qr->toSql();

        //Condition
        if (isset($this->dbSchema->condition)) {
            $this->qr = $this->qr->whereRaw($this->dbSchema->condition);
        }

        $this->search();

        //before fetch trigger
        $this->qr = $this->callTrigger('beforeFetch', $this->qr);

        if (property_exists($this->dbSchema, 'isClient') && $this->dbSchema->isClient) {
            $data = $this->qr->get();
            return $this->callTrigger('afterFetch', $data);
        }
        //return $this->qr->toSql();

        $data = $this->qr->paginate(request()->get('paginate'));
        return $this->callTrigger('afterFetch', $data);
    }

    public function sort($model, $order = 'desc')
    {
        if ($model != null && $model != '' && $model != "null") {
            $this->qr = $this->qr->orderBy($model, $order);
        }
    }

    public function search()
    {
        if (request()->get('search') && request()->get('search') != null && request()->get('search') != '') {
            $q = request()->get('search');
            $this->qr = $this->qr->where(function ($query) use ($q) {
                $i = 0;
                foreach ($this->schema as $s) {
                    if (!$s->hide) {
                        if ($i == 0) {
                            $query->where($s->model, 'like', '%' . $q . '%');
                        } else {
                            $query->orWhere($s->model, 'like', '%' . $q . '%');
                        }
                        ++$i;
                    }
                }
            });
        }
    }

    public function filter()
    {
        $user_condition = request()->get('user_condition');
        //dd($user_condition);
        if ($user_condition) {
            if (Auth::user()) {
                $user = Auth::user();
                $user = $user->toArray();
                foreach ($user_condition as $u_condition) {
                    $this->qr = $this->qr->where($u_condition['grid_field'], '=', $user[$u_condition['user_field']]);
                }
            }
        }

        $this->customFilter();

//        dd('... filter');
        foreach ($this->schema as $s) {
            if ($s->filterable && request()->get($s->model) != null && request()->get($s->model) != '') {

                //Side filter
                if (is_array(request()->get($s->model))) {
                    $this->floatFilter($s->model, request()->get($s->model));
                } else {
                    switch ($s->filter->type) {
                        case 'Number':
//                        $betweenNumbers = request()->get($s->model);
//                        if ($betweenDates[0] != '' && $betweenDates[1] != '') {
//                            $this->qr = $this->qr->whereBetween($s->model, $betweenDates);
//                        }
                            break;
                        case 'DateRange':
                            $betweenDates = request()->get($s->model);
                            if ($betweenDates[0] != '' && $betweenDates[1] != '') {
                                $this->qr = $this->qr->whereBetween($s->model, $betweenDates);
                            }
                            break;
                        case 'DateRangeDouble':
                            $betweenDates = request()->get($s->model);
                            if ($betweenDates[0] != '' && $betweenDates[1] != '') {
                                $this->qr = $this->qr->whereBetween($s->model, $betweenDates);
                            }

                            if (($betweenDates[1] == '' || $betweenDates[1] == null) && $betweenDates[0] != '') {
                                $this->qr = $this->qr->where($s->model, '>=', $betweenDates[0]);
                            }

                            if (($betweenDates[0] == '' || $betweenDates[0] == null) && $betweenDates[1] != '') {
                                $this->qr = $this->qr->where($s->model, '<=', $betweenDates[1]);
                            }
                            break;
                        case 'Tag':
                            $this->qr = $this->qr->where(function ($query) use ($s) {
                                $query->whereRaw("find_in_set('" . request()->get($s->model) . "'," . $s->model . ")");
                            });
                            break;
                        case 'Select':
                            $this->qr = $this->qr->where($s->model, request()->get($s->model));
                            break;
                        default:
                            $value = str_replace('*', '%', request()->get($s->model));
                            if (strpos($value, '%') == false) {
                                $value = $value . '%';
                            }
                            $this->qr = $this->qr->whereRaw('LOWER(' . $s->model . ') like ?', [strtolower($value)]);
                            break;
                    }
                }
            }
        }
    }

    function customFilter()
    {
        $custom_condition = request()->get('custom_condition');
        if ($custom_condition) {
            foreach ($custom_condition as $c_condition) {
                switch ($c_condition['type']) {
                    case 'equals':
                        if (isset($c_condition['value']) && $c_condition['value'] && $c_condition['value'] != null) {
                            $this->qr = $this->qr->where($c_condition['field'], '=', $c_condition['value']);
                        }
                        break;
                    case 'notEqual':
                        if ($c_condition['value']) {
                            $this->qr = $this->qr->where($c_condition['field'], '!=', $c_condition['value']);
                        }
                        break;
                    case 'range':
                        if ($c_condition['value']) {
                            $this->qr = $this->qr->where($c_condition['field'], '>=', Carbon::parse($c_condition['value'][0])->subDay())->where($c_condition['field'], '<=', Carbon::parse($c_condition['value'][1])->addDays(1));
                        }
                        break;
                    case 'lessThan':
                        if (isset($c_condition['value']) && $c_condition['value'] && $c_condition['value'] != null) {
                            $this->qr = $this->qr->where($c_condition['field'], '<=', $c_condition['value']);
                        }
                        break;
                    case 'greaterThan':
                        if (isset($c_condition['value'])) {
                            $this->qr = $this->qr->where($c_condition['field'], '>=', $c_condition['value']);
                        }
                        break;
                    default:
                        if ($c_condition['value'] && strval($c_condition['value']) != strval(0)) {
                            $this->qr = $this->qr->where($c_condition['field'], '=', $c_condition['value']);
                        }
                        break;
                }
            }
        }
    }

    function floatFilter($model, $filter)
    {
        if (!isset($filter['filterType'])) {
            return;
        }

        if ($filter['filterType'] == 'date') {
            if ($filter['dateFrom'] != null && isset($filter['type'])) {
                switch ($filter['type']) {
                    case 'equals':
                        $this->qr = $this->qr->whereRaw('DATE(' . $model . ') = "' . $filter['dateFrom'] . '"');
                        break;
                    case 'notEqual':
                        $this->qr = $this->qr->where($model, '!=', $filter['dateFrom']);
                        break;
                    case 'lessThan':
                        $this->qr = $this->qr->where($model, '<=', $filter['dateFrom']);
                        break;
                    case 'greaterThan':
                        $this->qr = $this->qr->where($model, '>=', $filter['dateFrom']);
                        break;
                    case 'inRange':
                        $this->qr = $this->qr->where($model, '>=', $filter['dateFrom']);
                        break;
                    default:
                        break;
                }
            }

            if ($filter['dateTo'] != null) {
                $this->qr = $this->qr->where($model, '<=', $filter['dateTo']);
            }
        } elseif ($filter['filterType'] == 'set') {
//            if(is_array($filter['values']) && count($filter['values']) > 0){
            $this->qr = $this->qr->whereIn($model, $filter['values']);
//            }
        } else {
            if (!isset($filter['type'])) {
                return;
            }

            if (strpos($filter['filter'], '*') !== false) {
                $value = str_replace('*', '%', $filter['filter']);
                $this->qr = $this->qr->whereRaw('LOWER(' . $model . ') like ?', [strtolower($value)]);
            } else {
                switch ($filter['type']) {
                    case 'equals':
                        $this->qr = $this->qr->where($model, '=', $filter['filter']);
                        break;
                    case 'notEqual':
                        $this->qr = $this->qr->where($model, '!=', $filter['filter']);
                        break;
                    case 'contains':
                        $this->qr = $this->qr->whereRaw('LOWER(' . $model . ') like ?', ['%' . strtolower($filter['filter']) . '%']);
                        break;
                    case 'notContains':
                        $this->qr = $this->qr->whereRaw('LOWER(' . $model . ') not like ?', ['%' . strtolower($filter['filter']) . '%']);
                        break;
                    case 'startsWith':
                        $this->qr = $this->qr->whereRaw('LOWER(' . $model . ') like ?', [strtolower($filter['filter']) . '%']);
                        break;
                    case 'endsWith':
                        $this->qr = $this->qr->whereRaw('LOWER(' . $model . ') like ?', ['%' . strtolower($filter['filter'])]);
                        break;
                    case 'greaterThan':
                        $this->qr = $this->qr->where($model, '>', $filter['filter']);
                        break;
                    case 'greaterThanOrEqual':
                        $this->qr = $this->qr->where($model, '>=', $filter['filter']);
                        break;
                    case 'lessThan':
                        $this->qr = $this->qr->where($model, '<', $filter['filter']);
                        break;
                    case 'lessThanOrEqual':
                        $this->qr = $this->qr->where($model, '<=', $filter['filter']);
                        break;
                    case 'inRange':
                        $this->qr = $this->qr->whereBetween($model, [$filter['filter'], $filter['filterTo']]);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public function aggergation()
    {
        //Soft delete
        if (isset($this->dbSchema->softDelete) && $this->dbSchema->softDelete) {
            $this->qr = $this->qr->where($this->dbSchema->model . '.deleted_at', null);
        }
        //Tag select
        //Tag select
        $this->filter();
        $this->search();
        if (isset($this->dbSchema->condition)) {
            //dd($this->dbSchema->condition);
            $this->qr = $this->qr->whereRaw($this->dbSchema->condition);
        }
        return $this->qr->get();
    }

    public function buildAggergation($schemaID)
    {
        $this->dbSchema = DB::table('vb_schemas')->where('id', (int)$schemaID)->first();
        $this->dbSchema = json_decode($this->dbSchema->schema);
        $columnAggregations = $this->dbSchema->columnAggregations;

        $this->schema = $this->dbSchema->schema;
        $this->qr = DB::table($this->dbSchema->model);
        foreach ($columnAggregations as $s) {
            $this->qr->addSelect(DB::raw("$s->aggregation($s->column) as $s->aggregation" . '_' . $s->column));
        }
    }

    public function setExcelHeader($s)
    {
        $this->excelHeader[] = $s->label ? $s->label : $s->model;
    }

    public function exportExcel()
    {
        $this->filter();
        if (isset($this->dbSchema->condition)) {
            $this->qr = $this->qr->whereRaw($this->dbSchema->condition);
        }

        $this->qr = $this->callTrigger('beforeFetch', $this->qr);
        $excelFile = Excel::raw(new ExportExcel($this->qr, $this->excelHeader), \Maatwebsite\Excel\Excel::XLSX);

        $response = [
            'name' => $this->title . '-' . Carbon::today() . '.xlsx',
            'file' => base64_encode($excelFile),
        ];

        return response()->json($response);
    }

    public function cusTomData($schemaID)
    {
        $ids = request()->get('ids');


        if (isset($this->dbSchema->condition)) {
            $this->qr = $this->qr->whereRaw($this->dbSchema->condition);
        }


        $this->qr->whereIn('id', $ids);


        $data = $this->qr->get();


        return response()->json(["data" => $data, "schema" => $this->schema]);
    }

    public function printData($schemaID)
    {
        $this->filter();
        if (isset($this->dbSchema->condition)) {
            $this->qr = $this->qr->whereRaw($this->dbSchema->condition);
        }

        $data = $this->qr->get();
        return $this->callTrigger('beforePrint', $data);

    }

    public function deleteData($id)
    {

        $table = $this->dbSchema->model;
        if (isset($this->dbSchema->mainTable) && ($this->dbSchema->mainTable != null || $this->dbSchema->mainTable != "")) {
            $table = $this->dbSchema->mainTable;
        }

        if (isset($this->dbSchema->softDelete) && $this->dbSchema->softDelete) {
            $deleted = DB::table($table)->where($this->dbSchema->identity, $id)->update([
                'deleted_at' => Carbon::now(),
            ]);
            if ($deleted) {
                return $this->callTrigger('afterDelete', true, $id);
            } else {
                return false;
            }
        }

        $deleted = DB::table($table)->where($this->dbSchema->identity, $id)->delete();
        if ($deleted) {
            return $this->callTrigger('afterDelete', true, $id);
        } else {
            return false;
        }

    }

    public function updateRow()
    {
        $model = request()->get('model');
        $value = request()->get('value');
        $ids = request()->get('ids');


        if ($model && $value && $ids) {
            $table = $this->dbSchema->model;
            if (isset($this->dbSchema->mainTable) && ($this->dbSchema->mainTable != null || $this->dbSchema->mainTable != "")) {
                $table = $this->dbSchema->mainTable;
            }
            foreach ($ids as $id) {
                DB::table($table)->where($this->dbSchema->identity, $id)->update([
                    $model => $value
                ]);
            }
            return true;
        } else {
            return false;
        }


    }

    public function joinData($fieldData)
    {
    }
}
