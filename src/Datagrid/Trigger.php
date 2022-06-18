<?php

namespace Lambda\Datagrid;

trait Trigger
{
    //For specific ID
    public function callTrigger($action, $qrOrData, $id = null)
    {

        if (!property_exists($this->dbSchema, 'triggers')) {
            return $qrOrData;
        }

        if (!property_exists($this->dbSchema->triggers, 'namespace')) {
            return $qrOrData;
        }

        switch ($action) {
            case 'beforeFetch':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->beforeFetch, $qrOrData);
            case 'afterFetch':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->afterFetch, $qrOrData);
                break;
            case 'beforeDelete':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->beforeDelete, $qrOrData, $id);
            case 'afterDelete':
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->afterDelete, $qrOrData, $id);
                break;
            case 'beforePrint':
                if (!property_exists($this->dbSchema->triggers, 'beforePrint')) {
                    return $qrOrData;
                }
                return $this->execTrigger($this->dbSchema->triggers->namespace, $this->dbSchema->triggers->beforePrint, $qrOrData);
                break;
        }
    }

    public function execTrigger($namespace, $trigger, $qrOrData, $id = null)
    {
        if ($trigger == null || $trigger == '') {
            return $qrOrData;
        }

        $trigger = explode('@', $trigger);
//        dump($trigger[0]);
        if (is_array($trigger)) {
            if (method_exists(app($namespace . "\\" . $trigger[0]), $trigger[1])) {
                if ($id == null) {
                    $modified = app($namespace . "\\" . $trigger[0])->{$trigger[1]}($qrOrData);
                    if ($modified !== null) {
                        return $modified;
                    }
                } else {
                    $modified = app($namespace . "\\" . $trigger[0])->{$trigger[1]}($qrOrData, $id);
                    if ($modified !== null) {
                        return $modified;
                    }
                }

            }
        }

        return $qrOrData;
    }
}
