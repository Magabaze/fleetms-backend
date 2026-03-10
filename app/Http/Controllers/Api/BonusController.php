<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use App\Models\Viagem;
use App\Models\RegraBonus;
use App\Models\Ordem;
use App\Models\Distancia;
use App\Models\Carga;
use App\Models\Carteira;
use App\Models\CarteiraMovimento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BonusController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function getTenantId(): string
    {
        return Auth::user()->tenant_id ?? 'default';
    }

    // ─────────────────────────────────────────────────────────────────
    //  HELPER: converter para camelCase
    // ─────────────────────────────────────────────────────────────────
    private function paraCamelCase($bonus): array
    {
        return [
            'id'         => $bonus->id,
            'viagemId'   => $bonus->viagem_id,
            'motorista'  => $bonus->motorista,
            'tripNumber' => $bonus->trip_number,
            'legNumber'  => $bonus->leg_number,
            'descricao'  => $bonus->descricao,
            'valor'      => (float) $bonus->valor,
            'status'     => $bonus->status,
            'data'       => $bonus->created_at?->toISOString(),
            'createdAt'  => $bonus->created_at?->toISOString(),
            'updatedAt'  => $bonus->updated_at?->toISOString(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  HELPER: actualizar carteira quando bónus é APROVADO
    //
    //  REGRA CORRECTA:
    //  - Ao aprovar bónus → saldo += valor, total_bonus += valor
    //  - total_divida NÃO é tocada aqui (é gerida pelo DescontoController)
    //  - NÃO recalcular saldo como total_bonus - total_divida
    // ─────────────────────────────────────────────────────────────────
    private function atualizarCarteiraComBonus(Bonus $bonus, string $tenantId): void
    {
        try {
            $carteira = Carteira::firstOrCreate(
                [
                    'motorista' => $bonus->motorista,
                    'tenant_id' => $tenantId,
                ],
                [
                    'saldo'        => 0,
                    'total_bonus'  => 0,
                    'total_divida' => 0,
                ]
            );

            $saldoAnterior = $carteira->saldo;

            // ✅ CORRECTO: apenas incrementa — NÃO recalcula nem toca total_divida
            $carteira->total_bonus      += $bonus->valor;
            $carteira->saldo            += $bonus->valor;
            $carteira->ultimo_movimento  = now();
            $carteira->save();

            CarteiraMovimento::create([
                'motorista'       => $bonus->motorista,
                'origem_id'       => $bonus->id,
                'origem_type'     => Bonus::class,
                'tipo'            => 'credito',
                'origem_tipo'     => 'bonus',
                'descricao'       => $bonus->descricao ?: 'Bónus aprovado',
                'valor'           => $bonus->valor,
                'saldo_anterior'  => $saldoAnterior,
                'saldo_posterior' => $carteira->saldo,
                'tenant_id'       => $tenantId,
            ]);

            Log::info('💰 Carteira actualizada com bónus aprovado', [
                'motorista'   => $bonus->motorista,
                'bonus_id'    => $bonus->id,
                'trip_number' => $bonus->trip_number,
                'valor'       => $bonus->valor,
                'saldo_antes' => $saldoAnterior,
                'novo_saldo'  => $carteira->saldo,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao actualizar carteira com bónus: ' . $e->getMessage());
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  LISTAR
    // ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();

            Log::info('📥 GET /api/bonus/bonus', [
                'user_id'   => Auth::id(),
                'tenant_id' => $tenantId,
                'query'     => $request->all(),
            ]);

            $query = Bonus::where('tenant_id', $tenantId);

            if ($request->filled('status') && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('motorista',   'like', "%{$search}%")
                      ->orWhere('descricao',   'like', "%{$search}%")
                      ->orWhere('trip_number', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $request->get('limit', 10);
            $page    = (int) $request->get('page', 1);

            $data = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success'    => true,
                'data'       => $data->map(fn($b) => $this->paraCamelCase($b)),
                'pagination' => [
                    'page'       => $data->currentPage(),
                    'limit'      => $perPage,
                    'total'      => $data->total(),
                    'totalPages' => $data->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar bónus: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  CRIAR BÓNUS MANUAL
    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();

            Log::info('📥 POST /api/bonus/bonus (manual)', [
                'user_id'   => Auth::id(),
                'tenant_id' => $tenantId,
                'dados'     => $request->all(),
            ]);

            $validator = Validator::make($request->all(), [
                'motorista'   => 'required|string',
                'trip_number' => 'required|string',
                'leg_number'  => 'nullable|string',
                'descricao'   => 'required|string',
                'valor'       => 'required|numeric|min:0.01',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Erro de validação',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // Tentar associar a uma viagem existente pelo trip_number
            $viagem = Viagem::where('trip_number', $request->trip_number)
                ->where('tenant_id', $tenantId)
                ->first();

            DB::beginTransaction();

            $bonus = Bonus::create([
                'viagem_id'   => $viagem?->id,
                'motorista'   => $request->motorista,
                'trip_number' => $request->trip_number,
                'leg_number'  => $request->leg_number ?? '',
                'descricao'   => $request->descricao,
                'valor'       => $request->valor,
                'status'      => 'pending',
                'tenant_id'   => $tenantId,
            ]);

            DB::commit();

            Log::info('✅ Bónus manual criado', [
                'id'          => $bonus->id,
                'motorista'   => $bonus->motorista,
                'trip_number' => $bonus->trip_number,
                'leg_number'  => $bonus->leg_number,
                'viagem_id'   => $bonus->viagem_id,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $this->paraCamelCase($bonus),
                'message' => 'Bónus manual criado com sucesso! (Pendente de aprovação)',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar bónus manual: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  SHOW
    // ─────────────────────────────────────────────────────────────────
    public function show($id)
    {
        try {
            $tenantId = $this->getTenantId();
            $bonus    = Bonus::where('tenant_id', $tenantId)->with('viagem')->find($id);

            if (!$bonus) {
                return response()->json(['success' => false, 'error' => 'Bónus não encontrado'], 404);
            }

            return response()->json(['success' => true, 'data' => $this->paraCamelCase($bonus)]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  APROVAR EM LOTE
    // ─────────────────────────────────────────────────────────────────
    public function aprovarLote(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            $ids      = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'Nenhum ID fornecido'], 400);
            }

            DB::beginTransaction();

            // Carregar ANTES de actualizar para ter os dados completos no objeto
            $bonusPendentes = Bonus::where('tenant_id', $tenantId)
                ->whereIn('id', $ids)
                ->where('status', 'pending')
                ->get();

            if ($bonusPendentes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum bónus pendente encontrado com os IDs fornecidos',
                ], 404);
            }

            // Actualizar status para approved
            $count = Bonus::where('tenant_id', $tenantId)
                ->whereIn('id', $bonusPendentes->pluck('id'))
                ->update(['status' => 'approved']);

            // Actualizar carteira para cada bónus aprovado
            foreach ($bonusPendentes as $b) {
                $this->atualizarCarteiraComBonus($b, $tenantId);
            }

            DB::commit();

            Log::info("✅ {$count} bónus aprovados em lote", [
                'ids'    => $bonusPendentes->pluck('id'),
                'tenant' => $tenantId,
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$count} bónus aprovados e adicionados à carteira dos motoristas.",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao aprovar bónus em lote: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  APROVAR INDIVIDUAL
    // ─────────────────────────────────────────────────────────────────
    public function aprovar($id)
    {
        try {
            $tenantId = $this->getTenantId();

            DB::beginTransaction();

            $bonus = Bonus::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->where('status', 'pending')
                ->first();

            if (!$bonus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bónus não encontrado ou já processado',
                ], 404);
            }

            $bonus->status = 'approved';
            $bonus->save();

            $this->atualizarCarteiraComBonus($bonus, $tenantId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bónus aprovado e adicionado à carteira do motorista.',
                'data'    => $this->paraCamelCase($bonus),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao aprovar bónus: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  REJEITAR EM LOTE
    // ─────────────────────────────────────────────────────────────────
    public function rejeitarLote(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            $ids      = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json(['success' => false, 'message' => 'Nenhum ID fornecido'], 400);
            }

            $count = Bonus::where('tenant_id', $tenantId)
                ->whereIn('id', $ids)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            return response()->json([
                'success' => true,
                'message' => "{$count} bónus rejeitados. Nenhum valor foi adicionado à carteira.",
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  DESTROY
    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        try {
            $tenantId = $this->getTenantId();
            $bonus    = Bonus::where('tenant_id', $tenantId)->find($id);

            if (!$bonus) {
                return response()->json(['success' => false, 'error' => 'Bónus não encontrado'], 404);
            }

            if ($bonus->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Apenas bónus pendentes podem ser excluídos',
                ], 400);
            }

            $bonus->delete();

            return response()->json(['success' => true, 'message' => 'Bónus excluído com sucesso!']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  CALCULAR BÓNUS AUTOMÁTICO
    // ─────────────────────────────────────────────────────────────────
    public function calcularBonus(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();

            $regras = RegraBonus::where('tenant_id', $tenantId)
                ->where('status', 'ativo')
                ->get();

            if ($regras->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma regra de bónus ativa encontrada. Crie regras no Master primeiro.',
                ], 404);
            }

            // Viagens elegíveis: CLOSED, sem bónus ainda, últimos 30 dias
            $viagens = Viagem::where('tenant_id', $tenantId)
                ->where('status', 'CLOSED')
                ->whereDoesntHave('bonus')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->get();

            if ($viagens->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Nenhuma viagem nova para calcular bónus',
                    'data'    => ['total_calculado' => 0, 'bonus_gerados' => []],
                ]);
            }

            // Ordens
            $ordensIds = $viagens->pluck('order_number')->filter()->toArray();
            $ordens    = Ordem::whereIn('order_numero', $ordensIds)
                ->where('tenant_id', $tenantId)
                ->get()
                ->keyBy('order_numero');

            // Distâncias
            $mapaDistancias = [];
            foreach (Distancia::where('tenant_id', $tenantId)->get() as $dist) {
                $mapaDistancias[$dist->origem . '|' . $dist->destino] = (float) $dist->distancia_total;
                $mapaDistancias[$dist->destino . '|' . $dist->origem] = (float) $dist->distancia_total;
            }

            [$mapaCargasPorDescricao] = $this->construirMapasCargas($tenantId);

            DB::beginTransaction();

            $bonusGerados   = [];
            $totalCalculado = 0;

            foreach ($viagens as $viagem) {
                $ordem       = $ordens[$viagem->order_number] ?? null;
                $transitType = $ordem?->tipo_transito;
                $loadStatus  = $viagem->is_empty_trip ? 'Vazia' : 'Cheia';
                $cargoNature = $this->resolverNaturezaCarga($ordem, $mapaCargasPorDescricao);
                $distancia   = $mapaDistancias[$viagem->from_station . '|' . $viagem->to_station] ?? 0;

                $regraAplicavel = null;
                foreach ($regras as $regra) {
                    if ($this->regraAplica($regra, $transitType, $loadStatus, $cargoNature)) {
                        $regraAplicavel = $regra;
                        break;
                    }
                }

                if (!$regraAplicavel) {
                    Log::info('⏭️ Sem regra aplicável', [
                        'viagem'         => $viagem->trip_number,
                        'transitType'    => $transitType,
                        'loadStatus'     => $loadStatus,
                        'cargoNature'    => $cargoNature,
                        'cargo_type_raw' => $viagem->cargo_type,
                    ]);
                    continue;
                }

                // Não criar duplicado
                if (Bonus::where('viagem_id', $viagem->id)->exists()) continue;

                $valorBonus = $regraAplicavel->valor_bonus;
                if ($regraAplicavel->calculation_base === 'per_100km' && $distancia > 0) {
                    $valorBonus = round(($distancia / 100) * $regraAplicavel->valor_bonus, 2);
                }

                $descricao = $regraAplicavel->nome;
                if ($transitType)   $descricao .= " - {$transitType}";
                if ($cargoNature)   $descricao .= " / {$cargoNature}";
                if ($distancia > 0) $descricao .= " / {$distancia}km";

                $bonus = Bonus::create([
                    'viagem_id'   => $viagem->id,
                    'trip_number' => $viagem->trip_number,    // ← sempre gravado
                    'leg_number'  => $viagem->trip_slno ?? '', // ← sempre gravado
                    'motorista'   => $viagem->driver,
                    'descricao'   => $descricao,
                    'valor'       => $valorBonus,
                    'status'      => 'pending',
                    'tenant_id'   => $tenantId,
                ]);

                $bonusGerados[] = $this->paraCamelCase($bonus);
                $totalCalculado++;

                Log::info('💰 Bónus calculado', [
                    'viagem'      => $viagem->trip_number,
                    'leg'         => $viagem->trip_slno,
                    'regra'       => $regraAplicavel->nome,
                    'cargoNature' => $cargoNature,
                    'valor'       => $valorBonus,
                    'motorista'   => $viagem->driver,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$totalCalculado} bónus calculados com sucesso! (Pendentes de aprovação)",
                'data'    => [
                    'total_calculado' => $totalCalculado,
                    'bonus_gerados'   => $bonusGerados,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao calcular bónus: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  DIAGNÓSTICO
    // ─────────────────────────────────────────────────────────────────
    public function diagnosticoSimples()
    {
        try {
            $tenantId = $this->getTenantId();

            $regras  = RegraBonus::where('tenant_id', $tenantId)->where('status', 'ativo')->get();
            $viagens = Viagem::where('tenant_id', $tenantId)
                ->where('status', 'CLOSED')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->get();

            $ordensIds = $viagens->pluck('order_number')->filter()->toArray();
            $ordens    = Ordem::whereIn('order_numero', $ordensIds)
                ->where('tenant_id', $tenantId)
                ->get()
                ->keyBy('order_numero');

            $mapaDistancias = [];
            foreach (Distancia::where('tenant_id', $tenantId)->get() as $dist) {
                $mapaDistancias[$dist->origem . '|' . $dist->destino] = (float) $dist->distancia_total;
                $mapaDistancias[$dist->destino . '|' . $dist->origem] = (float) $dist->distancia_total;
            }

            [$mapaCargasPorDescricao] = $this->construirMapasCargas($tenantId);

            $bonusExistentes = Bonus::whereIn('viagem_id', $viagens->pluck('id'))
                ->get()
                ->keyBy('viagem_id');

            $analise  = [];
            $comOrdem = 0;
            $semOrdem = 0;

            foreach ($viagens as $viagem) {
                $ordem       = $ordens[$viagem->order_number] ?? null;
                $transitType = $ordem?->tipo_transito;
                $loadStatus  = $viagem->is_empty_trip ? 'Vazia' : 'Cheia';
                $cargoNature = $this->resolverNaturezaCarga($ordem, $mapaCargasPorDescricao);
                $distancia   = $mapaDistancias[$viagem->from_station . '|' . $viagem->to_station] ?? 0;

                if ($ordem) $comOrdem++; else $semOrdem++;

                $regrasAplicaveis = [];
                foreach ($regras as $regra) {
                    if ($this->regraAplica($regra, $transitType, $loadStatus, $cargoNature)) {
                        $regrasAplicaveis[] = [
                            'id'    => $regra->id,
                            'nome'  => $regra->nome,
                            'valor' => $regra->valor_bonus,
                            'tipo'  => $regra->calculation_base,
                        ];
                    }
                }

                $analise[] = [
                    'viagem' => [
                        'id'            => $viagem->id,
                        'trip_number'   => $viagem->trip_number,
                        'motorista'     => $viagem->driver,
                        'origem'        => $viagem->from_station,
                        'destino'       => $viagem->to_station,
                        'is_empty_trip' => $viagem->is_empty_trip ? 'Vazia' : 'Cheia',
                        'cargo_type'    => $viagem->cargo_type,
                        'order_number'  => $viagem->order_number,
                        'status'        => $viagem->status,
                    ],
                    'ordem' => $ordem ? [
                        'id'            => $ordem->id,
                        'tipo_transito' => $ordem->tipo_transito,
                        'tipo_carga'    => $ordem->tipo_carga,
                        'commodity'     => $ordem->commodity ?? null,
                    ] : null,
                    'caracteristicas' => [
                        'transitType'    => $transitType ?? 'SEM ORDEM',
                        'loadStatus'     => $loadStatus,
                        'cargoNature'    => $cargoNature,
                        'distancia'      => $distancia,
                        'commodity_raw'  => $ordem?->commodity ?? 'SEM COMMODITY',
                        'cargo_type_raw' => $viagem->cargo_type ?? 'SEM CARGO_TYPE',
                    ],
                    'regras_aplicaveis' => $regrasAplicaveis,
                    'tem_bonus'         => isset($bonusExistentes[$viagem->id]),
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'resumo' => [
                        'total_regras_ativas'      => $regras->count(),
                        'total_viagens_closed_30d' => $viagens->count(),
                        'viagens_com_ordem'        => $comOrdem,
                        'viagens_sem_ordem'        => $semOrdem,
                        'viagens_com_bonus'        => $bonusExistentes->count(),
                        'viagens_sem_bonus'        => $viagens->count() - $bonusExistentes->count(),
                    ],
                    'regras'  => $regras->map(fn($r) => [
                        'id'               => $r->id,
                        'nome'             => $r->nome,
                        'transit_type'     => $r->transit_type  ?? 'qualquer',
                        'load_status'      => $r->load_status   ?? 'qualquer',
                        'cargo_nature'     => $r->cargo_nature  ?? 'qualquer',
                        'calculation_base' => $r->calculation_base,
                        'valor_bonus'      => $r->valor_bonus,
                    ]),
                    'viagens'      => $analise,
                    'debug_cargas' => Carga::where('tenant_id', $tenantId)
                        ->get(['id', 'descricao', 'tipo_carga'])
                        ->toArray(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro no diagnóstico: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro no diagnóstico: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────

    private const TIPOS_CARGA_VALIDOS = [
        'General Cargo', 'Hazardous', 'Especial',
        'Refrigerada', 'Líquida', 'Seca',
    ];

    private function resolverNaturezaCarga(?Ordem $ordem, array $mapaCargasPorDescricao): string
    {
        if (!$ordem) return 'NÃO INFORMADO';
        $commodity = strtolower(trim($ordem->commodity ?? ''));
        return ($commodity && isset($mapaCargasPorDescricao[$commodity]))
            ? $mapaCargasPorDescricao[$commodity]
            : 'NÃO INFORMADO';
    }

    private function construirMapasCargas(string $tenantId): array
    {
        $porDescricao = [];
        foreach (Carga::where('tenant_id', $tenantId)->get() as $carga) {
            if ($carga->descricao) {
                $porDescricao[strtolower(trim($carga->descricao))] = $carga->tipo_carga;
            }
        }
        return [$porDescricao];
    }

    private function regraAplica(RegraBonus $regra, ?string $transitType, string $loadStatus, string $cargoNature): bool
    {
        if ($regra->transit_type && $regra->transit_type !== 'qualquer' && $regra->transit_type !== $transitType) return false;
        if ($regra->load_status  && $regra->load_status  !== 'qualquer' && $regra->load_status  !== $loadStatus)  return false;
        if ($regra->cargo_nature && $regra->cargo_nature !== 'qualquer' && $regra->cargo_nature !== $cargoNature)  return false;
        return true;
    }
}