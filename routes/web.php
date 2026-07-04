<?php

use Kyqo\Http\Router\Router;

/** @var Router $router */

$router->get('/', function () {
    return view('welcome');
});

// Auth routes
$router->get('/login',   [\App\Http\Controllers\Auth\LoginController::class,    'showLoginForm']);
$router->post('/login',  [\App\Http\Controllers\Auth\LoginController::class,    'login']);
$router->post('/logout', [\App\Http\Controllers\Auth\LoginController::class,    'logout'])->middleware('auth');

$router->get('/register',  [\App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm']);
$router->post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register']);

$router->get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth');
