<?php

namespace App\Providers;

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
        // Re-enabled with optimized queued jobs implementation
        \App\Models\Grade::observe(\App\Observers\GradeObserver::class);
    }
}
