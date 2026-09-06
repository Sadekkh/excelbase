<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'The email or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();
        $this->rememberSurface($request->user());

        return redirect()->intended(route('dashboard'));
    }

    public function demo(Request $request)
    {
        $user = User::query()->where('email', 'demo@baserow.io')->first();
        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => 'Demo account is not available. Run php artisan db:seed.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->rememberSurface($user);

        return redirect()->route('dashboard');
    }

    private function rememberSurface($user): void
    {
        $workspace = $user->workspaces()->with('members')->first();
        if ($workspace && ! \App\Support\Access::canBuild($user, $workspace)) {
            session(['surface' => 'app']);
        }
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
