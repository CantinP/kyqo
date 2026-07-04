<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Kyqo\Http\Request;
use Kyqo\Http\Response;

class RegisterController
{
    public function showRegistrationForm(): Response
    {
        return view('auth.register');
    }

    public function register(Request $request): Response
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
        ]);

        auth()->login($user);

        return Response::redirect(route_url('dashboard'));
    }
}
