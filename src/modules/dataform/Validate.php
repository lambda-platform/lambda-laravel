<?php

namespace Lambda\Dataform;

use Illuminate\Support\Facades\DB;
use Validator;
use JWTAuth;
use Illuminate\Support\Facades\Hash;

trait Validate
{
    public function validateFormRequest($action = false)
    {
        $models = [];
        $computedModels = [];
        $validations = [];
        $subForms = [];

        //For sub forms
        $generatedID = false;
        $identityModel = null;

        foreach ($this->schema as $s) {

            // Sub forms
            if (isset($s->formType) && $s->formType == 'SubForm') {
                $subForm = new \stdClass();
                $subForm->data = request()->get($s->model);
                $subForm->parent = $s->parent;
                $subForm->model = $s->model;

                //Setting ID when storing data
                foreach ($s->schema as $sch) {
                    if ($s->identity == $sch->model) {
                        if ($sch->extra == '' || $sch->extra == null) {
                            $subForm->generateID = true;
                            $subForm->identity = $sch->model;
                        } else {
                            $subForm->generateID = false;
                        }
                    }
                }
                array_push($subForms, $subForm);
            } elseif (isset($s->formType) && $s->formType == 'PasswordGenerate') {
                if ($action == 'update') {
                    if (strlen(request()->get($s->model)) > 0) {
                        $computedModels[$s->model] = bcrypt(request()->get($s->model));
                        if (property_exists($s, 'rules')) {
                            $validations = array_merge($validations, $this->makeValidationStr($s->model, $s->rules));
                        }
                    }
                } else {
                    $computedModels[$s->model] = bcrypt(request()->get($s->model));
                    if (property_exists($s, 'rules')) {
                        $validations = array_merge($validations, $this->makeValidationStr($s->model, $s->rules));
                    }
                }
            } elseif (isset($s->formType) && $s->formType == 'Password') {
                if ($action == 'update') {
                    if (strlen(request()->get($s->model)) > 0) {
                        $computedModels[$s->model] = bcrypt(request()->get($s->model));
                    }
                } else {
                    $computedModels[$s->model] = bcrypt(request()->get($s->model));
                }
            } elseif (isset($s->formType) && $s->formType == 'Hidden') {
                if (isset($s->hasUserId) && $s->hasUserId) {
                    //dd(request()->get($s->model));
                    if(auth()->id()!=null && request()->get($s->model)==null) {
                        $computedModels[$s->model] = auth()->id();
                    }
                    else{
                        $computedModels[$s->model]=request()->get($s->model);
                    }
                }
            } elseif (isset($s->formType) && ($s->formType == 'Date' || $s->formType == 'DateTime')) {
                $computedModels[$s->model] =null;
                if (request()->get($s->model))
                    $computedModels[$s->model] = \Carbon\Carbon::parse(request()->get($s->model));

                if (property_exists($s, 'rules')) {
                    $validations = array_merge($validations, $this->makeValidationStr($s->model, $s->rules));
                }
            } elseif (isset($s->formType) && ($s->formType == 'Image' && (isset($s->isMultiple) && $s->isMultiple === true))) {
                $computedModels[$s->model] = json_encode(request()->get($s->model));

                if (property_exists($s, 'rules')) {
                    $validations = array_merge($validations, $this->makeValidationStr($s->model, $s->rules));
                }
            } elseif (isset($s->formType) && ($s->formType == 'UniqueGeneration')) {
                $computedModels[$s->model] = $this->checkGenerated($s->model);
            } //Main form
            else {
                array_push($models, $s->model);
                if ($this->dbSchema->identity == $s->model) {
                    if (isset($s->extra)) {
                        if ($s->extra == '' || $s->extra == null) {
                            $generatedID = (string)Uuid::generate();
                            $identityModel = $s->model;
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }

                if (($s->model == 'created_at' || $s->model == 'updated_at') && $this->dbSchema->timestamp) {
                    if ($this->dbSchema->timestamp) {
                        if ($action == 'update') {
                            if ($s->model == 'updated_at')
                                $computedModels[$s->model] = \Carbon\Carbon::now();
                        } else
                            $computedModels[$s->model] = \Carbon\Carbon::now();
                    } else
                        continue;
                }

                if (property_exists($s, 'rules') && $s->hidden != true) {
                    $validations = array_merge($validations, $this->makeValidationStr($s->model, $s->rules));
                }
            }
        }

        $requestData = request()->only($models);
        $requestData = array_merge($requestData, $computedModels);
        if ($generatedID && !isset($requestData[$identityModel])) {
            $requestData[$identityModel] = $generatedID;
        }

        $validator = Validator::make($requestData, $validations);

        if ($validator->fails()) {
            return ['status' => false, 'error' => $validator->errors()];
        }

        return ['status' => true, 'data' => $requestData, 'subforms' => $subForms];
    }

    public function makeValidationStr($model, $rules)
    {
        $ruleStr = '';
        foreach ($rules as $r) {
            if ($r->type == 'min' || $r->type == 'max') {
                $r->type = $r->type . ':' . $r->val;
            }

            if ($r->type == 'unique') {
                $r->type = '';
            }

            if ($ruleStr == '') {
                $ruleStr = $r->type;
            } else {
                $ruleStr .= '|' . $r->type;
            }
        }

        $item = [
            $model => $ruleStr,
        ];

        return $item;
    }

    public static function checkUnique()
    {
        $table = request('table');
        $identityColumn = request('identityColumn');
        $identity = request('identity');
        $field = request('field');
        $val = request('val');

        if ($identityColumn && $identity) {
            $isExist = DB::table($table)->where($identityColumn, '!=', $identity)->where($field, $val)->first();
        } else {
            $isExist = DB::table($table)
                ->where($field, $val)
                ->first();
        }

        if ($isExist) {
            return [
                'status' => false,
                'msg' => "'" . $val . "' утга бүртгэлтэй байна"
            ];
        }

        return [
            'status' => true,
        ];
    }

    function checkGenerated($model)
    {
        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $generated = $this->generateCode($permitted_chars, 10);

        $r = DB::table($this->dbSchema->model)
            ->select($model)
            ->where($model, $generated)
            ->first();

        if ($r) {
            $this->checkGenerated($model);
        }
        return $generated;
    }

    function generateCode($input, $strength = 16)
    {
        $input_length = strlen($input);
        $random_string = '';
        for ($i = 0; $i < $strength; $i++) {
            $random_character = $input[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }

        return $random_string;
    }

    public static function checkCurrentPassword()
    {
        $password = request('password');
        $user = JWTAuth::parseToken()->toUser();

        if (!Hash::check($password, $user->password)) {
            return [
                'status' => false,
                'msg' => "Нууц үг буруу байна !!!"
            ];
        }

        return [
            'status' => true,
        ];
    }
}
