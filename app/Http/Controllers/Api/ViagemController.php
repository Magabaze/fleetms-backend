<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\Camiao;
use App\Models\Trela;
use App\Models\Motorista;
use App\Models\Ordem;
use App\Models\Cliente;
use App\Models\EmpresaCodigo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ViagemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    // Método auxiliar para obter o tenant_id atual
    private function getTenantId()
    {
        if (Auth::check() && Auth::user()->tenant_id) {
            return Auth::user()->tenant_id;
        }
        
        if (class_exists('Stancl\Tenancy\Facades\Tenancy') && tenancy()->initialized) {
            return tenancy()->tenant->id;
        }
        
        return 'default';
    }

    // Obter ou criar prefixo da empresa
    private function getOuCriarPrefixoEmpresa($tenantId)
    {
        try {
            // Primeiro, tentar buscar um prefixo existente
            $empresaCodigo = EmpresaCodigo::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();
            
            if ($empresaCodigo) {
                Log::info('✅ Prefixo encontrado na base de dados', [
                    'tenant_id' => $tenantId,
                    'prefixo' => $empresaCodigo->codigo_prefixo
                ]);
                return $empresaCodigo->codigo_prefixo;
            }
            
            // Se não existir, criar automaticamente
            Log::info('🔧 Nenhum prefixo encontrado, criando automaticamente...', [
                'tenant_id' => $tenantId
            ]);
            return $this->criarPrefixoAutomatico($tenantId);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao obter/criar prefixo: ' . $e->getMessage(), [
                'tenant_id' => $tenantId
            ]);
            return null;
        }
    }

    // Criar prefixo automático
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
            
            // Fallback de emergência
            $emergencyPrefix = 'VIP' . substr(str_pad($tenantId, 3, '0', STR_PAD_LEFT), -3);
            Log::critical('🚨 Usando prefixo de emergência', [
                'tenant_id' => $tenantId,
                'prefixo' => $emergencyPrefix
            ]);
            
            return $emergencyPrefix;
        }
    }

    // Gerar número de viagem com 5 dígitos (00001)
    private function gerarNumeroViagem($tenantId)
    {
        try {
            $prefixo = $this->getOuCriarPrefixoEmpresa($tenantId);
            
            if (!$prefixo) {
                // Fallback de emergência
                $prefixo = 'VIP' . substr(str_pad($tenantId, 3, '0', STR_PAD_LEFT), -3);
                Log::warning('⚠️ Usando fallback de emergência para prefixo', [
                    'tenant_id' => $tenantId,
                    'prefixo' => $prefixo
                ]);
            }
            
            // Buscar última viagem com este prefixo
            $ultimaViagem = Viagem::where('tenant_id', $tenantId)
                ->where('trip_number', 'like', $prefixo . '-%')
                ->orderBy('created_at', 'desc')
                ->first();
            
            // Determinar próximo número com 5 dígitos
            $proximoNumero = 1;
            if ($ultimaViagem) {
                // Extrair número da última viagem (formato: XX-00001)
                $parts = explode('-', $ultimaViagem->trip_number);
                if (count($parts) === 2 && is_numeric($parts[1])) {
                    $proximoNumero = intval($parts[1]) + 1;
                }
            }
            
            // Formatar com 5 dígitos
            $tripNumber = $prefixo . '-' . str_pad($proximoNumero, 5, '0', STR_PAD_LEFT);
            
            // Verificar duplicado (por segurança)
            $existente = Viagem::where('trip_number', $tripNumber)
                ->where('tenant_id', $tenantId)
                ->exists();
                
            if ($existente) {
                $proximoNumero++;
                $tripNumber = $prefixo . '-' . str_pad($proximoNumero, 5, '0', STR_PAD_LEFT);
            }
            
            Log::info('🔢 Número de viagem gerado', [
                'tenant_id' => $tenantId,
                'prefixo' => $prefixo,
                'numero' => $proximoNumero,
                'trip_number' => $tripNumber,
                'formato' => '5 dígitos (00001)'
            ]);
            
            return $tripNumber;
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar número de viagem: ' . $e->getMessage());
            
            // Fallback de emergência total
            $emergencyNumber = 'EMG-' . date('Ymd') . '-' . rand(1000, 9999);
            Log::critical('🚨 Usando número de emergência: ' . $emergencyNumber);
            
            return $emergencyNumber;
        }
    }

    // Converter snake_case para camelCase
    private function paraCamelCase($viagem)
    {
        return [
            'id' => $viagem->id,
            'tripNumber' => $viagem->trip_number,
            'tripSlno' => $viagem->trip_slno,
            'orderNumber' => $viagem->order_number,
            'customerName' => $viagem->customer_name,
            'fromStation' => $viagem->from_station,
            'toStation' => $viagem->to_station,
            'truckNumber' => $viagem->truck_number,
            'trailerNumber' => $viagem->trailer_number,
            'driver' => $viagem->driver,
            'containerNo' => $viagem->container_no,
            'blNumber' => $viagem->bl_number,
            'commodity' => $viagem->commodity,
            'cargoType' => $viagem->cargo_type,
            'weight' => $viagem->weight,
            'status' => $viagem->status,
            'currentStatus' => $viagem->current_status,
            'scheduleDate' => $viagem->schedule_date->toDateString(),
            'deliveryDate' => $viagem->delivery_date?->toDateString(),
            'actualDelivery' => $viagem->actual_delivery?->toDateString(),
            'podDeliveryDate' => $viagem->pod_delivery_date?->toDateString(),
            'currentPosition' => $viagem->current_position,
            'trackingComments' => $viagem->tracking_comments,
            'borderArrivalDate' => $viagem->border_arrival_date?->toDateString(),
            'borderDemurrageDays' => $viagem->border_demurrage_days,
            'offloadingArrivalDate' => $viagem->offloading_arrival_date?->toDateString(),
            'offloadingDemurrageDays' => $viagem->offloading_demurrage_days,
            'isEmptyTrip' => (bool)$viagem->is_empty_trip,
            'isCompanyOwned' => (bool)$viagem->is_company_owned,
            'isReadyForInvoice' => (bool)$viagem->is_ready_for_invoice,
            'invoiceNumber' => $viagem->invoice_number,
            'transporter' => $viagem->transporter,
            'orderOwner' => $viagem->order_owner,
            'createdBy' => $viagem->created_by,
            'tenantId' => $viagem->tenant_id,
            'createdAt' => $viagem->created_at->toISOString(),
            'updatedAt' => $viagem->updated_at->toISOString()
        ];
    }

    // VERIFICAR STATUS DO MOTORISTA
    public function verificarStatusMotorista($motoristaNome)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('🔍 Verificando status do motorista:', [
                'motorista' => $motoristaNome,
                'tenant_id' => $tenantId
            ]);
            
            $viagensAtivas = Viagem::where('driver', $motoristaNome)
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'Closed')
                ->get();
            
            $disponivel = $viagensAtivas->isEmpty();
            $mensagem = $disponivel 
                ? 'Motorista disponível' 
                : 'Motorista já está em uma viagem ativa. Status atual: ' . ($viagensAtivas->first() ? $viagensAtivas->first()->status : 'Desconhecido');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'disponivel' => $disponivel,
                    'mensagem' => $mensagem,
                    'viagensAtivas' => $viagensAtivas->count(),
                    'statusAtual' => $disponivel ? 'Disponível' : 'Ocupado',
                    'tenant_id' => $tenantId
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao verificar status do motorista: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // VERIFICAR STATUS DO CAMIÃO
    public function verificarStatusCamiao($matricula)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('🔍 Verificando status do camião:', [
                'matricula' => $matricula,
                'tenant_id' => $tenantId
            ]);
            
            $viagensAtivas = Viagem::where('truck_number', $matricula)
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'Closed')
                ->get();
            
            $disponivel = $viagensAtivas->isEmpty();
            $mensagem = $disponivel 
                ? 'Camião disponível' 
                : 'Camião já está em uma viagem ativa. Status atual: ' . ($viagensAtivas->first() ? $viagensAtivas->first()->status : 'Desconhecido');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'disponivel' => $disponivel,
                    'mensagem' => $mensagem,
                    'viagensAtivas' => $viagensAtivas->count(),
                    'statusAtual' => $disponivel ? 'Disponível' : 'Ocupado',
                    'tenant_id' => $tenantId
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao verificar status do camião: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // VERIFICAR STATUS DA TRELÁ
    public function verificarStatusTrela($matricula)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('🔍 Verificando status da trelá:', [
                'matricula' => $matricula,
                'tenant_id' => $tenantId
            ]);
            
            $viagensAtivas = Viagem::where('trailer_number', $matricula)
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'Closed')
                ->get();
            
            $disponivel = $viagensAtivas->isEmpty();
            $mensagem = $disponivel 
                ? 'Trelá disponível' 
                : 'Trelá já está em uma viagem ativa. Status atual: ' . ($viagensAtivas->first() ? $viagensAtivas->first()->status : 'Desconhecido');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'disponivel' => $disponivel,
                    'mensagem' => $mensagem,
                    'viagensAtivas' => $viagensAtivas->count(),
                    'statusAtual' => $disponivel ? 'Disponível' : 'Ocupado',
                    'tenant_id' => $tenantId
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao verificar status da trelá: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // Buscar recursos para criar viagem
    public function recursos()
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('🔍 [ViagemController] Buscando recursos para viagem...', [
                'tenant_id' => $tenantId
            ]);
            
            // Camiões disponíveis
            $camioesDisponiveis = [];
            $camioes = Camiao::where('tenant_id', $tenantId)
                ->where('estado', 'Operacional')
                ->select('id', 'matricula', 'marca', 'modelo', 'capacidade_carga as capacidadeCarga')
                ->orderBy('matricula')
                ->get();
            
            foreach ($camioes as $camiao) {
                $viagemAtiva = Viagem::where('truck_number', $camiao->matricula)
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'Closed')
                    ->exists();
                
                if (!$viagemAtiva) {
                    $camioesDisponiveis[] = $camiao;
                }
            }
            
            Log::info('🚚 Camiões disponíveis: ' . count($camioesDisponiveis) . ' de ' . $camioes->count(), [
                'tenant_id' => $tenantId
            ]);
            
            // Trelas disponíveis
            $trelasDisponiveis = [];
            $trelas = Trela::where('tenant_id', $tenantId)
                ->where('estado', 'Operacional')
                ->select('id', 'matricula', 'marca', 'modelo', 'tipo_trela as tipoTrela')
                ->orderBy('matricula')
                ->get();
            
            foreach ($trelas as $trela) {
                $viagemAtiva = Viagem::where('trailer_number', $trela->matricula)
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'Closed')
                    ->exists();
                
                if (!$viagemAtiva) {
                    $trelasDisponiveis[] = $trela;
                }
            }
            
            Log::info('📦 Trelas disponíveis: ' . count($trelasDisponiveis) . ' de ' . $trelas->count(), [
                'tenant_id' => $tenantId
            ]);
            
            // Motoristas disponíveis
            $motoristasDisponiveis = [];
            $motoristas = Motorista::where('tenant_id', $tenantId)
                ->where('status', 'Ativo')
                ->select('id', 'nome_completo as nomeCompleto', 'numero_carta as numeroCarta')
                ->orderBy('nome_completo')
                ->get();
            
            foreach ($motoristas as $motorista) {
                $viagemAtiva = Viagem::where('driver', $motorista->nomeCompleto)
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'Closed')
                    ->exists();
                
                if (!$viagemAtiva) {
                    $motoristasDisponiveis[] = $motorista;
                }
            }
            
            Log::info('👨‍✈️ Motoristas disponíveis: ' . count($motoristasDisponiveis) . ' de ' . $motoristas->count(), [
                'tenant_id' => $tenantId
            ]);
            
            // Ordens ativas
            $ordens = Ordem::where('ordens.tenant_id', $tenantId)
                ->where('ordens.status', 'approved')
                ->leftJoin('clientes', 'ordens.cliente_id', '=', 'clientes.id')
                ->select(
                    'ordens.id',
                    'ordens.order_numero as orderNumero',
                    'clientes.nome_empresa as clienteNome',
                    'ordens.origem',
                    'ordens.destino',
                    'ordens.commodity',
                    'ordens.tipo_carga as tipoCarga',
                    'ordens.peso_total',
                    'ordens.volume_total'
                )
                ->orderBy('ordens.created_at', 'desc')
                ->get();
            
            Log::info('📋 Ordens encontradas: ' . $ordens->count(), [
                'tenant_id' => $tenantId
            ]);
            
            // Buscar prefixo da empresa
            $prefixoEmpresa = $this->getOuCriarPrefixoEmpresa($tenantId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'camioes' => $camioesDisponiveis,
                    'trelas' => $trelasDisponiveis,
                    'motoristas' => $motoristasDisponiveis,
                    'ordens' => $ordens,
                    'empresa_prefixo' => $prefixoEmpresa,
                    'formato_viagem' => 'XX-00001 (5 dígitos)'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar recursos: ' . $e->getMessage());
            Log::error('🔧 Trace: ' . $e->getTraceAsString());
            
            // Em caso de erro, retornar arrays vazios para não quebrar o frontend
            return response()->json([
                'success' => true,
                'data' => [
                    'camioes' => [],
                    'trelas' => [],
                    'motoristas' => [],
                    'ordens' => [],
                    'empresa_prefixo' => 'EMP001',
                    'formato_viagem' => 'XX-00001 (5 dígitos)'
                ]
            ]);
        }
    }

    public function index(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('📥 GET /api/viagens', [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId,
                'query' => $request->all()
            ]);
            
            $query = Viagem::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('trip_number', 'like', "%{$search}%")
                      ->orWhere('order_number', 'like', "%{$search}%")
                      ->orWhere('container_no', 'like', "%{$search}%")
                      ->orWhere('truck_number', 'like', "%{$search}%")
                      ->orWhere('driver', 'like', "%{$search}%")
                      ->orWhere('customer_name', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('isEmptyTrip') && $request->isEmptyTrip !== '') {
                $query->where('is_empty_trip', $request->isEmptyTrip === 'true');
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $viagens = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $viagensCamelCase = $viagens->map(function ($viagem) {
                return $this->paraCamelCase($viagem);
            });
            
            Log::info('✅ Viagens listadas', [
                'total' => $viagens->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $viagensCamelCase->toArray(),
                'pagination' => [
                    'page' => $viagens->currentPage(),
                    'limit' => $perPage,
                    'total' => $viagens->total(),
                    'totalPages' => $viagens->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar viagens: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('📥 POST /api/viagens', [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'truckNumber' => 'required|string|max:20',
                'trailerNumber' => 'nullable|string|max:20',
                'driver' => 'required|string|max:255',
                'scheduleDate' => 'required|date',
                'fromStation' => 'required|string|max:100',
                'toStation' => 'required|string|max:100',
                'customerName' => 'required|string|max:255',
                'cargoType' => 'required|in:Container,Break Bulk,General Cargo,Empty',
                'orderId' => 'nullable|integer|exists:ordens,id',
                'containerNo' => 'nullable|string|max:50',
                'blNumber' => 'nullable|string|max:50',
                'commodity' => 'required|string|max:100',
                'weight' => 'nullable|numeric|min:0',
                'isEmptyTrip' => 'required|boolean',
                'isCompanyOwned' => 'boolean',
                'createdBy' => 'required|string|max:255',
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Verificar camião
            $camiao = Camiao::where('matricula', $request->truckNumber)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$camiao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Camião não encontrado'
                ], 404);
            }
            
            // VERIFICAÇÃO 1: Verificar se camião tem viagens ativas
            $camiaoAtivo = Viagem::where('truck_number', $request->truckNumber)
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'Closed')
                ->exists();
                
            if ($camiaoAtivo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Camião já está em uma viagem ativa. Só pode criar nova viagem quando a anterior estiver fechada (status Closed).'
                ], 409);
            }
            
            // Verificar motorista
            $motorista = Motorista::where('nome_completo', $request->driver)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$motorista) {
                return response()->json([
                    'success' => false,
                    'error' => 'Motorista não encontrado'
                ], 404);
            }
            
            // VERIFICAÇÃO 2: Verificar se motorista tem viagens ativas
            $motoristaAtivo = Viagem::where('driver', $request->driver)
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'Closed')
                ->exists();
                
            if ($motoristaAtivo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Motorista já está em uma viagem ativa. Só pode criar nova viagem quando a anterior estiver fechada (status Closed).'
                ], 409);
            }
            
            // VERIFICAÇÃO 3: Se tiver trela, verificar se está disponível
            if ($request->trailerNumber) {
                $trelaAtiva = Viagem::where('trailer_number', $request->trailerNumber)
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'Closed')
                    ->exists();
                    
                if ($trelaAtiva) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Trelá já está em uma viagem ativa. Só pode criar nova viagem quando a anterior estiver fechada (status Closed).'
                    ], 409);
                }
            }
            
            // Se tiver ordem, buscar informações
            $orderData = null;
            if ($request->orderId) {
                $ordem = Ordem::where('id', $request->orderId)
                    ->where('tenant_id', $tenantId)
                    ->first();
                    
                if ($ordem) {
                    $cliente = Cliente::where('id', $ordem->cliente_id)
                        ->where('tenant_id', $tenantId)
                        ->first();
                        
                    $clienteNome = $cliente ? $cliente->nome_empresa : 'Cliente não encontrado';
                    
                    $orderData = [
                        'order_number' => $ordem->order_numero,
                        'customer_name' => $clienteNome,
                        'from_station' => $ordem->origem,
                        'to_station' => $ordem->destino,
                        'commodity' => $ordem->commodity,
                        'cargo_type' => $ordem->tipo_carga
                    ];
                }
            }
            
            // GERAR NÚMERO DE VIAGEM (5 dígitos)
            $tripNumber = $this->gerarNumeroViagem($tenantId);
            
            if (!$tripNumber) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não foi possível gerar número de viagem. Sistema de prefixo da empresa não configurado.'
                ], 500);
            }
            
            $tripSlno = '1';
            
            $dados = [
                'trip_number' => $tripNumber,
                'trip_slno' => $tripSlno,
                'truck_number' => $request->truckNumber,
                'trailer_number' => $request->trailerNumber,
                'driver' => $request->driver,
                'schedule_date' => $request->scheduleDate,
                'from_station' => $orderData['from_station'] ?? $request->fromStation,
                'to_station' => $orderData['to_station'] ?? $request->toStation,
                'customer_name' => $orderData['customer_name'] ?? $request->customerName,
                'cargo_type' => $request->cargoType,
                'order_number' => $orderData['order_number'] ?? null,
                'container_no' => $request->containerNo,
                'bl_number' => $request->blNumber,
                'commodity' => $orderData['commodity'] ?? $request->commodity,
                'weight' => $request->weight,
                'status' => 'PENDING',
                'current_status' => 'SCHEDULED',
                'is_empty_trip' => $request->isEmptyTrip,
                'is_company_owned' => $request->isCompanyOwned ?? true,
                'created_by' => $request->createdBy,
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando viagem', $dados);
            
            $viagem = Viagem::create($dados);
            
            Log::info('✅ Viagem criada', [
                'id' => $viagem->id,
                'tripNumber' => $tripNumber,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($viagem),
                'message' => 'Viagem criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar viagem: ' . $e->getMessage());
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
            
            $viagem = Viagem::where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($viagem)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar viagem: ' . $e->getMessage());
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
            
            Log::info('📥 PUT /api/viagens/' . $id, [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $viagem = Viagem::where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            // Se for atualizar motorista, verificar disponibilidade
            if ($request->has('driver') && $request->driver !== $viagem->driver) {
                $motoristaAtivo = Viagem::where('driver', $request->driver)
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'Closed')
                    ->where('id', '!=', $id)
                    ->exists();
                    
                if ($motoristaAtivo) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Novo motorista já está em uma viagem ativa. Não pode ser atribuído a esta viagem.'
                    ], 409);
                }
            }
            
            // Se for atualizar camião, verificar disponibilidade
            if ($request->has('truckNumber') && $request->truckNumber !== $viagem->truck_number) {
                $camiaoAtivo = Viagem::where('truck_number', $request->truckNumber)
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'Closed')
                    ->where('id', '!=', $id)
                    ->exists();
                    
                if ($camiaoAtivo) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Novo camião já está em uma viagem ativa. Não pode ser atribuído a esta viagem.'
                    ], 409);
                }
            }
            
            // Se for atualizar trela, verificar disponibilidade
            if ($request->has('trailerNumber') && $request->trailerNumber !== $viagem->trailer_number) {
                $trelaAtiva = Viagem::where('trailer_number', $request->trailerNumber)
                    ->where('tenant_id', $tenantId)
                    ->where('status', '!=', 'Closed')
                    ->where('id', '!=', $id)
                    ->exists();
                    
                if ($trelaAtiva) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Nova trelá já está em uma viagem ativa. Não pode ser atribuída a esta viagem.'
                    ], 409);
                }
            }
            
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|string|max:50',
                'currentStatus' => 'nullable|string|max:255',
                'currentPosition' => 'nullable|string|max:100',
                'trackingComments' => 'nullable|string',
                'borderArrivalDate' => 'nullable|date',
                'borderDemurrageDays' => 'nullable|integer|min:0',
                'offloadingArrivalDate' => 'nullable|date',
                'offloadingDemurrageDays' => 'nullable|integer|min:0',
                'actualDelivery' => 'nullable|date',
                'podDeliveryDate' => 'nullable|date',
                'isReadyForInvoice' => 'nullable|boolean',
                'invoiceNumber' => 'nullable|string|max:50',
                'driver' => 'nullable|string|max:255',
                'truckNumber' => 'nullable|string|max:20',
                'trailerNumber' => 'nullable|string|max:20',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $dadosAtualizacao = [];
            
            $mapeamento = [
                'status' => 'status',
                'currentStatus' => 'current_status',
                'currentPosition' => 'current_position',
                'trackingComments' => 'tracking_comments',
                'borderArrivalDate' => 'border_arrival_date',
                'borderDemurrageDays' => 'border_demurrage_days',
                'offloadingArrivalDate' => 'offloading_arrival_date',
                'offloadingDemurrageDays' => 'offloading_demurrage_days',
                'actualDelivery' => 'actual_delivery',
                'podDeliveryDate' => 'pod_delivery_date',
                'isReadyForInvoice' => 'is_ready_for_invoice',
                'invoiceNumber' => 'invoice_number',
                'driver' => 'driver',
                'truckNumber' => 'truck_number',
                'trailerNumber' => 'trailer_number',
            ];
            
            foreach ($mapeamento as $campoCamel => $campoSnake) {
                if ($request->has($campoCamel) && $request->$campoCamel !== null) {
                    $dadosAtualizacao[$campoSnake] = $request->$campoCamel;
                }
            }
            
            if (!empty($dadosAtualizacao)) {
                $viagem->update($dadosAtualizacao);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($viagem->fresh()),
                'message' => 'Viagem atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar viagem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // ✅ CORREÇÃO CRÍTICA: Atualizar tracking/status da viagem
    // APENAS ESTE MÉTODO tem validação dos 6 status específicos
    public function atualizarTracking(Request $request, $id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('📥 PATCH /api/viagens/' . $id . '/tracking', [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $viagem = Viagem::where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            // ✅ APENAS AQUI: validação com APENAS OS 6 STATUS DO TRACKING
            $validator = Validator::make($request->all(), [
                'status' => [
                    'nullable', 
                    'string', 
                    'in:SCHEDULED,LOADED,IN TRANSIT,AT THE BORDER,DELIVERED,BREAKDOWN'
                ],
                'currentStatus' => 'required|string|max:255',
                'currentPosition' => 'nullable|string|max:255',
                'trackingComments' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $dadosAtualizacao = [];
            
            // Atualiza o status principal SE FOR ENVIADO
            if ($request->filled('status')) {
                $dadosAtualizacao['status'] = $request->status;
            }
            
            // Sempre atualiza o current_status (tracking / localização)
            $dadosAtualizacao['current_status'] = $request->currentStatus;
            
            // Posição atual (opcional)
            if ($request->filled('currentPosition')) {
                $dadosAtualizacao['current_position'] = $request->currentPosition;
            }
            
            // Comentários: acumula histórico
            if ($request->filled('trackingComments')) {
                $current = $viagem->tracking_comments ?? '';
                $dadosAtualizacao['tracking_comments'] = trim($current . "\n" . $request->trackingComments);
            }
            
            // Log detalhado
            Log::info('📝 Dados de atualização de tracking:', [
                'viagem_id' => $id,
                'status_atual' => $viagem->status,
                'current_status_atual' => $viagem->current_status,
                'dados_atualizacao' => $dadosAtualizacao,
                'tenant_id' => $tenantId
            ]);
            
            if (!empty($dadosAtualizacao)) {
                $viagem->update($dadosAtualizacao);
                
                Log::info('✅ Tracking atualizado com sucesso', [
                    'viagem_id' => $id,
                    'novo_status' => $request->status ?? '(não alterado)',
                    'novo_current_status' => $request->currentStatus,
                    'tenant_id' => $tenantId
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($viagem->fresh()),
                'message' => 'Tracking atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar tracking: ' . $e->getMessage());
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
            
            $viagem = Viagem::where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            if ($viagem->status === 'Running') {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir uma viagem em andamento'
                ], 400);
            }
            
            $viagem->delete();
            
            Log::info('✅ Viagem excluída', [
                'id' => $id,
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Viagem excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir viagem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // GERAR PDF DA VIAGEM
    public function gerarPDF($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $viagem = Viagem::where('tenant_id', $tenantId)->find($id);
            
            if (!$viagem) {
                abort(404, 'Viagem não encontrada');
            }
            
            $data = [
                'viagem' => $viagem,
                'data' => Carbon::now()->format('d/m/Y H:i:s')
            ];
            
            $pdf = PDF::loadView('viagens.pdf', $data);
            
            return $pdf->download('viagem-' . $viagem->trip_number . '.pdf');
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    // ESTATÍSTICAS DE NUMERAÇÃO
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
            
            // Contar viagens da empresa
            $totalViagens = Viagem::where('tenant_id', $tenantId)
                ->where('trip_number', 'like', $prefixo . '-%')
                ->count();
            
            // Última viagem
            $ultimaViagem = Viagem::where('tenant_id', $tenantId)
                ->where('trip_number', 'like', $prefixo . '-%')
                ->orderBy('created_at', 'desc')
                ->first();
            
            // Próximo número (5 dígitos)
            $proximoNumero = 1;
            if ($ultimaViagem) {
                $parts = explode('-', $ultimaViagem->trip_number);
                if (count($parts) === 2) {
                    $proximoNumero = intval($parts[1]) + 1;
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'codigo_prefixo' => $prefixo,
                    'total_viagens' => $totalViagens,
                    'ultima_viagem' => $ultimaViagem ? $ultimaViagem->trip_number : 'Nenhuma viagem',
                    'proximo_numero' => $prefixo . '-' . str_pad($proximoNumero, 5, '0', STR_PAD_LEFT),
                    'formato' => 'XX-00001 (5 dígitos)'
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

    // DASHBOARD ESTATÍSTICAS
    public function dashboard()
    {
        try {
            $tenantId = $this->getTenantId();
            
            $totalViagens = Viagem::where('tenant_id', $tenantId)->count();
            $viagensAtivas = Viagem::where('tenant_id', $tenantId)
                ->where('status', '!=', 'Closed')
                ->count();
            $viagensPendentes = Viagem::where('tenant_id', $tenantId)
                ->where('status', 'Pending')
                ->count();
            $viagensRunning = Viagem::where('tenant_id', $tenantId)
                ->where('status', 'Running')
                ->count();
            $viagensCompleted = Viagem::where('tenant_id', $tenantId)
                ->where('status', 'Completed')
                ->count();
            
            // Viagens por mês (últimos 6 meses)
            $viagensPorMes = Viagem::where('tenant_id', $tenantId)
                ->where('created_at', '>=', Carbon::now()->subMonths(6))
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as mes, COUNT(*) as total')
                ->groupBy('mes')
                ->orderBy('mes')
                ->get()
                ->pluck('total', 'mes');
            
            // Status distribution
            $statusDistribution = Viagem::where('tenant_id', $tenantId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'totais' => [
                        'totalViagens' => $totalViagens,
                        'viagensAtivas' => $viagensAtivas,
                        'viagensPendentes' => $viagensPendentes,
                        'viagensRunning' => $viagensRunning,
                        'viagensCompleted' => $viagensCompleted,
                    ],
                    'viagensPorMes' => $viagensPorMes,
                    'statusDistribution' => $statusDistribution,
                    'tenant_id' => $tenantId
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // Fechar viagem - USA STATUS CLOSED
    public function fecharViagem(Request $request, $id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            Log::info('📥 PUT /api/viagens/' . $id . '/fechar', [
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            $viagem = Viagem::where('tenant_id', $tenantId)
                ->find($id);
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'invoiceNumber' => 'nullable|string|max:50',
                'closingDate' => 'required|date',
                'closingComments' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $dadosAtualizacao = [
                'status' => 'Closed', // ✅ Status CLOSED para fechamento
                'is_ready_for_invoice' => true,
                'invoice_number' => $request->invoiceNumber,
                'actual_delivery' => $request->closingDate,
            ];
            
            if ($request->closingComments) {
                $dadosAtualizacao['tracking_comments'] = $viagem->tracking_comments . "\n--- FECHAMENTO ---\n" . $request->closingComments;
            }
            
            $viagem->update($dadosAtualizacao);
            
            Log::info('✅ Viagem fechada', [
                'viagem_id' => $id,
                'invoice_number' => $request->invoiceNumber,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($viagem->fresh()),
                'message' => 'Viagem fechada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao fechar viagem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}