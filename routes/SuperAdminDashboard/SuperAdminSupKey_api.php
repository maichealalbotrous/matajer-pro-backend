<?php

use App\Http\Controllers\Api\AdminDashboard\AdminSubscription_keys\AdminSubscription_keysController;
use Illuminate\Support\Facades\Route;


Route::prefix('SuperAdminDashboard')->middleware('auth:sanctum')->group(function () {
    Route::get('/Subscription_keys', [AdminSubscription_keysController::class, 'index']);
    Route::post('/Subscription_keys/generate', [AdminSubscription_keysController::class, 'generate']);
    Route::delete('/Subscription_keys/delete', [AdminSubscription_keysController::class, 'destroy']);
    });