<?php
// app/Http/Controllers/Api/CadastroController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Empresa;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Str;

class CadastroController extends Controller
{
    public function register(Request $request)
    {
        DB::beginTransaction();
        
        try {
            Log::info('📝 Iniciando cadastro de empresa', ['dados' => $request->all()]);
            
            // Validação dos dados
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'telefone' => 'required|string|max:20',
                'password' => 'required|string|min:8|confirmed',
                'empresa' => 'required|string|max:255',
                'tipoPessoa' => 'required|in:singular,colectiva',
                'userType' => 'required|in:transportador,agent,shipper',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Gerar IDs
            $tenantStringId = 'tenant-' . Str::slug($request->empresa) . '-' . Str::random(6);
            $ultimoTenantId = User::max('tenant_id') ?? 0;
            $novoTenantIdNumerico = $ultimoTenantId + 1;
            
            Log::info('📌 Tenant configurado', [
                'tenant_string' => $tenantStringId,
                'tenant_numerico' => $novoTenantIdNumerico
            ]);
            
            // ============================================
            // 1. CRIAR EMPRESA
            // ============================================
            $empresa = Empresa::create([
                'nome' => $request->empresa,
                'cnpj' => $request->cnpj,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'website' => $request->website,
                'endereco' => $request->endereco,
                'cidade' => $request->cidade,
                'estado' => $this->getSiglaEstado($request->provincia),
                'cep' => $request->cep,
                'setor' => $request->setor,
                'funcionarios' => $request->funcionarios,
                'descricao' => $request->descricao,
                'fundacao' => $request->fundacao,
                'missao' => $request->missao,
                'visao' => $request->visao,
                'moeda_padrao' => $request->moeda_padrao ?? 'MZN',
                'fuso_horario' => $request->fuso_horario ?? 'Africa/Maputo',
                'tenant_id' => $tenantStringId,
                'logo_url' => null,
            ]);
            
            Log::info('✅ Empresa criada', ['empresa_id' => $empresa->id]);
            
            // ============================================
            // 2. CRIAR ROLE COM NOME ÚNICO (usando tenant_id no nome)
            // ============================================
            try {
                // Tentar criar role com nome composto para evitar conflito
                $roleName = 'Admin_' . substr($tenantStringId, -8);
                
                $role = Role::create([
                    'nome' => $roleName, // Nome único! Ex: "Admin_ji8fVt"
                    'descricao' => 'Administrador do sistema',
                    'tenant_id' => $tenantStringId,
                    'is_super_admin' => true,
                ]);
                
                Log::info('✅ Role criada', ['role_id' => $role->id, 'nome' => $role->nome]);
                
            } catch (\Exception $e) {
                // Se falhar, tentar encontrar role existente
                $role = Role::where('tenant_id', $tenantStringId)->first();
                
                if (!$role) {
                    // Se não existir, criar com nome aleatório
                    $role = Role::create([
                        'nome' => 'Admin_' . uniqid(),
                        'descricao' => 'Administrador do sistema',
                        'tenant_id' => $tenantStringId,
                        'is_super_admin' => true,
                    ]);
                }
            }
            
            // ============================================
            // 3. CRIAR USUÁRIO
            // ============================================
            $user = User::create([
                'name' => $request->nome,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'telefone' => $request->telefone,
                'cargo' => 'Administrador',
                'departamento' => 'Administração',
                'role_id' => $role->id,
                'user_type' => $request->userType,
                'idioma' => 'pt',
                'fuso_horario' => $request->fuso_horario ?? 'Africa/Maputo',
                'ativo' => true,
                'tenant_id' => $novoTenantIdNumerico,
                'email_verified_at' => now(),
            ]);
            
            Log::info('✅ Usuário criado', ['user_id' => $user->id]);

            // ============================================
            // 4. CRIAR PERMISSÕES DO TENANT
            // ============================================
            (new PermissionSeeder())->run($tenantStringId);
            Log::info('✅ Permissões criadas para o tenant', ['tenant_id' => $tenantStringId]);

            DB::commit();
            
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'message' => 'Cadastro realizado com sucesso!',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'user_type' => $user->user_type,
                    ],
                    'empresa' => [
                        'id' => $empresa->id,
                        'nome' => $empresa->nome,
                    ],
                    'token' => $token,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Erro no cadastro: ' . $e->getMessage());
            Log::error('🔧 Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function getSiglaEstado($provincia)
    {
        $mapa = [
            'Maputo Cidade' => 'MC',
            'Maputo Província' => 'MP',
            'Gaza' => 'GZ',
            'Inhambane' => 'IN',
            'Manica' => 'MN',
            'Sofala' => 'SF',
            'Tete' => 'TT',
            'Zambézia' => 'ZM',
            'Nampula' => 'NP',
            'Cabo Delgado' => 'CD',
            'Niassa' => 'NS',
        ];
        
        return $mapa[$provincia] ?? 'MZ';
    }
}