<?php

namespace Lambda\Agent\Controllers;

use Carbon\Carbon;
//use Intervention\Image\Facades\Image;
//use Lambda\Agent\Models\Permission;
//use Lambda\Agent\Models\Role;
//use Lambda\Agent\Models\Profile;
//use Lambda\Agent\Request\UserRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function __construct()
    {
//        $this->middleware('web');
    }

    public function wizard()
    {
        return view('agent::wizard');
    }

    public function index()
    {
        return view('agent::index');
    }

    function getUsers($deleted = false)
    {
        if ($deleted == false) {
            $qr = DB::table('users')
                ->where('deleted_at', null)
                ->orderBy(request()->sort, request()->direction);
            if (request()->role != 'all') {
                $qr = $qr->where('role', request()->get('role'));
            }

            return $qr->paginate(16);
        }

        $qr = DB::table('users')->where('deleted_at', '!=', null)->orderBy(request()->sort, request()->direction);
        if (request()->role != 'all') {
            $qr = $qr->where('role', request()->get('role'));
        }
        return $qr->paginate(16);
    }

    function getUser($id)
    {
        return DB::table('users')->find($id);
    }

    function deleteUser($id)
    {
        $r = DB::table('users')->where('id', $id)->update([
            'deleted_at' => Carbon::now()
        ]);

        if ($r) {
            return ['status' => true];
        }
        return ['status' => false];
    }

    function deleteUserComplete($id)
    {
        $r = DB::table('users')->delete($id);

        if ($r) {
            return ['status' => true];
        }
        return ['status' => false];
    }

    function restoreUser($id)
    {
        $r = DB::table('users')->where('id', $id)->update([
            'deleted_at' => null
        ]);

        if ($r) {
            return ['status' => true];
        }
        return ['status' => false];
    }

    function searchUsers($q = null)
    {
        if ($q == '' || $q == null) {
            return DB::table('users')->where('deleted_at', null)->paginate(18);
        }

        $r = DB::table('users')
            ->where('deleted_at', null)
            ->whereRaw('lower(CONCAT_WS(\' \',login,first_name,last_name,phone)) like lower(\'%' . $q . '%\')')
            ->paginate(18);

        if ($r) {
            return ['status' => true, 'data' => $r];
        }
        return ['status' => false];
    }

    function getRoles()
    {
        return DB::table('roles')->select('id', 'display_name')->get();
    }
}
