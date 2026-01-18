<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ContainerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Atualizar container
     * PATCH /api/containers/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            Log::info('🔄 [ContainerController] Atualizando container:', [
                'container_id' => $id,
                'dados' => $request->all()
            ]);
            
            $user = auth()->user();
            $tenantId = $user->tenant_id ?? 'default';
            
            $container = Container::where('tenant_id', $tenantId)->find($id);
            
            if (!$container) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'numero_container' => 'sometimes|string|max:255',
                'tipo_recipiente' => 'sometimes|string|max:255',
                'tipo_carga' => 'sometimes|string|max:255',
                'unidade' => 'sometimes|string|max:255',
                'peso_liquido' => 'sometimes|numeric',
                'peso_container' => 'sometimes|numeric',
                'peso_total' => 'sometimes|numeric',
                'status' => 'sometimes|in:pending,loaded,in_transit,delivered,cancelled',
                'is_available' => 'sometimes|boolean',
                'selo' => 'nullable|string|max:255',
                'aterramento_ref' => 'nullable|string|max:255',
                'data_validade_do' => 'nullable|date',
                'drop_off_details' => 'nullable|string',
                'deposito_contentores' => 'nullable|string',
                'viagem_id' => 'nullable|integer|exists:viagens,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Atualizar o container
            $updateData = $request->only([
                'numero_container', 'tipo_recipiente', 'tipo_carga', 'unidade',
                'peso_liquido', 'peso_container', 'peso_total', 'status',
                'selo', 'aterramento_ref', 'data_validade_do', 'drop_off_details',
                'deposito_contentores', 'viagem_id'
            ]);
            
            if ($request->has('is_available')) {
                $updateData['is_available'] = $request->is_available;
            }
            
            // Se status for 'loaded', marcar como não disponível
            if ($request->has('status') && $request->status === 'loaded') {
                $updateData['is_available'] = false;
            }
            
            $container->update($updateData);
            
            Log::info('✅ Container atualizado:', [
                'container_id' => $container->id,
                'numero_container' => $container->numero_container,
                'novo_status' => $container->status,
                'is_available' => $container->is_available
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Container atualizado com sucesso',
                'data' => [
                    'id' => $container->id,
                    'numero_container' => $container->numero_container,
                    'tipo_recipiente' => $container->tipo_recipiente,
                    'peso_total' => $container->peso_total,
                    'status' => $container->status,
                    'is_available' => $container->is_available,
                    'viagem_id' => $container->viagem_id
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar container: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar containers
     * GET /api/containers
     */
    public function index(Request $request)
    {
        try {
            Log::info('📥 GET /api/containers', ['query' => $request->all()]);
            
            $user = auth()->user();
            $tenantId = $user->tenant_id ?? 'default';
            
            $query = Container::where('tenant_id', $tenantId);
            
            // Filtros
            if ($request->has('ordem_id')) {
                $query->where('ordem_id', $request->ordem_id);
            }
            
            if ($request->has('is_available')) {
                $query->where('is_available', $request->is_available);
            }
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Ordenação
            $query->orderBy('created_at', 'desc');
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $containers = $query->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => $containers->items(),
                'pagination' => [
                    'page' => $containers->currentPage(),
                    'limit' => $perPage,
                    'total' => $containers->total(),
                    'totalPages' => $containers->lastPage(),
                    'hasNextPage' => $containers->hasMorePages(),
                    'hasPrevPage' => $containers->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar containers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar container específico
     * GET /api/containers/{id}
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            $tenantId = $user->tenant_id ?? 'default';
            
            $container = Container::where('tenant_id', $tenantId)->find($id);
            
            if (!$container) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $container
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar container: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}