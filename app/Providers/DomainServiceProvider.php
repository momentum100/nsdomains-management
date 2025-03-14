<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\WhoisService;
use App\Services\PricingService;
use App\Services\DomainService;
use App\Services\ProxyService;
use Illuminate\Support\Facades\Log;

class DomainServiceProvider extends ServiceProvider
{
    public function register()
    {
        // First register ProxyService (it has default parameters so can be instantiated without arguments)
        $this->app->singleton(ProxyService::class, function ($app) {
            Log::info("Registering ProxyService in container");
            return new ProxyService();
        });

        // Then register WhoisService which depends on ProxyService
        $this->app->singleton(WhoisService::class, function ($app) {
            Log::info("Registering WhoisService in container");
            // Get the ProxyService from the container
            $proxyService = $app->make(ProxyService::class);
            return new WhoisService($proxyService);
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