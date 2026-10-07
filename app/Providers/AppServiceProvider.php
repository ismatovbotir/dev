<?php

namespace App\Providers;

use App\Enums\RequestStatus;
use App\Models\ShopRequest;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.admin', function ($view): void {
            $view->with('newRequestsCount', ShopRequest::where('status', RequestStatus::New)->count());
        });
    }
}
