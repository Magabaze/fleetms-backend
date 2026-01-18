<?php
// app/Http/Controllers/Api/AgenteController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AgenteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Função auxiliar para converter snake_case para camelCase
    private function paraCamelCase($agente)
    {
        return [
            'id' => $agente->id,
            'nomeCompleto' => $agente->nome_completo,
            'localAtuacao' => $agente->local_atuacao,
            'fronteiraAssociada' => $agente->fronteira_associada,
            'telefone' => $agente->telefone,
            'email' => $agente->email,
            'taxaServico' => $agente->taxa_servico ? (float) $agente->taxa_servico : null,
            'moeda' => $agente->moeda,
            'documentos' => $agente->documentos ?? [],
            'observacoes' => $agente->observacoes,
            'status' => $agente->status,
            'criadoPor' => $agente->criado_por,
            'tenantId' => $agente->tenant_id, // Adicionado para debug
            'createdAt' => $agente->created_at->toISOString(),
            'updatedAt' => $agente->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/agentes', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            // FILTRO IMPORTANTE: Usar o tenant_id do usuário logado
            $query = Agente::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome_completo', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('telefone', 'like', "%{$search}%")
                      ->orWhere('local_atuacao', 'like', "%{$search}%")
                      ->orWhere('fronteira_associada', 'like', "%{$search}%");
                });
            }
            
            // Filtro por status (igual ao cliente)
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $agentes = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter todos os itens para camelCase
            $agentesCamelCase = $agentes->map(function ($agente) {
                return $this->paraCamelCase($agente);
            });
            
            Log::info('✅ Agentes listados', [
                'total' => $agentes->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $agentesCamelCase->toArray(),
                'pagination' => [
                    'page' => $agentes->currentPage(),
                    'limit' => $perPage,
                    'total' => $agentes->total(),
                    'totalPages' => $agentes->lastPage(),
                    'hasNextPage' => $agentes->hasMorePages(),
                    'hasPrevPage' => $agentes->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar agentes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 POST /api/agentes', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'nomeCompleto' => 'required|string|max:255',
            'status' => 'required|in:ativo,inativo,pendente',
            'email' => 'nullable|email|unique:agentes,email,NULL,id,tenant_id,' . $tenantId,
        ], [
            'email.unique' => 'Já existe um agente com este email',
        ]);
        
        if ($validator->fails()) {
            Log::error('❌ Validação falhou', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $dados = [
                'nome_completo' => $request->nomeCompleto,
                'local_atuacao' => $request->localAtuacao ?? '',
                'fronteira_associada' => $request->fronteiraAssociada ?? '',
                'telefone' => $request->telefone ?? '',
                'email' => $request->email ?? '',
                'taxa_servico' => $request->taxaServico ?? null,
                'moeda' => $request->moeda ?? 'USD',
                'documentos' => $request->documentos ?? [],
                'observacoes' => $request->observacoes ?? '',
                'status' => $request->status,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId, // CRÍTICO: Usar o tenant_id do usuário
            ];
            
            Log::info('💾 Salvando agente', $dados);
            
            $agente = Agente::create($dados);
            
            Log::info('✅ Agente criado', [
                'id' => $agente->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($agente),
                'message' => 'Agente criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar agente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            // IMPORTANTE: Filtrar pelo tenant_id do usuário
            $agente = Agente::where('tenant_id', $tenantId)->find($id);
            
            if (!$agente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agente não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($agente)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar agente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 PUT /api/agentes/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            // IMPORTANTE: Filtrar pelo tenant_id do usuário
            $agente = Agente::where('tenant_id', $tenantId)->find($id);
            
            if (!$agente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agente não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'nomeCompleto' => 'required|string|max:255',
                'status' => 'required|in:ativo,inativo,pendente',
                'email' => 'nullable|email|unique:agentes,email,' . $id . ',id,tenant_id,' . $tenantId,
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $agente->update([
                'nome_completo' => $request->nomeCompleto,
                'local_atuacao' => $request->localAtuacao ?? $agente->local_atuacao,
                'fronteira_associada' => $request->fronteiraAssociada ?? $agente->fronteira_associada,
                'telefone' => $request->telefone ?? $agente->telefone,
                'email' => $request->email ?? $agente->email,
                'taxa_servico' => $request->taxaServico ?? $agente->taxa_servico,
                'moeda' => $request->moeda ?? $agente->moeda,
                'documentos' => $request->documentos ?? $agente->documentos,
                'observacoes' => $request->observacoes ?? $agente->observacoes,
                'status' => $request->status,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($agente->fresh()),
                'message' => 'Agente atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar agente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            // IMPORTANTE: Filtrar pelo tenant_id do usuário
            $agente = Agente::where('tenant_id', $tenantId)->find($id);
            
            if (!$agente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Agente não encontrado'
                ], 404);
            }
            
            $agente->delete();
            
            Log::info('✅ Agente excluído', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Agente excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir agente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}