<?php
// app/Http/Controllers/Api/ConfiguracaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ConfiguracaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // =============== PERFIL ===============

    public function getPerfil()
    {
        try {
            $user = Auth::user();
            
            Log::info('📥 GET /api/configuracoes/perfil', ['user_id' => $user->id]);
            
            // Determinar se é admin baseado no role
            $isAdmin = $user->role && in_array(strtolower($user->role->nome), ['administrador', 'admin', 'super admin']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'cargo' => $user->cargo,
                    'departamento' => $user->departamento,
                    'endereco' => $user->endereco,
                    'bio' => $user->bio,
                    'isAdmin' => $isAdmin,
                    'roleNome' => $user->role ? $user->role->nome : null,
                    'roleId' => $user->role_id,
                    'idioma' => $user->idioma,
                    'fusoHorario' => $user->fuso_horario,
                    'tenantId' => $user->tenant_id, // Adicionado
                    'membroDesde' => $user->created_at->format('d/m/Y'),
                    'createdAt' => $user->created_at->toISOString(),
                    'updatedAt' => $user->updated_at->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updatePerfil(Request $request)
    {
        try {
            $user = Auth::user();
            
            Log::info('📥 PUT /api/configuracoes/perfil', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'telefone' => 'nullable|string|max:20',
                'cargo' => 'nullable|string|max:255',
                'departamento' => 'nullable|string|max:255',
                'endereco' => 'nullable|string|max:500',
                'bio' => 'nullable|string|max:1000',
                'idioma' => 'required|in:pt,en,es',
                'fusoHorario' => 'required|string',
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user->update([
                'name' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'cargo' => $request->cargo,
                'departamento' => $request->departamento,
                'endereco' => $request->endereco,
                'bio' => $request->bio,
                'idioma' => $request->idioma,
                'fuso_horario' => $request->fusoHorario,
            ]);
            
            Log::info('✅ Perfil atualizado', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'cargo' => $user->cargo,
                    'departamento' => $user->departamento,
                    'endereco' => $user->endereco,
                    'bio' => $user->bio,
                    'idioma' => $user->idioma,
                    'fusoHorario' => $user->fuso_horario,
                    'tenantId' => $user->tenant_id,
                ],
                'message' => 'Perfil atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== EMPRESA ===============

    public function getEmpresa()
    {
        try {
            $user = Auth::user();
            
            Log::info('📥 GET /api/configuracoes/empresa', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role ? $user->role->nome : null
            ]);
            
            // Verificar se é admin (case insensitive)
            $isAdmin = false;
            if ($user->role && $user->role->nome) {
                $roleNome = strtolower($user->role->nome);
                $isAdmin = in_array($roleNome, ['administrador', 'admin', 'super admin', 'superadmin']);
            }
            
            Log::info('🔍 Verificação de admin', [
                'role_nome' => $user->role ? $user->role->nome : null,
                'is_admin' => $isAdmin
            ]);
            
            if (!$isAdmin) {
                Log::warning('❌ Usuário não é administrador', [
                    'user_role' => $user->role ? $user->role->nome : 'Sem role'
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem visualizar os dados da empresa.',
                    'user_role' => $user->role ? $user->role->nome : 'Sem role'
                ], 403);
            }
            
            // Buscar empresa pelo tenant_id do usuário
            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();
            
            Log::info('🔍 Buscando empresa', [
                'tenant_id' => $user->tenant_id,
                'encontrada' => $empresa ? 'Sim' : 'Não'
            ]);
            
            if (!$empresa) {
                // Criar empresa vazia se não existir
                $empresa = Empresa::create([
                    'nome' => 'Nome da Empresa',
                    'tenant_id' => $user->tenant_id,
                    'moeda_padrao' => 'BRL',
                    'fuso_horario' => 'America/Sao_Paulo',
                ]);
                Log::info('✅ Empresa criada para tenant', ['tenant_id' => $user->tenant_id]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $empresa->id,
                    'nome' => $empresa->nome,
                    'cnpj' => $empresa->cnpj,
                    'email' => $empresa->email,
                    'telefone' => $empresa->telefone,
                    'website' => $empresa->website,
                    'endereco' => $empresa->endereco,
                    'cidade' => $empresa->cidade,
                    'estado' => $empresa->estado,
                    'cep' => $empresa->cep,
                    'setor' => $empresa->setor,
                    'funcionarios' => $empresa->funcionarios,
                    'descricao' => $empresa->descricao,
                    'fundacao' => $empresa->fundacao,
                    'missao' => $empresa->missao,
                    'visao' => $empresa->visao,
                    'moedaPadrao' => $empresa->moeda_padrao,
                    'fusoHorario' => $empresa->fuso_horario,
                    'tenantId' => $empresa->tenant_id,
                    'createdAt' => $empresa->created_at->toISOString(),
                    'updatedAt' => $empresa->updated_at->toISOString(),
                ],
                'permissions' => [
                    'canEdit' => $isAdmin,
                    'isAdmin' => $isAdmin,
                    'userRole' => $user->role ? $user->role->nome : null,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar empresa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar empresa: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateEmpresa(Request $request)
    {
        try {
            $user = Auth::user();
            
            Log::info('📥 PUT /api/configuracoes/empresa', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'dados' => $request->all()
            ]);
            
            // Verificar se é admin (case insensitive)
            $isAdmin = false;
            if ($user->role && $user->role->nome) {
                $roleNome = strtolower($user->role->nome);
                $isAdmin = in_array($roleNome, ['administrador', 'admin', 'super admin', 'superadmin']);
            }
            
            if (!$isAdmin) {
                Log::warning('❌ Usuário não autorizado para editar empresa', [
                    'user_role' => $user->role ? $user->role->nome : 'Sem role'
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem editar os dados da empresa.',
                    'user_role' => $user->role ? $user->role->nome : 'Sem role'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'cnpj' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'website' => 'nullable|string|max:255',
                'endereco' => 'nullable|string|max:500',
                'cidade' => 'nullable|string|max:100',
                'estado' => 'nullable|string|max:2',
                'cep' => 'nullable|string|max:10',
                'setor' => 'nullable|string|max:100',
                'funcionarios' => 'nullable|string|max:50',
                'descricao' => 'nullable|string|max:1000',
                'fundacao' => 'nullable|string|max:4',
                'missao' => 'nullable|string|max:1000',
                'visao' => 'nullable|string|max:1000',
                'moedaPadrao' => 'nullable|in:BRL,USD,EUR',
                'fusoHorario' => 'nullable|string|max:50',
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Buscar empresa pelo tenant_id do usuário
            $empresa = Empresa::where('tenant_id', $user->tenant_id)->first();
            
            if (!$empresa) {
                $empresa = Empresa::create([
                    'tenant_id' => $user->tenant_id,
                ]);
                Log::info('✅ Nova empresa criada', ['tenant_id' => $user->tenant_id]);
            }
            
            // Converter camelCase para snake_case
            $empresa->update([
                'nome' => $request->nome,
                'cnpj' => $request->cnpj,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'website' => $request->website,
                'endereco' => $request->endereco,
                'cidade' => $request->cidade,
                'estado' => $request->estado,
                'cep' => $request->cep,
                'setor' => $request->setor,
                'funcionarios' => $request->funcionarios,
                'descricao' => $request->descricao,
                'fundacao' => $request->fundacao,
                'missao' => $request->missao,
                'visao' => $request->visao,
                'moeda_padrao' => $request->moedaPadrao ?? 'BRL',
                'fuso_horario' => $request->fusoHorario ?? 'America/Sao_Paulo',
            ]);
            
            Log::info('✅ Empresa atualizada', [
                'empresa_id' => $empresa->id,
                'tenant_id' => $empresa->tenant_id
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $empresa->id,
                    'nome' => $empresa->nome,
                    'cnpj' => $empresa->cnpj,
                    'email' => $empresa->email,
                    'telefone' => $empresa->telefone,
                    'website' => $empresa->website,
                    'endereco' => $empresa->endereco,
                    'cidade' => $empresa->cidade,
                    'estado' => $empresa->estado,
                    'cep' => $empresa->cep,
                    'setor' => $empresa->setor,
                    'funcionarios' => $empresa->funcionarios,
                    'descricao' => $empresa->descricao,
                    'fundacao' => $empresa->fundacao,
                    'missao' => $empresa->missao,
                    'visao' => $empresa->visao,
                    'moedaPadrao' => $empresa->moeda_padrao,
                    'fusoHorario' => $empresa->fuso_horario,
                    'tenantId' => $empresa->tenant_id,
                ],
                'message' => 'Dados da empresa atualizados com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar empresa: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar empresa: ' . $e->getMessage()
            ], 500);
        }
    }
}