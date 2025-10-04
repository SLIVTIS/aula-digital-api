<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\GroupController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\StudentParentController;
use App\Http\Controllers\API\GroupStudentController;
use App\Http\Controllers\API\TeacherGroupController;
use App\Http\Controllers\API\AnnouncementController;
use App\Http\Controllers\API\AnnouncementTargetController;

Route::get('/user', function (Request $request) {
    return "Hola esta es una prueba";
});

Route::group(['namespace' => 'App\Http\Controllers\API'], function () {
    // --------------- Registro y inicio se sesión ----------------//
    Route::post('register', 'AuthenticationController@register')->name('register');
    Route::post('login', 'AuthenticationController@login')->name('login');
    
    // ------------------ Authenticated ----------------------//
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', 'AuthenticationController@logOut')->name('logout');

        // Rutas CRUD generales (para autenticados), EXCLUYENDO las admin
        Route::apiResource('users', UserController::class)
            ->except(['index', 'store', 'destroy']);

        // --- Teacher ---
        Route::middleware('permission:admin')->group(function () {
            /*
            ========== 
             CRUD de avisos
            ==========
            */
            Route::get('announcements', [AnnouncementController::class, 'index']);
            Route::post('announcements', [AnnouncementController::class, 'store']);
            Route::get('announcements/{announcement}', [AnnouncementController::class, 'show']);
            Route::put('announcements/{announcement}', [AnnouncementController::class, 'update']);
            Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy']);

            Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish']);
            Route::post('announcements/{announcement}/archive', [AnnouncementController::class, 'archive']);
            Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'markRead']);

            // Objetivo del aviso
            Route::get('announcements/{announcement}/targets', [AnnouncementTargetController::class, 'index']);
            Route::post('announcements/{announcement}/targets', [AnnouncementTargetController::class, 'store']);
            Route::delete('announcements/{announcement}/targets/{target}', [AnnouncementTargetController::class, 'destroy']);
        });
       
        // --- Admin ---
        Route::middleware('permission:admin')->group(function () {
            /*
            ========== 
             CRUD de usuarios
            ==========
             */
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            /*
            ========== 
             CRUD de grupos
            ==========
             */
            Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
            Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.index');
            Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
            Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
            Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.delete');

            // Asignar grupo a los estudiantes
            Route::get('groups/{group}/students', [GroupStudentController::class, 'index']);
            Route::post('groups/{group}/students', [GroupStudentController::class, 'store']);
            Route::delete('groups/{group}/students/{student}', [GroupStudentController::class, 'destroy']);

            // Asignar grupo a los profesores
            Route::get('groups/{group}/teachers', [TeacherGroupController::class, 'index']);
            Route::post('groups/{group}/teachers', [TeacherGroupController::class, 'store']);
            Route::delete('groups/{group}/teachers/{teacher}', [TeacherGroupController::class, 'destroy']);

            /*
            ========== 
             CRUD de estudiantes
            ==========
             */
            Route::get('students', [StudentController::class, 'index'])->name('students.index');
            Route::get('students/{student}', [StudentController::class, 'show'])->name('students.index');
            Route::post('students', [StudentController::class, 'store'])->name('students.store');
            Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
            Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.delete');

            //Asignar padres
            Route::get('students/{student}/parents', [StudentParentController::class, 'index']);
            Route::post('students/{student}/parents', [StudentParentController::class, 'store']);
            Route::delete('students/{student}/parents/{parent}', [StudentParentController::class, 'destroy']); 
        });
    });
});
