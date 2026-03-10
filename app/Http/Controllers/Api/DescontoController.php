<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Desconto;
use App\Models\Carteira;
use App\Models\CarteiraMovimento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DescontoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function getTenantId(): string
    {
        return Auth::user()->tenant_id ?? 'default';
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Converte modelo Desconto para camelCase (resposta JSON).
     */
    private function paraCamelCase($desconto): array
    {
        return [
            'id'        => $desconto->id,
            'motorista' => $desconto->motorista,
            'tipo'      => $desconto->tipo,
            'descricao' => $desconto->descricao,
            'valor'     => (float) $desconto->valor,
            'data'      => $desconto->data_desconto?->toISOString(),
            'status'    => $desconto->status,
            'criadoPor' => $desconto->criado_por,
            'tenantId'  => $desconto->tenant_id,
            'createdAt' => $desconto->created_at?->toISOString(),
            'updatedAt' => $desconto->updated_at?->toISOString(),
        ];
    }

    /**
     * Regista um novo desconto na carteira.
     *
     * REGRA: ao criar um desconto, APENAS a total_divida aumenta.
     * O saldo NÃO muda aqui — só muda quando um pagamento é processado.
     *
     * Razão: o saldo representa bónus já ganho e ainda não pago.
     * A dívida é uma obrigação separada que será abatida no momento do pagamento.
     */
    private function registarDescontoNaCarteira(Desconto $desconto, string $tenantId): void
    {
        $carteira = Carteira::firstOrCreate(
            ['motorista' => $desconto->motorista, 'tenant_id' => $tenantId],
            ['saldo' => 0, 'total_bonus' => 0, 'total_divida' => 0]
        );

        // ✅ SÓ aumenta a dívida — o saldo permanece inalterado
        $carteira->total_divida    += $desconto->valor;
        $carteira->ultimo_movimento = now();
        $carteira->save();

        // Movimento informativo (saldo_anterior == saldo_posterior porque não mudou)
        CarteiraMovimento::create([
            'motorista'       => $desconto->motorista,
            'origem_id'       => $desconto->id,
            'origem_type'     => Desconto::class,
            'tipo'            => 'debito',
            'origem_tipo'     => 'desconto',
            'descricao'       => $desconto->descricao ?: "Desconto pendente: {$desconto->tipo}",
            'valor'           => $desconto->valor,
            'saldo_anterior'  => $carteira->saldo,
            'saldo_posterior' => $carteira->saldo,
            'tenant_id'       => $tenantId,
        ]);

        Log::info('📋 Dívida adicionada à carteira', [
            'motorista'   => $desconto->motorista,
            'valor'       => $desconto->valor,
            'nova_divida' => $carteira->total_divida,
            'saldo'       => $carteira->saldo,   // inalterado
        ]);
    }

    /**
     * Reverte um desconto da carteira (ao editar ou eliminar).
     *
     * REGRA: APENAS diminui a total_divida. O saldo NÃO muda.
     */
    private function reverterDescontoNaCarteira(Desconto $desconto, string $tenantId): void
    {
        $carteira = Carteira::where('motorista', $desconto->motorista)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$carteira) {
            Log::warning('Carteira não encontrada ao reverter desconto', [
                'motorista' => $desconto->motorista,
                'desconto'  => $desconto->id,
            ]);
            return;
        }

        // ✅ SÓ diminui a dívida — o saldo permanece inalterado
        $carteira->total_divida    = max(0, $carteira->total_divida - $desconto->valor);
        $carteira->ultimo_movimento = now();
        $carteira->save();

        CarteiraMovimento::create([
            'motorista'       => $desconto->motorista,
            'origem_id'       => $desconto->id,
            'origem_type'     => Desconto::class,
            'tipo'            => 'credito',
            'origem_tipo'     => 'desconto_reversao',
            'descricao'       => "Reversão de desconto: {$desconto->tipo}",
            'valor'           => $desconto->valor,
            'saldo_anterior'  => $carteira->saldo,
            'saldo_posterior' => $carteira->saldo,   // saldo não mudou
            'tenant_id'       => $tenantId,
        ]);

        Log::info('↩️ Dívida revertida na carteira', [
            'motorista'   => $desconto->motorista,
            'valor'       => $desconto->valor,
            'nova_divida' => $carteira->total_divida,
            'saldo'       => $carteira->saldo,   // inalterado
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  INDEX
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();

        Log::info('📥 GET /api/bonus/descontos', [
            'user_id'   => Auth::id(),
            'tenant_id' => $tenantId,
            'query'     => $request->all(),
        ]);

        try {
            $query = Desconto::where('tenant_id', $tenantId);

            if ($request->filled('status') && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('motorista', 'like', "%{$search}%")
                      ->orWhere('tipo', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $request->get('limit', 10);
            $page    = (int) $request->get('page', 1);

            $paginado = $query->orderBy('data_desconto', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success'    => true,
                'data'       => $paginado->map(fn($d) => $this->paraCamelCase($d))->toArray(),
                'pagination' => [
                    'page'        => $paginado->currentPage(),
                    'limit'       => $perPage,
                    'total'       => $paginado->total(),
                    'totalPages'  => $paginado->lastPage(),
                    'hasNextPage' => $paginado->hasMorePages(),
                    'hasPrevPage' => $paginado->currentPage() > 1,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar descontos: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  STORE
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $tenantId = $this->getTenantId();

        Log::info('📥 POST /api/bonus/descontos', [
            'user_id'   => Auth::id(),
            'tenant_id' => $tenantId,
            'dados'     => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'motorista' => 'required|string|max:255',
            'tipo'      => 'required|string|max:255',
            'valor'     => 'required|numeric|min:0.01',
            'data'      => 'nullable|date',
            'descricao' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => 'Erro de validação',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $desconto = Desconto::create([
                'motorista'     => $request->motorista,
                'tipo'          => $request->tipo,
                'descricao'     => $request->descricao ?? '',
                'valor'         => $request->valor,
                'data_desconto' => $request->data ?? now(),
                'status'        => 'pendente',
                'criado_por'    => Auth::user()->name ?? 'Admin',
                'tenant_id'     => $tenantId,
            ]);

            $this->registarDescontoNaCarteira($desconto, $tenantId);

            DB::commit();

            Log::info('✅ Desconto criado', [
                'id'        => $desconto->id,
                'motorista' => $desconto->motorista,
                'valor'     => $desconto->valor,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $this->paraCamelCase($desconto),
                'message' => 'Desconto criado com sucesso!',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar desconto: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SHOW
    // ─────────────────────────────────────────────────────────────────────────
    public function show($id)
    {
        $tenantId = $this->getTenantId();

        try {
            $desconto = Desconto::where('tenant_id', $tenantId)->find($id);

            if (!$desconto) {
                return response()->json(['success' => false, 'error' => 'Desconto não encontrado'], 404);
            }

            return response()->json(['success' => true, 'data' => $this->paraCamelCase($desconto)]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar desconto: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  UPDATE
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId();

        Log::info('📥 PUT /api/bonus/descontos/' . $id, [
            'user_id'   => Auth::id(),
            'tenant_id' => $tenantId,
            'dados'     => $request->all(),
        ]);

        DB::beginTransaction();

        try {
            $desconto = Desconto::where('tenant_id', $tenantId)->find($id);

            if (!$desconto) {
                return response()->json(['success' => false, 'error' => 'Desconto não encontrado'], 404);
            }

            if ($desconto->status === 'aplicado') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Desconto já foi aplicado e não pode ser alterado',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'motorista' => 'required|string|max:255',
                'tipo'      => 'required|string|max:255',
                'valor'     => 'required|numeric|min:0.01',
                'data'      => 'nullable|date',
                'descricao' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Erro de validação',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // 1. Reverter o valor antigo da dívida
            $this->reverterDescontoNaCarteira($desconto, $tenantId);

            // 2. Actualizar o desconto
            $desconto->update([
                'motorista'     => $request->motorista,
                'tipo'          => $request->tipo,
                'descricao'     => $request->descricao ?? $desconto->descricao,
                'valor'         => $request->valor,
                'data_desconto' => $request->data ?? $desconto->data_desconto,
            ]);

            // 3. Registar o novo valor na dívida
            $this->registarDescontoNaCarteira($desconto->fresh(), $tenantId);

            DB::commit();

            Log::info('✅ Desconto actualizado', ['id' => $id, 'motorista' => $desconto->motorista]);

            return response()->json([
                'success' => true,
                'data'    => $this->paraCamelCase($desconto->fresh()),
                'message' => 'Desconto actualizado com sucesso!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao actualizar desconto: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DESTROY
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        $tenantId = $this->getTenantId();

        DB::beginTransaction();

        try {
            $desconto = Desconto::where('tenant_id', $tenantId)->find($id);

            if (!$desconto) {
                return response()->json(['success' => false, 'error' => 'Desconto não encontrado'], 404);
            }

            if ($desconto->status === 'aplicado') {
                return response()->json([
                    'success' => false,
                    'error'   => 'Desconto já foi aplicado e não pode ser excluído',
                ], 400);
            }

            // Reverter o efeito na dívida antes de eliminar
            $this->reverterDescontoNaCarteira($desconto, $tenantId);

            $desconto->delete();

            DB::commit();

            Log::info('✅ Desconto eliminado', [
                'id'        => $id,
                'motorista' => $desconto->motorista,
                'user_id'   => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Desconto eliminado com sucesso!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao eliminar desconto: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }
}