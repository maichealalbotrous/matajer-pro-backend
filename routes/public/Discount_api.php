<?php
use Illuminate\Support\Facades\Route;


Route::prefix('discounts')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [App\Http\Controllers\Api\Discounts\DiscountsController::class, 'index']);
    Route::post('/store', [App\Http\Controllers\Api\Discounts\DiscountsController::class, 'store']);
    Route::put('/update/{id}', [App\Http\Controllers\Api\Discounts\DiscountsController::class, 'update']);
    Route::delete('/destroy/{id}', [App\Http\Controllers\Api\Discounts\DiscountsController::class, 'destroy']);
});