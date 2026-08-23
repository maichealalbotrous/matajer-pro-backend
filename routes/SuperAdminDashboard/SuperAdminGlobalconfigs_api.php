<?php

use App\Http\Controllers\Api\AdminDashboard\AdminGlobal_configs\AdminGlobal_configsController;
use Illuminate\Support\Facades\Route;


  Route::middleware(['auth:sanctum'])->prefix('GlobalConfigs')->group(function () {
  
    Route::post('/Getusers', [AdminGlobal_configsController::class, 'indexUsers']);
    Route::post('/Storeusers', [AdminGlobal_configsController::class, 'storeUser']);
    Route::post('/Updateusers', [AdminGlobal_configsController::class, 'updateUser']);
    Route::delete('/Deleteusers', [AdminGlobal_configsController::class, 'destroyUser']);
});