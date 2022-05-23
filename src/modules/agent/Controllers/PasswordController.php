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

class PasswordController extends Controller
{
    function sendMail()
    {
        $config = Config::get('lambda');
        $lang = Request::input('lang');

        $static_words = $config['static_words'][$lang];
        $email = strtolower(Request::input('email'));

        $user = DB::table('users')->where('email', $email)->first();

        if (!$email) {
            return response()->json(['status' => false, 'error' => $static_words['emailRequired']], 401);
        }

        if ($user) {
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $token_pre = substr(str_shuffle($permitted_chars), 0, 8);
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
        $email = Request::input('email');
        $password = Request::input('password');
        $password_confirm = Request::input('password_confirm');
        $config = Config::get('lambda');
        $lang = Request::input('lang');

        $static_words = $config['static_words'][$lang];
        $password_reset_time_out = $config['password_reset_time_out'];

        $reset = DB::table("password_resets")->where('email', $email)->first();
        $user = DB::table('users')->where('email', $email)->first();

        if (!$reset || !$user)
            return response()->json(['status' => false, 'error' => $static_words['passwordResetCodeRequired']], 401);

        $now = \Carbon\Carbon::now();
        $create_at = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $reset->created_at);


        $diff_in_minutes = $now->diffInMinutes($create_at);
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


}
