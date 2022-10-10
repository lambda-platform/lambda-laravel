<?php

namespace Lambda\Dataform\Helper;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function MongoDB\BSON\toJSON;

class ConfigHelper
{
    public static function setMailConfig(){

        //Get the data from settings table
            $config = null;
            try {
                if (env('DB_CONNECTION') == 'pgsql') {
                    $config = DB::table('public.api_config')->where('code', '10012')->first();
                } else {
                    $config = DB::table('api_config')->where('code', '10012')->first();
                }
            }
            catch(\Exception $ex)
            {
                Log::error('Config: no config DB::table(api_config)->where(code, 10012)');
            }

     if($config) {

         $mailConfig = null;
         if($config->token=='ssl') {
             $mailConfig = [
                 'transport' => $config->method,
                 'host' => $config->host,
                 'port' => $config->port,
                 'encryption' => $config->token,
                 'username' => $config->auth_username,
                 'password' => $config->auth_pass,
                 'timeout' => null,
                 'auth_mode' => null,
                 'stream' => [
                     'ssl' => [
                         'allow_self_signed' => true,
                         'verify_peer' => false,
                         'verify_peer_name' => false,
                     ],
                 ],
             ];
         }
         else{
             $mailConfig = [
                 'transport' => $config->method,
                 'host' => $config->host,
                 'port' => $config->port,
                 'encryption' => $config->token,
                 'username' => $config->auth_username,
                 'password' => $config->auth_pass,
                 'timeout' => null,
                 'auth_mode' => null
             ];
         }
         $mailFromConfig=[
             'address' => $config->auth_username,
             'name' =>  $config->body,
         ];

         config(['mail.mailers.smtp' => $mailConfig]);
         config(['mail.from' => $mailFromConfig]);
         Log::info('EMAIL - config SMPT:',$mailConfig);
         Log::info('EMAIL - config FROM:',$mailFromConfig);
        // dd(config('mail.mailers'));
     }
    }
}
