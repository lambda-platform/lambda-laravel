<?php

namespace Lambda\Dataform;

use Illuminate\Support\Facades\Http;

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

            $emailUri = "https://192.168.7.101:9150/notification/mail?toAddress=" . $toAddress . "&subject=" . $email->subject . "&body=" . $body . "&ccAddress=" . $ccAddress . "&contentType=text/html; charset=UTF-8";
            $response = Http::withToken('eyJhbGciOiJIUzUxMiJ9.eyJhdWQiOiJub3RpZmljYXRpb24tc2VydmljZS0xIiwiaXNzIjoibm90aWZpY2F0aW9uLXNlcnZpY2UtMSIsInR5cGUiOiJTeXN0ZW0iLCJleHAiOjI2MDMxNjgyMTAsImlhdCI6MTYwMjU2ODIxMH0.xLVPMnJlimQxezX1AOdJ1PvpPtToaEQnkUUv9qtZg9A4hrjUY56i98PVjhhqSNR671BLvAe3QDKC_Me3mWf36Q')->post($emailUri);
        };
    }
}
