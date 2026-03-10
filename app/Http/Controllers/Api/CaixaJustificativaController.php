<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaixaJustificativa;
use App\Models\DriverExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CaixaJustificativaController extends Controller
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
            $query = CaixaJustificativa::where('tenant_id', $tenantId);
            
            if ($request->has('motorista_id') && $request->motorista_id) {
                $query->where('motorista_id', $request->motorista_id);
            }
            
            if ($request->has('data_inicio') && $request->data_inicio) {
                $query->where('data_justificativa', '>=', $request->data_inicio);
            }
            if ($request->has('data_fim') && $request->data_fim) {
                $query->where('data_justificativa', '<=', $request->data_fim . ' 23:59:59');
            }
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('motorista_nome', 'like', "%{$search}%");
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $justificativas = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $justificativasCamelCase = $justificativas->map(function ($j) {
                return [
                    'id' => $j->id,
                    'turnoId' => $j->turno_id,
                    'viagemId' => $j->viagem_id,
                    'motoristaNome' => $j->motorista_nome,
                    'motoristaId' => $j->motorista_id,
                    'despesasIds' => $j->despesas_ids,
                    'tipo' => $j->tipo,
                    'moeda' => $j->moeda,
                    'valorDespesas' => (float) $j->valor_despesas,
                    'valorRecebido' => (float) $j->valor_recebido,
                    'valorComprovantes' => (float) $j->valor_comprovantes,
                    'valorDevolvido' => (float) $j->valor_devolvido,
                    'diferenca' => (float) $j->diferenca,
                    'data' => $j->data_justificativa?->toISOString(),
                    'observacoes' => $j->observacoes,
                    'criadoPor' => $j->criado_por,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $justificativasCamelCase->toArray(),
                'pagination' => [
                    'page' => $justificativas->currentPage(),
                    'limit' => $perPage,
                    'total' => $justificativas->total(),
                    'totalPages' => $justificativas->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar justificativas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * JUSTIFICAR DESPESAS (com recibo - sem movimento no caixa)
     */
    public function justificar(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'despesasIds' => 'required|array|min:1',
                'despesasIds.*' => 'integer|exists:driver_expenses,id',
                'turnoId' => 'required|exists:caixa_turnos,id',
                'observacoes' => 'nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Buscar despesas (apenas paid)
            $despesas = DriverExpense::whereIn('id', $request->despesasIds)
                ->where('status', 'paid')
                ->where('is_active', true)
                ->get();
                
            if ($despesas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhuma despesa paga encontrada'
                ], 404);
            }
            
            $primeiraDespesa = $despesas->first();
            $totalDespesas = $despesas->sum('amount');
            
            // Criar justificativa
            $justificativa = CaixaJustificativa::create([
                'turno_id' => $request->turnoId,
                'viagem_id' => $primeiraDespesa->viagem_id,
                'motorista_nome' => $primeiraDespesa->driver_name,
                'motorista_id' => null,
                'despesas_ids' => $request->despesasIds,
                'tipo' => 'justificativa',
                'moeda' => $primeiraDespesa->currency,
                'valor_despesas' => $totalDespesas,
                'valor_recebido' => 0,
                'valor_comprovantes' => $totalDespesas,
                'valor_devolvido' => 0,
                'diferenca' => 0,
                'data_justificativa' => now(),
                'observacoes' => $request->observacoes,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            // Atualizar status das despesas para 'settled'
            DriverExpense::whereIn('id', $request->despesasIds)
                ->update(['status' => 'settled']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Despesas justificadas com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao justificar despesas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * REGISTRAR DEVOLUÇÃO (com movimento no caixa)
     */
    public function devolver(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'despesasIds' => 'required|array|min:1',
                'despesasIds.*' => 'integer|exists:driver_expenses,id',
                'turnoId' => 'required|exists:caixa_turnos,id',
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
            
            // Buscar despesas (apenas paid)
            $despesas = DriverExpense::whereIn('id', $request->despesasIds)
                ->where('status', 'paid')
                ->where('is_active', true)
                ->get();
                
            if ($despesas->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhuma despesa paga encontrada'
                ], 404);
            }
            
            $primeiraDespesa = $despesas->first();
            $totalDespesas = $despesas->sum('amount');
            $diferenca = $request->valorDevolvido - $totalDespesas;
            
            // Criar justificativa de devolução
            $justificativa = CaixaJustificativa::create([
                'turno_id' => $request->turnoId,
                'viagem_id' => $primeiraDespesa->viagem_id,
                'motorista_nome' => $primeiraDespesa->driver_name,
                'motorista_id' => null,
                'despesas_ids' => $request->despesasIds,
                'tipo' => 'devolucao',
                'moeda' => $request->moeda,
                'valor_despesas' => $totalDespesas,
                'valor_recebido' => $request->valorDevolvido,
                'valor_comprovantes' => 0,
                'valor_devolvido' => $request->valorDevolvido,
                'diferenca' => $diferenca,
                'data_justificativa' => now(),
                'observacoes' => $request->observacoes,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            // Atualizar status das despesas para 'settled'
            DriverExpense::whereIn('id', $request->despesasIds)
                ->update(['status' => 'settled']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
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