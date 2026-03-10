<?php

namespace App\Http\Controllers\Api\Combustivel;

use App\Http\Controllers\Controller;
use App\Models\Combustivel\AbastecimentoExterno;
use App\Models\Combustivel\PostoCombustivel;
use App\Models\Combustivel\Camiao;
use App\Models\Combustivel\Motorista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AbastecimentoExternoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($abastecimento)
    {
        return [
            'id' => $abastecimento->id,
            'numero' => $abastecimento->numero,
            'veiculo_id' => $abastecimento->veiculo_id,
            'veiculo' => $abastecimento->veiculo ? [
                'id' => $abastecimento->veiculo->id,
                'matricula' => $abastecimento->veiculo->matricula,
                'marca' => $abastecimento->veiculo->marca,
                'modelo' => $abastecimento->veiculo->modelo,
            ] : null,
            'motorista_id' => $abastecimento->motorista_id,
            'motorista' => $abastecimento->motorista ? [
                'id' => $abastecimento->motorista->id,
                'nome' => $abastecimento->motorista->nome,
                'numero_identificacao' => $abastecimento->motorista->numero_identificacao,
            ] : null,
            'posto_id' => $abastecimento->posto_id,
            'posto' => $abastecimento->posto ? [
                'id' => $abastecimento->posto->id,
                'nome' => $abastecimento->posto->nome,
                'localizacao' => $abastecimento->posto->localizacao,
                'fornecedor' => $abastecimento->posto->fornecedor ? [
                    'id' => $abastecimento->posto->fornecedor->id,
                    'nome' => $abastecimento->posto->fornecedor->nome
                ] : null
            ] : null,
            'tipo_combustivel' => $abastecimento->tipo_combustivel,
            'quantidade' => (float) $abastecimento->quantidade,
            'preco_unitario' => (float) $abastecimento->preco_unitario,
            'valor_total' => (float) $abastecimento->valor_total,
            'moeda' => $abastecimento->moeda,
            'odometro' => $abastecimento->odometro,
            'data_abastecimento' => $abastecimento->data_abastecimento->toDateString(),
            'nota_fiscal' => $abastecimento->nota_fiscal,
            'status' => $abastecimento->status,
            'observacoes' => $abastecimento->observacoes,
            'responsavel_registro' => $abastecimento->responsavel_registro,
            'aprovado_por' => $abastecimento->aprovado_por,
            'data_aprovacao' => $abastecimento->data_aprovacao?->toISOString(),
            'pago_por' => $abastecimento->pago_por,
            'data_pagamento' => $abastecimento->data_pagamento?->toISOString(),
            'tenant_id' => $abastecimento->tenant_id,
            'created_at' => $abastecimento->created_at->toISOString(),
            'updated_at' => $abastecimento->updated_at->toISOString(),
            // ✅ CAMPOS ADICIONADOS PARA CORRIGIR O "NÃO INFORMADO"
            'viagem_id' => $abastecimento->viagem_id,
            'numero_viagem' => $abastecimento->numero_viagem,
            'distancia_percorrida' => (float) ($abastecimento->distancia_percorrida ?? 0),
            'veiculo_matricula' => $abastecimento->veiculo_matricula,
            'motorista_nome' => $abastecimento->motorista_nome,
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto.fornecedor'])
                ->where('tenant_id', $tenantId);
            
            // Busca
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                      ->orWhere('nota_fiscal', 'like', "%{$search}%")
                      ->orWhereHas('veiculo', function ($q) use ($search) {
                          $q->where('matricula', 'like', "%{$search}%");
                      })
                      ->orWhereHas('motorista', function ($q) use ($search) {
                          $q->where('nome', 'like', "%{$search}%");
                      })
                      ->orWhereHas('posto', function ($q) use ($search) {
                          $q->where('nome', 'like', "%{$search}%");
                      });
                });
            }
            
            // Filtro por status
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            // Ordenação
            $query->orderBy('data_abastecimento', 'desc')
                  ->orderBy('created_at', 'desc');
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $abastecimentos = $query->paginate($perPage, ['*'], 'page', $page);
            
            $abastecimentosCamelCase = $abastecimentos->map(function ($abastecimento) {
                return $this->paraCamelCase($abastecimento);
            });
            
            return response()->json([
                'success' => true,
                'data' => $abastecimentosCamelCase->toArray(),
                'pagination' => [
                    'page' => $abastecimentos->currentPage(),
                    'limit' => $perPage,
                    'total' => $abastecimentos->total(),
                    'totalPages' => $abastecimentos->lastPage(),
                    'hasNextPage' => $abastecimentos->hasMorePages(),
                    'hasPrevPage' => $abastecimentos->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar abastecimentos externos: ' . $e->getMessage());
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
        
        $validator = Validator::make($request->all(), [
            'veiculo_id' => 'nullable|exists:camioes,id',
            'motorista_id' => 'nullable|exists:motoristas,id',
            'posto_id' => 'required|exists:postos_combustivel,id',
            'tipo_combustivel' => 'required|in:diesel_s10,gasolina_95,gasolina_98,diesel_s500,diesel_s50',
            'quantidade' => 'required|numeric|min:0.01',
            'preco_unitario' => 'required|numeric|min:0.01',
            'moeda' => 'required|in:USD,EUR,MZN,ZAR',
            'odometro' => 'nullable|integer|min:0',
            'data' => 'required|date',
            'data_abastecimento' => 'nullable|date',
            'nota_fiscal' => 'nullable|string|max:100',
            'responsavel_registro' => 'required|string|max:255',
            'observacoes' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $ano = date('Y');
            $ultimoAbastecimento = AbastecimentoExterno::where('tenant_id', $tenantId)
                ->where('numero', 'like', "AE-{$ano}-%")
                ->orderBy('id', 'desc')
                ->first();
            
            $sequencia = 1;
            if ($ultimoAbastecimento && preg_match('/AE-' . $ano . '-(\d+)/', $ultimoAbastecimento->numero, $matches)) {
                $sequencia = intval($matches[1]) + 1;
            }
            
            $numero = "AE-{$ano}-" . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
            $valorTotal = $request->quantidade * $request->preco_unitario;
            
            $dados = [
                'numero' => $numero,
                'veiculo_id' => $request->veiculo_id,
                'motorista_id' => $request->motorista_id,
                'posto_id' => $request->posto_id,
                'tipo_combustivel' => $request->tipo_combustivel,
                'quantidade' => $request->quantidade,
                'preco_unitario' => $request->preco_unitario,
                'valor_total' => $valorTotal,
                'moeda' => $request->moeda,
                'odometro' => $request->odometro,
                'data_abastecimento' => $request->data,
                'nota_fiscal' => $request->nota_fiscal,
                'status' => $request->status ?? 'pendente',
                'observacoes' => $request->observacoes,
                'responsavel_registro' => $request->responsavel_registro,
                'tenant_id' => $tenantId,
                'viagem_id' => $request->viagem_id ?? null,
                'numero_viagem' => $request->numero_viagem ?? null,
                'distancia_percorrida' => $request->distancia_percorrida ?? null,
                'veiculo_matricula' => $request->veiculo_matricula ?? null,
                'motorista_nome' => $request->motorista_nome ?? null
            ];
            
            $abastecimento = AbastecimentoExterno::create($dados);
            
            Log::info('✅ Abastecimento externo criado', ['id' => $abastecimento->id]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->load(['veiculo', 'motorista', 'posto.fornecedor'])),
                'message' => 'Abastecimento externo criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar abastecimento externo: ' . $e->getMessage());
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
            $abastecimento = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto.fornecedor'])
                ->where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar abastecimento: ' . $e->getMessage());
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
        
        try {
            $abastecimento = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto.fornecedor'])
                ->where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }
            
            // Validação específica para atualização
            if (!in_array($abastecimento->status, ['pendente', 'rejeitado'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos pendentes ou rejeitados podem ser editados'
                ], 400);
            }
            
            $validator = Validator::make($request->all(), [
                'quantidade' => 'sometimes|numeric|min:0.01',
                'preco_unitario' => 'sometimes|numeric|min:0.01',
                'odometro' => 'nullable|integer|min:0',
                'nota_fiscal' => 'nullable|string|max:100',
                'observacoes' => 'nullable|string',
                'data' => 'nullable|date',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $dadosAtualizacao = $request->only([
                'quantidade',
                'preco_unitario',
                'odometro',
                'nota_fiscal',
                'observacoes'
            ]);
            
            if ($request->has('data')) {
                $dadosAtualizacao['data_abastecimento'] = $request->data;
            }
            
            if ($request->has('quantidade') || $request->has('preco_unitario')) {
                $quantidade = $request->quantidade ?? $abastecimento->quantidade;
                $precoUnitario = $request->preco_unitario ?? $abastecimento->preco_unitario;
                $dadosAtualizacao['valor_total'] = $quantidade * $precoUnitario;
            }
            
            $abastecimento->update($dadosAtualizacao);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar abastecimento: ' . $e->getMessage());
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
            $abastecimento = AbastecimentoExterno::where('tenant_id', $tenantId)->find($id);
            
            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }
            
            if (!in_array($abastecimento->status, ['pendente', 'rejeitado'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos pendentes ou rejeitados podem ser excluídos'
                ], 400);
            }
            
            $abastecimento->delete();
            
            Log::info('✅ Abastecimento excluído', ['id' => $id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Abastecimento excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function aprovar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $abastecimento = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto.fornecedor'])
                ->where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }
            
            if ($abastecimento->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos pendentes podem ser aprovados'
                ], 400);
            }
            
            $abastecimento->update([
                'status' => 'aprovado',
                'aprovado_por' => $user->name,
                'data_aprovacao' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento aprovado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao aprovar abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejeitar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $abastecimento = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto.fornecedor'])
                ->where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }
            
            if ($abastecimento->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos pendentes podem ser rejeitados'
                ], 400);
            }
            
            $observacoes = $abastecimento->observacoes ? $abastecimento->observacoes . "\n\n" : '';
            $observacoes .= "Rejeitado em: " . now()->format('d/m/Y H:i') . 
                           "\nMotivo: " . ($request->motivo ?? 'Não especificado');
            
            $abastecimento->update([
                'status' => 'rejeitado',
                'observacoes' => $observacoes
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento rejeitado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao rejeitar abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pagar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $abastecimento = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto.fornecedor'])
                ->where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }
            
            if ($abastecimento->status !== 'aprovado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos aprovados podem ser marcados como pagos'
                ], 400);
            }
            
            $abastecimento->update([
                'status' => 'pago',
                'pago_por' => $user->name,
                'data_pagamento' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento marcado como pago com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao marcar abastecimento como pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancelar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $abastecimento = AbastecimentoExterno::with(['veiculo', 'motorista', 'posto.fornecedor'])
                ->where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }
            
            if (!in_array($abastecimento->status, ['pendente', 'aprovado'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos pendentes ou aprovados podem ser cancelados'
                ], 400);
            }
            
            $observacoes = $abastecimento->observacoes ? $abastecimento->observacoes . "\n\n" : '';
            $observacoes .= "Cancelado em: " . now()->format('d/m/Y H:i') . 
                           "\nMotivo: " . ($request->motivo ?? 'Não especificado');
            
            $abastecimento->update([
                'status' => 'cancelado',
                'observacoes' => $observacoes
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento cancelado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao cancelar abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}