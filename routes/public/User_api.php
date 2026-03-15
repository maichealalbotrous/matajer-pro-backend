<?php

use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('User')->middleware('auth:sanctum')->group(function () {
Route::post('/add-employee', [UserController::class, 'addEmployee']);
});