<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BreakBulkItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BreakBulkItemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Atualizar break bulk item
     * PATCH /api/break-bulk-items/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            Log::info('🔄 [BreakBulkController] Atualizando break bulk:', [
                'break_bulk_id' => $id,
                'dados' => $request->all()
            ]);
            
            $user = auth()->user();
            $tenantId = $user->tenant_id ?? 'default';
            
            $breakBulkItem = BreakBulkItem::where('tenant_id', $tenantId)->find($id);
            
            if (!$breakBulkItem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Break bulk item não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'tipo_embalagem' => 'sometimes|string|max:255',
                'quantidade' => 'sometimes|integer|min:0',
                'unidades_embalagem' => 'sometimes|string|max:255',
                'peso_por_unidade' => 'sometimes|numeric|min:0',
                'peso_total' => 'sometimes|numeric|min:0',
                'peso_utilizado' => 'sometimes|numeric|min:0',
                'quantidade_utilizada' => 'sometimes|integer|min:0',
                'status' => 'sometimes|string|max:255',
                'viagem_id' => 'nullable|integer|exists:viagens,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $updateData = $request->only([
                'tipo_embalagem', 'quantidade', 'unidades_embalagem',
                'peso_por_unidade', 'peso_total', 'peso_utilizado',
                'quantidade_utilizada', 'status', 'viagem_id'
            ]);
            
            // Verificar se peso utilizado excede o total
            if ($request->has('peso_utilizado')) {
                $novoPesoUtilizado = $request->peso_utilizado;
                $pesoTotal = $breakBulkItem->peso_total;
                
                if ($novoPesoUtilizado > $pesoTotal) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Peso utilizado não pode exceder o peso total'
                    ], 400);
                }
                
                // Se todo o peso foi utilizado, marcar como completed
                if ($novoPesoUtilizado >= $pesoTotal) {
                    $updateData['status'] = 'completed';
                }
            }
            
            $breakBulkItem->update($updateData);
            
            Log::info('✅ Break bulk atualizado:', [
                'break_bulk_id' => $breakBulkItem->id,
                'tipo_embalagem' => $breakBulkItem->tipo_embalagem,
                'peso_total' => $breakBulkItem->peso_total,
                'peso_utilizado' => $breakBulkItem->peso_utilizado,
                'status' => $breakBulkItem->status
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Break bulk atualizado com sucesso',
                'data' => [
                    'id' => $breakBulkItem->id,
                    'tipo_embalagem' => $breakBulkItem->tipo_embalagem,
                    'quantidade' => $breakBulkItem->quantidade,
                    'peso_total' => $breakBulkItem->peso_total,
                    'peso_utilizado' => $breakBulkItem->peso_utilizado,
                    'peso_disponivel' => max(0, $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado),
                    'status' => $breakBulkItem->status,
                    'viagem_id' => $breakBulkItem->viagem_id
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar break bulk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar break bulk items
     * GET /api/break-bulk-items
     */
    public function index(Request $request)
    {
        try {
            Log::info('📥 GET /api/break-bulk-items', ['query' => $request->all()]);
            
            $user = auth()->user();
            $tenantId = $user->tenant_id ?? 'default';
            
            $query = BreakBulkItem::where('tenant_id', $tenantId);
            
            // Filtros
            if ($request->has('ordem_id')) {
                $query->where('ordem_id', $request->ordem_id);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Ordenação
            $query->orderBy('created_at', 'desc');
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $breakBulkItems = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => $breakBulkItems->items(),
                'pagination' => [
                    'page' => $breakBulkItems->currentPage(),
                    'limit' => $perPage,
                    'total' => $breakBulkItems->total(),
                    'totalPages' => $breakBulkItems->lastPage(),
                    'hasNextPage' => $breakBulkItems->hasMorePages(),
                    'hasPrevPage' => $breakBulkItems->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar break bulk items: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar break bulk item específico
     * GET /api/break-bulk-items/{id}
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            $tenantId = $user->tenant_id ?? 'default';
            
            $breakBulkItem = BreakBulkItem::where('tenant_id', $tenantId)->find($id);
            
            if (!$breakBulkItem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Break bulk item não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $breakBulkItem
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar break bulk item: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}