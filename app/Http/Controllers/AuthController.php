<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    /**
     * A PEG account is a username, not an email: the group keeps no addresses and the payroll list
     * the Treasurer was given has names and extension numbers on it.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'username' => 'That username and password do not match a PEG account.',
            ]);
        }

        $user = Auth::user();

        if ($user->isClosed()) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'username' => 'This account is closed. Ask the Chairperson to reopen it.',
            ]);
        }

        $request->session()->regenerate();
        ActivityLog::record($user, 'Signed in');

        return redirect()->intended(route('pages.dashboard'));
    }

    public function logout(Request $request)
    {
        ActivityLog::record(Auth::user(), 'Signed out');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
