<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceScanController extends Controller
{
    /**
     * Show the geolocation scan page.
     */
    public function show(Request $request): View
    {
        return view('attendance.scan');
    }

    /**
     * Store the attendance record with geolocation coordinates.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user = $request->user();
        $lastAttendance = $user->attendances()
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        $type = $lastAttendance?->type === 'entrada' ? 'salida' : 'entrada';

        $attendance = $user->attendances()->create([
            'type' => $type,
            'occurred_at' => now(),
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', __(':type registered at :time.', [
                'type' => str($attendance->type)->title(),
                'time' => $attendance->occurred_at->format('H:i'),
            ]));
    }
}
