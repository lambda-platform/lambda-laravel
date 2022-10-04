<?php

namespace Lambda\Dataform;

use Carbon\Carbon;
use Illuminate\Support\Facades\Facade;
use DB;
use Auth;
use Illuminate\Support\Facades\Log;

class Dataform extends Facade
{
    private $dbSchema;
    private $stepForms;
    private $schema;
    private $meta;

    use FileManager;
    use Validate;
    use Utils;
    use CustomUtils;
    use FormEmail;

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

        if (isset($this->dbSchema->step)) {
            $this->stepForms = $this->dbSchema->step->list;
        }
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
                        if ($sch->formType == 'SubForm' && isset($sch->subtype) && $sch->subtype == 'Form') {

                            $item = new \stdClass();
                            $item->model = $sch->model;
                            $item->parent = $sch->parent;
                            $item->subForms = $f->getFormSubTables($sch, $item);
                            $subforms[] = $item;
                        } elseif ($sch->formType == 'SubForm') {
                            //dd($sch);
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
                        //unset all subtables
                        foreach (array_keys($sd) as $key) {
                            if (is_array($sd[$key])) {
                                $array_name = null;
                                unset($sd[$key]);
                            };
                        }
                        //get parent id
                        $insert_id = $subqr->insertGetId($sd);
                        //starting to save subtables
                        if (count($subSubForms) > 0) {
                            foreach ($subSubForms as $sForm) {
                                if (count($sForm->data) > 0) {
                                    if (isset($sForm->generateID) && $sForm->generateID) {
                                        $sForm->{$sForm->identity} = (string)Uuid::generate();
                                    } else {
                                        if (isset($sForm->id))
                                            unset($sForm->id);
                                    }
                                    foreach ($sForm->data as $sFormData) {
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

    public function storeSteps($parentID)
    {
//        dd($stepForms);
        if (isset($this->stepForms) && count($this->stepForms) > 0) {
            foreach ($this->stepForms as $sf) {
                DB::table($sf->model)->where($sf->parent, $parentID)->delete();
                $subqr = DB::table($sf->model);
                $sf->data = request()->get($sf->model);

                if ($sf->data && count($sf->data) > 0) {
                    foreach ($sf->data as $key => $sd) {
                        $sd[$sf->parent] = $parentID;
                        $subqr->insertGetId($sd);
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
            $this->storeSteps($id);

            $data[$this->dbSchema->identity] = $id;
            $data = $this->callTrigger('afterInsert', $data, $id);
            $cache = $this->cacheClear();


            LOG::debug('ON DISPATCH');
            //FormEmail::sendEmail($data,$this->dbSchema);
            FormJob::dispatch($data, $this->dbSchema)->afterResponse();

            $response_data = ['status' => true, 'data' => $data, 'cache clear' => $cache];
            $response_data[$this->dbSchema->identity] = $id;
            LOG::debug('RESPONSE: ' . Carbon::now());
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
                        //form subform
                        $subSubForms = isset($sf->subForms) ? $sf->subForms : [];
                        foreach ($subSubForms as $sForm) {
                            $sForm->data = $sd[$sForm->model];
                        }


                        //unset all subtables
                        foreach (array_keys($sd) as $key) {
                            if (is_array($sd[$key])) {
                                $array_name = null;
                                unset($sd[$key]);
                            };
                        }

                        unset($oldSubData[$old->id]);
                        unset($sd['id']);
                        DB::table($sf->model)
                            ->where('id', $old->id)
                            ->update($sd);
                        //starting to update subtables data
                        if (count($subSubForms) > 0) {

                            foreach ($subSubForms as $sForm) {
                                //data baival
                                if (count($sForm->data) > 0) {
                                    //dd($sForm->data);
                                    if (isset($sForm->generateID) && $sForm->generateID) {
                                        $sForm->{$sForm->identity} = (string)Uuid::generate();
                                    } else {
                                        if (isset($sForm->id))
                                            unset($sForm->id);
                                    }

                                    //getting old data
                                    $oldSubFormDataIds = DB::table($sForm->model)
                                        ->where($sForm->parent, $old->id)
                                        ->pluck('id as val', 'id');

                                    foreach ($sForm->data as $sFormData) {
                                        if (isset($sFormData['id'])) {
                                            $oldSubFormData = DB::table($sForm->model)
                                                ->where('id', $sFormData['id'])->first();
                                            //getting old saved data
                                            if ($oldSubFormData) {
                                                unset($sFormData['id']);
                                                $sFormData[$sForm->parent] = $old->id;
                                                DB::table($sForm->model)->where('id', $oldSubFormData->id)->update($sFormData);
                                                unset($oldSubFormDataIds[$oldSubFormData->id]);
                                            }
                                        } else {
                                            $sFormData[$sForm->parent] = $old->id;
                                            if ($sForm->generateID) {
                                                $sFormData[$sForm->identity] = (string)Uuid::generate();
                                            } else {
                                                if (env('DB_CONNECTION') == 'sqlsrv') {
                                                    unset($sFormData['id']);
                                                }
                                            }
                                            DB::table($sForm->model)->insert($sFormData);
                                        }
                                    }

                                    foreach ($oldSubFormDataIds as $key => $value) {
                                        DB::table($sForm->model)->where('id', $key)->delete();
                                    }
                                }
                            }
                        }
                    } else {
                        $sd[$sf->parent] = $parentID;
                        if ($sf->generateID) {
                            $sd[$sf->identity] = (string)Uuid::generate();
                        } else {
                            if (env('DB_CONNECTION') == 'sqlsrv') {
                                unset($sd['id']);
                            }
                        }

                        //form subform
                        $subSubForms = isset($sf->subForms) ? $sf->subForms : [];

                        foreach ($subSubForms as $sForm) {
                            $sForm->data = $sd[$sForm->model];
                        }
                        //unset all subtables
                        foreach (array_keys($sd) as $key) {
                            if (is_array($sd[$key])) {
                                $array_name = null;
                                unset($sd[$key]);
                            };
                        }
                        $insertId = DB::table($sf->model)->insertGetId($sd);
                        if ($insertId) {
                            //starting to insert subtables data
                            if (count($subSubForms) > 0) {
                                //dd($subSubForms);
                                foreach ($subSubForms as $sForm) {
                                    if (count($sForm->data) > 0) {
                                        if (isset($sForm->generateID) && $sForm->generateID) {
                                            $sForm->{$sForm->identity} = (string)Uuid::generate();
                                        } else {
                                            if (isset($sForm->id))
                                                unset($sForm->id);
                                        }

                                        foreach ($sForm->data as $sFormData) {

                                            $sFormData[$sForm->parent] = $insertId;
                                            if ($sForm->generateID) {
                                                $sFormData[$sForm->identity] = (string)Uuid::generate();
                                            } else {
                                                if (env('DB_CONNECTION') == 'sqlsrv') {
                                                    unset($sFormData['id']);
                                                }
                                            }
                                            DB::table($sForm->model)->insert($sFormData);

                                        }
                                    }
                                }
                            }
                        }
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
        $cache = $this->cacheClear();
        $response_data = ['status' => true, 'data' => $data, 'cache clear' => $cache];
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
                if (isset($sub->subForms)) {
                    //fetching data
                    foreach ($r->{$sub->model} as $subFormTableData) {
                        foreach ($sub->subForms as $subFormTable) {
                            $subFormTableData->{$subFormTable->model} = DB::table($subFormTable->model)->where($subFormTable->parent, $subFormTableData->id)->get();
                        }
                    }
                }
            }
            return response()->json(['status' => true, 'data' => $r]);
        }
        return response()->json(['status' => false]);
    }

    public function options($relObj = false)
    {
//        dd($relObj);

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
        $sortField = false;
        $sortOrder = false;

        if ($relObj == false) {
            if (isset(request()->sortField)) {
                $sortField = request()->sortField;
            }

            if (isset(request()->sortOrder)) {
                $sortOrder = request()->sortOrder;
            }
        } else {

            $sortField = isset($relObj->sortField) ? $relObj->sortField : false;
            $sortOrder = isset($relObj->sortOrder) ? $relObj->sortOrder : false;
        }

//        $sortField = $relObj == false ? (isset(request()->sortField) ? request()->sortField : false) : $relObj->sortField;
//        $sortOrder = $relObj == false ? (isset(request()->sortOrder) ? request()->sortOrder : false) : $relObj->sortOrder;

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
            $this->restrictInjection($qr, $filter);
        }

        if ($filterWithUser) {
//            $qr->whereRaw($filterWithUser);
            $this->restrictInjection($qr, $filterWithUser);
        }
        //dd($qr->dd());
        $options = $qr->get();

        if ($relObj == false) {
            return response()->json($options);
        }

        return $options;
    }

    function restrictInjection($qr, $filter)
    {
        if (str_contains($filter, '=') && !str_contains($filter, '!=') && !str_contains($filter, '<=') && !str_contains($filter, '>=')) {
            $filterArr = explode("=", $filter);
            $qr->where(trim($filterArr[0]), str_replace(['"', " ", "'"], "", $filterArr[1]));
        }

        if (str_contains($filter, '!=')) {
            $filterArr = explode("!=", $filter);
            $qr->where(trim($filterArr[0]), '!=', str_replace(['"', " ", "'"], "", $filterArr[1]));
        }

        if (str_contains($filter, 'IN')) {
            $filterArr = explode("IN", $filter);
            $val = str_replace('(', '', $filterArr[1]);
            $val = str_replace(')', '', $val);
            $qr->whereRaw(trim($filterArr[0]) . ' IN (?)', $val);
        }

        if (str_contains($filter, 'NOT IN')) {
            $filterArr = explode("NOT IN", $filter);
            $val = str_replace('(', '', $filterArr[1]);
            $val = str_replace(')', '', $val);
            $qr->whereRaw(trim($filterArr[0]) . ' NOT IN (?)', $val);
        }


        if (str_contains($filter, '<') && !str_contains($filter, '<=')) {
            $filterArr = explode("<", $filter);
            $qr->where(trim($filterArr[0]), '<', str_replace(['"', " ", "'"], "", $filterArr[1]));
        }

        if (str_contains($filter, '<=')) {
            $filterArr = explode("<=", $filter);
            $qr->where(trim($filterArr[0]), '<=', str_replace(['"', " ", "'"], "", $filterArr[1]));
        }

        if (str_contains($filter, '>') && !str_contains($filter, '>=')) {
            $filterArr = explode(">", $filter);
            $qr->where(trim($filterArr[0]), '>', str_replace(['"', " ", "'"], "", $filterArr[1]));
        }

        if (str_contains($filter, '>=')) {
            $filterArr = explode(">=", $filter);
            $qr->where(trim($filterArr[0]), '>=', str_replace(['"', " ", "'"], "", $filterArr[1]));
        }

        if (str_contains($filter, 'IS NULL')) {
            $field = str_replace('IS NULL', '', $filter);
            $qr->whereNull(trim($field));
        }

        if (str_contains($filter, 'IS NOT NULL')) {
            $field = str_replace('IS NOT NULL', '', $filter);
            $qr->whereNotNull(trim($field));
        }

        if (str_contains($filter, 'BETWEEN') && !str_contains($filter, 'NOT BETWEEN')) {
            $filterArr = explode("BETWEEN", $filter);
            $filterArrVal = explode(" AND ", $filterArr[1]);
            $qr->whereBetween(trim($filterArr[0]), $filterArrVal);
        }

        if (str_contains($filter, 'NOT BETWEEN')) {
            $filterArr = explode("NOT BETWEEN", $filter);
            $filterArrVal = explode(" AND ", $filterArr[1]);
            $qr->whereNotBetween(trim($filterArr[0]), $filterArrVal);
        }
    }

    function getFormSubTables($s)
    {
        if (isset($s->formId)) {
            $localSubForms = [];
            $subFormDbSchema = \Illuminate\Support\Facades\DB::table('vb_schemas')->where('id', (int)$s->formId)->first();
            $subFormDbSchema = json_decode($subFormDbSchema->schema);
            $localSchema = $subFormDbSchema->schema;
            foreach ($localSchema as $local_s) {
                if (isset($local_s->formType) && $local_s->formType == 'SubForm') {
                    // dd($local_s);
                    $localSubForm = new \stdClass();
                    $localSubForm->data = null;
                    $localSubForm->parent = $local_s->parent;
                    $localSubForm->model = $local_s->model;

                    //Setting ID when storing data
                    foreach ($local_s->schema as $localSch) {
                        if ($local_s->identity == $localSch->model) {

                            if (isset($localSch->extra) && ($localSch->extra == '' || $localSch->extra == null)) {
                                $localSubForm->generateID = true;
                                $localSubForm->identity = $localSch->model;
                            } else {
                                $localSubForm->generateID = false;
                            }
                        }
                    }
                    array_push($localSubForms, $localSubForm);
                }
            }
            // dd($localSubForms);
            return $localSubForms;
        }
    }
}
