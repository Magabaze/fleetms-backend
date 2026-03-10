<?php
// app/Http/Controllers/Api/OrdemFaturacaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrdemFaturacao;
use App\Models\Viagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrdemFaturacaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = OrdemFaturacao::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('codigo', 'like', "%{$search}%")
                      ->orWhere('cliente', 'like', "%{$search}%")
                      ->orWhere('motorista', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $ordens = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Buscar códigos das viagens
            $viagensIds = $ordens->pluck('viagem_id')->filter()->toArray();
            $viagens = Viagem::whereIn('id', $viagensIds)->get()->keyBy('id');
            
            $ordensMapeadas = $ordens->map(function ($ordem) use ($viagens) {
                $dados = $ordem->toArray();
                
                if ($ordem->viagem_id && isset($viagens[$ordem->viagem_id])) {
                    $dados['viagem_codigo'] = $viagens[$ordem->viagem_id]->tripNumber;
                }
                
                return $dados;
            });
            
            return response()->json([
                'success' => true,
                'data' => $ordensMapeadas,
                'pagination' => [
                    'page' => $ordens->currentPage(),
                    'limit' => $perPage,
                    'total' => $ordens->total(),
                    'totalPages' => $ordens->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar ordens: ' . $e->getMessage());
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
            'viagemId' => 'required|exists:viagens,id',
            'codigo' => 'required|string|unique:ordens_faturacao,codigo',
            'cliente' => 'required|string',
            'motorista' => 'required|string',
            'origem' => 'required|string',
            'destino' => 'required|string',
            'valor' => 'required|numeric|min:0',
            'dataViagem' => 'required|date',
            'status' => 'required|in:pendente,processado,cancelado',
            'criadoPor' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            DB::beginTransaction();
            
            $viagem = Viagem::where('tenant_id', $tenantId)
                ->where('id', $request->viagemId)
                ->first();
                
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            if ($viagem->isEmptyTrip) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível gerar ordem para viagem vazia'
                ], 400);
            }
            
            $ordemExistente = OrdemFaturacao::where('viagem_id', $request->viagemId)
                ->where('tenant_id', $tenantId)
                ->first();
                
            if ($ordemExistente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Já existe uma ordem para esta viagem'
                ], 400);
            }
            
            $ordem = OrdemFaturacao::create([
                'codigo' => $request->codigo,
                'viagem_id' => $request->viagemId,
                'cliente' => $request->cliente,
                'motorista' => $request->motorista,
                'origem' => $request->origem,
                'destino' => $request->destino,
                'valor' => $request->valor,
                'data_viagem' => $request->dataViagem,
                'status' => $request->status,
                'observacoes' => $request->observacoes ?? null,
                'criado_por' => $request->criadoPor,
                'tenant_id' => $tenantId,
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $ordem,
                'message' => 'Ordem criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar ordem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function marcarProcessado($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $ordem = OrdemFaturacao::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
                
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            if ($ordem->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas ordens pendentes podem ser processadas'
                ], 400);
            }
            
            $ordem->update(['status' => 'processado']);
            
            return response()->json([
                'success' => true,
                'data' => $ordem,
                'message' => 'Ordem processada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao processar ordem: ' . $e->getMessage());
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
            $ordem = OrdemFaturacao::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
                
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'status' => 'sometimes|in:pendente,processado,cancelado',
                'valor' => 'sometimes|numeric|min:0',
                'observacoes' => 'sometimes|nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $ordem->update($request->only(['status', 'valor', 'observacoes']));
            
            return response()->json([
                'success' => true,
                'data' => $ordem,
                'message' => 'Ordem atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar ordem: ' . $e->getMessage());
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
            $ordem = OrdemFaturacao::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
                
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            if ($ordem->notas()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir ordem com notas vinculadas'
                ], 400);
            }
            
            $ordem->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Ordem excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir ordem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}