<?php
// app/Http/Controllers/Api/OrdemDisponibilidadeController.php - OTIMIZADO

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ordem;
use App\Models\Container;
use App\Models\BreakBulkItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class OrdemDisponibilidadeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Função auxiliar para obter tenant_id
    private function getTenantId()
    {
        $user = Auth::user();
        return $user->tenant_id ?? 'default';
    }

    // Buscar containers disponíveis de uma ordem
    public function getContainersDisponiveis($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('📦 Buscando containers disponíveis para ordem', [
                'ordem_id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            $ordem = Ordem::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }

            // Buscar containers disponíveis (status pending e is_available = true)
            $containers = Container::where('ordem_id', $id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('is_available', true)
                ->select(
                    'id',
                    'numero_container',
                    'tipo_recipiente',
                    'peso_total',
                    'status',
                    'is_available',
                    'created_at'
                )
                ->get();

            Log::info('✅ Containers encontrados', [
                'count' => $containers->count(),
                'tenant_id' => $tenantId
            ]);

            return response()->json([
                'success' => true,
                'data' => $containers,
                'total' => $containers->count()
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar containers disponíveis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // Buscar break bulk disponível de uma ordem
    public function getBreakBulkDisponivel($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('📦 Buscando break bulk disponível para ordem', [
                'ordem_id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            $ordem = Ordem::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }

            // Buscar itens break bulk
            $breakBulkItems = BreakBulkItem::where('ordem_id', $id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->get();

            // Calcular peso disponível e filtrar apenas os que têm disponibilidade
            $itemsComDisponibilidade = $breakBulkItems->filter(function ($item) {
                return ($item->peso_total - $item->peso_utilizado) > 0;
            })->map(function ($item) {
                $pesoDisponivel = $item->peso_total - $item->peso_utilizado;
                $quantidadeDisponivel = $item->quantidade - $item->quantidade_utilizada;
                
                return [
                    'id' => $item->id,
                    'tipo_embalagem' => $item->tipo_embalagem,
                    'quantidade_total' => $item->quantidade,
                    'quantidade_disponivel' => max(0, $quantidadeDisponivel),
                    'peso_por_unidade' => $item->peso_por_unidade,
                    'peso_total' => $item->peso_total,
                    'peso_disponivel' => max(0, $pesoDisponivel),
                    'status' => $item->status,
                    'unidades_embalagem' => $item->unidades_embalagem,
                    'peso_utilizado' => $item->peso_utilizado,
                    'quantidade_utilizada' => $item->quantidade_utilizada
                ];
            })->values();

            Log::info('✅ Break bulk encontrado', [
                'count' => $itemsComDisponibilidade->count(),
                'tenant_id' => $tenantId
            ]);

            return response()->json([
                'success' => true,
                'data' => $itemsComDisponibilidade,
                'total_peso_disponivel' => $itemsComDisponibilidade->sum('peso_disponivel')
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar break bulk disponível: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // Atualizar status do container após uso
    public function updateContainerStatus(Request $request, $containerId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('🔄 Atualizando status do container', [
                'container_id' => $containerId,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:loaded,in_transit,delivered,returned',
                'viagem_id' => 'nullable|exists:viagens,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $container = Container::where('tenant_id', $tenantId)->find($containerId);
            
            if (!$container) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não encontrado'
                ], 404);
            }
            
            $updateData = [
                'status' => $request->status,
                'is_available' => false
            ];
            
            if ($request->viagem_id) {
                $updateData['viagem_id'] = $request->viagem_id;
            }
            
            if ($request->status === 'loaded') {
                $updateData['data_carregamento'] = now();
            } else if ($request->status === 'delivered') {
                $updateData['data_descarga'] = now();
            }
            
            $container->update($updateData);
            
            Log::info('✅ Status do container atualizado', [
                'container_id' => $containerId,
                'status' => $request->status,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Status do container atualizado com sucesso!',
                'data' => $container
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar status do container: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // Consumir peso do break bulk
    public function consumirBreakBulk(Request $request, $breakBulkId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('⚖️ Consumindo break bulk', [
                'break_bulk_id' => $breakBulkId,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'peso_utilizado' => 'required|numeric|min:0',
                'quantidade_utilizada' => 'required|integer|min:0',
                'viagem_id' => 'nullable|exists:viagens,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $breakBulkItem = BreakBulkItem::where('tenant_id', $tenantId)->find($breakBulkId);
            
            if (!$breakBulkItem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Item break bulk não encontrado'
                ], 404);
            }
            
            // Verificar se há disponibilidade suficiente
            $pesoDisponivel = $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado;
            $quantidadeDisponivel = $breakBulkItem->quantidade - $breakBulkItem->quantidade_utilizada;
            
            if ($request->peso_utilizado > $pesoDisponivel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Peso insuficiente disponível. Disponível: ' . $pesoDisponivel . ' kg'
                ], 400);
            }
            
            if ($request->quantidade_utilizada > $quantidadeDisponivel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Quantidade insuficiente disponível. Disponível: ' . $quantidadeDisponivel . ' unidades'
                ], 400);
            }
            
            // Atualizar valores
            $breakBulkItem->peso_utilizado += $request->peso_utilizado;
            $breakBulkItem->quantidade_utilizada += $request->quantidade_utilizada;
            
            // Atualizar status se todo o peso foi consumido
            if ($breakBulkItem->peso_utilizado >= $breakBulkItem->peso_total) {
                $breakBulkItem->status = 'loaded';
            }
            
            if ($request->viagem_id) {
                $breakBulkItem->viagem_id = $request->viagem_id;
            }
            
            $breakBulkItem->save();
            
            Log::info('✅ Break bulk consumido', [
                'break_bulk_id' => $breakBulkId,
                'peso_utilizado' => $request->peso_utilizado,
                'peso_restante' => $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Break bulk consumido com sucesso!',
                'data' => [
                    'id' => $breakBulkItem->id,
                    'peso_disponivel' => $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado,
                    'quantidade_disponivel' => $breakBulkItem->quantidade - $breakBulkItem->quantidade_utilizada,
                    'total_consumido' => $breakBulkItem->peso_utilizado,
                    'status' => $breakBulkItem->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao consumir break bulk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // Verificar viabilidade de criação de viagem
    public function checkViabilidade(Request $request, $ordemId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('🔍 Verificando viabilidade para ordem', [
                'ordem_id' => $ordemId,
                'tenant_id' => $tenantId
            ]);
            
            $ordem = Ordem::where('tenant_id', $tenantId)->find($ordemId);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            $response = [
                'ordem_id' => $ordem->id,
                'order_numero' => $ordem->order_numero,
                'tipo_carga' => $ordem->tipo_carga,
                'viavel' => true,
                'mensagem' => '',
                'tenant_id' => $tenantId
            ];
            
            if ($ordem->tipo_carga === 'Container') {
                // Verificar containers disponíveis
                $containersDisponiveis = Container::where('ordem_id', $ordemId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->where('is_available', true)
                    ->count();
                
                $response['containers_disponiveis'] = $containersDisponiveis;
                
                if ($containersDisponiveis === 0) {
                    $response['viavel'] = false;
                    $response['mensagem'] = 'Não há containers disponíveis para esta ordem';
                }
                
            } else if ($ordem->tipo_carga === 'Break Bulk') {
                // Verificar break bulk disponível
                $breakBulkDisponivel = BreakBulkItem::where('ordem_id', $ordemId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->get();
                
                $pesoTotalDisponivel = $breakBulkDisponivel->sum(function ($item) {
                    return max(0, $item->peso_total - $item->peso_utilizado);
                });
                
                $response['peso_disponivel'] = $pesoTotalDisponivel;
                $response['itens_disponiveis'] = $breakBulkDisponivel->count();
                
                if ($pesoTotalDisponivel <= 0) {
                    $response['viavel'] = false;
                    $response['mensagem'] = 'Não há peso disponível para esta ordem';
                }
            }
            
            Log::info('✅ Viabilidade verificada', $response);
            
            return response()->json([
                'success' => true,
                'data' => $response
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao verificar viabilidade: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Método adicional: Liberar container para uso novamente
    public function liberarContainer($containerId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('🔄 Liberando container', [
                'container_id' => $containerId,
                'tenant_id' => $tenantId
            ]);
            
            $container = Container::where('tenant_id', $tenantId)->find($containerId);
            
            if (!$container) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não encontrado'
                ], 404);
            }
            
            // Só pode liberar containers que estavam em viagem
            if (!in_array($container->status, ['loaded', 'in_transit'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não pode ser liberado. Status atual: ' . $container->status
                ], 400);
            }
            
            $container->update([
                'status' => 'pending',
                'is_available' => true,
                'viagem_id' => null,
                'data_carregamento' => null
            ]);
            
            Log::info('✅ Container liberado', [
                'container_id' => $containerId,
                'novo_status' => 'pending',
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Container liberado com sucesso!',
                'data' => $container->fresh()
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao liberar container: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Método adicional: Reverter consumo de break bulk
    public function reverterBreakBulk(Request $request, $breakBulkId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('↩️ Revertendo consumo de break bulk', [
                'break_bulk_id' => $breakBulkId,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'peso_reverter' => 'required|numeric|min:0',
                'quantidade_reverter' => 'required|integer|min:0',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $breakBulkItem = BreakBulkItem::where('tenant_id', $tenantId)->find($breakBulkId);
            
            if (!$breakBulkItem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Item break bulk não encontrado'
                ], 404);
            }
            
            // Verificar se pode reverter (não pode reverter mais do que foi consumido)
            if ($request->peso_reverter > $breakBulkItem->peso_utilizado) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível reverter mais peso do que foi consumido'
                ], 400);
            }
            
            if ($request->quantidade_reverter > $breakBulkItem->quantidade_utilizada) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível reverter mais quantidade do que foi consumida'
                ], 400);
            }
            
            // Reverter valores
            $breakBulkItem->peso_utilizado -= $request->peso_reverter;
            $breakBulkItem->quantidade_utilizada -= $request->quantidade_reverter;
            
            // Se ainda tem peso consumido, manter status como loaded, senão voltar para pending
            if ($breakBulkItem->peso_utilizado > 0) {
                $breakBulkItem->status = 'loaded';
            } else {
                $breakBulkItem->status = 'pending';
            }
            
            $breakBulkItem->save();
            
            Log::info('✅ Consumo revertido', [
                'break_bulk_id' => $breakBulkId,
                'peso_revertido' => $request->peso_reverter,
                'peso_utilizado_atual' => $breakBulkItem->peso_utilizado,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Consumo revertido com sucesso!',
                'data' => [
                    'id' => $breakBulkItem->id,
                    'peso_disponivel' => $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado,
                    'quantidade_disponivel' => $breakBulkItem->quantidade - $breakBulkItem->quantidade_utilizada,
                    'status' => $breakBulkItem->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao reverter break bulk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}