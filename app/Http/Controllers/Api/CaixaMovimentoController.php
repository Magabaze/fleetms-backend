<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaixaTurno;
use App\Models\CaixaMovimento;
use App\Models\DriverExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // ← IMPORT CORRETO

class CaixaMovimentoController extends Controller
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
            $query = CaixaMovimento::where('tenant_id', $tenantId);
            
            if ($request->has('turno_id') && $request->turno_id) {
                $query->where('turno_id', $request->turno_id);
            }
            
            $movimentos = $query->orderBy('data_movimento', 'desc')->get();
            
            $movimentosCamelCase = $movimentos->map(function ($mov) {
                return [
                    'id' => $mov->id,
                    'turnoId' => $mov->turno_id,
                    'tipo' => $mov->tipo,
                    'moeda' => $mov->moeda,
                    'valor' => (float) $mov->valor,
                    'descricao' => $mov->descricao,
                    'despesasIds' => $mov->despesas_ids,
                    'data' => $mov->data_movimento->toISOString(),
                    'saldoAnterior' => (float) $mov->saldo_anterior,
                    'saldoPosterior' => (float) $mov->saldo_posterior,
                    'criadoPor' => $mov->criado_por,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $movimentosCamelCase->toArray()
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar movimentos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PAGAR DESPESAS (saída de caixa)
     */
    public function pagarRequisicao(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'despesas_ids' => 'required|array|min:1',
                'despesas_ids.*' => 'integer|exists:driver_expenses,id',
                'turno_id' => 'required|exists:caixa_turnos,id',
                'moeda' => 'required|string|in:MZN,USD,ZAR,AOA',
                'observacoes' => 'nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Buscar turno
            $turno = CaixaTurno::where('tenant_id', $tenantId)
                ->where('id', $request->turno_id)
                ->where('status', 'aberto')
                ->first();
                
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => 'Turno não encontrado ou não está aberto'
                ], 404);
            }
            
            // Buscar despesas (apenas approved)
            $despesas = DriverExpense::whereIn('id', $request->despesas_ids)
                ->where('status', 'approved')
                ->where('is_active', true)
                ->get();
                
            if ($despesas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhuma despesa aprovada encontrada'
                ], 404);
            }
            
            // Calcular total
            $total = $despesas->sum('amount');
            
            // Verificar saldo na moeda específica
            $saldos = collect($turno->saldos);
            $saldoAtual = $saldos->firstWhere('moeda', $request->moeda)['saldoAtual'] ?? 0;
            
            if ($saldoAtual < $total) {
                return response()->json([
                    'success' => false,
                    'error' => "Saldo insuficiente em {$request->moeda}. Disponível: " . number_format($saldoAtual, 2)
                ], 422);
            }
            
            $novoSaldo = $saldoAtual - $total;
            
            // Atualizar saldo no array
            $novosSaldos = collect($turno->saldos)->map(function ($item) use ($request, $novoSaldo) {
                if ($item['moeda'] === $request->moeda) {
                    $item['saldoAtual'] = $novoSaldo;
                }
                return $item;
            })->toArray();
            
            $turno->update(['saldos' => $novosSaldos]);
            
            // Criar movimento
            $movimento = CaixaMovimento::create([
                'turno_id' => $turno->id,
                'tipo' => 'saida',
                'moeda' => $request->moeda,
                'valor' => $total,
                'descricao' => 'Pagamento de despesas: ' . $despesas->pluck('expense_head')->implode(', '),
                'despesas_ids' => $request->despesas_ids,
                'data_movimento' => now(),
                'saldo_anterior' => $saldoAtual,
                'saldo_posterior' => $novoSaldo,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            // Atualizar status das despesas para 'paid'
            DriverExpense::whereIn('id', $request->despesas_ids)
                ->update(['status' => 'paid']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'movimento' => [
                        'id' => $movimento->id,
                        'tipo' => $movimento->tipo,
                        'moeda' => $movimento->moeda,
                        'valor' => (float) $movimento->valor,
                        'descricao' => $movimento->descricao,
                        'data' => $movimento->data_movimento->toISOString(),
                        'saldoPosterior' => (float) $movimento->saldo_posterior,
                    ],
                    'saldoAtual' => (float) $novoSaldo
                ],
                'message' => 'Pagamento realizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao pagar despesas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * REGISTRAR DEVOLUÇÃO (entrada no caixa)
     */
    public function registrarDevolucao(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'despesas_ids' => 'required|array|min:1',
                'despesas_ids.*' => 'integer|exists:driver_expenses,id',
                'turno_id' => 'required|exists:caixa_turnos,id',
                'valorDevolvido' => 'required|numeric|min:0',
                'moeda' => 'required|string|in:MZN,USD,ZAR,AOA',
                'observacoes' => 'nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Buscar turno
            $turno = CaixaTurno::where('tenant_id', $tenantId)
                ->where('id', $request->turno_id)
                ->where('status', 'aberto')
                ->first();
                
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => 'Turno não encontrado ou não está aberto'
                ], 404);
            }
            
            // Buscar despesas (apenas paid)
            $despesas = DriverExpense::whereIn('id', $request->despesas_ids)
                ->where('status', 'paid')
                ->where('is_active', true)
                ->get();
                
            if ($despesas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhuma despesa paga encontrada'
                ], 404);
            }
            
            $totalDespesas = $despesas->sum('amount');
            
            // Atualizar saldo (entrada)
            $saldos = collect($turno->saldos);
            $saldoAtual = $saldos->firstWhere('moeda', $request->moeda)['saldoAtual'] ?? 0;
            $novoSaldo = $saldoAtual + $request->valorDevolvido;
            
            // Atualizar saldo no array
            $novosSaldos = collect($turno->saldos)->map(function ($item) use ($request, $novoSaldo) {
                if ($item['moeda'] === $request->moeda) {
                    $item['saldoAtual'] = $novoSaldo;
                }
                return $item;
            })->toArray();
            
            $turno->update(['saldos' => $novosSaldos]);
            
            // Criar movimento de entrada
            $movimento = CaixaMovimento::create([
                'turno_id' => $turno->id,
                'tipo' => 'entrada',
                'moeda' => $request->moeda,
                'valor' => $request->valorDevolvido,
                'descricao' => 'Devolução de ' . $despesas->first()->driver_name . 
                    ' (Despesas: ' . number_format($totalDespesas, 2) . ')',
                'despesas_ids' => $request->despesas_ids,
                'data_movimento' => now(),
                'saldo_anterior' => $saldoAtual,
                'saldo_posterior' => $novoSaldo,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            // Atualizar status das despesas para 'settled' (justificadas)
            DriverExpense::whereIn('id', $request->despesas_ids)
                ->update(['status' => 'settled']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'movimento' => [
                        'id' => $movimento->id,
                        'tipo' => $movimento->tipo,
                        'moeda' => $movimento->moeda,
                        'valor' => (float) $movimento->valor,
                        'descricao' => $movimento->descricao,
                        'data' => $movimento->data_movimento->toISOString(),
                        'saldoPosterior' => (float) $movimento->saldo_posterior,
                    ],
                    'saldoAtual' => (float) $novoSaldo
                ],
                'message' => 'Devolução registrada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao registrar devolução: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}