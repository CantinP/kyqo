<?php

use Kyqo\Http\Router\Router;

/** @var Router $router */

// Home
$router->get('/', function () {
    return view('welcome');
});

// ── Auth routes ───────────────────────────────────────────────────────────
// FIX C3 – added ->name() on every route so route_url('login'), etc. resolve.
$router->get('/login',   [\App\Http\Controllers\Auth\LoginController::class,    'showLoginForm'])->name('login');
$router->post('/login',  [\App\Http\Controllers\Auth\LoginController::class,    'login'])->name('login.submit');
$router->post('/logout', [\App\Http\Controllers\Auth\LoginController::class,    'logout'])->name('logout')->middleware('auth');

$router->get('/register',  [\App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])->name('register');
$router->post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register'])->name('register.submit');

// Protected
$router->get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');
