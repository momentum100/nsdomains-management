<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\WhoisService;
use App\Services\PricingService;
use App\Services\DomainService;

class DomainServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(WhoisService::class, function ($app) {
            return new WhoisService();
        });

        $this->app->singleton(PricingService::class, function ($app) {
            return new PricingService();
        });

        $this->app->singleton(DomainService::class, function ($app) {
            return new DomainService(
                $app->make(WhoisService::class),
                $app->make(PricingService::class)
            );
        });
    }
} 