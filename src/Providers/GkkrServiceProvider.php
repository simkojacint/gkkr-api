<?php

namespace FuturewebCMS2024\Gkkr\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class GkkrServiceProvider
 *
 * @package FuturewebCMS2024\Gkkr\Providers
 */
class GkkrServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/gkkr.php');
    }
}