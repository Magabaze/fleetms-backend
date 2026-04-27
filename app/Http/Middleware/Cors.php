<?php
// app/Http/Middleware/Cors.php - COMPLETO

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        $allowedOrigins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://10.206.203.1:3000',
            'http://localhost:3001',
            'http://127.0.0.1:3001',
        ];
        
        $origin = $request->headers->get('Origin');
        
        $response = $next($request);
        
        if (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With, X-CSRF-TOKEN');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        
        // Para requisições OPTIONS (preflight)
        if ($request->getMethod() === "OPTIONS") {
            return response()->json([], 200, [
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS, PATCH',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Accept, X-Requested-With, X-CSRF-TOKEN',
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
        
        return $response;
    }
}
