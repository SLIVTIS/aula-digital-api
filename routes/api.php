<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

Route::get('/user', function (Request $request) {
    return "Hola esta es una prueba";
});

Route::group(['namespace' => 'App\Http\Controllers\API'], function () {
    // --------------- Register and Login ----------------//
    Route::post('register', 'AuthenticationController@register')->name('register');
    Route::post('login', 'AuthenticationController@login')->name('login');
    
    // ------------------ Authenticated ----------------------//
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'AuthenticationController@logOut')->name('logout');

    // Rutas CRUD generales (para autenticados), EXCLUYENDO las admin
    Route::apiResource('users', UserController::class)
        ->except(['index', 'store', 'destroy']);

    // --- Admin ---
    Route::middleware('permission:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
    });
});
