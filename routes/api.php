<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\PropertyController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/update-user/{userId}', [AuthController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/user', [AuthController::class, 'getUserData']);
    Route::get('/user/email/{email}', [AuthController::class, 'getUserDataByEmail']);

    Route::put('/user/{userId}/roles', [RolesController::class, 'updateUserRoles']);
    Route::get('/user/{userId}/access-controls', [AuthController::class, 'getUserAccessControls']);

});
