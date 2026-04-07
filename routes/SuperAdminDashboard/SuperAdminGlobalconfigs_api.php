<?php

use App\Http\Controllers\Api\AdminDashboard\AdminGlobal_configs\AdminGlobal_configsController;
use Illuminate\Support\Facades\Route;


  Route::middleware(['auth:sanctum', 'isAdmin'])->prefix('admin/manage')->group(function () {
    Route::get('/branches', [AdminGlobal_configsController::class, 'indexBranches']);
    Route::post('/branches', [AdminGlobal_configsController::class, 'storeBranch']);
    Route::patch('/branches/{id}/toggle', [AdminGlobal_configsController::class, 'toggleBranchStatus']);

    Route::get('/users', [AdminGlobal_configsController::class, 'indexUsers']);
    Route::post('/users', [AdminGlobal_configsController::class, 'storeUser']);
    Route::post('/users/{id}', [AdminGlobal_configsController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminGlobal_configsController::class, 'destroyUser']);
});