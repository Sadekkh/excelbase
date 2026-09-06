<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        if ($request->input('password') === '') {
            $request->merge(['password' => null, 'password_confirmation' => null]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return $request->expectsJson()
            ? response()->json([
                'ok' => true,
                'status' => 'Profile saved.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => strtoupper(substr($user->name, 0, 1)),
                    'is_platform_admin' => (bool) $user->is_platform_admin,
                ],
            ])
            : back()->with('status', 'Profile saved.');
    }
}
