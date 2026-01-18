<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DriverExpense;
use App\Models\Viagem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DriverExpenseController extends Controller
{
    /**
     * Buscar despesas por viagem
     */
    public function buscarPorViagem($viagemId)
    {
        try {
            // Verificar se a viagem existe
            $viagem = Viagem::find($viagemId);
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }

            // Buscar despesas da viagem
            $despesas = DriverExpense::where('viagem_id', $viagemId)
                ->where('is_active', true)
                ->with(['usuario'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calcular resumo
            $total = $despesas->sum('amount');
            $approved = $despesas->where('status', 'approved')->sum('amount');
            $pending = $despesas->where('status', 'pending')->sum('amount');
            $cancelled = $despesas->where('status', 'cancelled')->sum('amount');

            return response()->json([
                'success' => true,
                'data' => $despesas,
                'summary' => [
                    'total' => $total,
                    'approved' => $approved,
                    'pending' => $pending,
                    'cancelled' => $cancelled,
                    'total_count' => $despesas->count(),
                    'approved_count' => $despesas->where('status', 'approved')->count(),
                    'pending_count' => $despesas->where('status', 'pending')->count(),
                    'cancelled_count' => $despesas->where('status', 'cancelled')->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar despesas da viagem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar nova despesa para viagem (CORRIGIDO)
     */
    public function criarParaViagem(Request $request, $viagemId)
    {
        DB::beginTransaction();
        
        try {
            // Validar dados (SEM tipo_despesa_id)
            $validated = $request->validate([
                'expense_head' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'currency' => 'required|string|size:3',
                'driver_name' => 'required|string|max:255',
                'payment_description' => 'nullable|string'
            ]);

            // Verificar se viagem existe
            $viagem = Viagem::find($viagemId);
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }

            // Criar despesa
            $despesa = new DriverExpense();
            $despesa->viagem_id = $viagemId;
            $despesa->expense_head = $validated['expense_head'];
            $despesa->amount = $validated['amount'];
            $despesa->currency = $validated['currency'];
            $despesa->driver_name = $validated['driver_name'];
            $despesa->payment_description = $validated['payment_description'] ?? null;
            $despesa->created_by = Auth::user()->name;
            $despesa->created_by_id = Auth::id();
            $despesa->status = 'pending';
            $despesa->is_active = true;
            
            $despesa->save();

            // Carregar relacionamentos
            $despesa->load(['usuario']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $despesa,
                'message' => 'Despesa criada com sucesso'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar despesa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprovar despesas em lote
     */
    public function aprovarLote(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:driver_expenses,id'
            ]);

            $updatedCount = DriverExpense::whereIn('id', $validated['ids'])
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} despesas aprovadas com sucesso",
                'count' => $updatedCount
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao aprovar despesas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprovar todas as despesas de uma viagem
     */
    public function aprovarTodas($viagemId)
    {
        DB::beginTransaction();
        
        try {
            // Verificar se viagem existe
            $viagem = Viagem::find($viagemId);
            if (!$viagem) {
                return response()->json([
                    'success' => false,
                    'error' => 'Viagem não encontrada'
                ], 404);
            }

            $updatedCount = DriverExpense::where('viagem_id', $viagemId)
                ->where('status', 'pending')
                ->where('is_active', true)
                ->update([
                    'status' => 'approved',
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} despesas aprovadas com sucesso",
                'count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao aprovar todas as despesas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar despesas em lote
     */
    public function cancelarLote(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:driver_expenses,id'
            ]);

            $updatedCount = DriverExpense::whereIn('id', $validated['ids'])
                ->update([
                    'status' => 'cancelled',
                    'is_active' => false,
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} despesas canceladas com sucesso",
                'count' => $updatedCount
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao cancelar despesas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar despesa
     */
    public function deletar($viagemId, $id)
    {
        DB::beginTransaction();
        
        try {
            // Verificar se a despesa pertence à viagem
            $despesa = DriverExpense::where('id', $id)
                ->where('viagem_id', $viagemId)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada ou não pertence a esta viagem'
                ], 404);
            }

            // Apenas o criador ou admin pode deletar
            $user = Auth::user();
            if ($despesa->created_by_id !== $user->id && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não autorizado'
                ], 403);
            }

            // Soft delete
            $despesa->is_active = false;
            $despesa->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Despesa removida com sucesso'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover despesa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar despesa (CORRIGIDO)
     */
    public function atualizar(Request $request, $viagemId, $id)
    {
        DB::beginTransaction();
        
        try {
            // Validar dados (SEM tipo_despesa_id)
            $validated = $request->validate([
                'expense_head' => 'sometimes|required|string|max:255',
                'amount' => 'sometimes|required|numeric|min:0',
                'currency' => 'sometimes|required|string|size:3',
                'driver_name' => 'sometimes|required|string|max:255',
                'payment_description' => 'nullable|string',
                'status' => 'sometimes|in:pending,approved,paid,settled,cancelled'
            ]);

            // Verificar se a despesa pertence à viagem
            $despesa = DriverExpense::where('id', $id)
                ->where('viagem_id', $viagemId)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada ou não pertence a esta viagem'
                ], 404);
            }

            // Verificar permissões
            $user = Auth::user();
            $canUpdate = $despesa->created_by_id === $user->id 
                || $user->hasRole('admin');

            if (!$canUpdate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não autorizado a atualizar esta despesa'
                ], 403);
            }

            // Atualizar despesa
            $despesa->fill($validated);
            $despesa->save();

            // Carregar relacionamentos
            $despesa->load(['usuario']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $despesa,
                'message' => 'Despesa atualizada com sucesso'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar despesa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter tipos de despesa disponíveis
     */
    public function tiposDespesa()
    {
        try {
            // Como não temos tabela tipo_despesas, retornar lista fixa
            $tipos = [
                ['id' => 1, 'nome' => 'Fuel', 'cor' => '#ef4444'],
                ['id' => 2, 'nome' => 'Tolls', 'cor' => '#3b82f6'],
                ['id' => 3, 'nome' => 'Parking', 'cor' => '#10b981'],
                ['id' => 4, 'nome' => 'Accommodation', 'cor' => '#f59e0b'],
                ['id' => 5, 'nome' => 'Food', 'cor' => '#8b5cf6'],
                ['id' => 6, 'nome' => 'Repairs', 'cor' => '#ec4899'],
                ['id' => 7, 'nome' => 'Other', 'cor' => '#6b7280'],
            ];

            return response()->json([
                'success' => true,
                'data' => $tipos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao carregar tipos de despesa'
            ], 500);
        }
    }

    /**
     * Buscar despesa por ID
     */
    public function buscarPorId($id)
    {
        try {
            $despesa = DriverExpense::where('id', $id)
                ->where('is_active', true)
                ->with(['usuario', 'viagem'])
                ->first();

            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $despesa
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar despesa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test endpoint
     */
    public function test()
    {
        return response()->json([
            'success' => true,
            'message' => 'Driver Expense API is working',
            'timestamp' => now()->toISOString()
        ]);
    }
}