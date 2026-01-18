<?php
// app/Http/Controllers/Api/CamiaoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Camiao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CamiaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Função auxiliar para converter snake_case para camelCase
    private function paraCamelCase($camiao)
    {
        return [
            'id' => $camiao->id,
            'matricula' => $camiao->matricula,
            'marca' => $camiao->marca,
            'modelo' => $camiao->modelo,
            'anoFabricacao' => $camiao->ano_fabricacao,
            'capacidadeCarga' => $camiao->capacidade_carga,
            'tipoCombustivel' => $camiao->tipo_combustivel,
            'consumoMedio' => $camiao->consumo_medio,
            'numeroEixos' => $camiao->numero_eixos,
            'tara' => $camiao->tara,
            'cmr' => $camiao->cmr,
            'seguroValidade' => $camiao->seguro_validade,
            'inspecaoValidade' => $camiao->inspecao_validade,
            'estado' => $camiao->estado,
            'localizacao' => $camiao->localizacao,
            'observacoes' => $camiao->observacoes,
            'criadoPor' => $camiao->criado_por,
            'tenantId' => $camiao->tenant_id, // Adicionado para debug
            'createdAt' => $camiao->created_at->toISOString(),
            'updatedAt' => $camiao->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/camioes', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Camiao::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('matricula', 'like', "%{$search}%")
                      ->orWhere('marca', 'like', "%{$search}%")
                      ->orWhere('modelo', 'like', "%{$search}%")
                      ->orWhere('localizacao', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $camioes = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter todos os itens para camelCase
            $camioesCamelCase = $camioes->map(function ($camiao) {
                return $this->paraCamelCase($camiao);
            });
            
            Log::info('✅ Camiões listados', [
                'total' => $camioes->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $camioesCamelCase->toArray(),
                'pagination' => [
                    'page' => $camioes->currentPage(),
                    'limit' => $perPage,
                    'total' => $camioes->total(),
                    'totalPages' => $camioes->lastPage(),
                    'hasNextPage' => $camioes->hasMorePages(),
                    'hasPrevPage' => $camioes->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar camiões: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/camioes', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'matricula' => 'required|string|max:20|unique:camioes,matricula,NULL,id,tenant_id,' . $tenantId,
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'anoFabricacao' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'capacidadeCarga' => 'required|string|max:50',
            'tipoCombustivel' => 'required|in:Diesel,Gasolina,Elétrico,Híbrido',
            'numeroEixos' => 'required|integer|min:2|max:10',
            'tara' => 'required|string|max:50',
            'cmr' => 'required|string|max:50',
            'seguroValidade' => 'required|date',
            'inspecaoValidade' => 'required|date',
            'estado' => 'required|in:Operacional,Manutenção,Avariado,Fora de Serviço',
            'localizacao' => 'required|string|max:255',
        ], [
            'matricula.unique' => 'Já existe um camião com esta matrícula',
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
                'matricula' => $request->matricula,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'ano_fabricacao' => $request->anoFabricacao,
                'capacidade_carga' => $request->capacidadeCarga,
                'tipo_combustivel' => $request->tipoCombustivel,
                'consumo_medio' => $request->consumoMedio ?? '',
                'numero_eixos' => $request->numeroEixos,
                'tara' => $request->tara,
                'cmr' => $request->cmr,
                'seguro_validade' => $request->seguroValidade,
                'inspecao_validade' => $request->inspecaoValidade,
                'estado' => $request->estado,
                'localizacao' => $request->localizacao,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando camião', $dados);
            
            $camiao = Camiao::create($dados);
            
            Log::info('✅ Camião criado', [
                'id' => $camiao->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($camiao),
                'message' => 'Camião criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar camião: ' . $e->getMessage());
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
            $camiao = Camiao::where('tenant_id', $tenantId)->find($id);
            
            if (!$camiao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Camião não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($camiao)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar camião: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/camioes/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $camiao = Camiao::where('tenant_id', $tenantId)->find($id);
            
            if (!$camiao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Camião não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'matricula' => 'required|string|max:20|unique:camioes,matricula,' . $id . ',id,tenant_id,' . $tenantId,
                'marca' => 'required|string|max:100',
                'modelo' => 'required|string|max:100',
                'anoFabricacao' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'capacidadeCarga' => 'required|string|max:50',
                'tipoCombustivel' => 'required|in:Diesel,Gasolina,Elétrico,Híbrido',
                'numeroEixos' => 'required|integer|min:2|max:10',
                'tara' => 'required|string|max:50',
                'cmr' => 'required|string|max:50',
                'seguroValidade' => 'required|date',
                'inspecaoValidade' => 'required|date',
                'estado' => 'required|in:Operacional,Manutenção,Avariado,Fora de Serviço',
                'localizacao' => 'required|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $camiao->update([
                'matricula' => $request->matricula,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'ano_fabricacao' => $request->anoFabricacao,
                'capacidade_carga' => $request->capacidadeCarga,
                'tipo_combustivel' => $request->tipoCombustivel,
                'consumo_medio' => $request->consumoMedio ?? $camiao->consumo_medio,
                'numero_eixos' => $request->numeroEixos,
                'tara' => $request->tara,
                'cmr' => $request->cmr,
                'seguro_validade' => $request->seguroValidade,
                'inspecao_validade' => $request->inspecaoValidade,
                'estado' => $request->estado,
                'localizacao' => $request->localizacao,
                'observacoes' => $request->observacoes ?? $camiao->observacoes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($camiao->fresh()),
                'message' => 'Camião atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar camião: ' . $e->getMessage());
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
            $camiao = Camiao::where('tenant_id', $tenantId)->find($id);
            
            if (!$camiao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Camião não encontrado'
                ], 404);
            }
            
            $camiao->delete();
            
            Log::info('✅ Camião excluído', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Camião excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir camião: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}