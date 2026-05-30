<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $attendances = $request->user()
            ->attendances()
            ->latest('occurred_at')
            ->latest('id')
            ->limit(30)
            ->get();

        return view('dashboard', [
            'attendances' => $attendances,
        ]);
    }
}
