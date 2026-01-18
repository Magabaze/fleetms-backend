<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller  
{
    // =============== HEALTH CHECK ===============
    public function health()
    {
        return response()->json([
            'status' => 'online',
            'service' => 'FleetMS API',
            'timestamp' => now()->toISOString(),
            'version' => '2.0.0'
        ]);
    }

    // =============== LOGIN ===============
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'senha' => 'required|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Busca usuário
            $user = User::where('email', $request->email)->first();

            // Verifica credenciais e se está ativo
            if (!$user || !Hash::check($request->senha, $user->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Credenciais inválidas'
                ], 401);
            }
            
            if (!$user->ativo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário desativado'
                ], 403);
            }

            // Cria token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'usuario' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ? $user->role->nome : null,
                    ],
                ],
                'message' => 'Login realizado com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro no login: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao fazer login'
            ], 500);
        }
    }

    // =============== REGISTER ===============
    public function register(Request $request)
    {
        // Em produção, registro é geralmente controlado
        return response()->json([
            'success' => false,
            'error' => 'Registro não está disponível'
        ], 403);
    }

    // =============== USER ===============
    public function user(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? $user->role->nome : null,
                'tenant_id' => $user->tenant_id,
            ]
        ]);
    }

    // =============== LOGOUT ===============
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Logout realizado'
        ]);
    }
}