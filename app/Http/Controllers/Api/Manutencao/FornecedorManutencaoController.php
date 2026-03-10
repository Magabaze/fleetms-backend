<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\FornecedorManutencao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class FornecedorManutencaoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($fornecedor)
    {
        return [
            'id' => $fornecedor->id,
            'nome' => $fornecedor->nome,
            'tipo' => $fornecedor->tipo,
            'especialidade' => $fornecedor->especialidade ?? [],
            'contacto' => $fornecedor->contacto,
            'email' => $fornecedor->email,
            'morada' => $fornecedor->morada,
            'avaliacao' => (float) $fornecedor->avaliacao,
            'totalServicos' => (int) $fornecedor->total_servicos,
            'ultimoServico' => $fornecedor->ultimo_servico,
            'status' => $fornecedor->status,
            'tempoMedioResposta' => $fornecedor->tempo_medio_resposta,
            'observacoes' => $fornecedor->observacoes,
            'criadoPor' => $fornecedor->criado_por,
            'tenantId' => $fornecedor->tenant_id,
            'createdAt' => $fornecedor->created_at?->toISOString(),
            'updatedAt' => $fornecedor->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = FornecedorManutencao::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                      ->orWhere('tipo', 'like', "%{$search}%")
                      ->orWhere('contacto', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $fornecedores = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $fornecedoresCamelCase = $fornecedores->map(function ($fornecedor) {
                return $this->paraCamelCase($fornecedor);
            });
            
            return response()->json([
                'success' => true,
                'data' => $fornecedoresCamelCase->toArray(),
                'pagination' => [
                    'page' => $fornecedores->currentPage(),
                    'limit' => $perPage,
                    'total' => $fornecedores->total(),
                    'totalPages' => $fornecedores->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar fornecedores: ' . $e->getMessage());
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
            'nome' => 'required|string|max:255',
            'tipo' => 'required|string',
            'especialidade' => 'required|array|min:1',
            'contacto' => 'required|string',
            'email' => 'nullable|email',
            'status' => 'required|in:ativo,inativo',
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
                'nome' => $request->nome,
                'tipo' => $request->tipo,
                'especialidade' => $request->especialidade,
                'contacto' => $request->contacto,
                'email' => $request->email ?? '',
                'morada' => $request->morada ?? '',
                'avaliacao' => $request->avaliacao ?? 5,
                'total_servicos' => 0,
                'status' => $request->status,
                'tempo_medio_resposta' => $request->tempoMedioResposta ?? '',
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            $fornecedor = FornecedorManutencao::create($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($fornecedor),
                'message' => 'Fornecedor criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar fornecedor: ' . $e->getMessage());
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
            $fornecedor = FornecedorManutencao::where('tenant_id', $tenantId)->find($id);
            
            if (!$fornecedor) {
                return response()->json([
                    'success' => false,
                    'error' => 'Fornecedor não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($fornecedor)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar fornecedor: ' . $e->getMessage());
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
            $fornecedor = FornecedorManutencao::where('tenant_id', $tenantId)->find($id);
            
            if (!$fornecedor) {
                return response()->json([
                    'success' => false,
                    'error' => 'Fornecedor não encontrado'
                ], 404);
            }
            
            $dados = [];
            if ($request->has('nome')) $dados['nome'] = $request->nome;
            if ($request->has('tipo')) $dados['tipo'] = $request->tipo;
            if ($request->has('especialidade')) $dados['especialidade'] = $request->especialidade;
            if ($request->has('contacto')) $dados['contacto'] = $request->contacto;
            if ($request->has('email')) $dados['email'] = $request->email;
            if ($request->has('morada')) $dados['morada'] = $request->morada;
            if ($request->has('avaliacao')) $dados['avaliacao'] = $request->avaliacao;
            if ($request->has('status')) $dados['status'] = $request->status;
            if ($request->has('tempoMedioResposta')) $dados['tempo_medio_resposta'] = $request->tempoMedioResposta;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            
            $fornecedor->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($fornecedor->fresh()),
                'message' => 'Fornecedor atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar fornecedor: ' . $e->getMessage());
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
            $fornecedor = FornecedorManutencao::where('tenant_id', $tenantId)->find($id);
            
            if (!$fornecedor) {
                return response()->json([
                    'success' => false,
                    'error' => 'Fornecedor não encontrado'
                ], 404);
            }
            
            $fornecedor->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Fornecedor excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir fornecedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}