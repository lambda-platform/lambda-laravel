<?php

namespace Lambda\Agent\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Lambda\Agent\Models\Permission;
use Lambda\Agent\Models\Role;
use Lambda\Agent\Models\User;
use Lambda\Agent\Models\Profile;
use Lambda\Agent\Request\UserRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
            return DB::table('users')->where('deleted_at', null)->paginate(16);
        }
        $r = DB::table('users')
            ->where('deleted_at', null)
            ->where('login', 'like', '%' . $q . '%')
            ->orWhere('first_name', 'like', '%' . $q . '%')
            ->orWhere('last_name', 'like', '%' . $q . '%')
            ->orWhere('register_number', 'like', '%' . $q . '%')
            ->orWhere('phone', 'like', '%' . $q . '%')
            ->paginate(16);
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
