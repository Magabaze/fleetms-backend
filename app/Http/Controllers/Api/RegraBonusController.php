<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegraBonus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class RegraBonusController extends Controller
{
    public function __construct() 
    { 
        $this->middleware('auth:sanctum'); 
    }

    // Função auxiliar para converter snake_case para camelCase (igual ao AgenteController)
    private function paraCamelCase($regra)
    {
        return [
            'id' => $regra->id,
            'nome' => $regra->nome,
            'transitType' => $regra->transit_type,
            'loadStatus' => $regra->load_status,
            'cargoNature' => $regra->cargo_nature,
            'calculationBase' => $regra->calculation_base,
            'valorBonus' => (float) $regra->valor_bonus,
            'status' => $regra->status,
            'criadoPor' => $regra->criado_por,
            'tenantId' => $regra->tenant_id,
            'createdAt' => $regra->created_at?->toISOString(),
            'updatedAt' => $regra->updated_at?->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/bonus/regras', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            // FILTRO IMPORTANTE: Usar o tenant_id do usuário logado
            $query = RegraBonus::where('tenant_id', $tenantId);

            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                      ->orWhere('transit_type', 'like', "%{$search}%");
                });
            }
            
            // Filtro por status (igual ao cliente)
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }

            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $data = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter todos os itens para camelCase
            $itemsCamelCase = $data->map(function ($item) {
                return $this->paraCamelCase($item);
            });
            
            Log::info('✅ Regras listadas', [
                'total' => $data->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $itemsCamelCase->toArray(),
                'pagination' => [
                    'page' => $data->currentPage(),
                    'limit' => $perPage,
                    'total' => $data->total(),
                    'totalPages' => $data->lastPage(),
                    'hasNextPage' => $data->hasMorePages(),
                    'hasPrevPage' => $data->currentPage() > 1,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar regras: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 POST /api/bonus/regras', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:255',
            'valorBonus' => 'required|numeric|min:0',
            'status' => 'required|in:ativo,inativo',
        ]);

        if ($validator->fails()) {
            Log::error('❌ Validação falhou', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $dados = [
                'nome' => $request->nome,
                'transit_type' => $request->transitType ?? null,
                'load_status' => $request->loadStatus ?? null,
                'cargo_nature' => $request->cargoNature ?? null,
                'calculation_base' => $request->calculationBase ?? 'fixed',
                'valor_bonus' => $request->valorBonus,
                'status' => $request->status,
                'criado_por' => $user->name ?? 'Admin',
                'tenant_id' => $tenantId, // CRÍTICO: Usar o tenant_id do usuário
            ];
            
            Log::info('💾 Salvando regra', $dados);
            
            $regra = RegraBonus::create($dados);
            
            Log::info('✅ Regra criada', [
                'id' => $regra->id,
                'tenant_id' => $tenantId
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($regra),
                'message' => 'Regra criada com sucesso!'
            ], 201);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar regra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            // IMPORTANTE: Filtrar pelo tenant_id do usuário
            $regra = RegraBonus::where('tenant_id', $tenantId)->find($id);
            
            if (!$regra) {
                return response()->json([
                    'success' => false,
                    'error' => 'Regra não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($regra)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar regra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 PUT /api/bonus/regras/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            // IMPORTANTE: Filtrar pelo tenant_id do usuário
            $regra = RegraBonus::where('tenant_id', $tenantId)->find($id);
            
            if (!$regra) {
                return response()->json([
                    'success' => false,
                    'error' => 'Regra não encontrada'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'valorBonus' => 'required|numeric|min:0',
                'status' => 'required|in:ativo,inativo',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            $regra->update([
                'nome' => $request->nome,
                'transit_type' => $request->transitType ?? $regra->transit_type,
                'load_status' => $request->loadStatus ?? $regra->load_status,
                'cargo_nature' => $request->cargoNature ?? $regra->cargo_nature,
                'calculation_base' => $request->calculationBase ?? $regra->calculation_base,
                'valor_bonus' => $request->valorBonus,
                'status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($regra->fresh()),
                'message' => 'Regra atualizada com sucesso!'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar regra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            // IMPORTANTE: Filtrar pelo tenant_id do usuário
            $regra = RegraBonus::where('tenant_id', $tenantId)->find($id);
            
            if (!$regra) {
                return response()->json([
                    'success' => false,
                    'error' => 'Regra não encontrada'
                ], 404);
            }
            
            $regra->delete();
            
            Log::info('✅ Regra excluída', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Regra excluída com sucesso!'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir regra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}