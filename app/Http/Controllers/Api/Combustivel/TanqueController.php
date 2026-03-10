<?php
// app/Http/Controllers/Api/Combustivel/TanqueController.php

namespace App\Http\Controllers\Api\Combustivel;

use App\Http\Controllers\Controller;
use App\Models\Combustivel\Tanque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TanqueController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Função auxiliar para converter camelCase para snake_case e vice-versa
    private function paraCamelCase($tanque)
    {
        return [
            'id' => $tanque->id,
            'nome' => $tanque->nome,
            'codigo' => $tanque->codigo,
            'tipoCombustivel' => $tanque->tipo_combustivel,
            'tipoCombustivelLabel' => $tanque->tipo_combustivel_label,
            'capacidadeTotal' => (float) $tanque->capacidade_total,
            'nivelAtual' => (float) $tanque->nivel_atual,
            'unidadeMedida' => $tanque->unidade_medida,
            'localizacao' => $tanque->localizacao,
            'status' => $tanque->status,
            'statusLabel' => $tanque->status_label,
            'alertaMinimo' => (int) $tanque->alerta_minimo,
            'alertaCritico' => (int) $tanque->alerta_critico,
            'percentualOcupacao' => $tanque->percentual_ocupacao,
            'nivelDisponivel' => (float) $tanque->nivel_disponivel,
            'nivelAlerta' => $tanque->nivel_alerta,
            'corNivel' => $tanque->cor_nivel,
            'observacoes' => $tanque->observacoes,
            'criadoPor' => $tanque->criado_por,
            'tenantId' => $tanque->tenant_id,
            'createdAt' => $tanque->created_at?->toISOString(),
            'updatedAt' => $tanque->updated_at?->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/combustivel/tanques', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Tanque::where('tenant_id', $tenantId);
            
            // Busca
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('localizacao', 'like', "%{$search}%")
                      ->orWhere('tipo_combustivel', 'like', "%{$search}%"); // 👈 BUSCA POR TIPO
                });
            }
            
            // Filtro por status
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            // Filtro por tipo de combustível (AGORA É LIKE, NÃO MAIS IN)
            if ($request->has('tipo_combustivel') && $request->tipo_combustivel && $request->tipo_combustivel !== 'todos') {
                $query->where('tipo_combustivel', 'like', "%{$request->tipo_combustivel}%");
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $tanques = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter todos os itens para camelCase
            $tanquesCamelCase = $tanques->map(function ($tanque) {
                return $this->paraCamelCase($tanque);
            });
            
            Log::info('✅ Tanques listados', [
                'total' => $tanques->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $tanquesCamelCase->toArray(),
                'pagination' => [
                    'page' => $tanques->currentPage(),
                    'limit' => $perPage,
                    'total' => $tanques->total(),
                    'totalPages' => $tanques->lastPage(),
                    'hasNextPage' => $tanques->hasMorePages(),
                    'hasPrevPage' => $tanques->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar tanques: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/combustivel/tanques', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        // 👈 REGRAS DE VALIDAÇÃO ATUALIZADAS - TIPO COMBUSTÍVEL AGORA É STRING
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'codigo' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tanques')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ],
            'tipoCombustivel' => 'required|string|max:100', // 👈 AGORA APENAS STRING, NÃO MAIS IN
            'capacidadeTotal' => 'required|numeric|min:0.01',
            'nivelAtual' => 'required|numeric|min:0',
            'unidadeMedida' => 'required|in:' . implode(',', array_keys(Tanque::UNIDADES_MEDIDA)),
            'status' => 'required|in:' . implode(',', array_keys(Tanque::STATUS)),
            'alertaMinimo' => 'required|integer|min:0|max:100',
            'alertaCritico' => 'required|integer|min:0|max:100|lte:alertaMinimo',
        ], [
            'codigo.unique' => 'Já existe um tanque com este código',
            'alertaCritico.lte' => 'O alerta crítico deve ser menor ou igual ao alerta mínimo',
            'nivelAtual.max' => 'O nível atual não pode ser maior que a capacidade total',
            'tipoCombustivel.required' => 'O tipo de combustível é obrigatório',
        ]);
        
        if ($validator->fails()) {
            Log::error('❌ Validação falhou', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Validar nível atual <= capacidade total
        if ($request->nivelAtual > $request->capacidadeTotal) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => [
                    'nivelAtual' => ['O nível atual não pode ser maior que a capacidade total']
                ]
            ], 422);
        }
        
        try {
            $dados = [
                'nome' => $request->nome,
                'codigo' => strtoupper($request->codigo),
                'tipo_combustivel' => $request->tipoCombustivel, // 👈 AGORA É STRING LIVRE
                'capacidade_total' => $request->capacidadeTotal,
                'nivel_atual' => $request->nivelAtual,
                'unidade_medida' => $request->unidadeMedida,
                'localizacao' => $request->localizacao ?? '',
                'status' => $request->status,
                'alerta_minimo' => $request->alertaMinimo,
                'alerta_critico' => $request->alertaCritico,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? $request->criadoPor ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando tanque', $dados);
            
            $tanque = Tanque::create($dados);
            
            Log::info('✅ Tanque criado', [
                'id' => $tanque->id,
                'codigo' => $tanque->codigo,
                'tipo_combustivel' => $tanque->tipo_combustivel,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($tanque),
                'message' => 'Tanque criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar tanque: ' . $e->getMessage());
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
            $tanque = Tanque::where('tenant_id', $tenantId)->find($id);
            
            if (!$tanque) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tanque não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($tanque)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar tanque: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/combustivel/tanques/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $tanque = Tanque::where('tenant_id', $tenantId)->find($id);
            
            if (!$tanque) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tanque não encontrado'
                ], 404);
            }
            
            // 👈 REGRAS DE VALIDAÇÃO ATUALIZADAS - TIPO COMBUSTÍVEL AGORA É STRING
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'codigo' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('tanques')->where(function ($query) use ($tenantId) {
                        return $query->where('tenant_id', $tenantId);
                    })->ignore($id)
                ],
                'tipoCombustivel' => 'required|string|max:100', // 👈 AGORA APENAS STRING
                'capacidadeTotal' => 'required|numeric|min:0.01',
                'unidadeMedida' => 'required|in:' . implode(',', array_keys(Tanque::UNIDADES_MEDIDA)),
                'status' => 'required|in:' . implode(',', array_keys(Tanque::STATUS)),
                'alertaMinimo' => 'required|integer|min:0|max:100',
                'alertaCritico' => 'required|integer|min:0|max:100|lte:alertaMinimo',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Não atualizamos nivel_atual aqui - será atualizado via movimentações
            $tanque->update([
                'nome' => $request->nome,
                'codigo' => strtoupper($request->codigo),
                'tipo_combustivel' => $request->tipoCombustivel, // 👈 AGORA É STRING LIVRE
                'capacidade_total' => $request->capacidadeTotal,
                'unidade_medida' => $request->unidadeMedida,
                'localizacao' => $request->localizacao ?? $tanque->localizacao,
                'status' => $request->status,
                'alerta_minimo' => $request->alertaMinimo,
                'alerta_critico' => $request->alertaCritico,
                'observacoes' => $request->observacoes ?? $tanque->observacoes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($tanque->fresh()),
                'message' => 'Tanque atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar tanque: ' . $e->getMessage());
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
            $tanque = Tanque::where('tenant_id', $tenantId)->find($id);
            
            if (!$tanque) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tanque não encontrado'
                ], 404);
            }
            
            $tanque->delete();
            
            Log::info('✅ Tanque excluído', [
                'id' => $id,
                'codigo' => $tanque->codigo,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Tanque excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir tanque: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Endpoint para obter recursos - AGORA SEM TIPOS_COMBUSTIVEL
    public function recursos()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => collect(Tanque::STATUS)->map(function ($label, $value) {
                        return ['value' => $value, 'label' => $label];
                    })->values(),
                    'unidadesMedida' => collect(Tanque::UNIDADES_MEDIDA)->map(function ($label, $value) {
                        return ['value' => $value, 'label' => $label];
                    })->values(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar recursos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}