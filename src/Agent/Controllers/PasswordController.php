<?php
/**
 * Created by PhpStorm.
 * User: munkh-altai
 * Date: 1/22/19
 * Time: 3:14 PM
 */


namespace Lambda\Agent\Controllers;

use App\Http\Controllers\Controller;
use Request;
use Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    function sendMail()
    {
        $config = Config::get('lambda');
        $static_words = $this->staticWords($config, Request::input('lang'));
        $email = strtolower((string)Request::input('email'));

        if (!$email) {
            return response()->json(['status' => false, 'error' => $static_words['emailRequired']], 401);
        }

        $user = DB::table('users')->where('email', $email)->first();

        if ($user) {
            // Cryptographically secure reset code
            $token_pre = Str::random(8);
            $token = bcrypt($token_pre);

            DB::table("password_resets")->where('email', $email)->delete();
            DB::table("password_resets")->insert(['email' => $email, 'token' => $token, 'created_at' => \Carbon\Carbon::now()]);


            Mail::send('agent::emails.forgot', ['token' => $token_pre, 'static_words' => $static_words], function ($message) use ($email, $static_words) {
                $message->to($email);
                $message->subject($static_words['passwordResetCode']);
            });

            return response()
                ->json([
                    'status' => true,
                    'msg' => $static_words['passwordResetCodeSent'],
                ], 200);

        } else {
            return response()->json(['status' => false, 'error' => $static_words['userNotFound']], 401);
        }
    }

    public function passwordReset()
    {
        $code = Request::input('code');
        $email = strtolower((string)Request::input('email'));
        $password = Request::input('password');
        $password_confirm = Request::input('password_confirm');
        $config = Config::get('lambda');

        $static_words = $this->staticWords($config, Request::input('lang'));
        $password_reset_time_out = $config['password_reset_time_out'] ?? 30;

        $reset = DB::table("password_resets")->where('email', $email)->first();
        $user = DB::table('users')->where('email', $email)->first();

        if (!$reset || !$user)
            return response()->json(['status' => false, 'error' => $static_words['passwordResetCodeRequired']], 401);

        $now = \Carbon\Carbon::now();
        $create_at = \Carbon\Carbon::parse($reset->created_at);

        $diff_in_minutes = abs($now->diffInMinutes($create_at));
        if ($password_reset_time_out >= $diff_in_minutes) {
            if (Hash::check($code, $reset->token)) {
                if ($password && $password_confirm && $password == $password_confirm) {
                    $password = bcrypt($password);

                    DB::table('users')->where('id', $user->id)->update(['password' => $password]);
                    DB::table("password_resets")->where('email', $email)->delete();
                    return response()
                        ->json([
                            'status' => true,
                            'msg' => $static_words['passwordResetSuccess'],
                        ], 200);
                } else {
                    return response()->json(['status' => false, 'error' => $static_words['passwordConfirmError']], 401);
                }

            } else {
                return response()->json(['status' => false, 'error' => $static_words['passwordResetCodeIncorrect']], 401);
            }

        } else {
            return response()->json(['status' => false, 'error' => $static_words['passwordResetCodeTimeout']], 401);
        }


    }

    private function staticWords($config, $lang)
    {
        $words = $config['static_words'] ?? [];
        if (isset($words[$lang])) {
            return $words[$lang];
        }
        return reset($words) ?: [];
    }
}
