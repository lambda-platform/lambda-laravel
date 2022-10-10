<?php

namespace Lambda\Datagrid;

use Illuminate\Support\Facades\DB;

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
            case 'excelImport':
                return $this->execTrigger($this->dbSchema->excelUploadCustomNamespace, $this->dbSchema->excelUploadCustomTrigger, $qrOrData);
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

    public function cacheClear()
    {
        if(isset($this->dbSchema->triggers->cacheClearUrl) && $this->dbSchema->triggers->cacheClearUrl)
        {
            $config = null;

            if (env('DB_CONNECTION') == 'pgsql') {
                $config = DB::table('public.api_config')->where('code', '10011')->first();
            } else {
                $config = DB::table('api_config')->where('code', '10011')->first();
            }
            if ($config) {
                try {
                    if ($config->url && $config->auth_username
                        && $config->auth_pass) {
                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $config->url . $this->dbSchema->triggers->cacheClearUrl,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
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
