<?php

use App\Http\Controllers\Api\AdminDashboard\AdminPaymentsLog\AdminPaymentsLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('finance')->middleware('auth:sanctum')->group(function () {
    Route::post('/payments', [AdminPaymentsLogController::class, 'index']);
    Route::post('/payments/store', [AdminPaymentsLogController::class, 'store']);
    Route::post('/payments/update', [AdminPaymentsLogController::class, 'update']); 
    Route::delete('/payments/delete', [AdminPaymentsLogController::class, 'destroy']); 
});