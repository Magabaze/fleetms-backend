<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\Socorro;
use App\Models\Manutencao\OrdemTrabalho;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SocorroController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($socorro)
    {
        return [
            'id' => $socorro->id,
            'codigo' => $socorro->codigo,
            'ordemId' => $socorro->ordem_id,
            'ordemCodigo' => $socorro->ordem_codigo,
            'veiculo' => $socorro->veiculo,
            'matricula' => $socorro->matricula,
            'motorista' => $socorro->motorista,
            'tipo' => $socorro->tipo,
            'descricao' => $socorro->descricao,
            'status' => $socorro->status,
            'prioridade' => $socorro->prioridade,
            'dataOcorrencia' => $socorro->data_ocorrencia,
            'local' => $socorro->local,
            'km' => (int) $socorro->km,
            'tecnicoEnviado' => $socorro->tecnico_enviado,
            'tempoResposta' => (int) $socorro->tempo_resposta,
            'tempoReparo' => (int) $socorro->tempo_reparo,
            'custo' => $socorro->custo ? (float) $socorro->custo : null,
            'observacoes' => $socorro->observacoes,
            'criadoPor' => $socorro->criado_por,
            'tenantId' => $socorro->tenant_id,
            'createdAt' => $socorro->created_at?->toISOString(),
            'updatedAt' => $socorro->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/manutencao/socorro', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Socorro::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('veiculo', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('motorista', 'like', "%{$search}%")
                      ->orWhere('local', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $socorros = $query->orderBy('data_ocorrencia', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $socorrosCamelCase = $socorros->map(function ($socorro) {
                return $this->paraCamelCase($socorro);
            });
            
            Log::info('✅ Socorros listados', [
                'total' => $socorros->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $socorrosCamelCase->toArray(),
                'pagination' => [
                    'page' => $socorros->currentPage(),
                    'limit' => $perPage,
                    'total' => $socorros->total(),
                    'totalPages' => $socorros->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar socorros: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/manutencao/socorro', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'veiculo' => 'required|string|max:255',
            'matricula' => 'required|string|max:20',
            'motorista' => 'required|string',
            'tipo' => 'required|in:avaria_mecanica,acidente,pneu,combustivel,eletrica,outro',
            'descricao' => 'required|string',
            'prioridade' => 'required|in:normal,alta,urgente',
            'dataOcorrencia' => 'required|date',
            'local' => 'required|string',
            'km' => 'required|integer|min:0',
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
                'motorista' => $request->motorista,
                'tipo' => $request->tipo,
                'descricao' => $request->descricao,
                'status' => 'aberto',
                'prioridade' => $request->prioridade,
                'data_ocorrencia' => $request->dataOcorrencia,
                'local' => $request->local,
                'km' => $request->km,
                'tecnico_enviado' => $request->tecnicoEnviado ?? '',
                'tempo_resposta' => 0,
                'tempo_reparo' => 0,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando socorro', $dados);
            
            $socorro = Socorro::create($dados);
            
            Log::info('✅ Socorro criado', [
                'id' => $socorro->id,
                'codigo' => $socorro->codigo,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($socorro),
                'message' => 'Socorro registado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar socorro: ' . $e->getMessage());
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
            $socorro = Socorro::where('tenant_id', $tenantId)->find($id);
            
            if (!$socorro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Socorro não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($socorro)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar socorro: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/manutencao/socorro/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $socorro = Socorro::where('tenant_id', $tenantId)->find($id);
            
            if (!$socorro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Socorro não encontrado'
                ], 404);
            }
            
            $dados = [];
            if ($request->has('veiculo')) $dados['veiculo'] = $request->veiculo;
            if ($request->has('matricula')) $dados['matricula'] = $request->matricula;
            if ($request->has('motorista')) $dados['motorista'] = $request->motorista;
            if ($request->has('tipo')) $dados['tipo'] = $request->tipo;
            if ($request->has('descricao')) $dados['descricao'] = $request->descricao;
            if ($request->has('status')) $dados['status'] = $request->status;
            if ($request->has('prioridade')) $dados['prioridade'] = $request->prioridade;
            if ($request->has('dataOcorrencia')) $dados['data_ocorrencia'] = $request->dataOcorrencia;
            if ($request->has('local')) $dados['local'] = $request->local;
            if ($request->has('km')) $dados['km'] = $request->km;
            if ($request->has('tecnicoEnviado')) $dados['tecnico_enviado'] = $request->tecnicoEnviado;
            if ($request->has('tempoResposta')) $dados['tempo_resposta'] = $request->tempoResposta;
            if ($request->has('tempoReparo')) $dados['tempo_reparo'] = $request->tempoReparo;
            if ($request->has('custo')) $dados['custo'] = $request->custo;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            if ($request->has('ordemId')) {
                $dados['ordem_id'] = $request->ordemId;
                // Buscar código da ordem
                $ordem = OrdemTrabalho::find($request->ordemId);
                if ($ordem) {
                    $dados['ordem_codigo'] = $ordem->codigo;
                }
            }
            
            $socorro->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($socorro->fresh()),
                'message' => 'Socorro atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar socorro: ' . $e->getMessage());
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
            $socorro = Socorro::where('tenant_id', $tenantId)->find($id);
            
            if (!$socorro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Socorro não encontrado'
                ], 404);
            }
            
            $socorro->delete();
            
            Log::info('✅ Socorro excluído', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Socorro excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir socorro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function iniciarAtendimento(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 POST /api/manutencao/socorro/' . $id . '/iniciar-atendimento', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'tecnicoEnviado' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $socorro = Socorro::where('tenant_id', $tenantId)->find($id);
            
            if (!$socorro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Socorro não encontrado'
                ], 404);
            }
            
            // Calcular tempo de resposta em minutos
            $dataOcorrencia = new \DateTime($socorro->data_ocorrencia);
            $agora = new \DateTime();
            $tempoResposta = $agora->diff($dataOcorrencia);
            $minutos = ($tempoResposta->days * 24 * 60) + ($tempoResposta->h * 60) + $tempoResposta->i;
            
            $socorro->update([
                'status' => 'em_atendimento',
                'tecnico_enviado' => $request->tecnicoEnviado,
                'tempo_resposta' => $minutos,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($socorro->fresh()),
                'message' => 'Atendimento iniciado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao iniciar atendimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function concluirAtendimento(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 POST /api/manutencao/socorro/' . $id . '/concluir-atendimento', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'custo' => 'required|numeric|min:0',
            'tempoReparo' => 'required|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $socorro = Socorro::where('tenant_id', $tenantId)->find($id);
            
            if (!$socorro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Socorro não encontrado'
                ], 404);
            }
            
            $socorro->update([
                'status' => 'concluido',
                'custo' => $request->custo,
                'tempo_reparo' => $request->tempoReparo,
            ]);
            
            // Atualizar a ordem de trabalho associada se existir
            if ($socorro->ordem_id) {
                OrdemTrabalho::where('id', $socorro->ordem_id)->update([
                    'status' => 'concluida',
                    'data_fim' => now()->toDateString(),
                    'custo' => $request->custo
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($socorro->fresh()),
                'message' => 'Atendimento concluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao concluir atendimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}