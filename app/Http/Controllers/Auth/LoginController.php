<?php

namespace App\Http\Controllers\Auth;

use Kyqo\Http\Request;
use Kyqo\Http\Response;

class LoginController
{
    public function showLoginForm(): Response
    {
        return view('auth.login');
    }

    public function login(Request $request): Response
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        $auth = auth();

        if ($auth->attempt($credentials, (bool) $request->input('remember'))) {
            return Response::redirect(route_url('dashboard'));
        }

        return Response::redirect(route_url('login'))
            ->withErrors(['email' => 'These credentials do not match our records.']);
    }

    public function logout(Request $request): Response
    {
        auth()->logout();
        session()->invalidate();
        session()->regenerateToken();
        return Response::redirect('/');
    }
}
