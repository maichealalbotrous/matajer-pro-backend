<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;


class RouteServiceProvider extends ServiceProvider
{
        protected $namespace = 'App\Http\Controllers';

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
         parent::boot();
        $this->map();
    }

    public function map()
    {
     $this->mapApiBranchRoutes();   
     $this->mapApiUserRoutes();
     $this->mapApiAuthRoutes();
     $this->mapApiProductRoutes();
     $this->mapApiEmployeeRoutes();
     $this->mapApiAdminSupKeyRoutes();
     $this->mapApiAdminBranchRoutes();   
     $this->mapApiProductVariantRoutes();
     $this->mapApiapprovedInvoiceRoutes();
     $this->mapApiInvoiceRoutes();
     $this->mapApiSettlementRoutes();
    }

    
    protected function mapApiBranchRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/Branch_api.php'));
    }

    protected function mapApiUserRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/User_api.php'));
    }
    
    protected function mapApiAuthRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/Auth_api.php'));
    }

    protected function mapApiProductRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/Product_api.php'));
    }

    protected function mapApiEmployeeRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/Employee_api.php'));
    }

    protected function mapApiAdminSupKeyRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/SuperAdminDashboard/SuperAdminSupKey_api.php'));
    }

    protected function mapApiAdminBranchRoutes()
    {   
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/SuperAdminDashboard/SuperAdminBranch_api.php'));
    }

    protected function mapApiProductVariantRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/Product_Variants_api.php'));
    }

    protected function mapApiapprovedInvoiceRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/ApprovedInvoice_api.php'));
    }

    protected function mapApiInvoiceRoutes(){
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/Invoices_api.php'));
    }


    protected function mapApiSettlementRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/public/Settlement_api.php'));
    }
}