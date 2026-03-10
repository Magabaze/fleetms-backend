<?php
// app/Http/Controllers/Api/Combustivel/AbastecimentoInternoController.php
// ✅ VERSÃO COMPLETA CORRIGIDA - TODOS OS MÉTODOS

namespace App\Http\Controllers\Api\Combustivel;

use App\Http\Controllers\Controller;
use App\Models\Combustivel\AbastecimentoInterno;
use App\Models\Combustivel\Tanque;
use App\Models\Camiao;
use App\Models\Motorista;
use App\Models\Viagem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AbastecimentoInternoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Converter modelo para formato camelCase (frontend)
     */
    private function paraCamelCase($abastecimento)
    {
        return [
            'id'                => $abastecimento->id,
            'numero'            => $abastecimento->numero,
            'viagem_id'         => $abastecimento->viagem_id,
            'veiculo_id'        => $abastecimento->veiculo_id,
            'veiculo'           => $abastecimento->camiao ? [
                'id'        => $abastecimento->camiao->id,
                'matricula' => $abastecimento->camiao->matricula,
                'marca'     => $abastecimento->camiao->marca,
                'modelo'    => $abastecimento->camiao->modelo,
            ] : null,
            'motorista_id'      => $abastecimento->motorista_id,
            'motorista'         => $abastecimento->motorista ? [
                'id'                => $abastecimento->motorista->id,
                'nome_completo'     => $abastecimento->motorista->nome_completo,
                'numero_carta'      => $abastecimento->motorista->numero_carta,
            ] : null,
            'tipo_combustivel'  => $abastecimento->tipo_combustivel,
            'quantidade'        => (float) $abastecimento->quantidade,
            'odometro'          => (int) ($abastecimento->odometro ?? 0),
            'data'              => $abastecimento->data_abastecimento?->toDateString(),
            'hora'              => $abastecimento->hora_abastecimento,
            'responsavel'       => $abastecimento->responsavel,
            'status'            => $abastecimento->status,
            'observacoes'       => $abastecimento->observacoes,
            'tanque_id'         => $abastecimento->tanque_id,
            'tanque_nome'       => $abastecimento->tanque->nome ?? null,
            'tanque_codigo'     => $abastecimento->tanque->codigo ?? null,
            'motorista_nome'    => $abastecimento->motorista->nome_completo ?? null,
            'veiculo_matricula' => $abastecimento->camiao->matricula ?? null,
            'viagem_numero'     => $abastecimento->viagem->trip_number ?? null,
            'created_at'        => $abastecimento->created_at->toISOString(),
            'updated_at'        => $abastecimento->updated_at->toISOString(),
        ];
    }

    /**
     * Listar todos os abastecimentos
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        try {
            $query = AbastecimentoInterno::with(['camiao', 'motorista', 'viagem', 'tanque'])
                ->where('tenant_id', $tenantId);

            // Busca
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search, $tenantId) {
                    $q->where('numero', 'like', "%{$search}%")
                      ->orWhereHas('camiao', function ($q) use ($search, $tenantId) {
                          $q->where('tenant_id', $tenantId)
                            ->where('matricula', 'like', "%{$search}%");
                      })
                      ->orWhereHas('motorista', function ($q) use ($search, $tenantId) {
                          $q->where('tenant_id', $tenantId)
                            ->where('nome_completo', 'like', "%{$search}%");
                      })
                      ->orWhereHas('viagem', function ($q) use ($search, $tenantId) {
                          $q->where('tenant_id', $tenantId)
                            ->where('trip_number', 'like', "%{$search}%");
                      });
                });
            }

            // Filtro status
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }

            // Filtro data
            if ($request->has('data') && $request->data) {
                $query->whereDate('data_abastecimento', $request->data);
            }

            // Ordenação
            $query->orderBy('data_abastecimento', 'desc')
                  ->orderBy('hora_abastecimento', 'desc');

            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);

            $abastecimentos = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => $abastecimentos->map(fn($item) => $this->paraCamelCase($item))->toArray(),
                'pagination' => [
                    'page' => $abastecimentos->currentPage(),
                    'limit' => $perPage,
                    'total' => $abastecimentos->total(),
                    'totalPages' => $abastecimentos->lastPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar abastecimentos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar abastecimento
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        Log::info('📥 POST /api/combustivel/abastecimentos-internos', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'viagem_id'         => 'required|exists:viagens,id',
            'tipo_combustivel'  => 'required|string|max:100',
            'quantidade'        => 'required|numeric|min:0.01',
            'odometro'          => 'nullable|integer|min:0',
            'data'              => 'required|date',
            'hora'              => 'required|date_format:H:i',
            'responsavel'       => 'required|string|max:255',
            'observacoes'       => 'nullable|string',
            'status'            => 'sometimes|in:pendente,aprovado,realizado,cancelado',
            'veiculo_matricula' => 'required|string|max:50',
            'motorista_nome'    => 'required|string|max:255',
            'tanque_id'         => 'nullable|integer|exists:tanques,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Buscar viagem
            $viagem = Viagem::where('tenant_id', $tenantId)->find($request->viagem_id);
            if (!$viagem) {
                throw new \Exception('Viagem não encontrada');
            }

            // Processar veículo
            $camiao = Camiao::firstOrCreate(
                ['matricula' => $request->veiculo_matricula, 'tenant_id' => $tenantId],
                [
                    'marca'            => 'Desconhecida',
                    'modelo'           => 'Desconhecido',
                    'ano_fabricacao'   => date('Y'),
                    'capacidade_carga' => 'Não especificada',
                    'tipo_combustivel' => $request->tipo_combustivel,
                    'numero_eixos'     => 2,
                    'tara'             => 'Não especificada',
                    'cmr'              => 'Não especificada',
                    'seguro_validade'  => now()->addYear(),
                    'inspecao_validade'=> now()->addYear(),
                    'estado'           => 'Operacional',
                    'localizacao'      => 'Pátio',
                    'observacoes'      => 'Criado automaticamente pelo sistema de abastecimento',
                    'criado_por'       => $user->name ?? 'Sistema',
                    'tenant_id'        => $tenantId,
                ]
            );

            // Processar motorista
            $motorista = Motorista::firstOrCreate(
                ['nome_completo' => $request->motorista_nome, 'tenant_id' => $tenantId],
                [
                    'numero_carta'     => 'TEMP-' . time(),
                    'nacionalidade'    => 'Não especificada',
                    'telefone'         => 'Não especificado',
                    'tipo_licenca'     => 'C',
                    'validade_licenca' => now()->addYear(),
                    'status'           => 'Ativo',
                    'criado_por'       => $user->name ?? 'Sistema',
                    'tenant_id'        => $tenantId,
                ]
            );

            // Gerar número do abastecimento
            $ano = date('Y');
            $ultimo = AbastecimentoInterno::where('tenant_id', $tenantId)
                ->where('numero', 'like', "AI-{$ano}-%")
                ->orderBy('id', 'desc')
                ->first();

            $sequencia = 1;
            if ($ultimo && preg_match('/AI-' . $ano . '-(\d+)/', $ultimo->numero, $matches)) {
                $sequencia = intval($matches[1]) + 1;
            }
            $numero = "AI-{$ano}-" . str_pad($sequencia, 4, '0', STR_PAD_LEFT);

            // Processar tanque (se status for realizado)
            $tanque = null;
            if ($request->status === 'realizado') {
                if ($request->has('tanque_id') && $request->tanque_id) {
                    $tanque = Tanque::where('tenant_id', $tenantId)
                        ->where('id', $request->tanque_id)
                        ->first();

                    if (!$tanque) throw new \Exception('Tanque não encontrado');
                    if (strtolower($tanque->tipo_combustivel) !== strtolower($request->tipo_combustivel)) {
                        throw new \Exception("Tanque incompatível: {$tanque->tipo_combustivel}");
                    }
                    if ($tanque->nivel_atual < $request->quantidade) {
                        throw new \Exception("Tanque sem saldo. Disponível: {$tanque->nivel_atual}L");
                    }
                } else {
                    $tanque = Tanque::where('tenant_id', $tenantId)
                        ->where('status', 'ativo')
                        ->where('tipo_combustivel', 'like', $request->tipo_combustivel)
                        ->where('nivel_atual', '>=', $request->quantidade)
                        ->orderBy('nivel_atual', 'asc')
                        ->first();

                    if (!$tanque) {
                        throw new \Exception("Nenhum tanque disponível para {$request->tipo_combustivel} com {$request->quantidade}L");
                    }
                }

                // Remover combustível do tanque
                $observacao = now()->format('d/m/Y H:i') . 
                    " - Saída de " . number_format($request->quantidade, 2, ',', '.') . 
                    "L para abastecimento {$numero} (Veículo: {$request->veiculo_matricula})";

                $tanque->update([
                    'nivel_atual' => $tanque->nivel_atual - $request->quantidade,
                    'ultima_atualizacao' => now(),
                    'observacoes' => $tanque->observacoes ? $tanque->observacoes . "\n" . $observacao : $observacao
                ]);
            }

            // Criar abastecimento
            $abastecimento = AbastecimentoInterno::create([
                'numero'             => $numero,
                'viagem_id'          => $request->viagem_id,
                'veiculo_id'         => $camiao->id,
                'motorista_id'       => $motorista->id,
                'tipo_combustivel'   => $request->tipo_combustivel,
                'quantidade'         => $request->quantidade,
                'unidade_medida'     => 'litros',
                'odometro'           => $request->odometro ?? 0,
                'data_abastecimento' => $request->data,
                'hora_abastecimento' => $request->hora,
                'responsavel'        => $request->responsavel,
                'status'             => $request->status ?? 'pendente',
                'observacoes'        => $request->observacoes,
                'tanque_id'          => $tanque?->id,
                'tenant_id'          => $tenantId,
            ]);

            DB::commit();

            $abastecimento->load(['camiao', 'motorista', 'viagem', 'tanque']);

            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento),
                'message' => $tanque 
                    ? '✅ Abastecimento realizado e combustível retirado do tanque!'
                    : '✅ Abastecimento registrado com sucesso!'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar abastecimento por ID
     */
    public function show($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        try {
            $abastecimento = AbastecimentoInterno::with(['camiao', 'motorista', 'viagem', 'tanque'])
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

    /**
     * Atualizar abastecimento
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        DB::beginTransaction();

        try {
            $abastecimento = AbastecimentoInterno::where('tenant_id', $tenantId)->find($id);

            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }

            if (!in_array($abastecimento->status, ['pendente', 'aprovado'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos pendentes ou aprovados podem ser editados'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'quantidade'  => 'sometimes|numeric|min:0.01',
                'odometro'    => 'sometimes|integer|min:0',
                'observacoes' => 'nullable|string',
                'data'        => 'sometimes|date',
                'hora'        => 'sometimes|date_format:H:i',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $dadosAtualizacao = [];
            if ($request->has('quantidade')) $dadosAtualizacao['quantidade'] = $request->quantidade;
            if ($request->has('odometro')) $dadosAtualizacao['odometro'] = $request->odometro;
            if ($request->has('observacoes')) $dadosAtualizacao['observacoes'] = $request->observacoes;
            if ($request->has('data')) $dadosAtualizacao['data_abastecimento'] = $request->data;
            if ($request->has('hora')) $dadosAtualizacao['hora_abastecimento'] = $request->hora;

            $abastecimento->update($dadosAtualizacao);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento atualizado com sucesso!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao atualizar abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir abastecimento + devolver ao tanque
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        DB::beginTransaction();

        try {
            $abastecimento = AbastecimentoInterno::with(['tanque'])
                ->where('tenant_id', $tenantId)
                ->find($id);

            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }

            // Devolver ao tanque se estava realizado
            if ($abastecimento->status === 'realizado' && $abastecimento->tanque_id) {
                $tanque = Tanque::where('tenant_id', $tenantId)
                    ->where('id', $abastecimento->tanque_id)
                    ->first();

                if ($tanque) {
                    $observacao = now()->format('d/m/Y H:i') . 
                        " - Devolução de " . number_format($abastecimento->quantidade, 2, ',', '.') . 
                        "L referente a exclusão do abastecimento {$abastecimento->numero}";

                    $tanque->update([
                        'nivel_atual' => $tanque->nivel_atual + $abastecimento->quantidade,
                        'ultima_atualizacao' => now(),
                        'observacoes' => $tanque->observacoes ? $tanque->observacoes . "\n" . $observacao : $observacao
                    ]);
                }
            }

            $abastecimento->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Abastecimento excluído com sucesso!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao excluir abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ APROVAR ABASTECIMENTO - ENDPOINT CORRIGIDO
     */
    public function aprovar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        Log::info('📝 Aprovar abastecimento chamado', [
            'id' => $id,
            'user_id' => $user->id,
            'tenant_id' => $tenantId
        ]);

        DB::beginTransaction();

        try {
            $abastecimento = AbastecimentoInterno::where('tenant_id', $tenantId)->find($id);

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

            $aprovador = $user->name ?? $user->email ?? 'Sistema';
            
            $observacoes = $abastecimento->observacoes ? $abastecimento->observacoes . "\n\n" : '';
            $observacoes .= "APROVADO em: " . now()->format('d/m/Y H:i') . "\n";
            $observacoes .= "Aprovado por: " . $aprovador;

            $abastecimento->update([
                'status' => 'aprovado',
                'observacoes' => $observacoes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento aprovado com sucesso!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao aprovar abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ CANCELAR ABASTECIMENTO - ENDPOINT CORRIGIDO
     */
    public function cancelar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        Log::info('📝 Cancelar abastecimento chamado', [
            'id' => $id,
            'user_id' => $user->id,
            'tenant_id' => $tenantId
        ]);

        DB::beginTransaction();

        try {
            $abastecimento = AbastecimentoInterno::with(['tanque'])
                ->where('tenant_id', $tenantId)
                ->find($id);

            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }

            if (!in_array($abastecimento->status, ['pendente', 'aprovado', 'realizado'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não pode ser cancelado'
                ], 400);
            }

            // Devolver ao tanque se estava realizado
            if ($abastecimento->status === 'realizado' && $abastecimento->tanque_id) {
                $tanque = Tanque::where('tenant_id', $tenantId)
                    ->where('id', $abastecimento->tanque_id)
                    ->first();

                if ($tanque) {
                    $observacao = now()->format('d/m/Y H:i') . 
                        " - Devolução de " . number_format($abastecimento->quantidade, 2, ',', '.') . 
                        "L referente a cancelamento do abastecimento {$abastecimento->numero}";

                    $tanque->update([
                        'nivel_atual' => $tanque->nivel_atual + $abastecimento->quantidade,
                        'ultima_atualizacao' => now(),
                        'observacoes' => $tanque->observacoes ? $tanque->observacoes . "\n" . $observacao : $observacao
                    ]);
                }
            }

            $observacoes = $abastecimento->observacoes ? $abastecimento->observacoes . "\n\n" : '';
            $observacoes .= "CANCELADO em: " . now()->format('d/m/Y H:i') .
                           "\nMotivo: " . ($request->motivo ?? 'Não especificado');

            $abastecimento->update([
                'status' => 'cancelado',
                'observacoes' => $observacoes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento->fresh()),
                'message' => 'Abastecimento cancelado com sucesso!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao cancelar abastecimento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ MARCAR COMO REALIZADO - ENDPOINT CORRIGIDO
     */
    public function marcarRealizado(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        Log::info('📝 Marcar realizado chamado', [
            'id' => $id,
            'user_id' => $user->id,
            'tenant_id' => $tenantId
        ]);

        DB::beginTransaction();

        try {
            $abastecimento = AbastecimentoInterno::where('tenant_id', $tenantId)->find($id);

            if (!$abastecimento) {
                return response()->json([
                    'success' => false,
                    'error' => 'Abastecimento não encontrado'
                ], 404);
            }

            if ($abastecimento->status !== 'aprovado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas abastecimentos aprovados podem ser marcados como realizados'
                ], 400);
            }

            // Buscar tanque automaticamente
            $tanque = Tanque::where('tenant_id', $tenantId)
                ->where('status', 'ativo')
                ->where('tipo_combustivel', 'like', $abastecimento->tipo_combustivel)
                ->where('nivel_atual', '>=', $abastecimento->quantidade)
                ->orderBy('nivel_atual', 'asc')
                ->first();

            if (!$tanque) {
                throw new \Exception("Nenhum tanque disponível para {$abastecimento->tipo_combustivel} com {$abastecimento->quantidade}L");
            }

            // Remover combustível do tanque
            $nivelAnterior = $tanque->nivel_atual;
            $nivelAtual = $tanque->nivel_atual - $abastecimento->quantidade;

            $observacao = now()->format('d/m/Y H:i') . 
                " - Saída de " . number_format($abastecimento->quantidade, 2, ',', '.') . 
                "L para abastecimento {$abastecimento->numero} (REALIZADO)";

            $tanque->update([
                'nivel_atual' => $nivelAtual,
                'ultima_atualizacao' => now(),
                'observacoes' => $tanque->observacoes ? $tanque->observacoes . "\n" . $observacao : $observacao
            ]);

            $realizadoPor = $request->realizado_por ?? $user->name ?? $user->email ?? 'Sistema';
            
            $observacoes = $abastecimento->observacoes ? $abastecimento->observacoes . "\n\n" : '';
            $observacoes .= "REALIZADO em: " . now()->format('d/m/Y H:i') . "\n";
            $observacoes .= "Realizado por: " . $realizadoPor . "\n";
            $observacoes .= "Tanque: {$tanque->nome} ({$tanque->codigo}) - Nível anterior: {$nivelAnterior}L, Nível atual: {$nivelAtual}L";

            $abastecimento->update([
                'status' => 'realizado',
                'tanque_id' => $tanque->id,
                'observacoes' => $observacoes
            ]);

            DB::commit();

            $abastecimento->load('tanque');

            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($abastecimento),
                'message' => '✅ Abastecimento realizado e combustível retirado do tanque!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao marcar abastecimento como realizado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de abastecimento
     */
    public function estatisticas(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';

        try {
            $query = AbastecimentoInterno::where('tenant_id', $tenantId)
                ->where('status', 'realizado');

            if ($request->has('data_inicio')) {
                $query->where('data_abastecimento', '>=', $request->data_inicio);
            }
            if ($request->has('data_fim')) {
                $query->where('data_abastecimento', '<=', $request->data_fim);
            }

            $totalGeral = $query->sum('quantidade');
            $totalAbastecimentos = $query->count();

            $porTipo = $query->clone()
                ->select('tipo_combustivel')
                ->selectRaw('SUM(quantidade) as total_litros')
                ->selectRaw('COUNT(*) as total_abastecimentos')
                ->groupBy('tipo_combustivel')
                ->get();

            $topVeiculos = $query->clone()
                ->with('camiao')
                ->select('veiculo_id')
                ->selectRaw('SUM(quantidade) as total_consumo')
                ->groupBy('veiculo_id')
                ->orderByDesc('total_consumo')
                ->limit(5)
                ->get()
                ->map(fn($item) => [
                    'veiculo' => $item->camiao->matricula ?? 'Desconhecido',
                    'total_consumo' => (float) $item->total_consumo,
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_geral_litros' => (float) $totalGeral,
                    'total_abastecimentos' => $totalAbastecimentos,
                    'por_tipo_combustivel' => $porTipo,
                    'top_veiculos_consumo' => $topVeiculos,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar estatísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}