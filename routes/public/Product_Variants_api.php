<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Products\Products_variants\ProductsVariantsController;

    Route::prefix('products/variants')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [ProductsVariantsController::class, 'index']);
    Route::post('/add', [ProductsVariantsController::class, 'addVariants']);
    Route::post('/update', [ProductsVariantsController::class, 'updateVariant']);
    Route::delete('/delete', [ProductsVariantsController::class, 'destroyVariant']);
    Route::delete('/delete-image', [ProductsVariantsController::class, 'destroyVariantImage']);
    Route::post('/filter', [ProductsVariantsController::class, 'filter']);
});