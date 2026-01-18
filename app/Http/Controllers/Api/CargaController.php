<?php
// app/Http/Controllers/Api/CargaController.php - VERSÃO CORRIGIDA

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carga;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CargaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Função auxiliar para converter snake_case para camelCase
    private function paraCamelCase($carga)
    {
        return [
            'id' => $carga->id,
            'tipoCarga' => $carga->tipo_carga,
            'descricao' => $carga->descricao,
            'valor' => $carga->valor ? (float) $carga->valor : null,
            'peso' => $carga->peso,
            'volume' => $carga->volume,
            'dimensoes' => $carga->dimensoes,
            'observacoes' => $carga->observacoes,
            'criadoPor' => $carga->criado_por,
            'tenantId' => $carga->tenant_id, // Adicionado para debug
            'createdAt' => $carga->created_at->toISOString(),
            'updatedAt' => $carga->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/cargas', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Carga::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('descricao', 'like', "%{$search}%")
                      ->orWhere('observacoes', 'like', "%{$search}%")
                      ->orWhere('tipo_carga', 'like', "%{$search}%");
                });
            }
            
            // Filtro por tipo
            if ($request->has('tipo_carga') && $request->tipo_carga && $request->tipo_carga !== 'todos') {
                $query->where('tipo_carga', $request->tipo_carga);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $cargas = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter todos os itens para camelCase
            $cargasCamelCase = $cargas->map(function ($carga) {
                return $this->paraCamelCase($carga);
            });
            
            Log::info('✅ Cargas listadas', [
                'total' => $cargas->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $cargasCamelCase->toArray(),
                'pagination' => [
                    'page' => $cargas->currentPage(),
                    'limit' => $perPage,
                    'total' => $cargas->total(),
                    'totalPages' => $cargas->lastPage(),
                    'hasNextPage' => $cargas->hasMorePages(),
                    'hasPrevPage' => $cargas->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar cargas: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/cargas', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'tipoCarga' => 'required|in:General Cargo,Hazardous,Especial,Refrigerada,Líquida,Seca',
            'descricao' => 'required|string|max:500',
            'valor' => 'nullable|numeric|min:0',
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
            // CORREÇÃO: Converter valor vazio para null
            $valor = null;
            if ($request->has('valor') && $request->valor !== '' && $request->valor !== null) {
                $valor = $request->valor;
            }
            
            $dados = [
                'tipo_carga' => $request->tipoCarga,
                'descricao' => $request->descricao,
                'valor' => $valor, // ✅ AGORA PODE SER NULL
                'peso' => $request->peso ?? '',
                'volume' => $request->volume ?? '',
                'dimensoes' => $request->dimensoes ?? '',
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando carga', $dados);
            
            $carga = Carga::create($dados);
            
            Log::info('✅ Carga criada', [
                'id' => $carga->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($carga),
                'message' => 'Carga criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar carga: ' . $e->getMessage());
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
            $carga = Carga::where('tenant_id', $tenantId)->find($id);
            
            if (!$carga) {
                return response()->json([
                    'success' => false,
                    'error' => 'Carga não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($carga)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar carga: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/cargas/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $carga = Carga::where('tenant_id', $tenantId)->find($id);
            
            if (!$carga) {
                return response()->json([
                    'success' => false,
                    'error' => 'Carga não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'tipoCarga' => 'required|in:General Cargo,Hazardous,Especial,Refrigerada,Líquida,Seca',
                'descricao' => 'required|string|max:500',
                'valor' => 'nullable|numeric|min:0',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // CORREÇÃO: Tratar valor vazio
            $valor = $carga->valor;
            if ($request->has('valor')) {
                if ($request->valor === '' || $request->valor === null) {
                    $valor = null;
                } else {
                    $valor = $request->valor;
                }
            }
            
            $carga->update([
                'tipo_carga' => $request->tipoCarga,
                'descricao' => $request->descricao,
                'valor' => $valor,
                'peso' => $request->peso ?? $carga->peso,
                'volume' => $request->volume ?? $carga->volume,
                'dimensoes' => $request->dimensoes ?? $carga->dimensoes,
                'observacoes' => $request->observacoes ?? $carga->observacoes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($carga->fresh()),
                'message' => 'Carga atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar carga: ' . $e->getMessage());
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
            $carga = Carga::where('tenant_id', $tenantId)->find($id);
            
            if (!$carga) {
                return response()->json([
                    'success' => false,
                    'error' => 'Carga não encontrada'
                ], 404);
            }
            
            $carga->delete();
            
            Log::info('✅ Carga excluída', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Carga excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir carga: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}