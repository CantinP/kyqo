<?php

use Kyqo\Http\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Register all web routes for your application here. These routes are
| loaded by the RouteServiceProvider with the "web" middleware group.
|
*/

Route::get('/', function () {
    return view('welcome');
});
