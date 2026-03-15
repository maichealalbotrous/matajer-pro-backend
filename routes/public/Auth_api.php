<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('Auth')->group(function () {
    Route::post('/register', [AuthController::class, 'registerAdmin']);
    Route::post('/login', [AuthController::class, 'login']);
});