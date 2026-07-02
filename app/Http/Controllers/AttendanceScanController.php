<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceScanController extends Controller
{
    /**
     * Show the geolocation scan page.
     */
    public function show(Request $request, Section $section): View
    {
        $user = $request->user();
        $assignedSection = $user->section;
        $error = null;

        if (! $assignedSection) {
            $error = __('No tienes una sección de asistencia asignada. Por favor, solicita a un administrador que te asigne una.');
        } elseif ($assignedSection->id !== $section->id) {
            $error = __('Esta sección (:scanned) no coincide con tu sección asignada (:assigned).', [
                'scanned' => $section->name,
                'assigned' => $assignedSection->name,
            ]);
        }

        return view('attendance.scan', compact('section', 'error'));
    }

    /**
     * Store the attendance record with geolocation coordinates.
     */
    public function store(Request $request, Section $section): RedirectResponse
    {
        $user = $request->user();
        $assignedSection = $user->section;

        if (! $assignedSection || $assignedSection->id !== $section->id) {
            abort(403, __('No estás autorizado a registrar asistencia en esta sección.'));
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

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
            'section_id' => $section->id,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', __(':type registered at :time in :section.', [
                'type' => str($attendance->type)->title(),
                'time' => $attendance->occurred_at->format('H:i'),
                'section' => $section->name,
            ]));
    }
}
