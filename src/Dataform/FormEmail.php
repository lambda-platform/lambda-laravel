<?php

namespace Lambda\Dataform;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use CURLFile;
use Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use mysql_xdevapi\Exception;

trait FormEmail
{
    public function sendEmail($data, $schema)
    {
        Log::debug('SEND EMAIL WORKED: '. Carbon::now());
        Log::debug('DATA: '. json_encode($schema->email));

        if (isset($schema->email) && count($schema->email->to) > 0 && $schema->email->subject) {
            $email = $schema->email;
            $to = $email->to;
            $cc = $email->cc;
            $bcc = $email->bcc;

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
                $findStr = '[[' . $key . ']]';
                $body = str_replace($findStr, $value, $body);
            }

            $pdfData = mb_convert_encoding(\View::make('puzzle::email', ['body' => $body, 'title' => $email->subject]), 'HTML-ENTITIES', 'UTF-8');
            $attach_file_name='attach.pdf';
            Pdf::loadHTML($pdfData)->setWarnings(false)->save($attach_file_name);

            $subject = urlencode($email->subject);

//            $config = null;
//            if (env('DB_CONNECTION') == 'pgsql') {
//                $config = DB::table('public.api_config')->where('code', '10008')->first();
//            } else {
//                $config = DB::table('api_config')->where('code', '10008')->first();
//            }

//            $config = new \stdClass();
//            $config->url = 'https://192.168.7.101:9150/notification/mail';

            //if ($config)
            {
                // $emailUri = $config->url . "?toAddress=" . $toAddress . "&subject=" . $subject . "&body=" . $body . "&ccAddress=" . $ccAddress . "&contentType=text/html;%20charset=UTF-8";

                try {
                    Log::info('START TO SENT EMAIL: ' . Carbon::now());
                    foreach ($to as $t) {
                        Mail::send([], [],
                            function ($message) use ($t, $subject, $ccAddress, $body, $attach_file_name) {
                                $message->to($t);
                                $message->subject($subject);
                                $message->setBody($body, 'text/html');
                                $message->attach($attach_file_name);
                            });
                    }

                } catch (Exception $e) {
                    Log::info('Email error: ' . $e);
                }
            }
        };
    }
}