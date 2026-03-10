<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaixaRequisicao;
use App\Models\Viagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CaixaRequisicaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($requisicao)
    {
        return [
            'id' => $requisicao->id,
            'viagemId' => $requisicao->viagem_id,
            'motoristaNome' => $requisicao->motorista_nome,
            'motoristaId' => $requisicao->motorista_id,
            'valor' => (float) $requisicao->valor,
            'descricao' => $requisicao->descricao,
            'dataRequisicao' => $requisicao->data_requisicao->toISOString(),
            'status' => $requisicao->status,
            'aprovadoPor' => $requisicao->aprovado_por,
            'dataAprovacao' => $requisicao->data_aprovacao?->toISOString(),
            'observacoes' => $requisicao->observacoes,
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = CaixaRequisicao::where('tenant_id', $tenantId);
            
            // Filtro por status
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            // Filtro por motorista
            if ($request->has('motorista_id') && $request->motorista_id) {
                $query->where('motorista_id', $request->motorista_id);
            }
            
            // Busca
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('motorista_nome', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%");
                });
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $requisicoes = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $requisicoesCamelCase = $requisicoes->map(function ($req) {
                return $this->paraCamelCase($req);
            });
            
            return response()->json([
                'success' => true,
                'data' => $requisicoesCamelCase->toArray(),
                'pagination' => [
                    'page' => $requisicoes->currentPage(),
                    'limit' => $perPage,
                    'total' => $requisicoes->total(),
                    'totalPages' => $requisicoes->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar requisições: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pendentes(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $requisicoes = CaixaRequisicao::where('tenant_id', $tenantId)
                ->whereIn('status', ['pendente', 'aprovado'])
                ->orderBy('data_requisicao', 'asc')
                ->get();
            
            $totalPendente = $requisicoes->sum('valor');
            
            $requisicoesCamelCase = $requisicoes->map(function ($req) {
                return $this->paraCamelCase($req);
            });
            
            return response()->json([
                'success' => true,
                'data' => $requisicoesCamelCase->toArray(),
                'totalPendente' => (float) $totalPendente
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar pendentes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function viagensComPendencias(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $requisicoes = CaixaRequisicao::where('tenant_id', $tenantId)
                ->where('status', 'pendente')
                ->orderBy('data_requisicao', 'asc')
                ->get();
            
            // Agrupar por viagem
            $viagens = [];
            foreach ($requisicoes as $req) {
                $key = $req->viagem_id ?? 'sem_viagem_' . $req->id;
                
                if (!isset($viagens[$key])) {
                    $viagens[$key] = [
                        'viagemId' => $req->viagem_id ? 'VIAGEM-' . $req->viagem_id : 'Avulso',
                        'motorista' => $req->motorista_nome,
                        'rota' => $req->viagem_id ? 'Rota da viagem' : 'Requisição avulsa',
                        'totalPendente' => 0,
                        'quantidadeDespesas' => 0,
                        'despesas' => []
                    ];
                }
                
                $viagens[$key]['totalPendente'] += $req->valor;
                $viagens[$key]['quantidadeDespesas']++;
                $viagens[$key]['despesas'][] = [
                    'id' => $req->id,
                    'tipo' => 'Requisição',
                    'valor' => (float) $req->valor,
                    'data' => $req->data_requisicao->format('Y-m-d'),
                    'status' => $req->status,
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => array_values($viagens)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar viagens com pendências: ' . $e->getMessage());
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
            'viagemId' => 'nullable|exists:viagens,id',
            'motoristaNome' => 'required|string|max:255',
            'valor' => 'required|numeric|min:0',
            'descricao' => 'required|string|max:500',
            'observacoes' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $dados = [
                'viagem_id' => $request->viagemId,
                'motorista_nome' => $request->motoristaNome,
                'motorista_id' => null,
                'valor' => $request->valor,
                'descricao' => $request->descricao,
                'data_requisicao' => now(),
                'status' => 'pendente',
                'observacoes' => $request->observacoes ?? '',
                'tenant_id' => $tenantId,
            ];
            
            $requisicao = CaixaRequisicao::create($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($requisicao),
                'message' => 'Requisição criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar requisição: ' . $e->getMessage());
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
            $requisicao = CaixaRequisicao::where('tenant_id', $tenantId)->find($id);
            
            if (!$requisicao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Requisição não encontrada'
                ], 404);
            }
            
            // Não permitir alterar se já estiver paga
            if ($requisicao->isPago()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível alterar uma requisição já paga'
                ], 422);
            }
            
            $validator = Validator::make($request->all(), [
                'valor' => 'required|numeric|min:0',
                'descricao' => 'required|string|max:500',
                'observacoes' => 'nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $requisicao->update([
                'valor' => $request->valor,
                'descricao' => $request->descricao,
                'observacoes' => $request->observacoes ?? $requisicao->observacoes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($requisicao),
                'message' => 'Requisição atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar requisição: ' . $e->getMessage());
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
            $requisicao = CaixaRequisicao::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->where('status', 'pendente')
                ->first();
            
            if (!$requisicao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Requisição não encontrada ou não está pendente'
                ], 404);
            }
            
            $requisicao->update([
                'status' => 'aprovado',
                'aprovado_por' => $user->name ?? 'Sistema',
                'data_aprovacao' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($requisicao),
                'message' => 'Requisição aprovada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao aprovar requisição: ' . $e->getMessage());
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
            $requisicao = CaixaRequisicao::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->where('status', 'pendente')
                ->first();
            
            if (!$requisicao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Requisição não encontrada ou não está pendente'
                ], 404);
            }
            
            $requisicao->update([
                'status' => 'rejeitado',
                'observacoes' => $request->observacoes ?? $requisicao->observacoes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($requisicao),
                'message' => 'Requisição rejeitada!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao rejeitar requisição: ' . $e->getMessage());
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
            $requisicao = CaixaRequisicao::where('tenant_id', $tenantId)->find($id);
            
            if (!$requisicao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Requisição não encontrada'
                ], 404);
            }
            
            // Não permitir excluir se já estiver paga
            if ($requisicao->isPago()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir uma requisição já paga'
                ], 422);
            }
            
            $requisicao->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Requisição excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir requisição: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}