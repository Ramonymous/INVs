<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 📅 Set locale global Carbon ke Bahasa Indonesia
        Carbon::setLocale('id');

        // Optional: pastikan aplikasi menggunakan locale 'id'
        $this->app->setLocale('id');
    }
}