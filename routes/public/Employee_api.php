<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Employee\EmployeeController;
Route::prefix('employees')->middleware('auth:sanctum')->group(function () {
    Route::get('/my-employees', [EmployeeController::class,'getMyEmployees']);
    Route::post('/add', [EmployeeController::class,'addEmployee']);
    Route::post('/update/{id}', [EmployeeController::class,'updateEmployee']);
    Route::delete('/delete/{id}', [EmployeeController::class,'deleteEmployee']);

});