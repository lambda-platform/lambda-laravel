<?php

namespace Lambda\Dataform;

use Illuminate\Support\Facades\DB;

trait Utils
{
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
        if(isset($this->dbSchema->triggers->cache_clear_url) && $this->dbSchema->triggers->cache_clear_url) {
            $config = null;

            if (DB::connection()->getDriverName() == 'pgsql') {
                $config = DB::table('public.api_config')->where('code', '10011')->first();
            } else {
                $config = DB::table('api_config')->where('code', '10011')->first();
            }
            //dd($config->url.$this->dbSchema->triggers->cache_clear_url);
            if ($config) {
                try {
                    if ($config->url && $config->auth_username
                        && $config->auth_pass) {
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $config->url . $this->dbSchema->triggers->cache_clear_url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_CONNECTTIMEOUT => 5,
                            CURLOPT_TIMEOUT => 15,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_CUSTOMREQUEST => "GET",
                            CURLOPT_HTTPHEADER => array(
                                'Content-Type: application/json',
                                "Authorization: Basic " . base64_encode($config->auth_username . ":" . $config->auth_pass)
                            ),
                        ));

                        if (!$result = curl_exec($curl)) {
                            trigger_error(curl_error($curl));
                        }

                        curl_close($curl);
                        if ($result == null)
                            return 0;
                        return $result;
                    }
                } catch (\Exception $ex) {
                    return $ex->getMessage();
                }
            }
        }
        return 0;
    }
}
