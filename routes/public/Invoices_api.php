<?php

use App\Http\Controllers\Api\Invoices\Invoices\InvoicesController;
use Illuminate\Support\Facades\Route;


Route::prefix('invoices')->middleware('auth:sanctum')->group(function () {
    Route::post('/storeInvoice', [InvoicesController::class, 'storeInvoice']);
    Route::post('/returnInvoice', [InvoicesController::class, 'returnInvoice']);
    Route::post('/exchangeInvoice', [InvoicesController::class, 'exchangeInvoice']);
    Route::get('/employeeInvoices', [InvoicesController::class, 'getEmployeeInvoices']);

});