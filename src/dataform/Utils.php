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
}
