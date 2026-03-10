<?php
// app/Http/Controllers/Api/Combustivel/FornecedorController.php

namespace App\Http\Controllers\Api\Combustivel;

use App\Http\Controllers\Controller;
use App\Models\Combustivel\FornecedorCombustivel;
use App\Models\Combustivel\PostoCombustivel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FornecedorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Listar fornecedores e postos
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            // Inicializar arrays
            $resultados = [];
            
            // SEMPRE listar fornecedores
            $fornecedoresQuery = FornecedorCombustivel::where('tenant_id', $tenantId);
            
            // Busca
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $fornecedoresQuery->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                      ->orWhere('nif', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            // Filtro por status
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $fornecedoresQuery->where('status', $request->status);
            }
            
            $fornecedores = $fornecedoresQuery->get();
            
            foreach ($fornecedores as $fornecedor) {
                $resultados[] = [
                    'id' => $fornecedor->id,
                    'tipo' => 'fornecedor',
                    'nome' => $fornecedor->nome,
                    'nif' => $fornecedor->nif,
                    'email' => $fornecedor->email,
                    'telefone' => $fornecedor->telefone,
                    'endereco' => $fornecedor->endereco,
                    'status' => $fornecedor->status,
                    'tenant_id' => $fornecedor->tenant_id,
                    'created_at' => $fornecedor->created_at->toISOString(),
                    'updated_at' => $fornecedor->updated_at->toISOString(),
                ];
            }
            
            // SEMPRE listar postos também
            $postosQuery = PostoCombustivel::with('fornecedor')->where('tenant_id', $tenantId);
            
            // Busca em postos
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $postosQuery->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                      ->orWhere('localizacao', 'like', "%{$search}%");
                });
            }
            
            // Filtro por status em postos
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $postosQuery->where('status', $request->status);
            }
            
            $postos = $postosQuery->get();
            
            foreach ($postos as $posto) {
                $resultados[] = [
                    'id' => $posto->id,
                    'tipo' => 'posto',
                    'nome' => $posto->nome,
                    'localizacao' => $posto->localizacao,
                    'fornecedor_id' => $posto->fornecedor_id,
                    'fornecedor_nome' => $posto->fornecedor->nome ?? null,
                    'status' => $posto->status,
                    'tenant_id' => $posto->tenant_id,
                    'created_at' => $posto->created_at->toISOString(),
                    'updated_at' => $posto->updated_at->toISOString(),
                ];
            }
            
            // Ordenar por nome
            usort($resultados, function ($a, $b) {
                return strcmp($a['nome'], $b['nome']);
            });
            
            // Paginação
            $perPage = $request->get('limit', 100);
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            
            $dadosPaginados = array_slice($resultados, $offset, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $dadosPaginados,
                'total' => count($resultados),
                'page' => (int)$page,
                'limit' => (int)$perPage,
                'totalPages' => ceil(count($resultados) / $perPage)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar fornecedores/postos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar fornecedor ou posto
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $tipo = $request->get('tipo', 'fornecedor');
        
        if ($tipo === 'fornecedor') {
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'nif' => 'required|string|max:20|unique:fornecedores_combustivel,nif',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'endereco' => 'nullable|string',
                'status' => 'required|in:ativo,inativo'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            try {
                $fornecedor = FornecedorCombustivel::create([
                    'nome' => $request->nome,
                    'nif' => $request->nif,
                    'email' => $request->email,
                    'telefone' => $request->telefone,
                    'endereco' => $request->endereco,
                    'status' => $request->status,
                    'tenant_id' => $tenantId,
                ]);
                
                Log::info('✅ Fornecedor criado', [
                    'id' => $fornecedor->id,
                    'nome' => $fornecedor->nome
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $fornecedor->id,
                        'tipo' => 'fornecedor',
                        'nome' => $fornecedor->nome,
                        'nif' => $fornecedor->nif,
                        'email' => $fornecedor->email,
                        'telefone' => $fornecedor->telefone,
                        'endereco' => $fornecedor->endereco,
                        'status' => $fornecedor->status,
                        'tenant_id' => $fornecedor->tenant_id,
                        'created_at' => $fornecedor->created_at->toISOString(),
                        'updated_at' => $fornecedor->updated_at->toISOString(),
                    ],
                    'message' => 'Fornecedor criado com sucesso!'
                ], 201);
                
            } catch (\Exception $e) {
                Log::error('❌ Erro ao criar fornecedor: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro interno: ' . $e->getMessage()
                ], 500);
            }
            
        } elseif ($tipo === 'posto') {
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'localizacao' => 'required|string',
                'fornecedor_id' => 'required|exists:fornecedores_combustivel,id',
                'status' => 'required|in:ativo,inativo'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            try {
                // Verificar se fornecedor existe
                $fornecedor = FornecedorCombustivel::where('id', $request->fornecedor_id)
                    ->where('tenant_id', $tenantId)
                    ->first();
                    
                if (!$fornecedor) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Fornecedor não encontrado'
                    ], 404);
                }
                
                $posto = PostoCombustivel::create([
                    'nome' => $request->nome,
                    'localizacao' => $request->localizacao,
                    'fornecedor_id' => $request->fornecedor_id,
                    'status' => $request->status,
                    'tenant_id' => $tenantId,
                ]);
                
                Log::info('✅ Posto criado', [
                    'id' => $posto->id,
                    'nome' => $posto->nome,
                    'fornecedor' => $fornecedor->nome
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $posto->id,
                        'tipo' => 'posto',
                        'nome' => $posto->nome,
                        'localizacao' => $posto->localizacao,
                        'fornecedor_id' => $posto->fornecedor_id,
                        'fornecedor_nome' => $fornecedor->nome,
                        'status' => $posto->status,
                        'tenant_id' => $posto->tenant_id,
                        'created_at' => $posto->created_at->toISOString(),
                        'updated_at' => $posto->updated_at->toISOString(),
                    ],
                    'message' => 'Posto criado com sucesso!'
                ], 201);
                
            } catch (\Exception $e) {
                Log::error('❌ Erro ao criar posto: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro interno: ' . $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'error' => 'Tipo inválido. Use "fornecedor" ou "posto"'
            ], 400);
        }
    }

    /**
     * Dropdown de fornecedores (apenas ativos)
     */
    public function dropdownFornecedores(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $fornecedores = FornecedorCombustivel::where('tenant_id', $tenantId)
                ->where('status', 'ativo')
                ->orderBy('nome')
                ->get(['id', 'nome']);
            
            return response()->json([
                'success' => true,
                'data' => $fornecedores
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar fornecedores para dropdown: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}