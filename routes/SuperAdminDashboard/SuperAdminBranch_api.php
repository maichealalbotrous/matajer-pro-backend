 <?php

 use App\Http\Controllers\Api\AdminDashboard\AdminBranch\AdminBrancheController;
 use Illuminate\Support\Facades\Route;

 Route::prefix('superadmin')->middleware('auth:sanctum')->group(function () {
 Route::get('/branches', [AdminBrancheController::class, 'index']);
 Route::post('/branches/toggle-status', [AdminBrancheController::class, 'toggleStatus']);
 Route::post('/branches/manualRenew', [AdminBrancheController::class, 'manualRenew']);
 Route::get('/branches/checkExpirations', [AdminBrancheController::class, 'checkExpirations']);
 Route::delete('/branches/Delete', [AdminBrancheController::class, 'destroy']);
 });