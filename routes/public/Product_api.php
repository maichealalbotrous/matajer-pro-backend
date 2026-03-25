<?php

use App\Http\Controllers\Api\Products\Product\ProductController;
use Illuminate\Support\Facades\Route;


Route::prefix('products')->middleware('auth:sanctum')->group(function () {
    Route::get('/get_products', [ProductController::class, 'index']);
    Route::post('/store', [ProductController::class, 'store']);
    Route::post('/update', [ProductController::class, 'update']);
    Route::delete('/delete', [ProductController::class, 'destroy']);
});