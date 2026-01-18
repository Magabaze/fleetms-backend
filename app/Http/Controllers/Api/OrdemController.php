<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ordem;
use App\Models\Cliente;
use App\Models\Container;
use App\Models\BreakBulkItem;
use App\Models\Viagem;
use App\Models\EmpresaCodigo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrdemController extends Controller
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

    // Obter prefixo da empresa (com fallback automático)
    private function getOuCriarPrefixoEmpresa($tenantId)
    {
        // Primeiro tenta buscar prefixo existente
        $prefixo = $this->getPrefixoEmpresa($tenantId);
        
        // Se não tem, tenta criar automaticamente
        if (!$prefixo) {
            Log::warning('⚠️ Empresa sem prefixo, tentando criar automaticamente...', [
                'tenant_id' => $tenantId
            ]);
            
            $prefixo = $this->criarPrefixoAutomatico($tenantId);
        }
        
        return $prefixo;
    }

    // Buscar prefixo existente
    private function getPrefixoEmpresa($tenantId)
    {
        $empresaCodigo = EmpresaCodigo::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
        
        if (!$empresaCodigo) {
            return null;
        }
        
        return $empresaCodigo->codigo_prefixo;
    }

    // Criar prefixo automático se não existir
    private function criarPrefixoAutomatico($tenantId)
{
    try {
        // Buscar empresa pelo tenant_id
        $empresa = \App\Models\Empresa::where('tenant_id', $tenantId)->first();
        
        if ($empresa) {
            // Usar lógica do Model EmpresaCodigo para criar
            $empresaCodigo = EmpresaCodigo::gerarParaEmpresa(
                $tenantId, 
                $empresa->nome
            );
            
            if ($empresaCodigo) {
                Log::info('✅ Prefixo criado automaticamente', [
                    'tenant_id' => $tenantId,
                    'empresa_nome' => $empresa->nome,
                    'prefixo' => $empresaCodigo->codigo_prefixo
                ]);
                
                return $empresaCodigo->codigo_prefixo;
            }
        }
        
        // Fallback: usar tenant_id como prefixo (último recurso)
        $fallbackPrefix = 'EMP' . substr(str_pad($tenantId, 3, '0', STR_PAD_LEFT), -3);
        
        // Criar registro de fallback
        EmpresaCodigo::create([
            'tenant_id' => $tenantId,
            'codigo_prefixo' => $fallbackPrefix,
            'descricao' => 'Criado automaticamente (fallback)',
            'is_active' => true,
        ]);
        
        Log::warning('⚠️ Usando prefixo de fallback', [
            'tenant_id' => $tenantId,
            'prefixo' => $fallbackPrefix
        ]);
        
        return $fallbackPrefix;
        
    } catch (\Exception $e) {
        Log::error('❌ Falha ao criar prefixo automático: ' . $e->getMessage(), [
            'tenant_id' => $tenantId
        ]);
        return null;
    }
}

    // Gerar número da ordem com 4 dígitos (0001)
    private function gerarNumeroOrdem($tenantId)
    {
        try {
            $prefixo = $this->getOuCriarPrefixoEmpresa($tenantId);
            
            if (!$prefixo) {
                Log::error('❌ Não foi possível obter prefixo da empresa', ['tenant_id' => $tenantId]);
                return null;
            }
            
            Log::info('🏢 Prefixo da empresa para ordem:', [
                'prefixo' => $prefixo,
                'tenant_id' => $tenantId
            ]);
            
            // Buscar última ordem desta empresa (com o mesmo prefixo)
            $lastOrder = Ordem::where('tenant_id', $tenantId)
                ->where('order_numero', 'like', $prefixo . '-%')
                ->orderBy('created_at', 'desc')
                ->first();
            
            $nextNumber = 1;
            if ($lastOrder) {
                // Extrair número da última ordem (formato: XX-0001)
                $parts = explode('-', $lastOrder->order_numero);
                if (count($parts) === 2) {
                    $lastNumber = (int) $parts[1];
                    $nextNumber = $lastNumber + 1;
                    Log::info('🔢 Último número encontrado', [
                        'order_numero' => $lastOrder->order_numero,
                        'last_number' => $lastNumber,
                        'next_number' => $nextNumber
                    ]);
                }
            }
            
            // Gerar número no formato XX-0001 (4 dígitos)
            $orderNumero = $prefixo . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            
            // Verificar se não existe (segurança extra)
            $tentativas = 0;
            while (Ordem::where('tenant_id', $tenantId)->where('order_numero', $orderNumero)->exists() && $tentativas < 10) {
                Log::warning('⚠️ Número duplicado, tentando próximo', ['order_numero' => $orderNumero]);
                $nextNumber++;
                $orderNumero = $prefixo . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                $tentativas++;
            }
            
            Log::info('✅ Número da ordem final', [
                'order_numero' => $orderNumero,
                'tentativas' => $tentativas,
                'tenant_id' => $tenantId,
                'formato' => '4 dígitos (0001)'
            ]);
            
            return $orderNumero;
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar número da ordem: ' . $e->getMessage());
            return null;
        }
    }

    // Converter para camelCase
    private function paraCamelCase($ordem)
    {
        return [
            'id' => $ordem->id,
            'orderNumero' => $ordem->order_numero,
            'tipoTransito' => $ordem->tipo_transito,
            'clienteId' => $ordem->cliente_id,
            'cliente' => $ordem->cliente ? [
                'id' => $ordem->cliente->id,
                'nomeEmpresa' => $ordem->cliente->nome_empresa,
                'tipoCliente' => $ordem->cliente->tipo_cliente,
            ] : null,
            'consigneeId' => $ordem->consignee_id,
            'consignee' => $ordem->consignee ? [
                'id' => $ordem->consignee->id,
                'nomeEmpresa' => $ordem->consignee->nome_empresa,
                'tipoCliente' => $ordem->consignee->tipo_cliente,
            ] : null,
            'expedidorId' => $ordem->expedidor_id,
            'expedidor' => $ordem->expedidor ? [
                'id' => $ordem->expedidor->id,
                'nomeEmpresa' => $ordem->expedidor->nome_empresa,
                'tipoCliente' => $ordem->expedidor->tipo_cliente,
            ] : null,
            'origem' => $ordem->origem,
            'destino' => $ordem->destino,
            'commodity' => $ordem->commodity,
            'tipoCarga' => $ordem->tipo_carga,
            'status' => $ordem->status,
            'createdDate' => $ordem->created_date,
            'previsaoCarregamento' => $ordem->previsao_carregamento,
            'numeroBL' => $ordem->numero_bl,
            'shippingLine' => $ordem->shipping_line,
            'fronteira' => $ordem->fronteira,
            'agenteFronteira' => $ordem->agente_fronteira,
            'taxaClienteId' => $ordem->taxa_cliente_id,
            'moedaFatura' => $ordem->moeda_fatura,
            'pesoTotal' => $ordem->peso_total,
            'volumeTotal' => $ordem->volume_total,
            'observacoes' => $ordem->observacoes,
            'criadoPor' => $ordem->criado_por,
            'aprovadoPor' => $ordem->aprovado_por,
            'empresa' => $ordem->empresa,
            'tenantId' => $ordem->tenant_id,
            'containers' => $ordem->containers ? $ordem->containers->map(function ($container) {
                return [
                    'id' => $container->id,
                    'numeroContainer' => $container->numero_container,
                    'tipoRecipiente' => $container->tipo_recipiente,
                    'tipoCarga' => $container->tipo_carga,
                    'unidade' => $container->unidade,
                    'pesoLiquido' => $container->peso_liquido,
                    'pesoContainer' => $container->peso_container,
                    'pesoTotal' => $container->peso_total,
                    'status' => $container->status,
                    'isAvailable' => $container->is_available,
                    'selo' => $container->selo,
                    'aterramentoRef' => $container->aterramento_ref,
                    'dataValidadeDO' => $container->data_validade_do,
                    'dropOffDetails' => $container->drop_off_details,
                    'depositoContentores' => $container->deposito_contentores,
                    'createdAt' => $container->created_at ? $container->created_at->toISOString() : null,
                ];
            }) : [],
            'breakBulkItems' => $ordem->breakBulkItems ? $ordem->breakBulkItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tipoEmbalagem' => $item->tipo_embalagem,
                    'quantidade' => $item->quantidade,
                    'unidadesEmbalagem' => $item->unidades_embalagem,
                    'pesoPorUnidade' => $item->peso_por_unidade,
                    'pesoTotal' => $item->peso_total,
                    'pesoUtilizado' => $item->peso_utilizado,
                    'quantidadeUtilizada' => $item->quantidade_utilizada,
                    'status' => $item->status,
                    'createdAt' => $item->created_at ? $item->created_at->toISOString() : null,
                ];
            }) : [],
            'createdAt' => $ordem->created_at->toISOString(),
            'updatedAt' => $ordem->updated_at->toISOString()
        ];
    }

    // Função para obter peso do container baseado no tipo
    private function getPesoContainer($tipoRecipiente)
    {
        $pesos = [
            'Container 20" Dry' => 2.2,
            'Container 40" Dry' => 3.7,
            'Container 20" Reefer' => 2.8,
            'Container 40" Reefer' => 4.5,
            'Container 20" Open Top' => 2.3,
            'Container 40" Open Top' => 3.9,
            'Container 20" Flat Rack' => 2.4,
            'Container 40" Flat Rack' => 4.1
        ];
        
        return $pesos[$tipoRecipiente] ?? 0;
    }
    
    // Função para converter peso para toneladas
    private function converterPesoParaToneladas($peso, $unidade)
    {
        switch ($unidade) {
            case 'EM KILOGRAMS':
                return $peso / 1000;
            case 'EM LIBRAS':
                return $peso / 2204.62;
            default:
                return $peso; // Já está em toneladas
        }
    }

    // ========== MÉTODOS PARA VIAGENS ==========

    /**
     * Buscar containers disponíveis de uma ordem específica
     */
    public function containersDisponiveis($ordemId)
    {
        try {
            Log::info('📦 [API] Buscando containers disponíveis para ordem:', ['ordem_id' => $ordemId]);
            
            $tenantId = $this->getTenantId();
            
            $ordem = Ordem::where('tenant_id', $tenantId)->find($ordemId);
            
            if (!$ordem) {
                Log::warning('⚠️ Ordem não encontrada:', ['ordem_id' => $ordemId]);
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            $containers = Container::where('ordem_id', $ordemId)
                ->where('tenant_id', $tenantId)
                ->where('is_available', true)
                ->where('status', '!=', 'loaded')
                ->get();
            
            $containersFormatados = $containers->map(function ($container) {
                return [
                    'id' => $container->id,
                    'numero_container' => $container->numero_container,
                    'tipo_recipiente' => $container->tipo_recipiente,
                    'tipo_carga' => $container->tipo_carga,
                    'peso_liquido' => $container->peso_liquido,
                    'peso_container' => $container->peso_container,
                    'peso_total' => $container->peso_total,
                    'status' => $container->status,
                    'is_available' => $container->is_available,
                    'ordem_id' => $container->ordem_id,
                    'selo' => $container->selo,
                    'aterramento_ref' => $container->aterramento_ref,
                    'data_validade_do' => $container->data_validade_do,
                    'drop_off_details' => $container->drop_off_details,
                    'deposito_contentores' => $container->deposito_contentores,
                    'created_at' => $container->created_at,
                ];
            });
            
            Log::info('✅ Containers disponíveis encontrados:', [
                'ordem_id' => $ordemId,
                'count' => $containersFormatados->count(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $containersFormatados
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar containers disponíveis: ' . $e->getMessage());
            Log::error('❌ Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar break bulk disponível de uma ordem específica
     */
    public function breakBulkDisponivel($ordemId)
    {
        try {
            Log::info('📦 [API] Buscando break bulk disponível para ordem:', ['ordem_id' => $ordemId]);
            
            $tenantId = $this->getTenantId();
            
            $breakBulkItems = BreakBulkItem::where('ordem_id', $ordemId)
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'completed')
                ->get();
            
            $disponibilidade = $breakBulkItems->map(function ($item) {
                $pesoDisponivel = max(0, $item->peso_total - $item->peso_utilizado);
                $quantidadeDisponivel = max(0, $item->quantidade - $item->quantidade_utilizada);
                
                return [
                    'id' => $item->id,
                    'tipo_embalagem' => $item->tipo_embalagem,
                    'quantidade_total' => $item->quantidade,
                    'quantidade_disponivel' => $quantidadeDisponivel,
                    'peso_por_unidade' => $item->peso_por_unidade,
                    'peso_total' => $item->peso_total,
                    'peso_disponivel' => $pesoDisponivel,
                    'status' => $item->status,
                    'unidades_embalagem' => $item->unidades_embalagem,
                    'peso_utilizado' => $item->peso_utilizado,
                    'quantidade_utilizada' => $item->quantidade_utilizada,
                    'ordem_id' => $item->ordem_id
                ];
            });
            
            $disponibilidade = $disponibilidade->filter(function ($item) {
                return $item['peso_disponivel'] > 0;
            })->values();
            
            Log::info('✅ Break bulk disponível encontrado:', [
                'ordem_id' => $ordemId,
                'count' => $disponibilidade->count(),
                'total_peso_disponivel' => $disponibilidade->sum('peso_disponivel'),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $disponibilidade
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar break bulk disponível: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar viabilidade de criar viagem para uma ordem
     */
    public function checkViabilidade($ordemId)
    {
        try {
            Log::info('🔍 [API] Verificando viabilidade da ordem:', ['ordem_id' => $ordemId]);
            
            $tenantId = $this->getTenantId();
            
            $ordem = Ordem::where('tenant_id', $tenantId)
                ->with(['containers', 'breakBulkItems'])
                ->find($ordemId);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            $mensagem = '';
            $viavel = true;
            
            if ($ordem->status !== 'approved') {
                $viavel = false;
                $mensagem = 'Ordem não está aprovada. Status atual: ' . $ordem->status;
            }
            
            if ($viavel) {
                if ($ordem->tipo_carga === 'Container') {
                    $containersDisponiveis = $ordem->containers->where('is_available', true)
                        ->where('status', '!=', 'loaded')
                        ->count();
                    
                    if ($containersDisponiveis === 0) {
                        $viavel = false;
                        $mensagem = 'Nenhum container disponível para esta ordem';
                    }
                    
                } elseif ($ordem->tipo_carga === 'Break Bulk') {
                    $pesoDisponivel = 0;
                    foreach ($ordem->breakBulkItems as $item) {
                        $pesoDisponivel += max(0, $item->peso_total - $item->peso_utilizado);
                    }
                    
                    if ($pesoDisponivel <= 0) {
                        $viavel = false;
                        $mensagem = 'Todo o break bulk já foi consumido';
                    }
                }
            }
            
            Log::info('📊 Viabilidade da ordem:', [
                'ordem_id' => $ordemId,
                'viavel' => $viavel,
                'mensagem' => $mensagem,
                'tipo_carga' => $ordem->tipo_carga,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'viavel' => $viavel,
                    'mensagem' => $mensagem,
                    'tipo_carga' => $ordem->tipo_carga,
                    'status' => $ordem->status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao verificar viabilidade: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar status do container após ser carregado em uma viagem
     */
    public function updateContainerStatus(Request $request, $containerId)
    {
        try {
            Log::info('🔄 [API] Atualizando status do container:', [
                'container_id' => $containerId,
                'dados' => $request->all()
            ]);
            
            $tenantId = $this->getTenantId();
            
            $container = Container::where('tenant_id', $tenantId)->find($containerId);
            
            if (!$container) {
                return response()->json([
                    'success' => false,
                    'error' => 'Container não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,loaded,in_transit,delivered,cancelled',
                'viagem_id' => 'nullable|integer|exists:viagens,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $updateData = [
                'status' => $request->status,
                'is_available' => false,
            ];
            
            if ($request->has('viagem_id')) {
                $updateData['viagem_id'] = $request->viagem_id;
            }
            
            $container->update($updateData);
            
            Log::info('✅ Status do container atualizado:', [
                'container_id' => $container->id,
                'numero_container' => $container->numero_container,
                'novo_status' => $container->status,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Status do container atualizado com sucesso',
                'data' => [
                    'id' => $container->id,
                    'numero_container' => $container->numero_container,
                    'status' => $container->status,
                    'is_available' => $container->is_available
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar status do container: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consumir break bulk (atualizar peso utilizado)
     */
    public function consumirBreakBulk(Request $request, $breakBulkId)
    {
        try {
            Log::info('🔄 [API] Consumindo break bulk:', [
                'break_bulk_id' => $breakBulkId,
                'dados' => $request->all()
            ]);
            
            $tenantId = $this->getTenantId();
            
            $breakBulkItem = BreakBulkItem::where('tenant_id', $tenantId)->find($breakBulkId);
            
            if (!$breakBulkItem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Break bulk item não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'peso_utilizado' => 'required|numeric|min:0',
                'quantidade_utilizada' => 'required|integer|min:0',
                'viagem_id' => 'nullable|integer|exists:viagens,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $pesoUtilizado = $request->peso_utilizado;
            $quantidadeUtilizada = $request->quantidade_utilizada;
            
            $pesoDisponivel = max(0, $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado);
            $quantidadeDisponivel = max(0, $breakBulkItem->quantidade - $breakBulkItem->quantidade_utilizada);
            
            if ($pesoUtilizado > $pesoDisponivel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Peso solicitado excede a disponibilidade. Disponível: ' . $pesoDisponivel
                ], 400);
            }
            
            if ($quantidadeUtilizada > $quantidadeDisponivel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Quantidade solicitada excede a disponibilidade. Disponível: ' . $quantidadeDisponivel
                ], 400);
            }
            
            $novoPesoUtilizado = $breakBulkItem->peso_utilizado + $pesoUtilizado;
            $novaQuantidadeUtilizada = $breakBulkItem->quantidade_utilizada + $quantidadeUtilizada;
            
            $updateData = [
                'peso_utilizado' => $novoPesoUtilizado,
                'quantidade_utilizada' => $novaQuantidadeUtilizada,
            ];
            
            if ($novoPesoUtilizado >= $breakBulkItem->peso_total) {
                $updateData['status'] = 'completed';
            }
            
            if ($request->has('viagem_id')) {
                $updateData['viagem_id'] = $request->viagem_id;
            }
            
            $breakBulkItem->update($updateData);
            
            Log::info('✅ Break bulk consumido:', [
                'break_bulk_id' => $breakBulkItem->id,
                'tipo_embalagem' => $breakBulkItem->tipo_embalagem,
                'peso_utilizado_total' => $breakBulkItem->peso_utilizado,
                'novo_status' => $breakBulkItem->status,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Break bulk atualizado com sucesso',
                'data' => [
                    'id' => $breakBulkItem->id,
                    'tipo_embalagem' => $breakBulkItem->tipo_embalagem,
                    'peso_total' => $breakBulkItem->peso_total,
                    'peso_utilizado' => $breakBulkItem->peso_utilizado,
                    'peso_disponivel' => max(0, $breakBulkItem->peso_total - $breakBulkItem->peso_utilizado),
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

    // ========== MÉTODOS EXISTENTES ==========

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('📥 GET /api/ordens', [
            'query' => $request->all(),
            'tenant_id' => $tenantId
        ]);
        
        try {
            $query = Ordem::where('tenant_id', $tenantId)
                ->with(['cliente', 'consignee', 'expedidor']);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_numero', 'like', "%{$search}%")
                      ->orWhere('numero_bl', 'like', "%{$search}%")
                      ->orWhere('origem', 'like', "%{$search}%")
                      ->orWhere('destino', 'like', "%{$search}%")
                      ->orWhere('commodity', 'like', "%{$search}%")
                      ->orWhere('shipping_line', 'like', "%{$search}%")
                      ->orWhereHas('cliente', function ($q) use ($search) {
                          $q->where('nome_empresa', 'like', "%{$search}%");
                      });
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $ordens = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $ordensCamelCase = $ordens->map(function ($ordem) {
                return $this->paraCamelCase($ordem);
            });
            
            Log::info('✅ Ordens listadas', [
                'total' => $ordens->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $ordensCamelCase->toArray(),
                'pagination' => [
                    'page' => $ordens->currentPage(),
                    'limit' => $perPage,
                    'total' => $ordens->total(),
                    'totalPages' => $ordens->lastPage(),
                    'hasNextPage' => $ordens->hasMorePages(),
                    'hasPrevPage' => $ordens->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar ordens: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'tipoTransito' => 'required|in:Import,Export,Local Import,Local Export,Shutting,Socorro,Interno',
                'clienteId' => 'required|integer|exists:clientes,id',
                'consigneeId' => 'nullable|integer|exists:clientes,id',
                'expedidorId' => 'nullable|integer|exists:clientes,id',
                'origem' => 'required|string|max:255',
                'destino' => 'required|string|max:255',
                'commodity' => 'required|string|max:255',
                'tipoCarga' => 'required|in:Container,Break Bulk,Bulk Loose,General Cargo',
                'empresa' => 'required|string|max:255',
                'moedaFatura' => 'required|in:USD,EUR,MZN,ZAR',
                'createdDate' => 'required|date',
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validação falhou', ['errors' => $validator->errors()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            Log::info('📥 POST /api/ordens', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'empresa' => $request->empresa,
                'dados' => $request->except(['containers', 'breakBulkItems'])
            ]);
            
            // GERAR NÚMERO DA ORDEM (4 dígitos)
            $orderNumero = $this->gerarNumeroOrdem($tenantId);
            
            if (!$orderNumero) {
                throw new \Exception('Não foi possível gerar número da ordem. Sistema de prefixo da empresa não configurado.');
            }
            
            Log::info('🔢 Número da ordem gerado', ['order_numero' => $orderNumero]);
            
            $dados = [
                'order_numero' => $orderNumero,
                'tipo_transito' => $request->tipoTransito,
                'cliente_id' => $request->clienteId,
                'consignee_id' => $request->consigneeId ?? null,
                'expedidor_id' => $request->expedidorId ?? null,
                'origem' => $request->origem,
                'destino' => $request->destino,
                'commodity' => $request->commodity,
                'tipo_carga' => $request->tipoCarga,
                'status' => 'pending',
                'created_date' => $request->createdDate ?? now()->toDateString(),
                'previsao_carregamento' => $request->previsaoCarregamento ?? null,
                'numero_bl' => $request->numeroBL ?? null,
                'shipping_line' => $request->shippingLine ?? null,
                'fronteira' => $request->fronteira ?? null,
                'agente_fronteira' => $request->agenteFronteira ?? null,
                'taxa_cliente_id' => $request->taxaClienteId ?? null,
                'moeda_fatura' => $request->moedaFatura ?? 'USD',
                'peso_total' => $request->pesoTotal ?? '0',
                'volume_total' => $request->volumeTotal ?? null,
                'observacoes' => $request->observacoes ?? null,
                'criado_por' => $user->name ?? 'Sistema',
                'empresa' => $request->empresa ?? 'TCM BEIRA',
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Dados para criação da ordem', $dados);
            
            $ordem = Ordem::create($dados);
            
            if (!$ordem) {
                throw new \Exception('Falha ao criar ordem no banco de dados');
            }
            
            Log::info('✅ Ordem criada com sucesso', [
                'ordem_id' => $ordem->id,
                'order_numero' => $ordem->order_numero,
                'tipo_carga' => $ordem->tipo_carga,
                'tenant_id' => $tenantId
            ]);
            
            // SALVAR CONTAINERS
            if ($request->tipoCarga === 'Container' && $request->has('containers')) {
                $containers = $request->containers;
                Log::info('📦 Salvando containers', [
                    'count' => count($containers),
                    'tenant_id' => $tenantId
                ]);
                
                foreach ($containers as $containerData) {
                    $pesoContainer = $this->getPesoContainer($containerData['tipoRecipiente']);
                    $pesoLiquido = floatval($containerData['pesoLiquido']) ?? 0;
                    $pesoTotalBruto = $pesoLiquido + $pesoContainer;
                    
                    $pesoTotalFinal = $this->converterPesoParaToneladas(
                        $pesoTotalBruto, 
                        $containerData['unidade']
                    );
                    
                    $pesoLiquidoFinal = $this->converterPesoParaToneladas(
                        $pesoLiquido,
                        $containerData['unidade']
                    );
                    
                    $container = Container::create([
                        'ordem_id' => $ordem->id,
                        'tipo_recipiente' => $containerData['tipoRecipiente'],
                        'tipo_carga' => $containerData['tipoCarga'] ?? 'FCL',
                        'unidade' => $containerData['unidade'] ?? 'EM TONELADAS MÉTRICAS',
                        'peso_liquido' => $pesoLiquidoFinal,
                        'peso_container' => $pesoContainer,
                        'peso_total' => $pesoTotalFinal,
                        'numero_container' => $containerData['numeroContainer'],
                        'selo' => $containerData['selo'] ?? null,
                        'aterramento_ref' => $containerData['aterramentoRef'] ?? null,
                        'data_validade_do' => $containerData['dataValidadeDO'] ?? null,
                        'drop_off_details' => $containerData['dropOffDetails'],
                        'deposito_contentores' => $containerData['depositoContentores'],
                        'status' => 'pending',
                        'is_available' => true,
                        'tenant_id' => $tenantId,
                    ]);
                    
                    Log::info('✅ Container criado', [
                        'container_id' => $container->id,
                        'numero_container' => $container->numero_container,
                        'peso_total' => $container->peso_total,
                        'tenant_id' => $tenantId
                    ]);
                }
            }
            
            // SALVAR BREAK BULK
            if ($request->tipoCarga === 'Break Bulk' && $request->has('breakBulkItems')) {
                $breakBulkItems = $request->breakBulkItems;
                Log::info('📦 Salvando break bulk items', [
                    'count' => count($breakBulkItems),
                    'tenant_id' => $tenantId
                ]);
                
                foreach ($breakBulkItems as $itemData) {
                    $breakBulkItem = BreakBulkItem::create([
                        'ordem_id' => $ordem->id,
                        'tipo_embalagem' => $itemData['tipoEmbalagem'],
                        'quantidade' => $itemData['quantidade'] ?? 0,
                        'unidades_embalagem' => $itemData['unidadesEmbalagem'],
                        'peso_por_unidade' => $itemData['pesoPorUnidade'] ?? 0,
                        'peso_total' => $itemData['pesoTotal'] ?? 0,
                        'peso_utilizado' => 0,
                        'quantidade_utilizada' => 0,
                        'status' => 'pending',
                        'tenant_id' => $tenantId,
                    ]);
                    
                    Log::info('✅ Break bulk item criado', [
                        'break_bulk_id' => $breakBulkItem->id,
                        'tipo_embalagem' => $breakBulkItem->tipo_embalagem,
                        'peso_total' => $breakBulkItem->peso_total,
                        'tenant_id' => $tenantId
                    ]);
                }
            }
            
            $ordem->load(['cliente', 'consignee', 'expedidor', 'containers', 'breakBulkItems']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem),
                'message' => 'Ordem criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar ordem: ' . $e->getMessage());
            Log::error('❌ Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $ordem = Ordem::where('tenant_id', $tenantId)
                ->with(['cliente', 'consignee', 'expedidor', 'containers', 'breakBulkItems'])
                ->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar ordem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('📥 PUT /api/ordens/' . $id, [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $ordem = Ordem::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'tipoTransito' => 'sometimes|in:Import,Export,Local Import,Local Export,Shutting,Socorro,Interno',
                'clienteId' => 'sometimes|integer|exists:clientes,id',
                'consigneeId' => 'nullable|integer|exists:clientes,id',
                'expedidorId' => 'nullable|integer|exists:clientes,id',
                'origem' => 'sometimes|string|max:255',
                'destino' => 'sometimes|string|max:255',
                'commodity' => 'sometimes|string|max:255',
                'tipoCarga' => 'sometimes|in:Container,Break Bulk,Bulk Loose,General Cargo',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $updateData = [];
            
            $fieldMapping = [
                'tipoTransito' => 'tipo_transito',
                'clienteId' => 'cliente_id',
                'consigneeId' => 'consignee_id',
                'expedidorId' => 'expedidor_id',
                'tipoCarga' => 'tipo_carga',
                'previsaoCarregamento' => 'previsao_carregamento',
                'numeroBL' => 'numero_bl',
                'shippingLine' => 'shipping_line',
                'agenteFronteira' => 'agente_fronteira',
                'taxaClienteId' => 'taxa_cliente_id',
                'moedaFatura' => 'moeda_fatura',
                'pesoTotal' => 'peso_total',
                'volumeTotal' => 'volume_total',
            ];
            
            foreach ($fieldMapping as $camelField => $snakeField) {
                if ($request->has($camelField)) {
                    $updateData[$snakeField] = $request->$camelField;
                }
            }
            
            $directFields = ['origem', 'destino', 'commodity', 'fronteira', 'observacoes'];
            foreach ($directFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }
            
            Log::info('📝 Dados para atualização', array_merge($updateData, ['tenant_id' => $tenantId]));
            
            $ordem->update($updateData);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem->fresh()->load(['cliente', 'consignee', 'expedidor', 'containers', 'breakBulkItems'])),
                'message' => 'Ordem atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar ordem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $tenantId = $this->getTenantId();
            $user = Auth::user();
            
            $ordem = Ordem::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,completed,cancelled',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $updateData = ['status' => $request->status];
            
            if ($request->status === 'approved') {
                $updateData['aprovado_por'] = $user->name ?? 'Sistema';
                $updateData['aprovado_em'] = now();
            }
            
            $ordem->update($updateData);
            
            Log::info('✅ Status da ordem atualizado', [
                'ordem_id' => $id,
                'novo_status' => $request->status,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem->fresh()),
                'message' => 'Status da ordem atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar status da ordem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $ordem = Ordem::where('tenant_id', $tenantId)->find($id);
            
            if (!$ordem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ordem não encontrada'
                ], 404);
            }
            
            $ordem->delete();
            
            Log::info('✅ Ordem excluída', [
                'id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ordem excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir ordem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function clientesSelect()
    {
        try {
            $tenantId = $this->getTenantId();
            
            $clientes = Cliente::where('tenant_id', $tenantId)
                ->select('id', 'nome_empresa as nomeEmpresa', 'tipo_cliente as tipoCliente')
                ->orderBy('nome_empresa')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $clientes
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar clientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function commodities()
    {
        try {
            $tenantId = $this->getTenantId();
            
            $commodities = Ordem::where('tenant_id', $tenantId)
                ->distinct('commodity')
                ->orderBy('commodity')
                ->pluck('commodity')
                ->filter()
                ->values()
                ->toArray();
            
            return response()->json([
                'success' => true,
                'data' => $commodities
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar commodities: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // ESTATÍSTICAS DE NUMERAÇÃO DAS ORDENS
    public function estatisticasNumeracao()
    {
        try {
            $tenantId = $this->getTenantId();
            
            $prefixo = $this->getOuCriarPrefixoEmpresa($tenantId);
            
            if (!$prefixo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Empresa não tem código configurado'
                ], 404);
            }
            
            // Contar ordens da empresa
            $totalOrdens = Ordem::where('tenant_id', $tenantId)
                ->where('order_numero', 'like', $prefixo . '-%')
                ->count();
            
            // Última ordem
            $ultimaOrdem = Ordem::where('tenant_id', $tenantId)
                ->where('order_numero', 'like', $prefixo . '-%')
                ->orderBy('created_at', 'desc')
                ->first();
            
            // Próximo número (4 dígitos)
            $proximoNumero = 1;
            if ($ultimaOrdem) {
                $parts = explode('-', $ultimaOrdem->order_numero);
                if (count($parts) === 2) {
                    $proximoNumero = intval($parts[1]) + 1;
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'codigo_prefixo' => $prefixo,
                    'total_ordens' => $totalOrdens,
                    'ultima_ordem' => $ultimaOrdem ? $ultimaOrdem->order_numero : 'Nenhuma ordem',
                    'proximo_numero' => $prefixo . '-' . str_pad($proximoNumero, 4, '0', STR_PAD_LEFT),
                    'formato' => 'XX-0001 (4 dígitos)'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar estatísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}