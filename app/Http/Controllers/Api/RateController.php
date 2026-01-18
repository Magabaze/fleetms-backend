<?php
// app/Http/Controllers/Api/RateController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rate;
use App\Models\Cliente;
use App\Models\Distancia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RateController extends Controller
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

    // Converter snake_case para camelCase
    private function paraCamelCase($rate)
    {
        $itensCarga = json_decode($rate->itens_carga, true) ?? [];
        
        return [
            'id' => $rate->id,
            'clienteId' => $rate->cliente_id,
            'clienteNome' => $rate->cliente_nome,
            'distanciaId' => $rate->distancia_id,
            'distanciaRota' => $rate->distancia_rota,
            'moeda' => $rate->moeda,
            'validade' => $rate->validade->toDateString(),
            'observacoes' => $rate->observacoes,
            'status' => $rate->status,
            'criadoPor' => $rate->criado_por,
            'aprovadoPor' => $rate->aprovado_por,
            'itensCarga' => $itensCarga,
            'tenantId' => $rate->tenant_id,
            'createdAt' => $rate->created_at->toISOString(),
            'updatedAt' => $rate->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('📥 GET /api/rates', [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Rate::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cliente_nome', 'like', "%{$search}%")
                      ->orWhere('distancia_rota', 'like', "%{$search}%")
                      ->orWhere('moeda', 'like', "%{$search}%");
                });
            }
            
            // Filtrar por status
            if ($request->has('status') && in_array($request->status, ['pendente', 'aprovado', 'rejeitado'])) {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $rates = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter para camelCase
            $ratesCamelCase = $rates->map(function ($rate) {
                return $this->paraCamelCase($rate);
            });
            
            Log::info('✅ Rates listados', [
                'total' => $rates->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $ratesCamelCase->toArray(),
                'pagination' => [
                    'page' => $rates->currentPage(),
                    'limit' => $perPage,
                    'total' => $rates->total(),
                    'totalPages' => $rates->lastPage(),
                    'hasNextPage' => $rates->hasMorePages(),
                    'hasPrevPage' => $rates->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar rates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();
        
        Log::info('📥 POST /api/rates', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'clienteId' => 'required|integer|exists:clientes,id',
            'distanciaId' => 'required|integer|exists:distancias,id',
            'moeda' => 'required|in:USD,EUR,BRL,MZN,ZAR',
            'validade' => 'required|date|after_or_equal:today',
            'observacoes' => 'nullable|string',
            'criadoPor' => 'required|string|max:255',
            'itensCarga' => 'required|array|min:1',
            'itensCarga.*.tipoCarga' => 'required|string',
            'itensCarga.*.precoUnitario' => 'required|numeric|min:0',
            'itensCarga.*.unidadeMedida' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            Log::error('❌ Validação falhou', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            // Buscar cliente do mesmo tenant
            $cliente = Cliente::where('tenant_id', $tenantId)->find($request->clienteId);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente não encontrado ou não pertence ao seu tenant'
                ], 404);
            }
            
            // Buscar distância do mesmo tenant
            $distancia = Distancia::where('tenant_id', $tenantId)->find($request->distanciaId);
            if (!$distancia) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distância/rota não encontrada ou não pertence ao seu tenant'
                ], 404);
            }
            
            // Verificar se já existe rate ativo para o mesmo cliente, rota e tenant
            $rateExistente = Rate::where('tenant_id', $tenantId)
                ->where('cliente_id', $request->clienteId)
                ->where('distancia_id', $request->distanciaId)
                ->where('status', 'aprovado')
                ->where('validade', '>=', now()->toDateString())
                ->exists();
            
            if ($rateExistente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Já existe um rate aprovado e válido para este cliente nesta rota'
                ], 400);
            }
            
            $dados = [
                'cliente_id' => $request->clienteId,
                'cliente_nome' => $cliente->nome_empresa,
                'distancia_id' => $request->distanciaId,
                'distancia_rota' => $distancia->origem . ' → ' . $distancia->destino,
                'moeda' => $request->moeda,
                'validade' => $request->validade,
                'observacoes' => $request->observacoes ?? '',
                'status' => $request->status ?? 'pendente',
                'criado_por' => $request->criadoPor,
                'itens_carga' => json_encode($request->itensCarga),
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando rate', $dados);
            
            $rate = Rate::create($dados);
            
            DB::commit();
            
            Log::info('✅ Rate criado', [
                'id' => $rate->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($rate),
                'message' => 'Rate criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar rate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $tenantId = $this->getTenantId();
        
        try {
            $rate = Rate::where('tenant_id', $tenantId)->find($id);
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($rate)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar rate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();
        
        Log::info('📥 PUT /api/rates/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        DB::beginTransaction();
        try {
            $rate = Rate::where('tenant_id', $tenantId)->find($id);
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não encontrado'
                ], 404);
            }
            
            // Se já está aprovado, não pode editar (apenas criar novo)
            if ($rate->status === 'aprovado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate aprovado não pode ser editado. Crie um novo rate.'
                ], 400);
            }
            
            $validator = Validator::make($request->all(), [
                'clienteId' => 'required|integer|exists:clientes,id',
                'distanciaId' => 'required|integer|exists:distancias,id',
                'moeda' => 'required|in:USD,EUR,BRL,MZN,ZAR',
                'validade' => 'required|date|after_or_equal:today',
                'observacoes' => 'nullable|string',
                'itensCarga' => 'required|array|min:1',
                'itensCarga.*.tipoCarga' => 'required|string',
                'itensCarga.*.precoUnitario' => 'required|numeric|min:0',
                'itensCarga.*.unidadeMedida' => 'required|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Buscar cliente do mesmo tenant
            $cliente = Cliente::where('tenant_id', $tenantId)->find($request->clienteId);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente não encontrado ou não pertence ao seu tenant'
                ], 404);
            }
            
            // Buscar distância do mesmo tenant
            $distancia = Distancia::where('tenant_id', $tenantId)->find($request->distanciaId);
            if (!$distancia) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distância/rota não encontrada ou não pertence ao seu tenant'
                ], 404);
            }
            
            $rate->update([
                'cliente_id' => $request->clienteId,
                'cliente_nome' => $cliente->nome_empresa,
                'distancia_id' => $request->distanciaId,
                'distancia_rota' => $distancia->origem . ' → ' . $distancia->destino,
                'moeda' => $request->moeda,
                'validade' => $request->validade,
                'observacoes' => $request->observacoes ?? $rate->observacoes,
                'itens_carga' => json_encode($request->itensCarga),
            ]);
            
            DB::commit();
            
            Log::info('✅ Rate atualizado', [
                'id' => $rate->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($rate->fresh()),
                'message' => 'Rate atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao atualizar rate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $tenantId = $this->getTenantId();
        
        try {
            $rate = Rate::where('tenant_id', $tenantId)->find($id);
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não encontrado'
                ], 404);
            }
            
            // Não permitir excluir rates aprovados
            if ($rate->status === 'aprovado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate aprovado não pode ser excluído. Use a função de rejeitar.'
                ], 400);
            }
            
            $rate->delete();
            
            Log::info('✅ Rate excluído', [
                'id' => $id,
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Rate excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir rate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function aprovar($id, Request $request)
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();
        
        try {
            $rate = Rate::where('tenant_id', $tenantId)->find($id);
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não encontrado'
                ], 404);
            }
            
            if ($rate->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não está pendente de aprovação'
                ], 400);
            }
            
            $validator = Validator::make($request->all(), [
                'aprovadoPor' => 'required|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Verificar se já existe rate ativo para o mesmo cliente, rota e tenant
            $rateAtivoExistente = Rate::where('tenant_id', $tenantId)
                ->where('cliente_id', $rate->cliente_id)
                ->where('distancia_id', $rate->distancia_id)
                ->where('status', 'aprovado')
                ->where('validade', '>=', now()->toDateString())
                ->where('id', '!=', $id)
                ->exists();
            
            if ($rateAtivoExistente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Já existe um rate aprovado e válido para este cliente nesta rota'
                ], 400);
            }
            
            $rate->update([
                'status' => 'aprovado',
                'aprovado_por' => $request->aprovadoPor,
            ]);
            
            Log::info('✅ Rate aprovado', [
                'id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($rate->fresh()),
                'message' => 'Rate aprovado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao aprovar rate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejeitar($id, Request $request)
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();
        
        try {
            $rate = Rate::where('tenant_id', $tenantId)->find($id);
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não encontrado'
                ], 404);
            }
            
            if ($rate->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não está pendente de aprovação'
                ], 400);
            }
            
            $rate->update([
                'status' => 'rejeitado',
                'aprovado_por' => $user->name ?? 'Sistema',
            ]);
            
            Log::info('✅ Rate rejeitado', [
                'id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($rate->fresh()),
                'message' => 'Rate rejeitado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao rejeitar rate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // NOVO MÉTODO: Desfazer aprovação de rate
    public function desfazerAprovacao($id)
    {
        $user = Auth::user();
        $tenantId = $this->getTenantId();
        
        try {
            $rate = Rate::where('tenant_id', $tenantId)->find($id);
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não encontrado'
                ], 404);
            }
            
            if ($rate->status !== 'aprovado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Rate não está aprovado'
                ], 400);
            }
            
            $rate->update([
                'status' => 'pendente',
                'aprovado_por' => null,
            ]);
            
            Log::info('✅ Aprovação do rate desfeita', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($rate->fresh()),
                'message' => 'Aprovação desfeita com sucesso! O rate agora está pendente.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao desfazer aprovação do rate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    // API para buscar clientes para select
    public function clientes()
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

    // API para buscar distâncias para select - CORRIGIDO
    public function distancias()
    {
        try {
            $tenantId = $this->getTenantId();
            
            $distancias = Distancia::where('tenant_id', $tenantId)
                ->select('id', 'origem', 'destino', 'distancia_total as distanciaTotal')
                ->orderBy('origem')
                ->get()
                ->map(function ($distancia) {
                    return [
                        'id' => $distancia->id,
                        'rotaCompleta' => $distancia->origem . ' → ' . $distancia->destino,
                        'distanciaTotal' => $distancia->distanciaTotal
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $distancias
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar distâncias: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Buscar rates por cliente e rota
    public function buscarRateCliente(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $validator = Validator::make($request->all(), [
                'clienteId' => 'required|integer|exists:clientes,id',
                'distanciaId' => 'required|integer|exists:distancias,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Buscar rate ativo (aprovado e válido) para o cliente e rota
            $rate = Rate::where('tenant_id', $tenantId)
                ->where('cliente_id', $request->clienteId)
                ->where('distancia_id', $request->distanciaId)
                ->where('status', 'aprovado')
                ->where('validade', '>=', now()->toDateString())
                ->orderBy('validade', 'desc')
                ->first();
            
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhum rate válido encontrado para esta combinação de cliente e rota'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($rate),
                'message' => 'Rate válido encontrado'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar rate por cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Buscar histórico de rates por cliente
    public function historicoCliente($clienteId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Verificar se cliente pertence ao tenant
            $cliente = Cliente::where('tenant_id', $tenantId)->find($clienteId);
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente não encontrado'
                ], 404);
            }
            
            $rates = Rate::where('tenant_id', $tenantId)
                ->where('cliente_id', $clienteId)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $ratesCamelCase = $rates->map(function ($rate) {
                return $this->paraCamelCase($rate);
            });
            
            return response()->json([
                'success' => true,
                'data' => $ratesCamelCase
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar histórico de rates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}