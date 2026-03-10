<?php
// app/Http/Controllers/Api/Combustivel/PedidoCompraController.php

namespace App\Http\Controllers\Api\Combustivel;

use App\Http\Controllers\Controller;
use App\Models\Combustivel\PedidoCompra;
use App\Models\Combustivel\Tanque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PedidoCompraController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Converter snake_case para camelCase (frontend)
     */
    private function paraCamelCase($pedido)
    {
        $data = [
            'id' => $pedido->id,
            'numero' => $pedido->numero,
            'fornecedor' => $pedido->fornecedor,
            'tipo_combustivel' => $pedido->tipo_combustivel,
            'quantidade' => (float) $pedido->quantidade,
            'unidade_medida' => $pedido->unidade_medida,
            'preco_unitario' => (float) $pedido->preco_unitario,
            'valor_total' => (float) $pedido->valor_total,
            'moeda' => $pedido->moeda,
            'data_pedido' => $pedido->data_pedido?->toISOString(),
            'data_entrega_prevista' => $pedido->data_entrega_prevista?->toISOString(),
            'data_entrega_real' => $pedido->data_entrega_real?->toISOString(),
            'status' => $pedido->status,
            'observacoes' => $pedido->observacoes,
            'criado_por' => $pedido->criado_por,
            'aprovado_por' => $pedido->aprovado_por,
            'data_aprovacao' => $pedido->data_aprovacao?->toISOString(),
            'tenant_id' => $pedido->tenant_id,
            'created_at' => $pedido->created_at?->toISOString(),
            'updated_at' => $pedido->updated_at?->toISOString()
        ];

        // Se tiver informações do tanque (para pedidos entregues)
        if ($pedido->tanque_id) {
            $data['tanque_id'] = $pedido->tanque_id;
            $data['tanque_nome'] = $pedido->tanque_nome;
            $data['tanque_codigo'] = $pedido->tanque_codigo;
        }

        return $data;
    }

    /**
     * Listar todos os pedidos com paginação e filtros
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/combustivel/pedidos-compra', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = PedidoCompra::where('tenant_id', $tenantId);
            
            // Busca por fornecedor, número, tipo combustível
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                      ->orWhere('fornecedor', 'like', "%{$search}%")
                      ->orWhere('tipo_combustivel', 'like', "%{$search}%")
                      ->orWhere('observacoes', 'like', "%{$search}%");
                });
            }
            
            // Filtro por status
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            // Filtro por período
            if ($request->has('data_inicio') && $request->data_inicio) {
                $query->whereDate('data_pedido', '>=', $request->data_inicio);
            }
            
            if ($request->has('data_fim') && $request->data_fim) {
                $query->whereDate('data_pedido', '<=', $request->data_fim);
            }
            
            // Ordenação
            $orderBy = $request->get('order_by', 'created_at');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $pedidos = $query->paginate($perPage, ['*'], 'page', $page);
            
            $pedidosCamelCase = $pedidos->map(function ($pedido) {
                return $this->paraCamelCase($pedido);
            });
            
            Log::info('✅ Pedidos listados', [
                'total' => $pedidos->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $pedidosCamelCase->toArray(),
                'pagination' => [
                    'page' => $pedidos->currentPage(),
                    'limit' => $perPage,
                    'total' => $pedidos->total(),
                    'totalPages' => $pedidos->lastPage(),
                    'hasNextPage' => $pedidos->hasMorePages(),
                    'hasPrevPage' => $pedidos->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar pedidos: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno ao listar pedidos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar novo pedido de compra
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 POST /api/combustivel/pedidos-compra', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'fornecedor' => 'required|string|max:255',
            'tipo_combustivel' => 'required|string|max:100',
            'quantidade' => 'required|numeric|min:0.01',
            'unidade_medida' => 'required|in:litros,galoes,metros_cubicos',
            'preco_unitario' => 'required|numeric|min:0.01',
            'moeda' => 'required|in:USD,EUR,MZN,ZAR,BRL',
            'data_entrega_prevista' => 'required|date|after_or_equal:today',
            'observacoes' => 'nullable|string',
            'status' => 'sometimes|in:pendente,aprovado,rejeitado,entregue,cancelado',
            'criado_por' => 'required|string|max:255',
        ], [
            'fornecedor.required' => 'O fornecedor é obrigatório',
            'tipo_combustivel.required' => 'O tipo de combustível é obrigatório',
            'quantidade.required' => 'A quantidade é obrigatória',
            'quantidade.min' => 'A quantidade deve ser maior que zero',
            'preco_unitario.required' => 'O preço unitário é obrigatório',
            'preco_unitario.min' => 'O preço unitário deve ser maior que zero',
            'data_entrega_prevista.required' => 'A data de entrega prevista é obrigatória',
            'data_entrega_prevista.after_or_equal' => 'A data de entrega não pode ser no passado',
            'criado_por.required' => 'O criador do pedido é obrigatório',
        ]);
        
        if ($validator->fails()) {
            Log::warning('⚠️ Validação falhou ao criar pedido', [
                'errors' => $validator->errors()->toArray()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Gerar número do pedido automaticamente
            $ano = date('Y');
            $mes = date('m');
            
            $ultimoPedido = PedidoCompra::where('tenant_id', $tenantId)
                ->where('numero', 'like', "PO-{$ano}{$mes}-%")
                ->orderBy('id', 'desc')
                ->first();
            
            $sequencia = 1;
            if ($ultimoPedido && preg_match('/PO-' . $ano . $mes . '-(\d+)/', $ultimoPedido->numero, $matches)) {
                $sequencia = intval($matches[1]) + 1;
            }
            
            $numeroPedido = "PO-{$ano}{$mes}-" . str_pad($sequencia, 4, '0', STR_PAD_LEFT);
            
            // Calcular valor total
            $valorTotal = $request->quantidade * $request->preco_unitario;
            
            $dados = [
                'numero' => $numeroPedido,
                'fornecedor' => $request->fornecedor,
                'tipo_combustivel' => $request->tipo_combustivel,
                'quantidade' => $request->quantidade,
                'unidade_medida' => $request->unidade_medida,
                'preco_unitario' => $request->preco_unitario,
                'valor_total' => $valorTotal,
                'moeda' => $request->moeda,
                'data_pedido' => now(),
                'data_entrega_prevista' => $request->data_entrega_prevista,
                'status' => $request->status ?? 'pendente',
                'observacoes' => $request->observacoes,
                'criado_por' => $request->criado_por,
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando pedido', $dados);
            
            $pedido = PedidoCompra::create($dados);
            
            Log::info('✅ Pedido criado com sucesso', [
                'id' => $pedido->id,
                'numero' => $pedido->numero,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($pedido),
                'message' => 'Pedido criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar pedido: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno ao criar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar pedido por ID
     */
    public function show($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $pedido = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            
            if (!$pedido) {
                Log::warning('⚠️ Pedido não encontrado', ['id' => $id, 'tenant_id' => $tenantId]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($pedido)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar pedido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar pedido (apenas pendentes)
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 PUT /api/combustivel/pedidos-compra/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $pedido = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            
            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }
            
            // Só permite editar pedidos pendentes
            if ($pedido->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas pedidos pendentes podem ser editados'
                ], 400);
            }
            
            $validator = Validator::make($request->all(), [
                'fornecedor' => 'required|string|max:255',
                'tipo_combustivel' => 'required|string|max:100',
                'quantidade' => 'required|numeric|min:0.01',
                'preco_unitario' => 'required|numeric|min:0.01',
                'data_entrega_prevista' => 'required|date|after_or_equal:today',
                'observacoes' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Calcular novo valor total
            $valorTotal = $request->quantidade * $request->preco_unitario;
            
            $dadosAtualizacao = [
                'fornecedor' => $request->fornecedor,
                'tipo_combustivel' => $request->tipo_combustivel,
                'quantidade' => $request->quantidade,
                'preco_unitario' => $request->preco_unitario,
                'valor_total' => $valorTotal,
                'data_entrega_prevista' => $request->data_entrega_prevista,
                'observacoes' => $request->observacoes ?? $pedido->observacoes,
            ];
            
            $pedido->update($dadosAtualizacao);
            
            Log::info('✅ Pedido atualizado', ['id' => $id]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($pedido->fresh()),
                'message' => 'Pedido atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar pedido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir pedido (apenas pendentes)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $pedido = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            
            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }
            
            // Só permite excluir pedidos pendentes
            if ($pedido->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas pedidos pendentes podem ser excluídos'
                ], 400);
            }
            
            $pedido->delete();
            
            Log::info('✅ Pedido excluído', ['id' => $id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Pedido excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir pedido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprovar pedido
     */
    public function aprovar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $pedido = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            
            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }
            
            if ($pedido->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas pedidos pendentes podem ser aprovados'
                ], 400);
            }
            
            $pedido->update([
                'status' => 'aprovado',
                'aprovado_por' => $user->name ?? $user->email,
                'data_aprovacao' => now()
            ]);
            
            Log::info('✅ Pedido aprovado', [
                'id' => $id,
                'aprovado_por' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($pedido->fresh()),
                'message' => 'Pedido aprovado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao aprovar pedido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeitar pedido
     */
    public function rejeitar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $pedido = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            
            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }
            
            if ($pedido->status !== 'pendente') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas pedidos pendentes podem ser rejeitados'
                ], 400);
            }
            
            $motivo = $request->motivo ?? 'Não especificado';
            
            $observacoes = $pedido->observacoes 
                ? $pedido->observacoes . "\n\n" 
                : '';
            
            $observacoes .= "REJEITADO em: " . now()->format('d/m/Y H:i') . "\n";
            $observacoes .= "Motivo: " . $motivo;
            
            $pedido->update([
                'status' => 'rejeitado',
                'observacoes' => $observacoes
            ]);
            
            Log::info('✅ Pedido rejeitado', [
                'id' => $id,
                'motivo' => $motivo
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($pedido->fresh()),
                'message' => 'Pedido rejeitado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao rejeitar pedido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ REGISTRAR ENTREGA + ADICIONAR AO TANQUE AUTOMATICAMENTE
     */
    public function entregar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📦 Processando entrega do pedido', [
            'pedido_id' => $id,
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            // 1️⃣ BUSCAR O PEDIDO
            $pedido = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            
            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }
            
            // 2️⃣ VALIDAR STATUS
            if ($pedido->status !== 'aprovado') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas pedidos aprovados podem ser marcados como entregues'
                ], 400);
            }
            
            // 3️⃣ VALIDAR DATA
            $validator = Validator::make($request->all(), [
                'data_entrega_real' => 'required|date'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // 4️⃣ VALIDAR UNIDADE DE MEDIDA (APENAS LITROS)
            if ($pedido->unidade_medida !== 'litros') {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas pedidos em litros podem ser adicionados automaticamente aos tanques'
                ], 400);
            }
            
            // 🔥 INICIAR TRANSAÇÃO
            DB::beginTransaction();
            
            // 5️⃣ ENCONTRAR TANQUE COMPATÍVEL
            $tanque = Tanque::where('tenant_id', $tenantId)
                ->where('status', 'ativo')
                ->where('tipo_combustivel', 'like', $pedido->tipo_combustivel)
                ->whereRaw('(capacidade_total - nivel_atual) >= ?', [$pedido->quantidade])
                ->orderBy('nivel_atual', 'desc') // Prefere tanques mais cheios
                ->first();
            
            if (!$tanque) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => "Nenhum tanque disponível para {$pedido->tipo_combustivel} com capacidade para {$pedido->quantidade}L"
                ], 404);
            }
            
            // 6️⃣ GUARDAR VALORES ANTES DA ATUALIZAÇÃO
            $nivelAnterior = $tanque->nivel_atual;
            $nivelAtual = $tanque->nivel_atual + $pedido->quantidade;
            $percentualOcupacao = ($nivelAtual / $tanque->capacidade_total) * 100;
            
            // 7️⃣ ATUALIZAR O TANQUE
            $observacaoEntrega = now()->format('d/m/Y H:i') . 
                " - Entrada de " . number_format($pedido->quantidade, 2, ',', '.') . 
                "L via pedido {$pedido->numero} (Fornecedor: {$pedido->fornecedor})";
            
            $tanque->update([
                'nivel_atual' => $nivelAtual,
                'ultima_atualizacao' => now(),
                'observacoes' => $tanque->observacoes 
                    ? $tanque->observacoes . "\n" . $observacaoEntrega
                    : $observacaoEntrega
            ]);
            
            // 8️⃣ ATUALIZAR O PEDIDO
            $pedido->update([
                'status' => 'entregue',
                'data_entrega_real' => $request->data_entrega_real
            ]);
            
            // ✅ COMMITAR TRANSAÇÃO
            DB::commit();
            
            Log::info('✅ Pedido entregue e tanque atualizado com sucesso', [
                'pedido_id' => $pedido->id,
                'pedido_numero' => $pedido->numero,
                'tanque_id' => $tanque->id,
                'tanque_nome' => $tanque->nome,
                'quantidade' => $pedido->quantidade,
                'nivel_anterior' => $nivelAnterior,
                'nivel_atual' => $nivelAtual,
                'percentual_ocupacao' => round($percentualOcupacao, 2) . '%'
            ]);
            
            // 9️⃣ PREPARAR RESPOSTA
            $pedidoAtualizado = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            $dadosPedido = $this->paraCamelCase($pedidoAtualizado);
            
            // Adicionar informações do tanque
            $dadosPedido['tanque'] = [
                'id' => $tanque->id,
                'nome' => $tanque->nome,
                'codigo' => $tanque->codigo,
                'nivel_anterior' => $nivelAnterior,
                'nivel_atual' => $nivelAtual,
                'quantidade_adicionada' => $pedido->quantidade,
                'percentual_ocupacao' => round($percentualOcupacao, 2)
            ];
            
            return response()->json([
                'success' => true,
                'data' => $dadosPedido,
                'message' => '✅ Pedido entregue e combustível adicionado ao tanque com sucesso!'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao registrar entrega: ' . $e->getMessage(), [
                'pedido_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno ao processar entrega: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar pedido
     */
    public function cancelar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $pedido = PedidoCompra::where('tenant_id', $tenantId)->find($id);
            
            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pedido não encontrado'
                ], 404);
            }
            
            if (!in_array($pedido->status, ['pendente', 'aprovado'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas pedidos pendentes ou aprovados podem ser cancelados'
                ], 400);
            }
            
            $motivo = $request->motivo ?? 'Não especificado';
            
            $observacoes = $pedido->observacoes 
                ? $pedido->observacoes . "\n\n" 
                : '';
            
            $observacoes .= "CANCELADO em: " . now()->format('d/m/Y H:i') . "\n";
            $observacoes .= "Motivo: " . $motivo;
            
            $pedido->update([
                'status' => 'cancelado',
                'observacoes' => $observacoes
            ]);
            
            Log::info('✅ Pedido cancelado', [
                'id' => $id,
                'motivo' => $motivo
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($pedido->fresh()),
                'message' => 'Pedido cancelado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao cancelar pedido: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerar relatório de pedidos
     */
    public function relatorio(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = PedidoCompra::where('tenant_id', $tenantId);
            
            // Filtros
            if ($request->has('data_inicio') && $request->data_inicio) {
                $query->whereDate('data_pedido', '>=', $request->data_inicio);
            }
            
            if ($request->has('data_fim') && $request->data_fim) {
                $query->whereDate('data_pedido', '<=', $request->data_fim);
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('fornecedor') && $request->fornecedor) {
                $query->where('fornecedor', 'like', "%{$request->fornecedor}%");
            }
            
            $pedidos = $query->orderBy('data_pedido', 'desc')->get();
            
            // Estatísticas
            $totalPedidos = $pedidos->count();
            $totalValor = $pedidos->sum('valor_total');
            $pedidosPorStatus = $pedidos->groupBy('status')->map->count();
            $valorPorStatus = $pedidos->groupBy('status')->map(function ($group) {
                return $group->sum('valor_total');
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'pedidos' => $pedidos->map(function ($pedido) {
                        return $this->paraCamelCase($pedido);
                    }),
                    'estatisticas' => [
                        'total_pedidos' => $totalPedidos,
                        'total_valor' => (float) $totalValor,
                        'pedidos_por_status' => $pedidosPorStatus,
                        'valor_por_status' => $valorPorStatus,
                        'moeda' => $pedidos->first()?->moeda ?? 'EUR'
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao gerar relatório: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}