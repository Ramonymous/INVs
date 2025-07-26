<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Livewire\Volt\Volt;
use Illuminate\Support\ServiceProvider;
use App\Services\ChatService;

class VoltServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {

    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 🌐 1. Set Laravel & Carbon locale to Indonesian
        App::setLocale('id');
        Carbon::setLocale('id'); // Ensures ->diffForHumans() is in Bahasa Indonesia

        // 🔌 2. Mount Volt component directories
        Volt::mount([
            config('livewire.view_path', resource_path('views/livewire')),
            resource_path('views/pages'),
        ]);

        // 💡 Optional: Share global data with all Volt components
        // Volt::share('siteName', config('app.name'));

        // 📊 Optional: Enable debug mode indicator if app is local
        // if (App::isLocal()) {
        //     Volt::composer(fn () => ['debug' => true]);
        // }
    }
}