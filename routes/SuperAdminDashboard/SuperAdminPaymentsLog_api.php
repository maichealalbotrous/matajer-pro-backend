<?php

use App\Http\Controllers\Api\AdminDashboard\AdminGlobal_configs\AdminGlobal_configsController;
use Illuminate\Support\Facades\Route;

Route::prefix('finance')->group(function () {
    Route::get('/payments', [AdminGlobal_configsController::class, 'index']);
    Route::post('/payments/store', [AdminGlobal_configsController::class, 'store']);
    Route::put('/payments/update/{id}', [AdminGlobal_configsController::class, 'update']); 
    Route::delete('/payments/delete/{id}', [AdminGlobal_configsController::class, 'destroy']); 
});