<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class RegistrationPinController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'string'],
        ]);

        if (! hash_equals('1226500', $validated['pin'])) {
            return back()
                ->withErrors(['pin' => __('The registration PIN is incorrect.')])
                ->onlyInput('pin');
        }

        $request->session()->put('registration_pin_verified', true);

        return redirect()->route('register');
    }
}
