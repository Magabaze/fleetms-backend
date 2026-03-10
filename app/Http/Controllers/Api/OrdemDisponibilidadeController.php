<?php
// app/Http/Controllers/Api/OrdemDisponibilidadeController.php - VERSÃO COMPATÍVEL

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ordem;
use App\Models\Container;
use App\Models\BreakBulkItem;
use App\Models\Viagem;
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
                    'tipo_carga',
                    'unidade',
                    'peso_total',
                    'status',
                    'is_available',
                    'ordem_id',
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

            // Buscar itens break bulk com disponibilidade
            $breakBulkItems = BreakBulkItem::where('ordem_id', $id)
                ->where('tenant_id', $tenantId)
                ->where(function($query) {
                    $query->where('status', 'pending')
                          ->orWhere('status', 'partially_used');
                })
                ->get();

            // Calcular disponibilidade para cada item
            $itemsComDisponibilidade = $breakBulkItems->map(function ($item) {
                $pesoDisponivel = max(0, $item->peso_total - $item->peso_utilizado);
                $quantidadeDisponivel = max(0, $item->quantidade - $item->quantidade_utilizada);
                
                return [
                    'id' => $item->id,
                    'tipo_embalagem' => $item->tipo_embalagem,
                    'descricao_embalagem' => $item->descricao_embalagem,
                    'quantidade_total' => $item->quantidade,
                    'quantidade_disponivel' => $quantidadeDisponivel,
                    'peso_por_unidade' => $item->peso_por_unidade,
                    'peso_total' => $item->peso_total,
                    'peso_disponivel' => $pesoDisponivel,
                    'unidades_embalagem' => $item->unidades_embalagem,
                    'status' => $item->status,
                    'peso_utilizado' => $item->peso_utilizado,
                    'quantidade_utilizada' => $item->quantidade_utilizada,
                    'ordem_id' => $item->ordem_id,
                    'classe_perigosa' => $item->classe_perigosa,
                    'numero_onu' => $item->numero_onu
                ];
            })->filter(function ($item) {
                // Filtrar apenas itens que têm disponibilidade
                return $item['peso_disponivel'] > 0;
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

    // ✅ MÉTODO CORRIGIDO: Atualizar container após ser usado em viagem
    public function marcarContainerComoUsado(Request $request, $containerId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('✅ Marcando container como usado', [
                'container_id' => $containerId,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'viagem_id' => 'required|exists:viagens,id'
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
            
            // Verificar se o container já está em uso
            if ($container->status === 'loaded' && $container->viagem_id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container já está em uso na viagem #' . $container->viagem_id
                ], 400);
            }
            
            // Verificar se o container ainda está disponível
            if (!$container->is_available) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não está disponível para uso'
                ], 400);
            }
            
            // Atualizar container conforme modelo
            $container->update([
                'status' => 'loaded',
                'is_available' => false,
                'viagem_id' => $request->viagem_id,
                'data_carregamento' => now()
            ]);
            
            Log::info('✅ Container marcado como usado', [
                'container_id' => $containerId,
                'viagem_id' => $request->viagem_id,
                'novo_status' => 'loaded',
                'is_available' => false,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Container marcado como usado na viagem!',
                'data' => [
                    'id' => $container->id,
                    'numero_container' => $container->numero_container,
                    'status' => $container->status,
                    'is_available' => $container->is_available,
                    'viagem_id' => $container->viagem_id
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao marcar container como usado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // ✅ MÉTODO CORRIGIDO: Consumir break bulk após ser usado em viagem
    public function consumirBreakBulkParaViagem(Request $request, $breakBulkId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('⚖️ Consumindo break bulk para viagem', [
                'break_bulk_id' => $breakBulkId,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'peso_utilizado' => 'required|numeric|min:0.01',
                'viagem_id' => 'required|exists:viagens,id'
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
            
            // Calcular quantidade baseada no peso por unidade
            $pesoPorUnidade = $breakBulkItem->peso_por_unidade ?: 50; // padrão 50kg se não definido
            $quantidadeUtilizada = ceil($request->peso_utilizado / $pesoPorUnidade);
            
            // Verificar disponibilidade
            $pesoDisponivel = $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado;
            $quantidadeDisponivel = $breakBulkItem->quantidade - $breakBulkItem->quantidade_utilizada;
            
            if ($request->peso_utilizado > $pesoDisponivel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Peso insuficiente disponível. Disponível: ' . $pesoDisponivel . ' kg'
                ], 400);
            }
            
            if ($quantidadeUtilizada > $quantidadeDisponivel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Quantidade insuficiente disponível. Disponível: ' . $quantidadeDisponivel . ' unidades'
                ], 400);
            }
            
            // Atualizar break bulk conforme modelo
            $breakBulkItem->peso_utilizado += $request->peso_utilizado;
            $breakBulkItem->quantidade_utilizada += $quantidadeUtilizada;
            
            // Determinar novo status
            if ($breakBulkItem->peso_utilizado >= $breakBulkItem->peso_total) {
                $breakBulkItem->status = 'loaded';
            } else {
                $breakBulkItem->status = 'partially_used';
            }
            
            // Associar à viagem
            $breakBulkItem->viagem_id = $request->viagem_id;
            $breakBulkItem->save();
            
            Log::info('✅ Break bulk consumido para viagem', [
                'break_bulk_id' => $breakBulkId,
                'viagem_id' => $request->viagem_id,
                'peso_utilizado' => $request->peso_utilizado,
                'peso_total_utilizado' => $breakBulkItem->peso_utilizado,
                'status' => $breakBulkItem->status,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Break bulk consumido para viagem com sucesso!',
                'data' => [
                    'id' => $breakBulkItem->id,
                    'tipo_embalagem' => $breakBulkItem->tipo_embalagem,
                    'peso_disponivel' => $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado,
                    'quantidade_disponivel' => $breakBulkItem->quantidade - $breakBulkItem->quantidade_utilizada,
                    'peso_utilizado' => $breakBulkItem->peso_utilizado,
                    'status' => $breakBulkItem->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao consumir break bulk para viagem: ' . $e->getMessage());
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
                    ->where(function($query) {
                        $query->where('status', 'pending')
                              ->orWhere('status', 'partially_used');
                    })
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
    
    // ✅ MÉTODO ADICIONAL: Verificar status atual do container
    public function getContainerStatus($containerId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $container = Container::where('tenant_id', $tenantId)->find($containerId);
            
            if (!$container) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $container->id,
                    'numero_container' => $container->numero_container,
                    'status' => $container->status,
                    'is_available' => $container->is_available,
                    'viagem_id' => $container->viagem_id,
                    'ordem_id' => $container->ordem_id
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar status do container: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ✅ MÉTODO ADICIONAL: Verificar status atual do break bulk
    public function getBreakBulkStatus($breakBulkId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $breakBulkItem = BreakBulkItem::where('tenant_id', $tenantId)->find($breakBulkId);
            
            if (!$breakBulkItem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Item break bulk não encontrado'
                ], 404);
            }
            
            $pesoDisponivel = max(0, $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado);
            $quantidadeDisponivel = max(0, $breakBulkItem->quantidade - $breakBulkItem->quantidade_utilizada);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $breakBulkItem->id,
                    'tipo_embalagem' => $breakBulkItem->tipo_embalagem,
                    'status' => $breakBulkItem->status,
                    'peso_total' => $breakBulkItem->peso_total,
                    'peso_utilizado' => $breakBulkItem->peso_utilizado,
                    'peso_disponivel' => $pesoDisponivel,
                    'quantidade' => $breakBulkItem->quantidade,
                    'quantidade_utilizada' => $breakBulkItem->quantidade_utilizada,
                    'quantidade_disponivel' => $quantidadeDisponivel,
                    'viagem_id' => $breakBulkItem->viagem_id,
                    'ordem_id' => $breakBulkItem->ordem_id
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar status do break bulk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}