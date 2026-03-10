<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\ManutencaoExterna;
use App\Models\Manutencao\OrdemTrabalho;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ManutencaoExternaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($externa)
    {
        return [
            'id' => $externa->id,
            'codigo' => $externa->codigo,
            'ordemId' => $externa->ordem_id,
            'ordemCodigo' => $externa->ordem_codigo,
            'veiculo' => $externa->veiculo,
            'matricula' => $externa->matricula,
            'fornecedorId' => $externa->fornecedor_id,
            'fornecedorNome' => $externa->fornecedor_nome,
            'orcamentoId' => $externa->orcamento_id,
            'orcamentoCodigo' => $externa->orcamento_codigo,
            'descricao' => $externa->descricao,
            'status' => $externa->status,
            'prioridade' => $externa->prioridade,
            'dataSaida' => $externa->data_saida,
            'dataPrevistaRetorno' => $externa->data_prevista_retorno,
            'dataRetorno' => $externa->data_retorno,
            'valorOrcado' => (float) $externa->valor_orcado,
            'valorFinal' => $externa->valor_final ? (float) $externa->valor_final : null,
            'observacoes' => $externa->observacoes,
            'criadoPor' => $externa->criado_por,
            'tenantId' => $externa->tenant_id,
            'createdAt' => $externa->created_at?->toISOString(),
            'updatedAt' => $externa->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/manutencao/externa', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = ManutencaoExterna::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('veiculo', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('fornecedor_nome', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $externas = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $externasCamelCase = $externas->map(function ($externa) {
                return $this->paraCamelCase($externa);
            });
            
            Log::info('✅ Manutenções externas listadas', [
                'total' => $externas->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $externasCamelCase->toArray(),
                'pagination' => [
                    'page' => $externas->currentPage(),
                    'limit' => $perPage,
                    'total' => $externas->total(),
                    'totalPages' => $externas->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar manutenções externas: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/manutencao/externa', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'veiculo' => 'required|string|max:255',
            'matricula' => 'required|string|max:20',
            'fornecedorId' => 'required|integer',
            'fornecedorNome' => 'required|string',
            'orcamentoId' => 'required|integer',
            'descricao' => 'required|string',
            'prioridade' => 'required|in:baixa,media,alta,urgente',
            'dataSaida' => 'required|date',
            'dataPrevistaRetorno' => 'required|date',
            'valorOrcado' => 'required|numeric|min:0',
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
                'veiculo' => $request->veiculo,
                'matricula' => $request->matricula,
                'fornecedor_id' => $request->fornecedorId,
                'fornecedor_nome' => $request->fornecedorNome,
                'orcamento_id' => $request->orcamentoId,
                'descricao' => $request->descricao,
                'status' => 'pendente',
                'prioridade' => $request->prioridade,
                'data_saida' => $request->dataSaida,
                'data_prevista_retorno' => $request->dataPrevistaRetorno,
                'valor_orcado' => $request->valorOrcado,
                'observacoes' => $request->observacoes ?? '',
                'ordem_id' => $request->ordemId ?? null,
                'ordem_codigo' => $request->ordemCodigo ?? null,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            // Buscar código do orçamento se não veio
            if (empty($dados['orcamento_codigo']) && $request->orcamentoId) {
                $orcamento = \App\Models\Manutencao\Orcamento::find($request->orcamentoId);
                if ($orcamento) {
                    $dados['orcamento_codigo'] = $orcamento->codigo;
                }
            }
            
            Log::info('💾 Salvando manutenção externa', $dados);
            
            $externa = ManutencaoExterna::create($dados);
            
            Log::info('✅ Manutenção externa criada', [
                'id' => $externa->id,
                'codigo' => $externa->codigo,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($externa),
                'message' => 'Manutenção externa registada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar manutenção externa: ' . $e->getMessage());
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
            $externa = ManutencaoExterna::where('tenant_id', $tenantId)->find($id);
            
            if (!$externa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Manutenção externa não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($externa)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar manutenção externa: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/manutencao/externa/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $externa = ManutencaoExterna::where('tenant_id', $tenantId)->find($id);
            
            if (!$externa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Manutenção externa não encontrada'
                ], 404);
            }
            
            $dados = [];
            if ($request->has('veiculo')) $dados['veiculo'] = $request->veiculo;
            if ($request->has('matricula')) $dados['matricula'] = $request->matricula;
            if ($request->has('descricao')) $dados['descricao'] = $request->descricao;
            if ($request->has('status')) $dados['status'] = $request->status;
            if ($request->has('prioridade')) $dados['prioridade'] = $request->prioridade;
            if ($request->has('dataSaida')) $dados['data_saida'] = $request->dataSaida;
            if ($request->has('dataPrevistaRetorno')) $dados['data_prevista_retorno'] = $request->dataPrevistaRetorno;
            if ($request->has('dataRetorno')) $dados['data_retorno'] = $request->dataRetorno;
            if ($request->has('valorFinal')) $dados['valor_final'] = $request->valorFinal;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            
            $externa->update($dados);
            
            // Se status mudou para concluída, atualizar a ordem de trabalho também
            if ($request->has('status') && $request->status === 'concluida' && $externa->ordem_id) {
                OrdemTrabalho::where('id', $externa->ordem_id)->update([
                    'status' => 'concluida',
                    'data_fim' => $request->dataRetorno ?? now()->toDateString(),
                    'custo' => $request->valorFinal ?? $externa->valor_orcado
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($externa->fresh()),
                'message' => 'Manutenção externa atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar manutenção externa: ' . $e->getMessage());
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
            $externa = ManutencaoExterna::where('tenant_id', $tenantId)->find($id);
            
            if (!$externa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Manutenção externa não encontrada'
                ], 404);
            }
            
            $externa->delete();
            
            Log::info('✅ Manutenção externa excluída', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Manutenção externa excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir manutenção externa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function registrarRetorno(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 POST /api/manutencao/externa/' . $id . '/registrar-retorno', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'dataRetorno' => 'required|date',
            'valorFinal' => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $externa = ManutencaoExterna::where('tenant_id', $tenantId)->find($id);
            
            if (!$externa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Manutenção externa não encontrada'
                ], 404);
            }
            
            $externa->update([
                'status' => 'concluida',
                'data_retorno' => $request->dataRetorno,
                'valor_final' => $request->valorFinal,
            ]);
            
            // Atualizar a ordem de trabalho associada
            if ($externa->ordem_id) {
                OrdemTrabalho::where('id', $externa->ordem_id)->update([
                    'status' => 'concluida',
                    'data_fim' => $request->dataRetorno,
                    'custo' => $request->valorFinal
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($externa->fresh()),
                'message' => 'Retorno registado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao registrar retorno: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}