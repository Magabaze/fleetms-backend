<?php
// app/Http/Controllers/Api/ViagemFaturacaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\OrdemFaturacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ViagemFaturacaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Buscar viagens disponíveis para faturação
     * Apenas viagens cheias (isEmptyTrip = false) que ainda não têm ordem
     */
    public function paraFaturar(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            // Query base: apenas viagens do tenant e cheias
            $query = Viagem::where('tenant_id', $tenantId)
                ->where('isEmptyTrip', false); // Apenas viagens com carga
            
            // Busca por texto (opcional)
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('tripNumber', 'like', "%{$search}%")
                      ->orWhere('customerName', 'like', "%{$search}%")
                      ->orWhere('driver', 'like', "%{$search}%");
                });
            }
            
            // Buscar IDs das viagens que já têm ordem de faturação
            $viagensComOrdem = OrdemFaturacao::where('tenant_id', $tenantId)
                ->pluck('viagem_id')
                ->filter()
                ->toArray();
            
            // Excluir viagens que já têm ordem
            if (!empty($viagensComOrdem)) {
                $query->whereNotIn('id', $viagensComOrdem);
            }
            
            // Ordenar por data (mais recentes primeiro)
            $query->orderBy('scheduleDate', 'desc');
            
            // Buscar resultados
            $viagens = $query->get();
            
            // Mapear dados para o formato esperado pelo frontend
            $viagensMapeadas = $viagens->map(function ($viagem) {
                return [
                    'id' => $viagem->id,
                    'tripNumber' => $viagem->tripNumber,
                    'customerName' => $viagem->customerName,
                    'driver' => $viagem->driver,
                    'fromStation' => $viagem->fromStation,
                    'toStation' => $viagem->toStation,
                    'totalValue' => $viagem->totalValue ?? 0,
                    'scheduleDate' => $viagem->scheduleDate,
                    'currentStatus' => $viagem->currentStatus,
                    'isEmptyTrip' => $viagem->isEmptyTrip,
                ];
            });
            
            Log::info('📋 Viagens disponíveis para faturação', [
                'total' => $viagensMapeadas->count(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $viagensMapeadas,
                'total' => $viagensMapeadas->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar viagens para faturar: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}