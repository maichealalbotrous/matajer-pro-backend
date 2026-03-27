<?php

use App\Http\Controllers\Api\Invoices\ApprovedInvoices\ApprovedInvoicesController;
use Illuminate\Support\Facades\Route;
use Termwind\Components\Raw;

Route::prefix('invoices/approve')->middleware('auth:sanctum')->group(function () {
    Route::post('approve', [ApprovedInvoicesController::class, 'approveInvoice']);
});