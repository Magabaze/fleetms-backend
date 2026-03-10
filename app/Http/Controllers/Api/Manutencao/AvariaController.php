<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\Avaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AvariaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($avaria)
    {
        return [
            'id' => $avaria->id,
            'codigo' => $avaria->codigo,
            'veiculo' => $avaria->veiculo,
            'matricula' => $avaria->matricula,
            'descricao' => $avaria->descricao,
            'causaRaiz' => $avaria->causa_raiz,
            'reportadoPor' => $avaria->reportado_por,
            'tecnico' => $avaria->tecnico,
            'status' => $avaria->status,
            'prioridade' => $avaria->prioridade,
            'dataReporte' => $avaria->data_reporte,
            'horasImobilizado' => (int) $avaria->horas_imobilizado,
            'localAvaria' => $avaria->local_avaria,
            'criadoPor' => $avaria->criado_por,
            'tenantId' => $avaria->tenant_id,
            'createdAt' => $avaria->created_at?->toISOString(),
            'updatedAt' => $avaria->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = Avaria::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('veiculo', 'like', "%{$search}%")
                      ->orWhere('codigo', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $avarias = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $avariasCamelCase = $avarias->map(function ($avaria) {
                return $this->paraCamelCase($avaria);
            });
            
            return response()->json([
                'success' => true,
                'data' => $avariasCamelCase->toArray(),
                'pagination' => [
                    'page' => $avarias->currentPage(),
                    'limit' => $perPage,
                    'total' => $avarias->total(),
                    'totalPages' => $avarias->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar avarias: ' . $e->getMessage());
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
        
        $validator = Validator::make($request->all(), [
            'veiculo' => 'required|string|max:255',
            'matricula' => 'required|string|max:20',
            'descricao' => 'required|string',
            'reportadoPor' => 'required|string',
            'tecnico' => 'required|string',
            'status' => 'required|in:aberta,em_diagnostico,em_reparacao,resolvida',
            'prioridade' => 'required|in:normal,alta,urgente',
            'dataReporte' => 'required|date',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $dados = [
                'veiculo' => $request->veiculo,
                'matricula' => $request->matricula,
                'descricao' => $request->descricao,
                'causa_raiz' => $request->causaRaiz ?? '',
                'reportado_por' => $request->reportadoPor,
                'tecnico' => $request->tecnico,
                'status' => $request->status,
                'prioridade' => $request->prioridade,
                'data_reporte' => $request->dataReporte,
                'horas_imobilizado' => $request->horasImobilizado ?? 0,
                'local_avaria' => $request->localAvaria ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            $avaria = Avaria::create($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($avaria),
                'message' => 'Avaria registada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar avaria: ' . $e->getMessage());
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
            $avaria = Avaria::where('tenant_id', $tenantId)->find($id);
            
            if (!$avaria) {
                return response()->json([
                    'success' => false,
                    'error' => 'Avaria não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($avaria)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar avaria: ' . $e->getMessage());
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
        
        try {
            $avaria = Avaria::where('tenant_id', $tenantId)->find($id);
            
            if (!$avaria) {
                return response()->json([
                    'success' => false,
                    'error' => 'Avaria não encontrada'
                ], 404);
            }
            
            $dados = [];
            if ($request->has('veiculo')) $dados['veiculo'] = $request->veiculo;
            if ($request->has('matricula')) $dados['matricula'] = $request->matricula;
            if ($request->has('descricao')) $dados['descricao'] = $request->descricao;
            if ($request->has('causaRaiz')) $dados['causa_raiz'] = $request->causaRaiz;
            if ($request->has('tecnico')) $dados['tecnico'] = $request->tecnico;
            if ($request->has('status')) $dados['status'] = $request->status;
            if ($request->has('prioridade')) $dados['prioridade'] = $request->prioridade;
            if ($request->has('horasImobilizado')) $dados['horas_imobilizado'] = $request->horasImobilizado;
            if ($request->has('localAvaria')) $dados['local_avaria'] = $request->localAvaria;
            
            $avaria->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($avaria->fresh()),
                'message' => 'Avaria atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar avaria: ' . $e->getMessage());
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
            $avaria = Avaria::where('tenant_id', $tenantId)->find($id);
            
            if (!$avaria) {
                return response()->json([
                    'success' => false,
                    'error' => 'Avaria não encontrada'
                ], 404);
            }
            
            $avaria->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Avaria excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir avaria: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}