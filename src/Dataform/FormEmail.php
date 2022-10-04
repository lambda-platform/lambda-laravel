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
//        Log::debug('SEND EMAIL WORKED: '. Carbon::now());
//        Log::debug('DATA: '. json_encode($schema->email));

        if (isset($schema->email) && count($schema->email->to) > 0 && $schema->email->subject) {
            $email = $schema->email;
            $to = $email->to;
            $cc = $email->cc;
            $bcc = $email->bcc;

//            $toAddress = "";
//            foreach ($to as $t) {
//                if ($toAddress == "") {
//                    $toAddress = $t;
//                } else {
//                    $toAddress .= "," . $t;
//                }
//            }

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



            //dd(html_entity_decode($body));
            //$pdf = PDF::loadView('puzzle::email', ['body' => $body, 'title' => $email->subject]);
            $pdfData = mb_convert_encoding(\View::make('puzzle::email', ['body' => $body, 'title' => $email->subject]), 'HTML-ENTITIES', 'UTF-8');
            $attach_file_name='attach.pdf';
            Pdf::loadHTML($pdfData)->setWarnings(false)->save($attach_file_name);


            //$pdf=PDF::loadHtml($pdfData);
            //$pdf->set_option('font-family', 'Tahoma');
            //$body = $body;
            $subject = urlencode($email->subject);

            // $now = Carbon::now()->toAtomString();
            // $attach_file_name = $email->subject ? $email->subject . '-' . $now . '.pdf' : 'attach' . $now . '.pdf';
            //file_put_contents($attach_file_name, $pdf->output());

            // Pdf::loadHTML($body)->setPaper('a4', 'landscape')->setWarnings(false)->save($attach_file_name);
            //file_put_contents('attach.pdf', $pdf->output());
//            $attach = new CURLFILE("email.pdf");
//            $attach->setPostFilename($email->subject ? $email->subject . '.pdf' : 'attach.pdf');
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

                    Log::info('ON TRY CURL: ' . Carbon::now());
                    foreach ($to as $t) {
                        Mail::send([], [],
                            function ($message) use ($t, $subject, $ccAddress, $body, $attach_file_name) {
                                $message->to($t);
                                $message->subject($subject);
                                $message->setBody($body, 'text/html');
                                $message->attach($attach_file_name);
                            });

                    }


//                    $curl = curl_init();
//                    curl_setopt_array($curl, array(
//                        CURLOPT_URL => $emailUri,
//                        CURLOPT_RETURNTRANSFER => true,
//                        CURLOPT_ENCODING => '',
//                        CURLOPT_MAXREDIRS => 10,
//                        CURLOPT_TIMEOUT => 30,
//                        CURLOPT_SSL_VERIFYPEER => true,
//                        CURLOPT_FOLLOWLOCATION => true,
//                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                        CURLOPT_CUSTOMREQUEST => 'POST',
//                        CURLOPT_POSTFIELDS => array('file' => $attach),
//                        CURLOPT_HTTPHEADER => array(
//                            'Authorization: Bearer eyJhbGciOiJIUzUxMiJ9.eyJhdWQiOiJub3RpZmljYXRpb24tc2VydmljZS0xIiwiaXNzIjoibm90aWZpY2F0aW9uLXNlcnZpY2UtMSIsInR5cGUiOiJTeXN0ZW0iLCJleHAiOjI2MDMxNjgyMTAsImlhdCI6MTYwMjU2ODIxMH0.xLVPMnJlimQxezX1AOdJ1PvpPtToaEQnkUUv9qtZg9A4hrjUY56i98PVjhhqSNR671BLvAe3QDKC_Me3mWf36Q'
//                        ),
//                    ));
//
//                    $response = curl_exec($curl);
//                    curl_close($curl);

//                    if ($response != false) {
//                        DB::table('public.log_email')->insert([
//                            'to' => $toAddress,
//                            'cc' => $ccAddress,
//                            'subject' => $subject,
//                            'email' => $body,
//                            'is_sent' => true,
//                            'created_at' => Carbon::now()
//                        ]);
//                    } else {
//                        DB::table('public.log_email')->insert([
//                            'to' => $toAddress,
//                            'cc' => $ccAddress,
//                            'subject' => $subject,
//                            'email' => $body,
//                            'is_sent' => false,
//                            'created_at' => Carbon::now()
//                        ]);
//                    }

                } catch (Exception $e) {
                    dump($e);
                }
            }
        };
    }
}