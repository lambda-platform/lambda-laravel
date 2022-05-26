<?php

namespace Lambda\Dataform;

trait Field
{
    private $field;

    public function meta($model = null, $label = null, $type, $meta = null)
    {
        if ($model !== null) {
            $field['model'] = $model;
        }

        if ($label !== null) {
            $field['label'] = $label;
        }

        $field['type'] = $type;

        if ($meta !== null) {
            $field['meta'] = $meta;
        }

        $this->field = $field;

        return $this;
    }

    public function rule($rule, $msg = null)
    {
        if (!array_key_exists('rule', $this->field)) {
            $this->field['rule'] = [];
        }

        $ruleVal = null;
        if (strpos($rule, 'min') !== false || strpos($rule, 'max') !== false) {
            $ruleAsArr = explode(':', $rule);
            $rule = $ruleAsArr[0];
            $ruleVal = $ruleAsArr[1];
        }

        $r['type'] = $rule;

        if ($ruleVal !== null) {
            $r['val'] = $ruleVal;
        }

        if ($msg !== null) {
            $r['msg'] = $msg;
        }
        array_push($this->field['rule'], $r);

        return $this;
    }

    public function add()
    {
        array_push($this->schema, $this->field);

        return $this->field;
    }
}
