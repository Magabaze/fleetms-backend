<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Observers\EmpresaObserver;
use Illuminate\Support\ServiceProvider;
use Barryvdh\Snappy\ServiceProvider as SnappyServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar o Snappy Service Provider
        $this->app->register(SnappyServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar o Observer para Empresa
        Empresa::observe(EmpresaObserver::class);
        
        // Opcional: outras configurações globais
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);
    }
}