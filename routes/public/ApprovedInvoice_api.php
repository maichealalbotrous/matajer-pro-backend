<?php

use App\Http\Controllers\Api\Invoices\ApprovedInvoices\ApprovedInvoicesController;
use Illuminate\Support\Facades\Route;
use Termwind\Components\Raw;

Route::prefix('invoices/approve')->middleware('auth:sanctum')->group(function () {
    Route::post('/approve', [ApprovedInvoicesController::class, 'approveInvoice']);
    Route::post('/approve-all', [ApprovedInvoicesController::class, 'approveAllInvoices']);
    Route::get('/index', [ApprovedInvoicesController::class, 'index']);
    Route::post('/revert', [ApprovedInvoicesController::class, 'revertApproval']);
    Route::post('/show', [ApprovedInvoicesController::class, 'show']);
    Route::post('/revertApproval', [ApprovedInvoicesController::class, 'revertApproval']);
    Route::delete('/deleteInvoice', [ApprovedInvoicesController::class, 'deleteInvoice']);

    });