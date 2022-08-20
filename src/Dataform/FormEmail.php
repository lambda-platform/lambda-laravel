<?php

namespace Lambda\Dataform;

use Barryvdh\DomPDF\Facade\Pdf;
use CURLFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use mysql_xdevapi\Exception;

trait FormEmail
{
    public function sendEmail($data, $schema)
    {
        if (isset($schema->email)) {
            $email = $schema->email;
            $to = $email->to;
            $cc = $email->cc;
            $bcc = $email->bcc;

            $toAddress = "";
            foreach ($to as $t) {
                if ($toAddress == "") {
                    $toAddress = $t;
                } else {
                    $toAddress .= "," . $t;
                }
            }

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
            $pdf = PDF::loadView('puzzle::email', ['body' => html_entity_decode($body), 'title' => $email->subject]);
            $body = urlencode($body);
            $subject = urlencode($email->subject);

            file_put_contents("email.pdf", $pdf->output());
            $attach = new CURLFILE("email.pdf");
            $attach->setPostFilename($email->subject ? $email->subject . '.pdf' : 'attach.pdf');

            $emailUri = "https://192.168.7.101:9150/notification/mail?toAddress=" . $toAddress . "&subject=" . $subject . "&body=" . $body . "&ccAddress=" . $ccAddress . "&contentType=text/html;%20charset=UTF-8";
            try {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $emailUri,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => array('file' => $attach),
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer eyJhbGciOiJIUzUxMiJ9.eyJhdWQiOiJub3RpZmljYXRpb24tc2VydmljZS0xIiwiaXNzIjoibm90aWZpY2F0aW9uLXNlcnZpY2UtMSIsInR5cGUiOiJTeXN0ZW0iLCJleHAiOjI2MDMxNjgyMTAsImlhdCI6MTYwMjU2ODIxMH0.xLVPMnJlimQxezX1AOdJ1PvpPtToaEQnkUUv9qtZg9A4hrjUY56i98PVjhhqSNR671BLvAe3QDKC_Me3mWf36Q'
                    ),
                ));

                $response = curl_exec($curl);
                curl_close($curl);
            }catch (Exception $e){

            }
        };
    }
}
