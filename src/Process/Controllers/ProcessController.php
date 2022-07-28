<?php

namespace Lambda\Process\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ProcessController extends Controller
{
    public function getProcessList()
    {
        return DB::table('vb_processes')->get();
    }
}
