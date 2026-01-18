<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedException;

class InitializeTenancyByDomain
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Resolver tenant pelo domínio (subdomínio ou domínio completo)
            $tenant = app(DomainTenantResolver::class)->resolve(
                $request->getHost() // ou subdomínio customizado
            );
            
            // Inicializar tenancy
            tenancy()->initialize($tenant);
            
        } catch (TenantCouldNotBeIdentifiedException $e) {
            // Se for API, retornar JSON error
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Tenant not found',
                    'message' => 'The requested tenant domain could not be identified'
                ], 404);
            }
            
            // Se for web, redirecionar ou mostrar erro
            abort(404, 'Tenant not found');
        }
        
        return $next($request);
    }
}