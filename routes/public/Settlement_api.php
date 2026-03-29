<?php

use App\Http\Controllers\Api\Invoices\Settlement\SettlementController;
use Illuminate\Support\Facades\Route;

Route::prefix('settlements')->middleware('auth:sanctum')->group(function () {
    Route::get('/get_settlements', [SettlementController::class, 'index']);
    Route::get('/reports', [SettlementController::class, 'reports']);
    Route::get('/inventory_audit', [SettlementController::class, 'inventoryAudit']);
    Route::get('/top_products', [SettlementController::class, 'topProducts']);
    Route::get('/lowStockAlerts', [SettlementController::class, 'lowStockAlerts']);
    Route::get('/show', [SettlementController::class, 'show']);
    Route::get('/delete', [SettlementController::class, 'delete']);


    
    
});