<?php

use App\Http\Controllers\Api\Discounts\DiscountsController;
use Illuminate\Support\Facades\Route;


Route::prefix('discounts')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [DiscountsController::class, 'index']);
    Route::post('/store', [DiscountsController::class, 'store']);
    Route::post('/update', [DiscountsController::class, 'update']);
    Route::delete('/destroy', [DiscountsController::class, 'destroy']);
});