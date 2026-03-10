<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\Peca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PecaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($peca)
    {
        return [
            'id' => $peca->id,
            'codigo' => $peca->codigo,
            'nome' => $peca->nome,
            'categoria' => $peca->categoria,
            'stockAtual' => (int) $peca->stock_atual,
            'stockMinimo' => (int) $peca->stock_minimo,
            'unidade' => $peca->unidade,
            'precoUnitario' => (float) $peca->preco_unitario,
            'fornecedor' => $peca->fornecedor,
            'ultimaEntrada' => $peca->ultima_entrada,
            'status' => $peca->status,
            'observacoes' => $peca->observacoes,
            'criadoPor' => $peca->criado_por,
            'tenantId' => $peca->tenant_id,
            'createdAt' => $peca->created_at?->toISOString(),
            'updatedAt' => $peca->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = Peca::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $status = $request->status;
                $query->where(function ($q) use ($status) {
                    if ($status === 'ok') {
                        $q->where('stock_atual', '>', 'stock_minimo');
                    } elseif ($status === 'alerta') {
                        $q->where('stock_atual', '=', 'stock_minimo');
                    } elseif ($status === 'critico') {
                        $q->where('stock_atual', '<', 'stock_minimo');
                    }
                });
            }
            
            if ($request->has('categoria') && $request->categoria && $request->categoria !== 'Todos') {
                $query->where('categoria', $request->categoria);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $pecas = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $pecasCamelCase = $pecas->map(function ($peca) {
                return $this->paraCamelCase($peca);
            });
            
            return response()->json([
                'success' => true,
                'data' => $pecasCamelCase->toArray(),
                'pagination' => [
                    'page' => $pecas->currentPage(),
                    'limit' => $perPage,
                    'total' => $pecas->total(),
                    'totalPages' => $pecas->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar peças: ' . $e->getMessage());
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
            'codigo' => 'required|string|unique:pecas,codigo,NULL,id,tenant_id,' . $tenantId,
            'nome' => 'required|string|max:255',
            'categoria' => 'required|string',
            'stockAtual' => 'required|numeric|min:0',
            'stockMinimo' => 'required|numeric|min:0',
            'unidade' => 'required|string',
            'precoUnitario' => 'required|numeric|min:0',
            'fornecedor' => 'required|string',
            'ultimaEntrada' => 'required|date',
        ], [
            'codigo.unique' => 'Já existe uma peça com este código',
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
                'codigo' => $request->codigo,
                'nome' => $request->nome,
                'categoria' => $request->categoria,
                'stock_atual' => $request->stockAtual,
                'stock_minimo' => $request->stockMinimo,
                'unidade' => $request->unidade,
                'preco_unitario' => $request->precoUnitario,
                'fornecedor' => $request->fornecedor,
                'ultima_entrada' => $request->ultimaEntrada,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            $peca = Peca::create($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($peca),
                'message' => 'Peça criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar peça: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function entradaStock(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $validator = Validator::make($request->all(), [
            'quantidade' => 'required|numeric|min:1',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Quantidade inválida',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $peca = Peca::where('tenant_id', $tenantId)->find($id);
            
            if (!$peca) {
                return response()->json([
                    'success' => false,
                    'error' => 'Peça não encontrada'
                ], 404);
            }
            
            $peca->stock_atual += $request->quantidade;
            $peca->ultima_entrada = now()->toDateString();
            $peca->save();
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($peca->fresh()),
                'message' => 'Entrada de stock registada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao registar entrada de stock: ' . $e->getMessage());
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
            $peca = Peca::where('tenant_id', $tenantId)->find($id);
            
            if (!$peca) {
                return response()->json([
                    'success' => false,
                    'error' => 'Peça não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($peca)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar peça: ' . $e->getMessage());
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
            $peca = Peca::where('tenant_id', $tenantId)->find($id);
            
            if (!$peca) {
                return response()->json([
                    'success' => false,
                    'error' => 'Peça não encontrada'
                ], 404);
            }
            
            if ($request->has('codigo') && $request->codigo !== $peca->codigo) {
                $exists = Peca::where('tenant_id', $tenantId)
                    ->where('codigo', $request->codigo)
                    ->where('id', '!=', $id)
                    ->exists();
                    
                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Já existe uma peça com este código'
                    ], 422);
                }
            }
            
            $dados = [];
            if ($request->has('codigo')) $dados['codigo'] = $request->codigo;
            if ($request->has('nome')) $dados['nome'] = $request->nome;
            if ($request->has('categoria')) $dados['categoria'] = $request->categoria;
            if ($request->has('stockAtual')) $dados['stock_atual'] = $request->stockAtual;
            if ($request->has('stockMinimo')) $dados['stock_minimo'] = $request->stockMinimo;
            if ($request->has('unidade')) $dados['unidade'] = $request->unidade;
            if ($request->has('precoUnitario')) $dados['preco_unitario'] = $request->precoUnitario;
            if ($request->has('fornecedor')) $dados['fornecedor'] = $request->fornecedor;
            if ($request->has('ultimaEntrada')) $dados['ultima_entrada'] = $request->ultimaEntrada;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            
            $peca->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($peca->fresh()),
                'message' => 'Peça atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar peça: ' . $e->getMessage());
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
            $peca = Peca::where('tenant_id', $tenantId)->find($id);
            
            if (!$peca) {
                return response()->json([
                    'success' => false,
                    'error' => 'Peça não encontrada'
                ], 404);
            }
            
            $peca->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Peça excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir peça: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}