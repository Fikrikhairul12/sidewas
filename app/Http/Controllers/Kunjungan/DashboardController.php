<?php

namespace App\Http\Controllers\Kunjungan;

use App\Http\Controllers\Controller;
use App\Services\Kunjungan\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        return view('kunjungan.dashboard', $dashboard->data($request->user()));
    }
}
