<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Employee\UserController;
Route::prefix('employees')->middleware('auth:sanctum')->group(function () {
    Route::get('/my-employees', [UserController::class, 'index']);
    Route::post('/add', [UserController::class, 'addEmployee']);
    Route::post('/update', [UserController::class, 'updateEmployee']);
    Route::delete('/delete', [UserController::class, 'deletEmployee']);

});