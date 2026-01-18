<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoDespesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TipoDespesaController extends Controller
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

    // 1. LISTAR TODOS OS TIPOS
    public function index(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Buscar tipos do tenant
            $tipos = TipoDespesa::where('tenant_id', $tenantId)
                ->orderBy('nome')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $tipos->map(function ($tipo) {
                    return [
                        'id' => $tipo->id,
                        'nome' => $tipo->nome,
                        'descricao' => $tipo->descricao,
                        'cor' => $tipo->cor,
                        'requerComprovante' => (bool) $tipo->requer_comprovante,
                        'criadoPor' => $tipo->criado_por,
                        'tenantId' => $tipo->tenant_id,
                        'createdAt' => $tipo->created_at?->toISOString(),
                        'updatedAt' => $tipo->updated_at?->toISOString(),
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao listar tipos de despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 2. LISTAR COM PAGINAÇÃO
    public function paginado(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Parâmetros de paginação
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            
            // Query base
            $query = TipoDespesa::where('tenant_id', $tenantId);
            
            // Aplicar busca
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('nome', 'like', "%{$search}%")
                      ->orWhere('descricao', 'like', "%{$search}%");
                });
            }
            
            // Paginar resultados
            $tipos = $query->orderBy('nome')
                ->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => $tipos->map(function ($tipo) {
                    return [
                        'id' => $tipo->id,
                        'nome' => $tipo->nome,
                        'descricao' => $tipo->descricao,
                        'cor' => $tipo->cor,
                        'requerComprovante' => (bool) $tipo->requer_comprovante,
                        'criadoPor' => $tipo->criado_por,
                        'tenantId' => $tipo->tenant_id,
                        'createdAt' => $tipo->created_at?->toISOString(),
                        'updatedAt' => $tipo->updated_at?->toISOString(),
                    ];
                }),
                'pagination' => [
                    'total' => $tipos->total(),
                    'page' => $tipos->currentPage(),
                    'limit' => $tipos->perPage(),
                    'totalPages' => $tipos->lastPage(),
                    'hasNextPage' => $tipos->hasMorePages(),
                    'hasPrevPage' => $tipos->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao listar tipos de despesa paginados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor'
            ], 500);
        }
    }

    // 3. BUSCAR POR ID
    public function show($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $tipo = TipoDespesa::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
            
            if (!$tipo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tipo de despesa não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tipo->id,
                    'nome' => $tipo->nome,
                    'descricao' => $tipo->descricao,
                    'cor' => $tipo->cor,
                    'requerComprovante' => (bool) $tipo->requer_comprovante,
                    'criadoPor' => $tipo->criado_por,
                    'tenantId' => $tipo->tenant_id,
                    'createdAt' => $tipo->created_at?->toISOString(),
                    'updatedAt' => $tipo->updated_at?->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao buscar tipo de despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor'
            ], 500);
        }
    }

    // 4. CRIAR NOVO TIPO
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            $validator = Validator::make($request->all(), [
                'nome' => [
                    'required',
                    'string',
                    'max:100',
                    // Garantir que o nome seja único no tenant
                    Rule::unique('tipos_despesa')->where(function ($query) use ($tenantId) {
                        return $query->where('tenant_id', $tenantId);
                    })
                ],
                'descricao' => 'nullable|string|max:500',
                'cor' => 'required|string|regex:/^#[0-9A-F]{6}$/i',
                'requerComprovante' => 'boolean',
            ], [
                'nome.unique' => 'Já existe um tipo de despesa com este nome',
                'cor.regex' => 'A cor deve estar no formato HEX (ex: #0aca7d)',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erro de validação'
                ], 422);
            }
            
            // Criar tipo
            $tipo = TipoDespesa::create([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'cor' => $request->cor,
                'requer_comprovante' => $request->requerComprovante ?? false,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            Log::info('✅ Tipo de despesa criado', [
                'id' => $tipo->id,
                'nome' => $tipo->nome,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Tipo de despesa criado com sucesso!',
                'data' => [
                    'id' => $tipo->id,
                    'nome' => $tipo->nome,
                    'descricao' => $tipo->descricao,
                    'cor' => $tipo->cor,
                    'requerComprovante' => (bool) $tipo->requer_comprovante,
                    'criadoPor' => $tipo->criado_por,
                    'tenantId' => $tipo->tenant_id,
                    'createdAt' => $tipo->created_at->toISOString(),
                    'updatedAt' => $tipo->updated_at->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao criar tipo de despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar tipo de despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 5. ATUALIZAR TIPO
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            // Buscar tipo
            $tipo = TipoDespesa::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
            
            if (!$tipo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tipo de despesa não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'nome' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                    // Garantir que o nome seja único, ignorando o próprio registro
                    Rule::unique('tipos_despesa')->where(function ($query) use ($tenantId, $id) {
                        return $query->where('tenant_id', $tenantId)
                                     ->where('id', '!=', $id);
                    })
                ],
                'descricao' => 'nullable|string|max:500',
                'cor' => 'sometimes|required|string|regex:/^#[0-9A-F]{6}$/i',
                'requerComprovante' => 'boolean',
            ], [
                'nome.unique' => 'Já existe um tipo de despesa com este nome',
                'cor.regex' => 'A cor deve estar no formato HEX (ex: #0aca7d)',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erro de validação'
                ], 422);
            }
            
            // Atualizar apenas os campos fornecidos
            $updateData = [];
            if ($request->has('nome')) {
                $updateData['nome'] = $request->nome;
            }
            if ($request->has('descricao')) {
                $updateData['descricao'] = $request->descricao;
            }
            if ($request->has('cor')) {
                $updateData['cor'] = $request->cor;
            }
            if ($request->has('requerComprovante')) {
                $updateData['requer_comprovante'] = $request->requerComprovante;
            }
            
            $tipo->update($updateData);
            
            Log::info('✅ Tipo de despesa atualizado', [
                'id' => $tipo->id,
                'nome' => $tipo->nome,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Tipo de despesa atualizado com sucesso!',
                'data' => [
                    'id' => $tipo->id,
                    'nome' => $tipo->nome,
                    'descricao' => $tipo->descricao,
                    'cor' => $tipo->cor,
                    'requerComprovante' => (bool) $tipo->requer_comprovante,
                    'criadoPor' => $tipo->criado_por,
                    'tenantId' => $tipo->tenant_id,
                    'createdAt' => $tipo->created_at->toISOString(),
                    'updatedAt' => $tipo->updated_at->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar tipo de despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar tipo de despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 6. EXCLUIR TIPO
    public function destroy($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Buscar tipo
            $tipo = TipoDespesa::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
            
            if (!$tipo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tipo de despesa não encontrado'
                ], 404);
            }
            
            // Verificar se há despesas associadas a este tipo
            $despesasAssociadas = $tipo->despesas()->count();
            
            if ($despesasAssociadas > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir este tipo pois existem despesas associadas',
                    'despesasAssociadas' => $despesasAssociadas
                ], 400);
            }
            
            // Excluir tipo
            $tipo->delete();
            
            Log::info('✅ Tipo de despesa excluído', [
                'id' => $id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Tipo de despesa excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao excluir tipo de despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir tipo de despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 7. VERIFICAR SE NOME JÁ EXISTE
    public function verificarNome(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string',
                'excludeId' => 'nullable|integer'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parâmetros inválidos'
                ], 400);
            }
            
            $query = TipoDespesa::where('tenant_id', $tenantId)
                ->where('nome', $request->nome);
            
            // Excluir um ID específico (para atualizações)
            if ($request->has('excludeId')) {
                $query->where('id', '!=', $request->excludeId);
            }
            
            $exists = $query->exists();
            
            return response()->json([
                'success' => true,
                'exists' => $exists
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao verificar nome: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar nome'
            ], 500);
        }
    }

    // 8. TESTE
    public function test()
    {
        $tenantId = $this->getTenantId();
        
        return response()->json([
            'success' => true,
            'message' => 'API Tipos de Despesa está funcionando!',
            'descricao' => 'Esta API gerencia tipos de despesa pré-cadastrados',
            'tenant_id' => $tenantId,
            'endpoints' => [
                'GET /tipos-despesa' => 'Listar todos os tipos',
                'GET /tipos-despesa/paginado' => 'Listar com paginação',
                'GET /tipos-despesa/{id}' => 'Buscar por ID',
                'POST /tipos-despesa' => 'Criar novo tipo',
                'PUT /tipos-despesa/{id}' => 'Atualizar tipo',
                'DELETE /tipos-despesa/{id}' => 'Excluir tipo',
                'GET /tipos-despesa/verificar-nome' => 'Verificar se nome já existe',
            ]
        ]);
    }

    // 9. GERAR TIPOS PADRÃO (para inicialização)
    public function gerarTiposPadrao()
    {
        try {
            $tenantId = $this->getTenantId();
            $user = Auth::user();
            
            // Tipos padrão
            $tiposPadrao = [
                [
                    'nome' => 'Combustível',
                    'descricao' => 'Gastos com combustível',
                    'cor' => '#3b82f6', // Azul
                    'requer_comprovante' => true,
                ],
                [
                    'nome' => 'Alimentação',
                    'descricao' => 'Refeições durante a viagem',
                    'cor' => '#10b981', // Verde
                    'requer_comprovante' => false,
                ],
                [
                    'nome' => 'Hospedagem',
                    'descricao' => 'Custos de hospedagem',
                    'cor' => '#8b5cf6', // Roxo
                    'requer_comprovante' => true,
                ],
                [
                    'nome' => 'Pedágio',
                    'descricao' => 'Taxas de pedágio',
                    'cor' => '#f59e0b', // Amarelo
                    'requer_comprovante' => true,
                ],
                [
                    'nome' => 'Estacionamento',
                    'descricao' => 'Custos de estacionamento',
                    'cor' => '#ef4444', // Vermelho
                    'requer_comprovante' => true,
                ],
                [
                    'nome' => 'Manutenção',
                    'descricao' => 'Manutenção do veículo',
                    'cor' => '#f97316', // Laranja
                    'requer_comprovante' => true,
                ],
                [
                    'nome' => 'Outros',
                    'descricao' => 'Outras despesas diversas',
                    'cor' => '#6b7280', // Cinza
                    'requer_comprovante' => false,
                ],
            ];
            
            $tiposCriados = [];
            $tiposIgnorados = [];
            
            foreach ($tiposPadrao as $tipoData) {
                // Verificar se já existe
                $exists = TipoDespesa::where('tenant_id', $tenantId)
                    ->where('nome', $tipoData['nome'])
                    ->exists();
                
                if (!$exists) {
                    $tipo = TipoDespesa::create([
                        'nome' => $tipoData['nome'],
                        'descricao' => $tipoData['descricao'],
                        'cor' => $tipoData['cor'],
                        'requer_comprovante' => $tipoData['requer_comprovante'],
                        'criado_por' => $user->name ?? 'Sistema',
                        'tenant_id' => $tenantId,
                    ]);
                    
                    $tiposCriados[] = $tipo->nome;
                } else {
                    $tiposIgnorados[] = $tipoData['nome'];
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Tipos padrão processados',
                'criados' => $tiposCriados,
                'ignorados' => $tiposIgnorados,
                'totalCriados' => count($tiposCriados),
                'totalIgnorados' => count($tiposIgnorados),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao gerar tipos padrão: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar tipos padrão'
            ], 500);
        }
    }
}