<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carteira;
use App\Models\CarteiraMovimento;
use App\Models\CarteiraPagamento;
use App\Models\Motorista;
use App\Models\Bonus;
use App\Models\Desconto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CarteiraController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function getTenantId(): string
    {
        return Auth::user()->tenant_id ?? 'default';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  LISTAR
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();

            $query = Carteira::where('tenant_id', $tenantId);

            if ($request->filled('search')) {
                $query->where('motorista', 'like', '%' . $request->search . '%');
            }

            $perPage   = (int) $request->get('limit', 10);
            $page      = (int) $request->get('page', 1);
            $carteiras = $query->orderBy('motorista')->paginate($perPage, ['*'], 'page', $page);

            $data = $carteiras->map(fn($c) => [
                'motorista'            => $c->motorista,
                'saldo'                => (float) $c->saldo,
                'total_bonus'          => (float) $c->total_bonus,
                'total_divida'         => (float) $c->total_divida,
                'ultimo_movimento'     => $c->ultimo_movimento?->toISOString(),
                'movimentos'           => $this->getMovimentos($c->motorista, 10),
                'historico_pagamentos' => $this->getHistoricoPagamentos($c->motorista, 5),
            ]);

            return response()->json([
                'success'    => true,
                'data'       => $data,
                'pagination' => [
                    'page'       => $carteiras->currentPage(),
                    'limit'      => $perPage,
                    'total'      => $carteiras->total(),
                    'totalPages' => $carteiras->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao listar carteiras: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao carregar carteiras'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DETALHE
    // ─────────────────────────────────────────────────────────────────────────
    public function show($motorista)
    {
        try {
            $tenantId = $this->getTenantId();
            $carteira = Carteira::where('tenant_id', $tenantId)
                ->where('motorista', $motorista)
                ->first() ?? $this->criarCarteira($motorista);

            return response()->json([
                'success' => true,
                'data'    => [
                    'motorista'            => $carteira->motorista,
                    'saldo'                => (float) $carteira->saldo,
                    'total_bonus'          => (float) $carteira->total_bonus,
                    'total_divida'         => (float) $carteira->total_divida,
                    'ultimo_movimento'     => $carteira->ultimo_movimento?->toISOString(),
                    'movimentos'           => $this->getMovimentos($motorista),
                    'historico_pagamentos' => $this->getHistoricoPagamentos($motorista),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar carteira: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao carregar carteira'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  PAGAR
    //
    //  MODELO DE NEGÓCIO:
    //  ------------------
    //  saldo       = bónus bruto acumulado (o que a empresa deve ao motorista)
    //  total_divida = descontos pendentes  (o que o motorista deve à empresa)
    //
    //  Ao pagar:
    //    desconto_aplicado → abate a total_divida E sai do saldo
    //    valor             → transferido ao motorista, também sai do saldo
    //
    //  Resultado:
    //    novo_saldo        = saldo - valor - desconto_aplicado
    //    nova_total_divida = total_divida - desconto_aplicado
    //
    //  Caso especial "perdoar":
    //    novo_saldo        = saldo - valor   (valor = saldo completo)
    //    nova_total_divida = 0               (zerada sem custo ao saldo)
    // ─────────────────────────────────────────────────────────────────────────
    public function pagar(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();

            $validator = Validator::make($request->all(), [
                'motorista'         => 'required|string',
                'valor'             => 'required|numeric|min:0',
                'desconto_aplicado' => 'required|numeric|min:0',
                'tipo_pagamento'    => 'required|string',
                'percentual'        => 'nullable|numeric|min:0|max:100',
                'perdoar_divida'    => 'nullable|boolean',
                'observacoes'       => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Erro de validação',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            $carteira = Carteira::where('motorista', $request->motorista)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$carteira) {
                return response()->json(['success' => false, 'error' => 'Carteira não encontrada'], 404);
            }

            $valorPago         = (float) $request->valor;
            $descontoAplicado  = (float) $request->desconto_aplicado;
            $perdoarDivida     = (bool)  $request->get('perdoar_divida', false);
            $totalDebitarSaldo = $valorPago + $descontoAplicado;

            // ── Validações de negócio ─────────────────────────────────────
            if (round($totalDebitarSaldo, 4) > round($carteira->saldo, 4)) {
                return response()->json([
                    'success' => false,
                    'error'   => sprintf(
                        'Saldo insuficiente. Disponível: %.2f MZN | Necessário: %.2f MZN',
                        $carteira->saldo,
                        $totalDebitarSaldo
                    ),
                ], 422);
            }

            if (!$perdoarDivida && round($descontoAplicado, 4) > round($carteira->total_divida, 4)) {
                return response()->json([
                    'success' => false,
                    'error'   => sprintf(
                        'Desconto (%.2f MZN) não pode exceder a dívida actual (%.2f MZN)',
                        $descontoAplicado,
                        $carteira->total_divida
                    ),
                ], 422);
            }

            Log::info('💳 Processando pagamento', [
                'motorista'          => $request->motorista,
                'saldo_antes'        => $carteira->saldo,
                'divida_antes'       => $carteira->total_divida,
                'valor_a_pagar'      => $valorPago,
                'desconto_aplicado'  => $descontoAplicado,
                'perdoar_divida'     => $perdoarDivida,
                'total_debitar_saldo'=> $totalDebitarSaldo,
            ]);

            // ── Gravar o pagamento ────────────────────────────────────────
            $pagamento = CarteiraPagamento::create([
                'motorista'         => $request->motorista,
                'valor'             => $valorPago,
                'desconto_aplicado' => $descontoAplicado,
                'tipo_pagamento'    => $request->tipo_pagamento,
                'percentual'        => $request->percentual,
                'observacoes'       => $request->observacoes,
                'tenant_id'         => $tenantId,
            ]);

            // ── Marcar descontos pendentes como aplicados ─────────────────
            if ($descontoAplicado > 0) {
                $this->marcarDescontosComoAplicados($request->motorista, $descontoAplicado, $tenantId);
            }

            // ── Se for perdão, zerar a dívida sem custo adicional ao saldo ─
            if ($perdoarDivida && $carteira->total_divida > 0) {
                $this->zerarDividaPorPerdao($request->motorista, $carteira->total_divida, $pagamento->id, $tenantId);
            }

            // ── Actualizar carteira ───────────────────────────────────────
            $saldoAnterior  = $carteira->saldo;
            $dividaAnterior = $carteira->total_divida;

            // Saldo: diminui pelo total (valor pago ao motorista + desconto que abateu dívida)
            $carteira->saldo = max(0, $carteira->saldo - $totalDebitarSaldo);

            // Dívida:
            if ($perdoarDivida) {
                $carteira->total_divida = 0;
            } else {
                $carteira->total_divida = max(0, $carteira->total_divida - $descontoAplicado);
            }

            $carteira->ultimo_movimento = now();
            $carteira->save();

            // ── Registar movimento ────────────────────────────────────────
            $descricao = "Pagamento ao motorista: {$valorPago} MZN";
            if ($descontoAplicado > 0) {
                $descricao .= " | Abate dívida: {$descontoAplicado} MZN";
            }
            if ($perdoarDivida && $dividaAnterior > 0) {
                $descricao .= " | Dívida perdoada: {$dividaAnterior} MZN";
            }

            CarteiraMovimento::create([
                'motorista'       => $request->motorista,
                'origem_id'       => $pagamento->id,
                'origem_type'     => CarteiraPagamento::class,
                'tipo'            => 'debito',
                'origem_tipo'     => 'pagamento',
                'descricao'       => $descricao,
                'valor'           => $totalDebitarSaldo,
                'saldo_anterior'  => $saldoAnterior,
                'saldo_posterior' => $carteira->saldo,
                'tenant_id'       => $tenantId,
            ]);

            DB::commit();

            Log::info('✅ Pagamento concluído', [
                'motorista'     => $request->motorista,
                'saldo_antes'   => $saldoAnterior,
                'novo_saldo'    => $carteira->saldo,
                'divida_antes'  => $dividaAnterior,
                'nova_divida'   => $carteira->total_divida,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pagamento registado com sucesso!',
                'data'    => [
                    'novo_saldo'      => (float) $carteira->saldo,
                    'divida_restante' => (float) $carteira->total_divida,
                    'pagamento_id'    => $pagamento->id,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao registar pagamento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => 'Erro ao registar pagamento: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  EXTRATO
    // ─────────────────────────────────────────────────────────────────────────
    public function extrato(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();

            $validator = Validator::make($request->all(), [
                'motorista'   => 'required|string',
                'data_inicio' => 'required|date',
                'data_fim'    => 'required|date|after_or_equal:data_inicio',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $carteira = Carteira::where('tenant_id', $tenantId)
                ->where('motorista', $request->motorista)
                ->first() ?? $this->criarCarteira($request->motorista);

            $saldoInicial = CarteiraMovimento::where('motorista', $request->motorista)
                ->where('tenant_id', $tenantId)
                ->where('created_at', '<', $request->data_inicio)
                ->orderBy('created_at', 'desc')
                ->value('saldo_posterior') ?? 0;

            $movimentos = CarteiraMovimento::where('motorista', $request->motorista)
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$request->data_inicio, $request->data_fim])
                ->orderBy('created_at')
                ->get();

            $pagamentos = CarteiraPagamento::where('motorista', $request->motorista)
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$request->data_inicio, $request->data_fim])
                ->orderBy('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => [
                    'motorista'    => $request->motorista,
                    'saldo_inicial'=> (float) $saldoInicial,
                    'saldo_final'  => (float) $carteira->saldo,
                    'periodo'      => ['inicio' => $request->data_inicio, 'fim' => $request->data_fim],
                    'creditos'     => $movimentos->where('tipo', 'credito')->map(fn($m) => [
                        'id' => $m->id, 'data' => $m->created_at->toISOString(),
                        'descricao' => $m->descricao, 'valor' => (float) $m->valor, 'origem' => $m->origem_tipo,
                    ])->values(),
                    'debitos'      => $movimentos->where('tipo', 'debito')->map(fn($m) => [
                        'id' => $m->id, 'data' => $m->created_at->toISOString(),
                        'descricao' => $m->descricao, 'valor' => (float) $m->valor, 'origem' => $m->origem_tipo,
                    ])->values(),
                    'pagamentos'   => $pagamentos->map(fn($p) => [
                        'id' => $p->id, 'data' => $p->created_at->toISOString(),
                        'valor' => (float) $p->valor, 'desconto' => (float) $p->desconto_aplicado,
                        'tipo' => $p->tipo_pagamento, 'observacoes' => $p->observacoes,
                    ]),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar extrato: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao gerar extrato'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  RESUMO GERAL
    // ─────────────────────────────────────────────────────────────────────────
    public function resumo()
    {
        try {
            $tenantId  = $this->getTenantId();
            $carteiras = Carteira::where('tenant_id', $tenantId)->get();

            $totalPagoMes = CarteiraPagamento::where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('valor');

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_motoristas'      => $carteiras->count(),
                    'total_saldo'           => (float) $carteiras->sum('saldo'),
                    'total_divida'          => (float) $carteiras->sum('total_divida'),
                    'total_pago_mes'        => (float) $totalPagoMes,
                    'motoristas_com_saldo'  => $carteiras->where('saldo', '>', 0)->count(),
                    'motoristas_com_divida' => $carteiras->where('total_divida', '>', 0)->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao gerar resumo: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao gerar resumo'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  INICIALIZAR TODAS
    // ─────────────────────────────────────────────────────────────────────────
    public function inicializarTodas(Request $request)
    {
        try {
            $tenantId   = $this->getTenantId();
            $motoristas = Motorista::where('tenant_id', $tenantId)->get();

            if ($motoristas->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Nenhum motorista encontrado'], 404);
            }

            $res = ['total' => $motoristas->count(), 'criadas' => 0, 'ja_existiam' => 0, 'erros' => 0];

            foreach ($motoristas as $motorista) {
                try {
                    DB::beginTransaction();

                    if (Carteira::where('motorista', $motorista->nome_completo)->where('tenant_id', $tenantId)->exists()) {
                        $res['ja_existiam']++;
                        DB::commit();
                        continue;
                    }

                    $totalBonus  = Bonus::where('motorista', $motorista->nome_completo)->where('tenant_id', $tenantId)->where('status', 'approved')->sum('valor');
                    $totalDivida = Desconto::where('motorista', $motorista->nome_completo)->where('tenant_id', $tenantId)->where('status', 'pendente')->sum('valor');

                    Carteira::create([
                        'motorista'        => $motorista->nome_completo,
                        'saldo'            => max(0, $totalBonus - $totalDivida),
                        'total_bonus'      => $totalBonus,
                        'total_divida'     => $totalDivida,
                        'ultimo_movimento' => now(),
                        'tenant_id'        => $tenantId,
                    ]);

                    $res['criadas']++;
                    DB::commit();

                } catch (\Exception $e) {
                    DB::rollBack();
                    $res['erros']++;
                    Log::error('Erro ao criar carteira: ' . $e->getMessage());
                }
            }

            return response()->json(['success' => true, 'message' => 'Inicialização concluída', 'data' => $res]);

        } catch (\Exception $e) {
            Log::error('Erro ao inicializar carteiras: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  VERIFICAR DADOS
    // ─────────────────────────────────────────────────────────────────────────
    public function verificarDados()
    {
        try {
            $tenantId  = $this->getTenantId();
            $carteiras = Carteira::where('tenant_id', $tenantId)->get();

            $motoristasSemCarteira = Motorista::where('tenant_id', $tenantId)
                ->whereNotIn('nome_completo', $carteiras->pluck('motorista'))
                ->get(['nome_completo']);

            return response()->json([
                'success' => true,
                'data'    => [
                    'motoristas' => [
                        'total'              => Motorista::where('tenant_id', $tenantId)->count(),
                        'sem_carteira'       => $motoristasSemCarteira->count(),
                        'lista_sem_carteira' => $motoristasSemCarteira,
                    ],
                    'bonus' => [
                        'total'    => Bonus::where('tenant_id', $tenantId)->count(),
                        'approved' => Bonus::where('tenant_id', $tenantId)->where('status', 'approved')->count(),
                        'paid'     => Bonus::where('tenant_id', $tenantId)->where('status', 'paid')->count(),
                        'pending'  => Bonus::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
                    ],
                    'descontos' => [
                        'total'     => Desconto::where('tenant_id', $tenantId)->count(),
                        'pendentes' => Desconto::where('tenant_id', $tenantId)->where('status', 'pendente')->count(),
                        'aplicados' => Desconto::where('tenant_id', $tenantId)->where('status', 'aplicado')->count(),
                    ],
                    'carteiras' => [
                        'total'        => $carteiras->count(),
                        'saldo_total'  => (float) $carteiras->sum('saldo'),
                        'bonus_total'  => (float) $carteiras->sum('total_bonus'),
                        'divida_total' => (float) $carteiras->sum('total_divida'),
                        'lista'        => $carteiras->map(fn($c) => [
                            'motorista' => $c->motorista,
                            'saldo'     => (float) $c->saldo,
                            'bonus'     => (float) $c->total_bonus,
                            'divida'    => (float) $c->total_divida,
                        ]),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao verificar dados: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Marca descontos pendentes como aplicados (mais antigos primeiro).
     * Não toca no saldo — isso é responsabilidade de pagar().
     */
    private function marcarDescontosComoAplicados(string $motorista, float $valorDesconto, string $tenantId): void
    {
        $descontos = Desconto::where('motorista', $motorista)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pendente')
            ->orderBy('created_at')
            ->get();

        $restante = round($valorDesconto, 4);

        foreach ($descontos as $desconto) {
            if ($restante <= 0) break;

            if (round($desconto->valor, 4) <= $restante + 0.001) {
                $desconto->status = 'aplicado';
                $desconto->save();
                $restante -= $desconto->valor;

                Log::info('✅ Desconto aplicado', ['id' => $desconto->id, 'valor' => $desconto->valor]);
            }
        }
    }

    /**
     * Quando o tipo é "perdoar", marca todos os descontos pendentes como perdoados
     * sem deduzir do saldo.
     */
    private function zerarDividaPorPerdao(string $motorista, float $totalDivida, int $pagamentoId, string $tenantId): void
    {
        Desconto::where('motorista', $motorista)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pendente')
            ->update(['status' => 'perdoado']);

        Log::info('🎁 Dívida perdoada', [
            'motorista'   => $motorista,
            'total_divida'=> $totalDivida,
            'pagamento_id'=> $pagamentoId,
        ]);
    }

    private function criarCarteira(string $motorista): Carteira
    {
        $tenantId    = $this->getTenantId();
        $totalBonus  = Bonus::where('motorista', $motorista)->where('tenant_id', $tenantId)->where('status', 'approved')->sum('valor');
        $totalDivida = Desconto::where('motorista', $motorista)->where('tenant_id', $tenantId)->where('status', 'pendente')->sum('valor');

        return Carteira::create([
            'motorista'        => $motorista,
            'saldo'            => max(0, $totalBonus - $totalDivida),
            'total_bonus'      => $totalBonus,
            'total_divida'     => $totalDivida,
            'ultimo_movimento' => now(),
            'tenant_id'        => $tenantId,
        ]);
    }

    private function getMovimentos(string $motorista, int $limit = 50): array
    {
        return CarteiraMovimento::where('motorista', $motorista)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($m) => [
                'id'        => $m->id,
                'data'      => $m->created_at->toISOString(),
                'descricao' => $m->descricao,
                'valor'     => (float) $m->valor,
                'tipo'      => $m->tipo,
                'origem'    => $m->origem_tipo,
            ])
            ->toArray();
    }

    private function getHistoricoPagamentos(string $motorista, int $limit = 20): array
    {
        return CarteiraPagamento::where('motorista', $motorista)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'data'        => $p->created_at->toISOString(),
                'valor'       => (float) $p->valor,
                'desconto'    => (float) $p->desconto_aplicado,
                'tipo'        => $p->tipo_pagamento,
                'percentual'  => $p->percentual ? (float) $p->percentual : null,
                'observacoes' => $p->observacoes ?? '',
            ])
            ->toArray();
    }
}