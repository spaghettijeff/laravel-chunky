<?php

namespace spaghettijeff\chunky;

use Illuminate\Support\ServiceProvider;

class ChunkyServiceProvider extends ServiceProvider
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
        $this->publishes([
            __DIR__.'/../config/chunky.php' => config_path('chunky.php'),
            __DIR__.'/../resources/js/chunkyClient.js' => public_path('spaghettijeff/chunky/chunkyClient.js'),
        ], 'public');
    }
}
