<?php

use App\Http\Controllers\Api\Products\Product\ProductController;
use Illuminate\Support\Facades\Route;


Route::prefix('products')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::post('/store', [ProductController::class, 'store']);
    Route::post('/update/{id}', [ProductController::class, 'update']);
    Route::delete('/delete/{id}', [ProductController::class, 'destroy']);
});