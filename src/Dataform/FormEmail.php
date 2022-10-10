<?php

namespace Lambda\Dataform;

use App\Helpers\ConfigHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Mail;
use Illuminate\Support\Facades\Log;
use mysql_xdevapi\Exception;
use Lambda\Dataform\Helper;

trait FormEmail
{
    public function sendEmail($data, $schema)
    {
        Log::debug('EMAIL - SEND EMAIL WORKED: ' . Carbon::now());
        Log::debug('EMAIL - DATA: ' . json_encode($schema->email));

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
            $attach_file_name = 'attach.pdf';
            Pdf::loadHTML($pdfData)->setWarnings(false)->save($attach_file_name);

            $subject = urlencode($email->subject);
            Helper\ConfigHelper::setMailConfig();

            foreach ($to as $t) {
                try {
                    Log::debug('EMAIL - START TO SEND: ' . $t);
                    $email = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if($email &&  filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        Mail::send([], [],
                            function ($message) use ($t, $subject, $ccAddress, $body, $attach_file_name) {
                                $message->to($t);
                                $message->subject($subject);
                                $message->setBody($body, 'text/html');
                                $message->attach($attach_file_name);
                            });
                        Log::info('EMAIL - DONE: ' . $t);
                    }else {
                        Log::error('EMAIL - validation error:' . $t);
                    }
                } catch (Exception $e) {
                    Log::error('EMAIL - Email error: ' . $e);
                }
            }

        }
    }
}
