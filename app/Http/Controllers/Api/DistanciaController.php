<?php
// app/Http/Controllers/Api/DistanciaController.php - CORRIGIDO
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distancia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DistanciaController extends Controller
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
    private function paraCamelCase($distancia)
    {
        return [
            'id' => $distancia->id,
            'origem' => $distancia->origem,
            'destino' => $distancia->destino,
            'distanciaTotal' => $distancia->distancia_total,
            'tempoEstimado' => $distancia->tempo_estimado,
            'pontosParada' => $distancia->pontos_parada,
            'estradaPreferencial' => $distancia->estrada_preferencial,
            'observacoes' => $distancia->observacoes,
            'criadoPor' => $distancia->criado_por,
            'tenantId' => $distancia->tenant_id, // Adicionado para debug
            'createdAt' => $distancia->created_at->toISOString(),
            'updatedAt' => $distancia->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('📥 GET /api/distancias', [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Distancia::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('origem', 'like', "%{$search}%")
                      ->orWhere('destino', 'like', "%{$search}%")
                      ->orWhere('estrada_preferencial', 'like', "%{$search}%")
                      ->orWhere('pontos_parada', 'like', "%{$search}%");
                });
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $distancias = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter para camelCase
            $distanciasCamelCase = $distancias->map(function ($distancia) {
                return $this->paraCamelCase($distancia);
            });
            
            Log::info('✅ Distâncias listadas', [
                'total' => $distancias->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $distanciasCamelCase->toArray(),
                'pagination' => [
                    'page' => $distancias->currentPage(),
                    'limit' => $perPage,
                    'total' => $distancias->total(),
                    'totalPages' => $distancias->lastPage(),
                    'hasNextPage' => $distancias->hasMorePages(),
                    'hasPrevPage' => $distancias->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar distâncias: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/distancias', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'origem' => 'required|string|max:255',
            'destino' => 'required|string|max:255',
            'distanciaTotal' => 'required|numeric|min:0',
            'tempoEstimado' => 'nullable|numeric|min:0',
            'pontosParada' => 'nullable|string',
            'estradaPreferencial' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            Log::error('❌ Validação falhou', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Validar que origem e destino não sejam iguais
        if (strtolower($request->origem) === strtolower($request->destino)) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => ['destino' => ['Origem e destino não podem ser iguais']]
            ], 422);
        }
        
        // Validar combinação única de origem/destino por tenant
        $exists = Distancia::where('tenant_id', $tenantId)
            ->where('origem', $request->origem)
            ->where('destino', $request->destino)
            ->exists();
        
        if ($exists) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => ['destino' => ['Já existe uma distância com esta origem e destino']]
            ], 422);
        }
        
        try {
            $dados = [
                'origem' => $request->origem,
                'destino' => $request->destino,
                'distancia_total' => $request->distanciaTotal,
                'tempo_estimado' => $request->tempoEstimado ?? '',
                'pontos_parada' => $request->pontosParada ?? '',
                'estrada_preferencial' => $request->estradaPreferencial ?? '',
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando distância', $dados);
            
            $distancia = Distancia::create($dados);
            
            Log::info('✅ Distância criada', [
                'id' => $distancia->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($distancia),
                'message' => 'Distância criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar distância: ' . $e->getMessage());
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
            $distancia = Distancia::where('tenant_id', $tenantId)->find($id);
            
            if (!$distancia) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distância não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($distancia)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar distância: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('📥 PUT /api/distancias/' . $id, [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $distancia = Distancia::where('tenant_id', $tenantId)->find($id);
            
            if (!$distancia) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distância não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'origem' => 'required|string|max:255',
                'destino' => 'required|string|max:255',
                'distanciaTotal' => 'required|numeric|min:0',
                'tempoEstimado' => 'nullable|numeric|min:0',
                'pontosParada' => 'nullable|string',
                'estradaPreferencial' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Validar que origem e destino não sejam iguais
            if (strtolower($request->origem) === strtolower($request->destino)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => ['destino' => ['Origem e destino não podem ser iguais']]
                ], 422);
            }
            
            // Validar combinação única de origem/destino por tenant (excluindo o próprio registro)
            $exists = Distancia::where('tenant_id', $tenantId)
                ->where('origem', $request->origem)
                ->where('destino', $request->destino)
                ->where('id', '!=', $id)
                ->exists();
            
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => ['destino' => ['Já existe outra distância com esta origem e destino']]
                ], 422);
            }
            
            $distancia->update([
                'origem' => $request->origem,
                'destino' => $request->destino,
                'distancia_total' => $request->distanciaTotal,
                'tempo_estimado' => $request->tempoEstimado ?? $distancia->tempo_estimado,
                'pontos_parada' => $request->pontosParada ?? $distancia->pontos_parada,
                'estrada_preferencial' => $request->estradaPreferencial ?? $distancia->estrada_preferencial,
                'observacoes' => $request->observacoes ?? $distancia->observacoes,
            ]);
            
            Log::info('✅ Distância atualizada', [
                'id' => $distancia->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($distancia->fresh()),
                'message' => 'Distância atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar distância: ' . $e->getMessage());
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
            $distancia = Distancia::where('tenant_id', $tenantId)->find($id);
            
            if (!$distancia) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distância não encontrada'
                ], 404);
            }
            
            $distancia->delete();
            
            Log::info('✅ Distância excluída', [
                'id' => $id,
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Distância excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir distância: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Método adicional: Buscar distância por origem e destino
    public function buscarPorRota(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        $validator = Validator::make($request->all(), [
            'origem' => 'required|string|max:255',
            'destino' => 'required|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $distancia = Distancia::where('tenant_id', $tenantId)
                ->where('origem', $request->origem)
                ->where('destino', $request->destino)
                ->first();
            
            if (!$distancia) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distância não encontrada para esta rota'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($distancia)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar distância por rota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}