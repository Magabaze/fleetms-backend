<?php
// app/Http/Controllers/Api/TrelaController.php - CORRIGIDO

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trela;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class TrelaController extends Controller
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

    // Função auxiliar para converter snake_case para camelCase
    private function paraCamelCase($trela)
    {
        return [
            'id' => $trela->id,
            'matricula' => $trela->matricula,
            'marca' => $trela->marca,
            'modelo' => $trela->modelo,
            'anoFabricacao' => $trela->ano_fabricacao,
            'tipoTrela' => $trela->tipo_trela,
            'capacidadeCarga' => $trela->capacidade_carga,
            'numeroEixos' => $trela->numero_eixos,
            'tara' => $trela->tara,
            'cmr' => $trela->cmr,
            'seguroValidade' => $trela->seguro_validade,
            'inspecaoValidade' => $trela->inspecao_validade,
            'estado' => $trela->estado,
            'camiaoAssociado' => $trela->camiao_associado,
            'observacoes' => $trela->observacoes,
            'criadoPor' => $trela->criado_por,
            'tenantId' => $trela->tenant_id, // Adicionado para debug
            'createdAt' => $trela->created_at->toISOString(),
            'updatedAt' => $trela->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('📥 GET /api/trelas', [
            'user_id' => Auth::id(),
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Trela::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('matricula', 'like', "%{$search}%")
                      ->orWhere('marca', 'like', "%{$search}%")
                      ->orWhere('modelo', 'like', "%{$search}%")
                      ->orWhere('camiao_associado', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('estado') && $request->estado !== 'todos') {
                $query->where('estado', $request->estado);
            }
            
            if ($request->has('tipoTrela') && $request->tipoTrela !== 'todos') {
                $query->where('tipo_trela', $request->tipoTrela);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $trelas = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter todos os itens para camelCase
            $trelasCamelCase = $trelas->map(function ($trela) {
                return $this->paraCamelCase($trela);
            });
            
            Log::info('✅ Trelas listadas', [
                'total' => $trelas->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $trelasCamelCase->toArray(),
                'pagination' => [
                    'page' => $trelas->currentPage(),
                    'limit' => $perPage,
                    'total' => $trelas->total(),
                    'totalPages' => $trelas->lastPage(),
                    'hasNextPage' => $trelas->hasMorePages(),
                    'hasPrevPage' => $trelas->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar trelas: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/trelas', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'matricula' => 'required|string|max:20|unique:trelas,matricula,NULL,id,tenant_id,' . $tenantId,
            'marca' => 'required|string|max:100',
            'modelo' => 'required|string|max:100',
            'anoFabricacao' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'tipoTrela' => 'required|in:Reboque,Semi-reboque,Cisterna,Frigorífico,Plataforma',
            'capacidadeCarga' => 'required|string|max:50',
            'numeroEixos' => 'required|integer|min:1|max:10',
            'tara' => 'required|string|max:50',
            'cmr' => 'required|string|max:50',
            'seguroValidade' => 'required|date',
            'inspecaoValidade' => 'required|date',
            'estado' => 'required|in:Operacional,Manutenção,Avariado,Fora de Serviço',
        ], [
            'matricula.unique' => 'Já existe uma trela com esta matrícula',
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
                'tipo_trela' => $request->tipoTrela,
                'capacidade_carga' => $request->capacidadeCarga,
                'numero_eixos' => $request->numeroEixos,
                'tara' => $request->tara,
                'cmr' => $request->cmr,
                'seguro_validade' => $request->seguroValidade,
                'inspecao_validade' => $request->inspecaoValidade,
                'estado' => $request->estado,
                'camiao_associado' => $request->camiaoAssociado ?? '',
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            Log::info('💾 Salvando trela', $dados);
            
            $trela = Trela::create($dados);
            
            Log::info('✅ Trela criada', [
                'id' => $trela->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($trela),
                'message' => 'Trela criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar trela: ' . $e->getMessage());
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
            $trela = Trela::where('tenant_id', $tenantId)->find($id);
            
            if (!$trela) {
                return response()->json([
                    'success' => false,
                    'error' => 'Trela não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($trela)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar trela: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/trelas/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $trela = Trela::where('tenant_id', $tenantId)->find($id);
            
            if (!$trela) {
                return response()->json([
                    'success' => false,
                    'error' => 'Trela não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'matricula' => 'required|string|max:20|unique:trelas,matricula,' . $id . ',id,tenant_id,' . $tenantId,
                'marca' => 'required|string|max:100',
                'modelo' => 'required|string|max:100',
                'anoFabricacao' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'tipoTrela' => 'required|in:Reboque,Semi-reboque,Cisterna,Frigorífico,Plataforma',
                'capacidadeCarga' => 'required|string|max:50',
                'numeroEixos' => 'required|integer|min:1|max:10',
                'tara' => 'required|string|max:50',
                'cmr' => 'required|string|max:50',
                'seguroValidade' => 'required|date',
                'inspecaoValidade' => 'required|date',
                'estado' => 'required|in:Operacional,Manutenção,Avariado,Fora de Serviço',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $trela->update([
                'matricula' => $request->matricula,
                'marca' => $request->marca,
                'modelo' => $request->modelo,
                'ano_fabricacao' => $request->anoFabricacao,
                'tipo_trela' => $request->tipoTrela,
                'capacidade_carga' => $request->capacidadeCarga,
                'numero_eixos' => $request->numeroEixos,
                'tara' => $request->tara,
                'cmr' => $request->cmr,
                'seguro_validade' => $request->seguroValidade,
                'inspecao_validade' => $request->inspecaoValidade,
                'estado' => $request->estado,
                'camiao_associado' => $request->camiaoAssociado ?? $trela->camiao_associado,
                'observacoes' => $request->observacoes ?? $trela->observacoes,
            ]);
            
            Log::info('✅ Trela atualizada', [
                'id' => $trela->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($trela->fresh()),
                'message' => 'Trela atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar trela: ' . $e->getMessage());
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
            $trela = Trela::where('tenant_id', $tenantId)->find($id);
            
            if (!$trela) {
                return response()->json([
                    'success' => false,
                    'error' => 'Trela não encontrada'
                ], 404);
            }
            
            $trela->delete();
            
            Log::info('✅ Trela excluída', [
                'id' => $id,
                'user_id' => Auth::id(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Trela excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir trela: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Método adicional: Buscar trelas disponíveis (sem caminhão associado)
    public function disponiveis()
    {
        $tenantId = $this->getTenantId();
        
        try {
            $trelas = Trela::where('tenant_id', $tenantId)
                ->where('estado', 'Operacional')
                ->where(function ($query) {
                    $query->whereNull('camiao_associado')
                          ->orWhere('camiao_associado', '');
                })
                ->orderBy('marca')
                ->orderBy('modelo')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $trelas->map(function ($trela) {
                    return [
                        'id' => $trela->id,
                        'matricula' => $trela->matricula,
                        'marca' => $trela->marca,
                        'modelo' => $trela->modelo,
                        'tipoTrela' => $trela->tipo_trela,
                        'capacidadeCarga' => $trela->capacidade_carga,
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar trelas disponíveis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Método adicional: Associar/desassociar caminhão
    public function associarCamiao(Request $request, $id)
    {
        $tenantId = $this->getTenantId();
        
        Log::info('🔗 Associando caminhão à trela', [
            'trela_id' => $id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'camiaoAssociado' => 'nullable|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $trela = Trela::where('tenant_id', $tenantId)->find($id);
            
            if (!$trela) {
                return response()->json([
                    'success' => false,
                    'error' => 'Trela não encontrada'
                ], 404);
            }
            
            $trela->update([
                'camiao_associado' => $request->camiaoAssociado ?? '',
            ]);
            
            Log::info('✅ Caminhão associado/desassociado', [
                'trela_id' => $id,
                'camiao_associado' => $trela->camiao_associado,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($trela->fresh()),
                'message' => $request->camiaoAssociado 
                    ? 'Caminhão associado com sucesso!' 
                    : 'Caminhão desassociado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao associar caminhão: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}