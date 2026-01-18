<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distancia;
use App\Models\DespesaMotorista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class DespesaMotoristaController extends Controller
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

    // 1. LISTAR ROTAS COM DESPESAS (paginação + busca)
    public function index(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Parâmetros de paginação
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search', '');
            
            // Buscar distâncias (rotas)
            $query = Distancia::where('tenant_id', $tenantId);
            
            // Aplicar busca
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('origem', 'like', "%{$search}%")
                      ->orWhere('destino', 'like', "%{$search}%")
                      ->orWhere('criado_por', 'like', "%{$search}%");
                });
            }
            
            $distancias = $query->orderBy('origem')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Buscar despesas das distâncias encontradas
            $distanciaIds = $distancias->pluck('id')->toArray();
            
            $despesasPorDistancia = DespesaMotorista::whereIn('distancia_id', $distanciaIds)
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('distancia_id');
            
            // Formatar resposta
            $formattedData = $distancias->map(function ($distancia) use ($despesasPorDistancia) {
                $despesasDaDistancia = $despesasPorDistancia->get($distancia->id, collect());
                
                return [
                    'id' => $distancia->id,
                    'rota' => "{$distancia->origem} → {$distancia->destino}",
                    'origem' => $distancia->origem,
                    'destino' => $distancia->destino,
                    'distancia' => (float) $distancia->distancia_total,
                    'despesas' => $despesasDaDistancia->map(function ($despesa) {
                        return [
                            'id' => $despesa->id,
                            'tipo' => $despesa->tipo,
                            'descricao' => $despesa->descricao,
                            'valorEstimado' => (float) $despesa->valor_estimado,
                            'moeda' => $despesa->moeda,
                            'requerComprovante' => (bool) $despesa->requer_comprovante,
                            'criadoPor' => $despesa->criado_por,
                            'tenantId' => $despesa->tenant_id,
                            'createdAt' => $despesa->created_at?->toISOString(),
                            'updatedAt' => $despesa->updated_at?->toISOString(),
                        ];
                    })->toArray(),
                    'criadoPor' => $distancia->criado_por ?? 'Sistema',
                    'tenantId' => $distancia->tenant_id,
                    'createdAt' => $distancia->created_at?->toISOString(),
                    'updatedAt' => $distancia->updated_at?->toISOString(),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'pagination' => [
                    'total' => $distancias->total(),
                    'page' => $distancias->currentPage(),
                    'limit' => $distancias->perPage(),
                    'totalPages' => $distancias->lastPage(),
                    'hasNextPage' => $distancias->hasMorePages(),
                    'hasPrevPage' => $distancias->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao listar rotas com despesas: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 2. LISTAR DISTÂNCIAS DISPONÍVEIS (para o modal - apenas distâncias sem despesas)
    public function distanciasDisponiveis(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Parâmetros de busca
            $search = $request->get('search', '');
            
            $query = Distancia::where('tenant_id', $tenantId);
            
            // Aplicar busca
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('origem', 'like', "%{$search}%")
                      ->orWhere('destino', 'like', "%{$search}%")
                      ->orWhere('criado_por', 'like', "%{$search}%");
                });
            }
            
            $distancias = $query->orderBy('origem')->get();
            
            return response()->json([
                'success' => true,
                'data' => $distancias->map(function ($distancia) {
                    return [
                        'id' => $distancia->id,
                        'rota' => "{$distancia->origem} → {$distancia->destino}",
                        'origem' => $distancia->origem,
                        'destino' => $distancia->destino,
                        'distancia' => (float) $distancia->distancia_total,
                        'criadoPor' => $distancia->criado_por ?? 'Sistema',
                        'tenantId' => $distancia->tenant_id,
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao listar distâncias disponíveis: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor'
            ], 500);
        }
    }

    // 3. CRIAR NOVA DESPESA
    public function storeDespesa(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            $validator = Validator::make($request->all(), [
                'distanciaId' => 'required|exists:distancias,id',
                'tipo' => 'required|string|max:100', // ALTERADO: de ENUM para string
                'descricao' => 'nullable|string|max:500',
                'valorEstimado' => 'required|numeric|min:0.01',
                'moeda' => ['required', Rule::in(['MZN', 'USD', 'EUR', 'ZAR', 'BRL'])],
                'requerComprovante' => 'boolean',
            ], [
                'tipo.required' => 'O tipo de despesa é obrigatório',
                'tipo.max' => 'O tipo de despesa não pode ter mais de 100 caracteres',
                'valorEstimado.required' => 'O valor estimado é obrigatório',
                'valorEstimado.numeric' => 'O valor estimado deve ser um número',
                'valorEstimado.min' => 'O valor estimado deve ser maior que zero',
                'moeda.required' => 'A moeda é obrigatória',
                'moeda.in' => 'Moeda inválida. Valores permitidos: MZN, USD, EUR, ZAR, BRL',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'message' => 'Erro de validação'
                ], 422);
            }
            
            // Verificar se a distância pertence ao mesmo tenant
            $distancia = Distancia::where('id', $request->distanciaId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$distancia) {
                return response()->json([
                    'success' => false,
                    'error' => 'Distância não encontrada ou não pertence ao seu tenant'
                ], 404);
            }
            
            // Criar despesa
            $despesa = DespesaMotorista::create([
                'distancia_id' => $request->distanciaId,
                'tipo' => $request->tipo,
                'descricao' => $request->descricao,
                'valor_estimado' => $request->valorEstimado,
                'moeda' => $request->moeda,
                'requer_comprovante' => $request->requerComprovante ?? false,
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            Log::info('✅ Despesa criada', [
                'id' => $despesa->id,
                'distancia_id' => $despesa->distancia_id,
                'tipo' => $despesa->tipo,
                'valor' => $despesa->valor_estimado,
                'tenant_id' => $tenantId,
                'user' => $user->name ?? 'Sistema'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa criada com sucesso!',
                'data' => [
                    'id' => $despesa->id,
                    'tipo' => $despesa->tipo,
                    'descricao' => $despesa->descricao,
                    'valorEstimado' => (float) $despesa->valor_estimado,
                    'moeda' => $despesa->moeda,
                    'requerComprovante' => (bool) $despesa->requer_comprovante,
                    'criadoPor' => $despesa->criado_por,
                    'tenantId' => $despesa->tenant_id,
                    'createdAt' => $despesa->created_at->toISOString(),
                    'updatedAt' => $despesa->updated_at->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao criar despesa: ' . $e->getMessage());
            Log::error('Request data: ' . json_encode($request->all()));
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 4. ATUALIZAR DESPESA
    public function updateDespesa(Request $request, $id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $despesa = DespesaMotorista::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'tipo' => 'sometimes|required|string|max:100', // ALTERADO: de ENUM para string
                'descricao' => 'nullable|string|max:500',
                'valorEstimado' => 'sometimes|required|numeric|min:0.01',
                'moeda' => ['sometimes', 'required', Rule::in(['MZN', 'USD', 'EUR', 'ZAR', 'BRL'])],
                'requerComprovante' => 'boolean',
            ], [
                'tipo.required' => 'O tipo de despesa é obrigatório',
                'tipo.max' => 'O tipo de despesa não pode ter mais de 100 caracteres',
                'valorEstimado.required' => 'O valor estimado é obrigatório',
                'valorEstimado.numeric' => 'O valor estimado deve ser um número',
                'valorEstimado.min' => 'O valor estimado deve ser maior que zero',
                'moeda.required' => 'A moeda é obrigatória',
                'moeda.in' => 'Moeda inválida. Valores permitidos: MZN, USD, EUR, ZAR, BRL',
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
            if ($request->has('tipo')) {
                $updateData['tipo'] = $request->tipo;
            }
            if ($request->has('descricao')) {
                $updateData['descricao'] = $request->descricao;
            }
            if ($request->has('valorEstimado')) {
                $updateData['valor_estimado'] = $request->valorEstimado;
            }
            if ($request->has('moeda')) {
                $updateData['moeda'] = $request->moeda;
            }
            if ($request->has('requerComprovante')) {
                $updateData['requer_comprovante'] = $request->requerComprovante;
            }
            
            $despesa->update($updateData);
            
            Log::info('✅ Despesa atualizada', [
                'id' => $despesa->id,
                'tipo' => $despesa->tipo,
                'valor' => $despesa->valor_estimado,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa atualizada com sucesso!',
                'data' => [
                    'id' => $despesa->id,
                    'tipo' => $despesa->tipo,
                    'descricao' => $despesa->descricao,
                    'valorEstimado' => (float) $despesa->valor_estimado,
                    'moeda' => $despesa->moeda,
                    'requerComprovante' => (bool) $despesa->requer_comprovante,
                    'criadoPor' => $despesa->criado_por,
                    'tenantId' => $despesa->tenant_id,
                    'createdAt' => $despesa->created_at->toISOString(),
                    'updatedAt' => $despesa->updated_at->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar despesa: ' . $e->getMessage());
            Log::error('Request data: ' . json_encode($request->all()));
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 5. EXCLUIR DESPESA
    public function destroyDespesa($id)
    {
        try {
            $tenantId = $this->getTenantId();
            
            $despesa = DespesaMotorista::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
            
            if (!$despesa) {
                return response()->json([
                    'success' => false,
                    'error' => 'Despesa não encontrada'
                ], 404);
            }
            
            $despesa->delete();
            
            Log::info('✅ Despesa excluída', [
                'id' => $id,
                'tipo' => $despesa->tipo,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Despesa excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao excluir despesa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir despesa',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // 6. TIPOS DE DESPESA (do banco de dados, não mais estáticos)
    public function tiposDespesa()
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Buscar tipos únicos das despesas existentes
            $tiposExistentes = DespesaMotorista::where('tenant_id', $tenantId)
                ->distinct('tipo')
                ->pluck('tipo')
                ->toArray();
            
            // Tipos padrão como fallback
            $tiposPadrao = [
                'Combustível',
                'Alimentação',
                'Hospedagem',
                'Pedágio',
                'Estacionamento',
                'Manutenção',
                'Outros'
            ];
            
            // Combinar tipos existentes com padrão, removendo duplicatas
            $tiposCombinados = array_unique(array_merge($tiposExistentes, $tiposPadrao));
            sort($tiposCombinados); // Ordenar alfabeticamente
            
            return response()->json([
                'success' => true,
                'data' => $tiposCombinados,
                'metadata' => [
                    'total' => count($tiposCombinados),
                    'tiposExistentes' => count($tiposExistentes),
                    'tiposPadrao' => count($tiposPadrao),
                    'tenantId' => $tenantId,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao buscar tipos de despesa: ' . $e->getMessage());
            
            // Fallback para tipos padrão em caso de erro
            return response()->json([
                'success' => true,
                'data' => [
                    'Combustível',
                    'Alimentação',
                    'Hospedagem',
                    'Pedágio',
                    'Estacionamento',
                    'Manutenção',
                    'Outros'
                ],
                'message' => 'Usando tipos padrão devido a erro no servidor'
            ]);
        }
    }

    // 7. TESTE (para verificar se está funcionando)
    public function test()
    {
        $tenantId = $this->getTenantId();
        $user = Auth::user();
        
        $totalDespesas = DespesaMotorista::where('tenant_id', $tenantId)->count();
        $totalDistancias = Distancia::where('tenant_id', $tenantId)->count();
        
        return response()->json([
            'success' => true,
            'message' => 'API Despesas Motoristas está funcionando!',
            'descricao' => 'Esta API gerencia despesas pré-cadastradas para rotas',
            'tenant_id' => $tenantId,
            'user' => $user->name ?? 'Não autenticado',
            'estatisticas' => [
                'total_despesas' => $totalDespesas,
                'total_distancias' => $totalDistancias,
            ],
            'endpoints' => [
                'GET /api/despesas-motoristas' => 'Listar rotas com despesas (paginação)',
                'GET /api/despesas-motoristas/distancias-disponiveis' => 'Listar distâncias disponíveis',
                'POST /api/despesas-motoristas/despesas' => 'Criar nova despesa',
                'PUT /api/despesas-motoristas/despesas/{id}' => 'Atualizar despesa',
                'DELETE /api/despesas-motoristas/despesas/{id}' => 'Excluir despesa',
                'GET /api/despesas-motoristas/tipos' => 'Listar tipos de despesa',
            ],
            'notas' => [
                'A coluna "tipo" agora aceita qualquer string (alterado de ENUM para VARCHAR)',
                'Todos os endpoints requerem autenticação via token Bearer',
                'Multi-tenancy implementado via tenant_id',
            ]
        ]);
    }

    // 8. ESTATÍSTICAS DE DESPESAS
    public function estatisticas(Request $request)
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Total de despesas
            $totalDespesas = DespesaMotorista::where('tenant_id', $tenantId)->count();
            
            // Valor total de todas as despesas (em MZN)
            $valorTotal = DespesaMotorista::where('tenant_id', $tenantId)
                ->sum('valor_estimado');
            
            // Top 5 tipos de despesa mais comuns
            $tiposMaisComuns = DespesaMotorista::where('tenant_id', $tenantId)
                ->selectRaw('tipo, COUNT(*) as total, SUM(valor_estimado) as valor_total')
                ->groupBy('tipo')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'tipo' => $item->tipo,
                        'total' => (int) $item->total,
                        'valorTotal' => (float) $item->valor_total,
                    ];
                });
            
            // Distribuição por moeda
            $distribuicaoMoeda = DespesaMotorista::where('tenant_id', $tenantId)
                ->selectRaw('moeda, COUNT(*) as total, SUM(valor_estimado) as valor_total')
                ->groupBy('moeda')
                ->get()
                ->map(function ($item) {
                    return [
                        'moeda' => $item->moeda,
                        'total' => (int) $item->total,
                        'valorTotal' => (float) $item->valor_total,
                    ];
                });
            
            // Despesas que requerem comprovante
            $comComprovante = DespesaMotorista::where('tenant_id', $tenantId)
                ->where('requer_comprovante', true)
                ->count();
            
            // Últimas 5 despesas criadas
            $ultimasDespesas = DespesaMotorista::where('tenant_id', $tenantId)
                ->with('distancia')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function ($despesa) {
                    return [
                        'id' => $despesa->id,
                        'tipo' => $despesa->tipo,
                        'valor' => (float) $despesa->valor_estimado,
                        'moeda' => $despesa->moeda,
                        'rota' => $despesa->distancia ? "{$despesa->distancia->origem} → {$despesa->distancia->destino}" : 'N/A',
                        'criadoPor' => $despesa->criado_por,
                        'criadoEm' => $despesa->created_at?->toISOString(),
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'totais' => [
                        'totalDespesas' => $totalDespesas,
                        'valorTotal' => (float) $valorTotal,
                        'comComprovante' => $comComprovante,
                        'semComprovante' => $totalDespesas - $comComprovante,
                    ],
                    'tiposMaisComuns' => $tiposMaisComuns,
                    'distribuicaoMoeda' => $distribuicaoMoeda,
                    'ultimasDespesas' => $ultimasDespesas,
                    'tenantId' => $tenantId,
                    'atualizadoEm' => now()->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao buscar estatísticas de despesas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar estatísticas',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}