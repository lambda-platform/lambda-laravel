<?php

namespace Lambda\Notify\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;
use Lambda\Notify\Notify;

class NotifyController extends Controller
{
    public function getNewNotifications($user)
    {
        $unseenCount = DB::table('notification_status')
            ->where('receiver_id', $user)
            ->where('seen', 0)
            ->count();

//        $notifications = DB::table('notification_status as s')
//            ->join('notifications as n', 'n.id', '=', 's.notif_id')
//            ->join('users as u', 'u.id', '=', 'n.sender')
//            ->select('n.*', 'u.first_name', 'u.login', 's.id as sid', 's.seen')
//            ->where('s.receiver_id', $user)
//            ->orderBy('created_at', 'desc')
//            ->take('30')
//            ->get();

        $notifications = DB::select("select `n`.*, `u`.`first_name`, `u`.`login`, `s`.`id` as `sid`, `s`.`seen`
from `notification_status` as `s`
inner join `notifications` as `n` on `n`.`id` = `s`.`notif_id`
inner join `users` as `u` on `u`.`id` = `n`.`sender`  COLLATE utf8_general_ci
where `s`.`receiver_id` = '" . $user . "' order by `created_at` desc limit 30");


        return response()->json(['count' => $unseenCount, 'notifications' => $notifications]);
    }

    public function getAllNotifications()
    {

        $notifications = DB::select("select `n`.*, `u`.`first_name`, `u`.`login`, `s`.`id` as `sid`, `s`.`seen`
from `notification_status` as `s`
inner join `notifications` as `n` on `n`.`id` = `s`.`notif_id`
inner join `users` as `u` on `u`.`id` = `n`.`sender`  COLLATE utf8_general_ci
where `s`.`receiver_id` = '" . auth()->id() . "' order by `created_at` desc limit 30");

//        $notifications = DB::table('notifications')
//            ->where('receiver_id', auth()->id())
//            ->orderBy('created_at', 'desc')
//            ->paginate('50');
        return response()->json($notifications);
    }

    function setSeen($id)
    {
        $r = DB::table('notification_status')
            ->where('notif_id', $id)
            ->update([
                'seen' => true,
                'seen_time' => Carbon::now()
            ]);
        if ($r) {
            return response()->json(['status' => true]);
        }
        return response()->json(['status' => false]);
    }

    function setToken($userId, $token)
    {
        $r = DB::table('users')
            ->where('id', $userId)
            ->update([
                'token' => $token
            ]);

        if ($r) {
            return response()->json(['status' => true]);
        }
    }

    function test()
    {
        $data = [
            'title' => 'lambda notification',
            'body' => 'lambda notification msg body',
            'link' => 'http://luna.test',
            'users' => [189, 86]
        ];


        $client = new Client(new Version2X('http://localhost:3000'));
        $client->initialize();
        $client->emit('notify', $data);
        $client->close();
    }

    function fcm()
    {
        $receivers = [];

        $users = DB::table('users')->whereNotNull('token')->get();
        foreach ($users as $u) {
            array_push($receivers, $u->token);
        }

        $msg = [
            'message' => 'here is a message. message',
            'title' => 'This is a title. title',
            'subtitle' => 'This is a subtitle. subtitle',
            'tickerText' => 'Ticker text here...Ticker text here...Ticker text here',
            'vibrate' => 1,
            'sound' => '/lambda/notification.mp3',
            'largeIcon' => 'large_icon',
            'smallIcon' => 'small_icon'
        ];

        Notify::sendNotification($receivers, $msg);
    }
}
