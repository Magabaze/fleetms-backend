<?php

namespace App\Providers;

use App\Models\Empresa;
use App\Observers\EmpresaObserver;
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
        // Registrar o Observer para Empresa
        Empresa::observe(EmpresaObserver::class);
        
        // Opcional: outras configurações globais
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);
    }
}