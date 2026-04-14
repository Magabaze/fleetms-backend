<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ordem;
use App\Models\Cliente;
use App\Models\Container;
use App\Models\BreakBulkItem;
use App\Models\Rate;
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

    private function getTenantId()
    {
        $user = Auth::user();
        return $user->tenant_id ?? 'default';
    }

    private function getOuCriarPrefixoEmpresa($tenantId)
    {
        $prefixo = $this->getPrefixoEmpresa($tenantId);
        
        if (!$prefixo) {
            Log::warning('⚠️ Empresa sem prefixo, tentando criar automaticamente...', [
                'tenant_id' => $tenantId
            ]);
            
            $prefixo = $this->criarPrefixoAutomatico($tenantId);
        }
        
        return $prefixo;
    }

    private function getPrefixoEmpresa($tenantId)
    {
        $empresaCodigo = EmpresaCodigo::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
        
        return $empresaCodigo ? $empresaCodigo->codigo_prefixo : null;
    }

    private function criarPrefixoAutomatico($tenantId)
    {
        try {
            $empresa = \App\Models\Empresa::where('tenant_id', $tenantId)->first();
            
            if ($empresa) {
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
            
            $fallbackPrefix = 'EMP' . substr(str_pad($tenantId, 3, '0', STR_PAD_LEFT), -3);
            
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
            
            $lastOrder = Ordem::where('tenant_id', $tenantId)
                ->where('order_numero', 'like', $prefixo . '-%')
                ->orderBy('created_at', 'desc')
                ->first();
            
            $nextNumber = 1;
            if ($lastOrder) {
                $parts = explode('-', $lastOrder->order_numero);
                if (count($parts) === 2) {
                    $lastNumber = (int) $parts[1];
                    $nextNumber = $lastNumber + 1;
                }
            }
            
            $orderNumero = $prefixo . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            
            $tentativas = 0;
            while (Ordem::where('tenant_id', $tenantId)->where('order_numero', $orderNumero)->exists() && $tentativas < 10) {
                $nextNumber++;
                $orderNumero = $prefixo . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                $tentativas++;
            }
            
            Log::info('✅ Número da ordem final', [
                'order_numero' => $orderNumero,
                'tenant_id' => $tenantId
            ]);
            
            return $orderNumero;
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar número da ordem: ' . $e->getMessage());
            return null;
        }
    }

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
    
    private function converterPesoParaToneladas($peso, $unidade)
    {
        switch ($unidade) {
            case 'EM KILOGRAMS':
                return $peso / 1000;
            case 'EM LIBRAS':
                return $peso / 2204.62;
            default:
                return $peso;
        }
    }

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
            'rateId' => $ordem->rate_id,
            'rate' => $ordem->rate ? [
                'id' => $ordem->rate->id,
                'clienteNome' => $ordem->rate->cliente_nome,
                'moeda' => $ordem->rate->moeda,
                'validade' => $ordem->rate->validade,
                'status' => $ordem->rate->status,
                'distanciaRota' => $ordem->rate->distancia_rota,
                'criadoPor' => $ordem->rate->criado_por,
            ] : null,
            'containers' => $ordem->containers ? $ordem->containers->map(function ($container) {
                return [
                    'id' => $container->id,
                    'numeroContainer' => $container->numero_container,
                    'tipoRecipiente' => $container->tipo_recipiente,
                    'tipoCarga' => $container->tipo_carga,
                    'unidade' => $container->unidade,
                    'pesoLiquido' => $container->peso_liquido,
                    'pesoTotal' => $container->peso_total,
                    'selo' => $container->selo,
                    'aterramentoRef' => $container->aterramento_ref,
                    'dataValidadeDO' => $container->data_validade_do,
                    'dropOffDetails' => $container->drop_off_details,
                    'depositoContentores' => $container->deposito_contentores,
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
                ];
            }) : [],
            'createdAt' => $ordem->created_at->toISOString(),
            'updatedAt' => $ordem->updated_at->toISOString()
        ];
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        
        try {
            // VALIDAÇÃO COM RATE OBRIGATÓRIO
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
                'rateId' => 'required|integer|exists:rates,id', // RATE É OBRIGATÓRIO
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
                'rate_id' => $request->rateId,
                'empresa' => $request->empresa,
            ]);
            
            // Verificar se o rate existe e está aprovado
            $rate = Rate::where('id', $request->rateId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'aprovado')
                ->first();
            
            if (!$rate) {
                throw new \Exception('Rate não encontrado ou não está aprovado');
            }
            
            // Gerar número da ordem
            $orderNumero = $this->gerarNumeroOrdem($tenantId);
            
            if (!$orderNumero) {
                throw new \Exception('Não foi possível gerar número da ordem.');
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
                'rate_id' => $request->rateId, // SALVAR RATE_ID
            ];
            
            Log::info('💾 Dados para criação da ordem', $dados);
            
            $ordem = Ordem::create($dados);
            
            if (!$ordem) {
                throw new \Exception('Falha ao criar ordem no banco de dados');
            }
            
            Log::info('✅ Ordem criada com sucesso', [
                'ordem_id' => $ordem->id,
                'order_numero' => $ordem->order_numero,
                'rate_id' => $ordem->rate_id,
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
                    
                    Container::create([
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
                    BreakBulkItem::create([
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
                }
            }
            
            $ordem->load(['cliente', 'consignee', 'expedidor', 'containers', 'breakBulkItems', 'rate']);
            
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
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        try {
            $query = Ordem::where('tenant_id', $tenantId)
                ->with(['cliente', 'consignee', 'expedidor', 'rate']);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_numero', 'like', "%{$search}%")
                      ->orWhere('numero_bl', 'like', "%{$search}%")
                      ->orWhere('origem', 'like', "%{$search}%")
                      ->orWhere('destino', 'like', "%{$search}%")
                      ->orWhere('commodity', 'like', "%{$search}%")
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
            
            return response()->json([
                'success' => true,
                'data' => $ordensCamelCase->toArray(),
                'pagination' => [
                    'page' => $ordens->currentPage(),
                    'limit' => $perPage,
                    'total' => $ordens->total(),
                    'totalPages' => $ordens->lastPage(),
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

    public function show($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $ordem = Ordem::where('tenant_id', $tenantId)
                ->with(['cliente', 'consignee', 'expedidor', 'containers', 'breakBulkItems', 'rate'])
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
                'rateId' => 'sometimes|integer|exists:rates,id',
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
                'rateId' => 'rate_id',
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
            
            $ordem->update($updateData);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($ordem->fresh()->load(['cliente', 'consignee', 'expedidor', 'containers', 'breakBulkItems', 'rate'])),
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
            
            $totalOrdens = Ordem::where('tenant_id', $tenantId)
                ->where('order_numero', 'like', $prefixo . '-%')
                ->count();
            
            $ultimaOrdem = Ordem::where('tenant_id', $tenantId)
                ->where('order_numero', 'like', $prefixo . '-%')
                ->orderBy('created_at', 'desc')
                ->first();
            
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