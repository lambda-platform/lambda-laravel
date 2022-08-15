<?php

namespace Lambda\Dataform;

trait Utils
{
    //For specific ID
    public function setID()
    {
        foreach ($this->schema as $s) {
            // Sub forms
            if ($s->formType == 'SubForm') {
                $subForm = new \stdClass();
                $subForm->data = request()->get($s->model);
                $subForm->parent = $s->parent;
                $subForm->model = $s->model;
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
            } //Main form
            else {
                array_push($models, $s->model);
                if ($this->dbSchema->identity == $s->model) {
                    if ($s->extra == '' || $s->extra == null) {
                        $generatedID = (string)Uuid::generate();
                        $identityModel = $s->model;
                    } else {
                        continue;
                    }
                }

                if (($s->model == 'created_at' || $s->model == 'updated_at') && $this->dbSchema->timestamp) {
                    continue;
                }
            }
        }
    }

    public function callTrigger($action, $qrOrData, $id = null)
    {
        if (!property_exists($this->dbSchema, 'triggers')) {
            return $qrOrData;
        }

        if (!property_exists($this->dbSchema->triggers, 'namespace')) {
            return $qrOrData;
        }

        switch ($action) {
            case 'beforeInsert':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->insert->before, $qrOrData);
            case 'afterInsert':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->insert->after, $qrOrData, $id);
                break;
            case 'beforeUpdate':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->update->before, $qrOrData, $id);
            case 'afterUpdate':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->update->after, $qrOrData, $id);
                break;
            case 'beforeDelete':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->delete->before, $qrOrData, $id);
                break;
            case 'afterDelete':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->delete->after, $qrOrData, $id);
                break;
        }
    }

    public function execTrigger($namespace, $trigger, $data, $id = null)
    {
        if ($trigger == null || $trigger == '') {
            return $data;
        }
        $trigger = explode('@', $trigger);
        if (is_array($trigger)) {
            if (method_exists(app($namespace . "\\" . $trigger[0]), $trigger[1])) {
                if ($id == null) {
                    $modifiedData = app($namespace . "\\" . $trigger[0])->{$trigger[1]}($data);
                    if ($modifiedData !== null) {
                        return $modifiedData;
                    }
                } else {
                    $modifiedData = app($namespace . "\\" . $trigger[0])->{$trigger[1]}($data, $id);
                    if ($modifiedData !== null) {
                        return $modifiedData;
                    }
                }

            }
        }

        return $data;
    }

    public function cacheClear()
    {
        if (!property_exists($this->dbSchema, 'triggers')) {
            return 0;
        }

        if (!property_exists($this->dbSchema->triggers, 'cache_clear_url')) {
            return 0;
        }

        if ($this->dbSchema->triggers->cache_clear_url) {
            //do
            try {
                if (env('CACHE_BASE_URL') !== null && env('CACHE_BASE_URL') !== ''
                    && env('CACHE_AUTH_USERNAME') !== null && env('CACHE_AUTH_USERNAME') !== ''
                    && env('CACHE_AUTH_PASSWORD') !== null && env('CACHE_AUTH_PASSWORD') !== '') {
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => env('CACHE_BASE_URL') . $this->dbSchema->triggers->cache_clear_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_HTTPHEADER => array(
                            "Authorization: Basic " . base64_encode(env('CACHE_AUTH_USERNAME') . ":" . env('CACHE_AUTH_PASSWORD')),
                            "Content-Type: application/json",
                        ),
                    ));

                    $qpayResLogin = json_decode(curl_exec($curl));
                    curl_close($curl);
                    if ($qpayResLogin == null)
                        return 0;
                    return 1;
                }
            } catch (\Exception $ex) {
                return $ex->getMessage();
            }
        }
        return 0;
    }
}
