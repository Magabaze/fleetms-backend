<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\ViagemDespesa;
use App\Models\TipoDespesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ViagemDespesaController extends Controller
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

    // 1. LISTAR DESPESAS DE UMA VIAGEM
    public function index($viagemId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Verificar se a viagem existe e pertence ao tenant
            $viagem = Viagem::where('id', $viagemId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            // Buscar despesas da viagem com relacionamentos
            $despesas = ViagemDespesa::with(['tipoDespesa', 'usuario:id,name,email'])
                ->where('viagem_id', $viagemId)
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->get();
            
            // Formatar resposta
            $formattedDespesas = $despesas->map(function ($despesa) {
                return [
                    'id' => $despesa->id,
                    'viagem_id' => $despesa->viagem_id,
                    'tipo_despesa_id' => $despesa->tipo_despesa_id,
                    'expense_head' => $despesa->expense_head,
                    'amount' => (float) $despesa->amount,
                    'currency' => $despesa->currency,
                    'driver_name' => $despesa->driver_name,
                    'payment_description' => $despesa->payment_description,
                    'created_by' => $despesa->created_by,
                    'created_by_id' => $despesa->created_by_id,
                    'status' => $despesa->status,
                    'is_active' => (bool) $despesa->is_active,
                    'created_at' => $despesa->created_at?->toISOString(),
                    'updated_at' => $despesa->updated_at?->toISOString(),
                    'tipo_despesa' => $despesa->tipoDespesa ? [
                        'id' => $despesa->tipoDespesa->id,
                        'nome' => $despesa->tipoDespesa->nome,
                        'cor' => $despesa->tipoDespesa->cor,
                    ] : null,
                    'usuario' => $despesa->usuario ? [
                        'id' => $despesa->usuario->id,
                        'name' => $despesa->usuario->name,
                        'email' => $despesa->usuario->email,
                    ] : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedDespesas,
                'viagem_info' => [
                    'id' => $viagem->id,
                    'trip_number' => $viagem->tripNumber,
                    'driver' => $viagem->driver,
                ],
                'metadata' => [
                    'total' => $despesas->count(),
                    'tenantId' => $tenantId,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao listar despesas da viagem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar despesas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 2. CRIAR NOVA DESPESA PARA VIAGEM
    public function store(Request $request, $viagemId)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            // Verificar se a viagem existe e pertence ao tenant
            $viagem = Viagem::where('id', $viagemId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            // Validação
            $validator = Validator::make($request->all(), [
                'tipo_despesa_id' => 'required|exists:tipo_despesas,id',
                'amount' => 'required|numeric|min:0.01',
                'currency' => ['required', Rule::in(['MZN', 'USD', 'EUR', 'ZAR', 'ZWL', 'MWK', 'ZMW'])],
                'driver_name' => 'required|string|max:255',
                'payment_description' => 'nullable|string|max:1000',
            ], [
                'tipo_despesa_id.required' => 'O tipo de despesa é obrigatório',
                'tipo_despesa_id.exists' => 'Tipo de despesa inválido',
                'amount.required' => 'O valor é obrigatório',
                'amount.numeric' => 'O valor deve ser um número',
                'amount.min' => 'O valor deve ser maior que zero',
                'currency.required' => 'A moeda é obrigatória',
                'currency.in' => 'Moeda inválida',
                'driver_name.required' => 'O nome do motorista é obrigatório',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erro de validação'
                ], 422);
            }
            
            // Buscar tipo de despesa para pegar o nome
            $tipoDespesa = TipoDespesa::find($request->tipo_despesa_id);
            if (!$tipoDespesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tipo de despesa não encontrado'
                ], 404);
            }
            
            // Criar despesa
            $despesa = ViagemDespesa::create([
                'viagem_id' => $viagemId,
                'tipo_despesa_id' => $request->tipo_despesa_id,
                'expense_head' => $tipoDespesa->nome,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'driver_name' => $request->driver_name,
                'payment_description' => $request->payment_description,
                'created_by' => $user->name ?? 'Sistema',
                'created_by_id' => $user->id ?? 0,
                'status' => 'pending',
                'is_active' => true,
                'tenant_id' => $tenantId,
            ]);
            
            Log::info('✅ Despesa de viagem criada', [
                'id' => $despesa->id,
                'viagem_id' => $despesa->viagem_id,
                'tipo' => $despesa->expense_head,
                'amount' => $despesa->amount,
                'tenant_id' => $tenantId,
                'user' => $user->name ?? 'Sistema'
            ]);
            
            // Carregar relacionamentos para resposta
            $despesa->load(['tipoDespesa', 'usuario:id,name,email']);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa criada com sucesso!',
                'data' => [
                    'id' => $despesa->id,
                    'viagem_id' => $despesa->viagem_id,
                    'tipo_despesa_id' => $despesa->tipo_despesa_id,
                    'expense_head' => $despesa->expense_head,
                    'amount' => (float) $despesa->amount,
                    'currency' => $despesa->currency,
                    'driver_name' => $despesa->driver_name,
                    'payment_description' => $despesa->payment_description,
                    'created_by' => $despesa->created_by,
                    'created_by_id' => $despesa->created_by_id,
                    'status' => $despesa->status,
                    'is_active' => (bool) $despesa->is_active,
                    'created_at' => $despesa->created_at?->toISOString(),
                    'updated_at' => $despesa->updated_at?->toISOString(),
                    'tipo_despesa' => $despesa->tipoDespesa ? [
                        'id' => $despesa->tipoDespesa->id,
                        'nome' => $despesa->tipoDespesa->nome,
                        'cor' => $despesa->tipoDespesa->cor,
                    ] : null,
                    'usuario' => $despesa->usuario ? [
                        'id' => $despesa->usuario->id,
                        'name' => $despesa->usuario->name,
                        'email' => $despesa->usuario->email,
                    ] : null,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao criar despesa de viagem: ' . $e->getMessage());
            Log::error('Request data: ' . json_encode($request->all()));
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 3. APROVAR DESPESA
    public function approve($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $despesa = ViagemDespesa::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada'
                ], 404);
            }
            
            // Verificar se já está aprovada
            if ($despesa->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa já está aprovada'
                ], 400);
            }
            
            $despesa->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);
            
            Log::info('✅ Despesa aprovada', [
                'id' => $despesa->id,
                'viagem_id' => $despesa->viagem_id,
                'valor' => $despesa->amount,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa aprovada com sucesso!',
                'data' => [
                    'id' => $despesa->id,
                    'status' => $despesa->status,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao aprovar despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao aprovar despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 4. APROVAR LOTE DE DESPESAS
    public function approveBatch(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:viagem_despesas,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erro de validação'
                ], 422);
            }
            
            $ids = $request->ids;
            
            // Aprovar todas as despesas do lote que pertencem ao tenant
            $updated = ViagemDespesa::whereIn('id', $ids)
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', 'approved')
                ->update([
                    'status' => 'approved',
                    'updated_at' => now(),
                ]);
            
            Log::info('✅ Lote de despesas aprovado', [
                'total_ids' => count($ids),
                'aprovadas' => $updated,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "$updated despesa(s) aprovada(s) com sucesso!",
                'data' => [
                    'total_enviadas' => count($ids),
                    'aprovadas' => $updated,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao aprovar lote de despesas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao aprovar despesas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 5. APROVAR TODAS AS DESPESAS DE UMA VIAGEM
    public function approveAll($viagemId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Verificar se a viagem existe
            $viagem = Viagem::where('id', $viagemId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            // Aprovar todas as despesas pendentes da viagem
            $updated = ViagemDespesa::where('viagem_id', $viagemId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('is_active', true)
                ->update([
                    'status' => 'approved',
                    'updated_at' => now(),
                ]);
            
            Log::info('✅ Todas as despesas da viagem aprovadas', [
                'viagem_id' => $viagemId,
                'aprovadas' => $updated,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "$updated despesa(s) da viagem aprovada(s) com sucesso!",
                'data' => [
                    'viagem_id' => $viagemId,
                    'despesas_aprovadas' => $updated,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao aprovar todas as despesas da viagem: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao aprovar despesas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 6. CANCELAR DESPESA
    public function cancel($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $despesa = ViagemDespesa::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada'
                ], 404);
            }
            
            // Cancelar despesa (marcar como inativa)
            $despesa->update([
                'status' => 'cancelled',
                'is_active' => false,
                'updated_at' => now(),
            ]);
            
            Log::info('✅ Despesa cancelada', [
                'id' => $despesa->id,
                'viagem_id' => $despesa->viagem_id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa cancelada com sucesso!',
                'data' => [
                    'id' => $despesa->id,
                    'status' => $despesa->status,
                    'is_active' => (bool) $despesa->is_active,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao cancelar despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao cancelar despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 7. CANCELAR LOTE DE DESPESAS
    public function cancelBatch(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:viagem_despesas,id',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erro de validação'
                ], 422);
            }
            
            $ids = $request->ids;
            
            // Cancelar todas as despesas do lote
            $updated = ViagemDespesa::whereIn('id', $ids)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->update([
                    'status' => 'cancelled',
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            
            Log::info('✅ Lote de despesas cancelado', [
                'total_ids' => count($ids),
                'canceladas' => $updated,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "$updated despesa(s) cancelada(s) com sucesso!",
                'data' => [
                    'total_enviadas' => count($ids),
                    'canceladas' => $updated,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao cancelar lote de despesas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao cancelar despesas',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 8. ATUALIZAR DESPESA
    public function update(Request $request, $id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $despesa = ViagemDespesa::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'tipo_despesa_id' => 'sometimes|required|exists:tipo_despesas,id',
                'amount' => 'sometimes|required|numeric|min:0.01',
                'currency' => ['sometimes', 'required', Rule::in(['MZN', 'USD', 'EUR', 'ZAR', 'ZWL', 'MWK', 'ZMW'])],
                'driver_name' => 'sometimes|required|string|max:255',
                'payment_description' => 'nullable|string|max:1000',
                'status' => ['sometimes', 'required', Rule::in(['pending', 'approved', 'paid', 'settled', 'cancelled'])],
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erro de validação'
                ], 422);
            }
            
            // Atualizar apenas os campos fornecidos
            $updateData = [];
            
            if ($request->has('tipo_despesa_id')) {
                $updateData['tipo_despesa_id'] = $request->tipo_despesa_id;
                // Buscar novo nome do tipo de despesa
                $tipoDespesa = TipoDespesa::find($request->tipo_despesa_id);
                if ($tipoDespesa) {
                    $updateData['expense_head'] = $tipoDespesa->nome;
                }
            }
            
            if ($request->has('amount')) {
                $updateData['amount'] = $request->amount;
            }
            
            if ($request->has('currency')) {
                $updateData['currency'] = $request->currency;
            }
            
            if ($request->has('driver_name')) {
                $updateData['driver_name'] = $request->driver_name;
            }
            
            if ($request->has('payment_description')) {
                $updateData['payment_description'] = $request->payment_description;
            }
            
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }
            
            $despesa->update($updateData);
            
            Log::info('✅ Despesa atualizada', [
                'id' => $despesa->id,
                'viagem_id' => $despesa->viagem_id,
                'tenant_id' => $tenantId
            ]);
            
            // Carregar relacionamentos atualizados
            $despesa->load(['tipoDespesa', 'usuario:id,name,email']);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa atualizada com sucesso!',
                'data' => [
                    'id' => $despesa->id,
                    'viagem_id' => $despesa->viagem_id,
                    'tipo_despesa_id' => $despesa->tipo_despesa_id,
                    'expense_head' => $despesa->expense_head,
                    'amount' => (float) $despesa->amount,
                    'currency' => $despesa->currency,
                    'driver_name' => $despesa->driver_name,
                    'payment_description' => $despesa->payment_description,
                    'status' => $despesa->status,
                    'is_active' => (bool) $despesa->is_active,
                    'updated_at' => $despesa->updated_at?->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 9. EXCLUIR DESPESA (soft delete)
    public function destroy($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $despesa = ViagemDespesa::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada'
                ], 404);
            }
            
            // Marcar como inativa em vez de excluir
            $despesa->update([
                'is_active' => false,
                'status' => 'cancelled',
            ]);
            
            Log::info('✅ Despesa marcada como inativa', [
                'id' => $id,
                'viagem_id' => $despesa->viagem_id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa removida com sucesso!',
                'data' => [
                    'id' => $id,
                    'is_active' => false,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao remover despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 10. ESTATÍSTICAS DE DESPESAS DA VIAGEM
    public function estatisticas($viagemId)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Verificar se a viagem existe
            $viagem = Viagem::where('id', $viagemId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }
            
            // Buscar todas as despesas da viagem
            $despesas = ViagemDespesa::where('viagem_id', $viagemId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->get();
            
            // Calcular estatísticas
            $totalDespesas = $despesas->count();
            $totalValor = $despesas->sum('amount');
            
            $porStatus = $despesas->groupBy('status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => (float) $group->sum('amount'),
                ];
            });
            
            $porMoeda = $despesas->groupBy('currency')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => (float) $group->sum('amount'),
                ];
            });
            
            $porTipo = $despesas->groupBy('tipo_despesa_id')->map(function ($group) {
                $primeira = $group->first();
                return [
                    'tipo_id' => $primeira->tipo_despesa_id,
                    'tipo_nome' => $primeira->expense_head,
                    'count' => $group->count(),
                    'total' => (float) $group->sum('amount'),
                ];
            })->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'viagem_id' => $viagemId,
                    'trip_number' => $viagem->tripNumber,
                    'driver' => $viagem->driver,
                    'estatisticas' => [
                        'total_despesas' => $totalDespesas,
                        'total_valor' => (float) $totalValor,
                        'valor_medio' => $totalDespesas > 0 ? (float) ($totalValor / $totalDespesas) : 0,
                    ],
                    'distribuicao_status' => $porStatus,
                    'distribuicao_moeda' => $porMoeda,
                    'distribuicao_tipo' => $porTipo,
                    'tenant_id' => $tenantId,
                    'atualizado_em' => now()->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao buscar estatísticas de despesas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar estatísticas',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}