<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\Orcamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrcamentoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($orcamento)
    {
        return [
            'id' => $orcamento->id,
            'codigo' => $orcamento->codigo,
            'ordemId' => $orcamento->ordem_id,
            'veiculo' => $orcamento->veiculo,
            'matricula' => $orcamento->matricula,
            'fornecedor' => $orcamento->fornecedor,
            'descricao' => $orcamento->descricao,
            'valorOrcado' => (float) $orcamento->valor_orcado,
            'valorFinal' => $orcamento->valor_final ? (float) $orcamento->valor_final : null,
            'status' => $orcamento->status,
            'dataEmissao' => $orcamento->data_emissao,
            'dataResposta' => $orcamento->data_resposta,
            'dataEntrada' => $orcamento->data_entrada,
            'dataSaida' => $orcamento->data_saida,
            'observacoes' => $orcamento->observacoes,
            'criadoPor' => $orcamento->criado_por,
            'tenantId' => $orcamento->tenant_id,
            'createdAt' => $orcamento->created_at?->toISOString(),
            'updatedAt' => $orcamento->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = Orcamento::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('veiculo', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('fornecedor', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $orcamentos = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $orcamentosCamelCase = $orcamentos->map(function ($orcamento) {
                return $this->paraCamelCase($orcamento);
            });
            
            return response()->json([
                'success' => true,
                'data' => $orcamentosCamelCase->toArray(),
                'pagination' => [
                    'page' => $orcamentos->currentPage(),
                    'limit' => $perPage,
                    'total' => $orcamentos->total(),
                    'totalPages' => $orcamentos->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar orçamentos: ' . $e->getMessage());
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
            'ordemId' => 'required|string|max:50',
            'veiculo' => 'required|string|max:255',
            'matricula' => 'required|string|max:20',
            'fornecedor' => 'required|string|max:255',
            'descricao' => 'required|string',
            'valorOrcado' => 'required|numeric|min:0',
            'status' => 'required|in:pendente,aprovado,rejeitado,concluido',
            'dataEmissao' => 'required|date',
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
                'ordem_id' => $request->ordemId,
                'veiculo' => $request->veiculo,
                'matricula' => $request->matricula,
                'fornecedor' => $request->fornecedor,
                'descricao' => $request->descricao,
                'valor_orcado' => $request->valorOrcado,
                'valor_final' => $request->valorFinal ?? null,
                'status' => $request->status,
                'data_emissao' => $request->dataEmissao,
                'data_resposta' => $request->dataResposta ?? null,
                'data_entrada' => $request->dataEntrada ?? null,
                'data_saida' => $request->dataSaida ?? null,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            $orcamento = Orcamento::create($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($orcamento),
                'message' => 'Orçamento criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar orçamento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function alterarStatus(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:aprovado,rejeitado',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Status inválido',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $orcamento = Orcamento::where('tenant_id', $tenantId)->find($id);
            
            if (!$orcamento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Orçamento não encontrado'
                ], 404);
            }
            
            $orcamento->status = $request->status;
            $orcamento->data_resposta = now()->toDateString();
            $orcamento->save();
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($orcamento->fresh()),
                'message' => 'Status alterado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao alterar status do orçamento: ' . $e->getMessage());
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
            $orcamento = Orcamento::where('tenant_id', $tenantId)->find($id);
            
            if (!$orcamento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Orçamento não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($orcamento)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar orçamento: ' . $e->getMessage());
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
            $orcamento = Orcamento::where('tenant_id', $tenantId)->find($id);
            
            if (!$orcamento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Orçamento não encontrado'
                ], 404);
            }
            
            $dados = [];
            if ($request->has('fornecedor')) $dados['fornecedor'] = $request->fornecedor;
            if ($request->has('descricao')) $dados['descricao'] = $request->descricao;
            if ($request->has('valorOrcado')) $dados['valor_orcado'] = $request->valorOrcado;
            if ($request->has('valorFinal')) $dados['valor_final'] = $request->valorFinal;
            if ($request->has('status')) $dados['status'] = $request->status;
            if ($request->has('dataResposta')) $dados['data_resposta'] = $request->dataResposta;
            if ($request->has('dataEntrada')) $dados['data_entrada'] = $request->dataEntrada;
            if ($request->has('dataSaida')) $dados['data_saida'] = $request->dataSaida;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            
            $orcamento->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($orcamento->fresh()),
                'message' => 'Orçamento atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar orçamento: ' . $e->getMessage());
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
            $orcamento = Orcamento::where('tenant_id', $tenantId)->find($id);
            
            if (!$orcamento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Orçamento não encontrado'
                ], 404);
            }
            
            $orcamento->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Orçamento excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir orçamento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}