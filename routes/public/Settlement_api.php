<?php

use App\Http\Controllers\Api\Invoices\Settlement\SettlementController;
use Illuminate\Support\Facades\Route;

Route::prefix('settlements')->middleware('auth:sanctum')->group(function () {
    Route::get('/get_settlements', [SettlementController::class, 'index']);
});