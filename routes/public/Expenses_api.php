<?php

use App\Http\Controllers\Api\Expenses\ExpensesController;
use Illuminate\Support\Facades\Route;

Route::prefix('expenses')->middleware('auth:sanctum')->group(function () {
    Route::get('/index', [ExpensesController::class, 'index']);
    Route::post('/add', [ExpensesController::class, 'store']);
    Route::post('/update', [ExpensesController::class, 'update']);
    Route::delete('/delete', [ExpensesController::class, 'destroy']);
});