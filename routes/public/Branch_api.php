<?php

use App\Http\Controllers\Api\Branch\BranchController;
use Illuminate\Support\Facades\Route;

Route::prefix('branches')->middleware('auth:sanctum')->group(function () {
Route::post('/StoreBranch', [BranchController::class,'store']);
Route::post('/active', [BranchController::class,'activate']);
Route::get('/index', [BranchController::class,'index']);
});