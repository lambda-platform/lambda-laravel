<?php

namespace Lambda\Dataform;

use App\Helpers\ConfigHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use CURLFile;
use Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lambda\Dataform\Helper;
use Illuminate\Support\Str;

trait FormEmail
{
    use Utils;

    public function sendEmail($data, $schema)
    {
        if(isset($schema->email) && isset($schema->email->has_custom_trigger) && $schema->email->has_custom_trigger)
        {
            Log::debug('EMAIL - CUSTOM TRIGGER WORKED: '.isset($schema->email).'-'.isset($schema->email->has_custom_trigger).'-' . json_encode($schema->email));
            if (isset($schema->email->custom_trigger) && method_exists(app($schema->email->custom_trigger), $schema->email->custom_trigger_function)) {
                Log::debug('EMAIL - START WORKING CUSTOM EMAIL TRIGGER: ' . $schema->email->custom_trigger.'@'.$schema->email->custom_trigger_function);
                app($schema->email->custom_trigger)->{$schema->email->custom_trigger_function}($data, $schema);
            }
        }
        else{
            Log::debug('EMAIL - NORMAL EMAIL WORKED: ' . json_encode($schema));
            $this->sendEmailNormal($data,$schema);
        }

    }
    public function sendEmailNormal($data,$schema)
    {
        Log::debug('EMAIL - SEND EMAIL NORMAL LAMBDA FUNCTION: ' . Carbon::now());
        Log::debug('EMAIL - DATA: ' . json_encode($schema->email));

        if (isset($schema->email) && !empty($schema->email->to) && !empty($schema->email->subject)) {


            $config = null;
            try {
                if (DB::connection()->getDriverName() == 'pgsql') {
                    $config = DB::table('public.api_config')->where('code', '10013')->first();
                } else {
                    $config = DB::table('api_config')->where('code', '10013')->first();
                }
            }
            catch(\Exception $ex)
            {
                Log::error('Email Config: no config DB::table(api_config)->where(code, 10013)');
            }

            $email = $schema->email;
            $to = $email->to;
            $cc = $email->cc ?? [];
            $bcc = $email->bcc ?? [];

            $ccAddress = "";
            foreach ($cc as $t) {
                if ($ccAddress == "") {
                    $ccAddress = $t;
                } else {
                    $ccAddress .= "," . $t;
                }
            }

            $bccAddress = "";
            foreach ($bcc as $t) {
                if ($bccAddress == "") {
                    $bccAddress = $t;
                } else {
                    $bccAddress .= "," . $t;
                }
            }

            $body = $email->body;
            foreach ($data as $key => $value) {
                // Multi-select / json fields come as arrays, nulls as null
                $value = is_scalar($value) || $value === null ? (string)$value : json_encode($value, JSON_UNESCAPED_UNICODE);

                if ($config && str_contains($value, 'uploaded')) {
                    $value = str_replace(' ', '%20', $value);
                    $value = str_replace('\\', '/', $value);
                    $url=$config->host.$value;
                    $downloadLink='<a href="'.$url.'" target="_blank">Татаж авах</a>';
                    $findStr = '[[' . $key . ']]';
                    $body = str_replace($findStr, $downloadLink, $body);
                } else{
                    $findStr = '[[' . $key . ']]';
                    $body = str_replace($findStr, $value, $body);
                }

            }
            // Unique file outside public/ so concurrent submissions don't overwrite or expose each other's PDF
            $attach_file_name = storage_path('app/attach_' . Str::uuid() . '.pdf');
            $hasAttach = isset($schema->email->has_attach) && $schema->email->has_attach;
            if ($hasAttach) {
                $html = (string)\View::make('puzzle::email', ['body' => $body, 'title' => $email->subject]);
                $pdfData = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
                Pdf::loadHTML($pdfData)->setWarnings(false)->save($attach_file_name);
            }
            //$subject = urlencode($email->subject);
            $subject = mb_convert_encoding($email->subject,'UTF-8');
            //$subject = urlencode($email->subject);
            Helper\ConfigHelper::setMailConfig();

            foreach ($to as $t) {
                try {
                    Log::debug('EMAIL - START TO SEND: ' . $t);
                    $email = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Mail::send([], [],
                            function ($message) use ($t, $subject, $ccAddress, $bccAddress, $body, $schema, $attach_file_name) {
                                $message->to($t);
                                if ($ccAddress != "") {
                                    $message->cc($ccAddress);
                                }
                                if ($bccAddress != "") {
                                    $message->bcc($bccAddress);
                                }
                                $message->subject($subject);
                                if (method_exists($message, 'getSwiftMessage')) {
                                    // Laravel <= 8 (SwiftMailer)
                                    $message->setBody($body, 'text/html');
                                } else {
                                    // Laravel 9+ (Symfony Mailer)
                                    $message->html($body);
                                }
                                if (isset($schema->email->has_attach) && $schema->email->has_attach) {
                                    $message->attach($attach_file_name);
                                }
                            });
                        Log::info('EMAIL - DONE: ' . $t);
                    } else {
                        Log::error('EMAIL - validation error:' . $t);
                    }
                } catch (\Throwable $e) {
                    Log::error('EMAIL - Email error: ' . $e);
                }
            }

            if ($hasAttach && is_file($attach_file_name)) {
                @unlink($attach_file_name);
            }
        }
    }

}
