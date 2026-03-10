<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\Inspecao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class InspecaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($inspecao)
    {
        return [
            'id' => $inspecao->id,
            'veiculo' => $inspecao->veiculo,
            'matricula' => $inspecao->matricula,
            'tipo' => $inspecao->tipo,
            'entidade' => $inspecao->entidade,
            'dataUltima' => $inspecao->data_ultima,
            'dataValidade' => $inspecao->data_validade,
            'status' => $inspecao->status,
            'resultado' => $inspecao->resultado,
            'observacoes' => $inspecao->observacoes,
            'criadoPor' => $inspecao->criado_por,
            'tenantId' => $inspecao->tenant_id,
            'createdAt' => $inspecao->created_at?->toISOString(),
            'updatedAt' => $inspecao->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = Inspecao::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('veiculo', 'like', "%{$search}%")
                      ->orWhere('matricula', 'like', "%{$search}%")
                      ->orWhere('tipo', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $inspecoes = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $inspecoesCamelCase = $inspecoes->map(function ($inspecao) {
                return $this->paraCamelCase($inspecao);
            });
            
            return response()->json([
                'success' => true,
                'data' => $inspecoesCamelCase->toArray(),
                'pagination' => [
                    'page' => $inspecoes->currentPage(),
                    'limit' => $perPage,
                    'total' => $inspecoes->total(),
                    'totalPages' => $inspecoes->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar inspeções: ' . $e->getMessage());
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
            'tipo' => 'required|string',
            'entidade' => 'required|string',
            'dataUltima' => 'required|date',
            'dataValidade' => 'required|date|after_or_equal:dataUltima',
            'resultado' => 'required|in:aprovado,reprovado,pendente',
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
                'tipo' => $request->tipo,
                'entidade' => $request->entidade,
                'data_ultima' => $request->dataUltima,
                'data_validade' => $request->dataValidade,
                'resultado' => $request->resultado,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            $inspecao = Inspecao::create($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($inspecao),
                'message' => 'Inspeção registada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar inspeção: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function renovar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $validator = Validator::make($request->all(), [
            'novaValidade' => 'required|date|after:today',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Data de validade inválida',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $inspecaoAntiga = Inspecao::where('tenant_id', $tenantId)->find($id);
            
            if (!$inspecaoAntiga) {
                return response()->json([
                    'success' => false,
                    'error' => 'Inspeção não encontrada'
                ], 404);
            }
            
            // Criar nova inspeção baseada na antiga
            $novaInspecao = Inspecao::create([
                'veiculo' => $inspecaoAntiga->veiculo,
                'matricula' => $inspecaoAntiga->matricula,
                'tipo' => $inspecaoAntiga->tipo,
                'entidade' => $inspecaoAntiga->entidade,
                'data_ultima' => now()->toDateString(),
                'data_validade' => $request->novaValidade,
                'resultado' => 'pendente',
                'observacoes' => 'Renovação automática',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($novaInspecao),
                'message' => 'Inspeção renovada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao renovar inspeção: ' . $e->getMessage());
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
            $inspecao = Inspecao::where('tenant_id', $tenantId)->find($id);
            
            if (!$inspecao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Inspeção não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($inspecao)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar inspeção: ' . $e->getMessage());
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
            $inspecao = Inspecao::where('tenant_id', $tenantId)->find($id);
            
            if (!$inspecao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Inspeção não encontrada'
                ], 404);
            }
            
            $dados = [];
            if ($request->has('tipo')) $dados['tipo'] = $request->tipo;
            if ($request->has('entidade')) $dados['entidade'] = $request->entidade;
            if ($request->has('dataUltima')) $dados['data_ultima'] = $request->dataUltima;
            if ($request->has('dataValidade')) $dados['data_validade'] = $request->dataValidade;
            if ($request->has('resultado')) $dados['resultado'] = $request->resultado;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            
            $inspecao->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($inspecao->fresh()),
                'message' => 'Inspeção atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar inspeção: ' . $e->getMessage());
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
            $inspecao = Inspecao::where('tenant_id', $tenantId)->find($id);
            
            if (!$inspecao) {
                return response()->json([
                    'success' => false,
                    'error' => 'Inspeção não encontrada'
                ], 404);
            }
            
            $inspecao->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Inspeção excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir inspeção: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}