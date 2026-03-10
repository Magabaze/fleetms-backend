<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bonus;
use App\Models\Desconto;
use App\Models\PagamentoMotorista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PagamentoController extends Controller
{
    public function __construct() 
    { 
        $this->middleware('auth:sanctum'); 
    }

    // Função auxiliar para converter snake_case para camelCase
    private function paraCamelCase($pagamento)
    {
        return [
            'motorista' => $pagamento->motorista,
            'total_bonus' => (float) $pagamento->total_bonus,
            'total_descontos' => (float) $pagamento->total_descontos,
            'valor_liquido' => (float) $pagamento->valor_liquido,
        ];
    }

    public function pendentes(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/bonus/pagamentos/pendentes', [
            'user_id' => $user->id,
            'tenant_id' => $tenant,
            'query' => $request->all()
        ]);

        try {
            // Query de agregação
            $bonusAprovados = Bonus::where('tenant_id', $tenant)
                ->where('status', 'approved')
                ->select('motorista', DB::raw('SUM(valor) as total_bonus'))
                ->groupBy('motorista');

            $descontosPendentes = Desconto::where('tenant_id', $tenant)
                ->where('status', 'pendente')
                ->select('motorista', DB::raw('SUM(valor) as total_descontos'))
                ->groupBy('motorista');

            // União
            $pagamentos = DB::query()
                ->fromSub($bonusAprovados, 'b')
                ->leftJoinSub($descontosPendentes, 'd', 'b.motorista', '=', 'd.motorista')
                ->select(
                    'b.motorista',
                    DB::raw('COALESCE(b.total_bonus, 0) as total_bonus'),
                    DB::raw('COALESCE(d.total_descontos, 0) as total_descontos'),
                    DB::raw('(COALESCE(b.total_bonus, 0) - COALESCE(d.total_descontos, 0)) as valor_liquido')
                )
                ->whereRaw('(COALESCE(b.total_bonus, 0) - COALESCE(d.total_descontos, 0)) > 0')
                ->paginate($request->get('limit', 10));

            // Converter todos os itens para camelCase
            $itemsCamelCase = collect($pagamentos->items())->map(function ($item) {
                return $this->paraCamelCase($item);
            });
            
            Log::info('✅ Pagamentos pendentes listados', [
                'total' => $pagamentos->total(),
                'tenant_id' => $tenant
            ]);

            return response()->json([
                'success' => true,
                'data' => $itemsCamelCase->toArray(),
                'pagination' => [
                    'page' => $pagamentos->currentPage(),
                    'limit' => $request->get('limit', 10),
                    'total' => $pagamentos->total(),
                    'totalPages' => $pagamentos->lastPage(),
                    'hasNextPage' => $pagamentos->hasMorePages(),
                    'hasPrevPage' => $pagamentos->currentPage() > 1,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar pagamentos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function registrar(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant_id ?? 'default';
        
        Log::info('📥 POST /api/bonus/pagamentos/registrar', [
            'user_id' => $user->id,
            'tenant_id' => $tenant,
            'dados' => $request->all()
        ]);
        
        try {
            DB::transaction(function () use ($request, $user, $tenant) {
                PagamentoMotorista::create([
                    'motorista' => $request->motorista,
                    'total_bonus' => $request->total_bonus,
                    'total_descontos' => $request->total_descontos ?? 0,
                    'valor_liquido' => $request->valor_liquido,
                    'data_pagamento' => now(),
                    'status' => 'pago',
                    'observacoes' => $request->observacoes,
                    'tenant_id' => $tenant
                ]);

                Bonus::where('motorista', $request->motorista)
                    ->where('tenant_id', $tenant)
                    ->where('status', 'approved')
                    ->update(['status' => 'paid']);

                Desconto::where('motorista', $request->motorista)
                    ->where('tenant_id', $tenant)
                    ->where('status', 'pendente')
                    ->update(['status' => 'aplicado']);
            });

            Log::info('✅ Pagamento registrado', [
                'motorista' => $request->motorista,
                'tenant_id' => $tenant
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Pagamento registrado com sucesso!'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao registrar pagamento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}