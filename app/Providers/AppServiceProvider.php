<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $title = '[prov] wtj.temperli.io';
        if (!empty($_ENV)) {
            $title = $_ENV['APP_NAME'];
        }

        view()->share('html_title', $title);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
