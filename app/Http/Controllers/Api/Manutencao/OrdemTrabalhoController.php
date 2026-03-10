<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\OrdemTrabalho;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrdemTrabalhoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($ordem)
    {
        return [
            'id' => $ordem->id,
            'codigo' => $ordem->codigo,
            'veiculo' => $ordem->veiculo,
            'matricula' => $ordem->matricula,
            'tipo' => $ordem->tipo,
            'descricao' => $ordem->descricao,
            'tecnico' => $ordem->tecnico,
            'status' => $ordem->status,
            'prioridade' => $ordem->prioridade,
            'dataCriacao' => $ordem->data_criacao,
            'dataPrevista' => $ordem->data_prevista,
            'dataInicio' => $ordem->data_inicio,
            'dataFim' => $ordem->data_fim,
            'custo' => $ordem->custo ? (float) $ordem->custo : null,
            'fornecedorId' => $ordem->fornecedor_id,
            'fornecedorNome' => $ordem->fornecedor_nome,
            'orcamentoId' => $ordem->orcamento_id,
            'localSocorro' => $ordem->local_socorro,
            'kmSocorro' => $ordem->km_socorro,
            'observacoes' => $ordem->observacoes,
            'criadoPor' => $ordem->criado_por,
            'tenantId' => $ordem->tenant_id,
            'createdAt' => $ordem->created_at?->toISOString(),
            'updatedAt' => $ordem->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/manutencao/ordens-trabalho', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = OrdemTrabalho::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('veiculo', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%")
                      ->orWhere('tecnico', 'like', "%{$search}%")
                      ->orWhere('fornecedor_nome', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('prioridade') && $request->prioridade && $request->prioridade !== 'todos') {
                $query->where('prioridade', $request->prioridade);
            }
            
            if ($request->has('tipo') && $request->tipo && $request->tipo !== 'todos') {
                $query->where('tipo', $request->tipo);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $ordens = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $ordensCamelCase = $ordens->map(function ($ordem) {
                return $this->paraCamelCase($ordem);
            });
            
            Log::info('✅ Ordens de trabalho listadas', [
                'total' => $ordens->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $ordensCamelCase->toArray(),
                'pagination' => [
                    'page' => $ordens->currentPage(),
                    'limit' => $perPage,
                    'total' => $ordens->total(),
                    'totalPages' => $ordens->lastPage(),
                    'hasNextPage' => $ordens->hasMorePages(),
                    'hasPrevPage' => $ordens->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar ordens de trabalho: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/manutencao/ordens-trabalho', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'veiculo' => 'required|string|max:255',
            'matricula' => 'required|string|max:20',
            'tipo' => 'required|in:preventiva,corretiva,inspecao,externa,socorro',
            'descricao' => 'required|string',
            'tecnico' => 'required|string|max:255',
            'status' => 'required|in:pendente,em_progresso,concluida,cancelada',
            'prioridade' => 'required|in:baixa,media,alta,urgente',
            'dataPrevista' => 'required|date',
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
                'tipo' => $request->tipo,
                'descricao' => $request->descricao,
                'tecnico' => $request->tecnico,
                'status' => $request->status,
                'prioridade' => $request->prioridade,
                'data_criacao' => now()->toDateString(),
                'data_prevista' => $request->dataPrevista,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            // Campos opcionais para manutenção externa
            if ($request->has('fornecedorId')) {
                $dados['fornecedor_id'] = $request->fornecedorId;
            }
            if ($request->has('fornecedorNome')) {
                $dados['fornecedor_nome'] = $request->fornecedorNome;
            }
            if ($request->has('orcamentoId')) {
                $dados['orcamento_id'] = $request->orcamentoId;
            }
            if ($request->has('custo')) {
                $dados['custo'] = $request->custo;
            }
            
            // Campos opcionais para socorro
            if ($request->has('localSocorro')) {
                $dados['local_socorro'] = $request->localSocorro;
            }
            if ($request->has('kmSocorro')) {
                $dados['km_socorro'] = $request->kmSocorro;
            }
            
            Log::info('💾 Salvando ordem de trabalho', $dados);
            
            $ordem = OrdemTrabalho::create($dados);
            
            Log::info('✅ Ordem de trabalho criada', [
                'id' => $ordem->id,
                'codigo' => $ordem->codigo,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem),
                'message' => 'Ordem de trabalho criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar ordem de trabalho: ' . $e->getMessage());
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
            $ordem = OrdemTrabalho::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem de trabalho não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar ordem de trabalho: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/manutencao/ordens-trabalho/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $ordem = OrdemTrabalho::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem de trabalho não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'veiculo' => 'sometimes|required|string|max:255',
                'tipo' => 'sometimes|required|in:preventiva,corretiva,inspecao,externa,socorro',
                'status' => 'sometimes|required|in:pendente,em_progresso,concluida,cancelada',
                'prioridade' => 'sometimes|required|in:baixa,media,alta,urgente',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $dados = [];
            if ($request->has('veiculo')) $dados['veiculo'] = $request->veiculo;
            if ($request->has('matricula')) $dados['matricula'] = $request->matricula;
            if ($request->has('tipo')) $dados['tipo'] = $request->tipo;
            if ($request->has('descricao')) $dados['descricao'] = $request->descricao;
            if ($request->has('tecnico')) $dados['tecnico'] = $request->tecnico;
            if ($request->has('status')) $dados['status'] = $request->status;
            if ($request->has('prioridade')) $dados['prioridade'] = $request->prioridade;
            if ($request->has('dataPrevista')) $dados['data_prevista'] = $request->dataPrevista;
            if ($request->has('dataInicio')) $dados['data_inicio'] = $request->dataInicio;
            if ($request->has('dataFim')) $dados['data_fim'] = $request->dataFim;
            if ($request->has('custo')) $dados['custo'] = $request->custo;
            if ($request->has('fornecedorId')) $dados['fornecedor_id'] = $request->fornecedorId;
            if ($request->has('fornecedorNome')) $dados['fornecedor_nome'] = $request->fornecedorNome;
            if ($request->has('orcamentoId')) $dados['orcamento_id'] = $request->orcamentoId;
            if ($request->has('localSocorro')) $dados['local_socorro'] = $request->localSocorro;
            if ($request->has('kmSocorro')) $dados['km_socorro'] = $request->kmSocorro;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            
            $ordem->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem->fresh()),
                'message' => 'Ordem de trabalho atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar ordem de trabalho: ' . $e->getMessage());
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
            $ordem = OrdemTrabalho::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem de trabalho não encontrada'
                ], 404);
            }
            
            $ordem->delete();
            
            Log::info('✅ Ordem de trabalho excluída', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ordem de trabalho excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir ordem de trabalho: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar ordens por tipo específico
     */
    public function porTipo(Request $request, $tipo)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $ordens = OrdemTrabalho::where('tenant_id', $tenantId)
                ->where('tipo', $tipo)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('limit', 50));
            
            $ordensCamelCase = $ordens->map(function ($ordem) {
                return $this->paraCamelCase($ordem);
            });
            
            return response()->json([
                'success' => true,
                'data' => $ordensCamelCase->toArray(),
                'pagination' => [
                    'page' => $ordens->currentPage(),
                    'limit' => $ordens->perPage(),
                    'total' => $ordens->total(),
                    'totalPages' => $ordens->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar ordens por tipo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}