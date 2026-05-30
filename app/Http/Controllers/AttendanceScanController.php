<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceScanController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $lastAttendance = $user->attendances()
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        $type = $lastAttendance?->type === 'entrada' ? 'salida' : 'entrada';

        $attendance = $user->attendances()->create([
            'type' => $type,
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', __(':type registered at :time.', [
                'type' => str($attendance->type)->title(),
                'time' => $attendance->occurred_at->format('H:i'),
            ]));
    }
}
