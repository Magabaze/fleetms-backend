<?php

namespace App\Http\Controllers\Api\Manutencao;

use App\Http\Controllers\Controller;
use App\Models\Manutencao\PlanoPreventivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PlanoPreventivoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($plano)
    {
        return [
            'id' => $plano->id,
            'veiculo' => $plano->veiculo,
            'matricula' => $plano->matricula,
            'tipo' => $plano->tipo,
            'intervaloKm' => (int) $plano->intervalo_km,
            'intervaloDias' => (int) $plano->intervalo_dias,
            'ultimoKm' => (int) $plano->ultimo_km,
            'kmAtual' => (int) $plano->km_atual,
            'ultimaData' => $plano->ultima_data,
            'proximaData' => $plano->proxima_data,
            'status' => $plano->status,
            'observacoes' => $plano->observacoes,
            'criadoPor' => $plano->criado_por,
            'tenantId' => $plano->tenant_id,
            'createdAt' => $plano->created_at?->toISOString(),
            'updatedAt' => $plano->updated_at?->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = PlanoPreventivo::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('veiculo', 'like', "%{$search}%")
                      ->orWhere('tipo', 'like', "%{$search}%");
                });
            }
            
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $planos = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $planosCamelCase = $planos->map(function ($plano) {
                return $this->paraCamelCase($plano);
            });
            
            return response()->json([
                'success' => true,
                'data' => $planosCamelCase->toArray(),
                'pagination' => [
                    'page' => $planos->currentPage(),
                    'limit' => $perPage,
                    'total' => $planos->total(),
                    'totalPages' => $planos->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar planos preventivos: ' . $e->getMessage());
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
            'intervaloKm' => 'required|numeric|min:1',
            'intervaloDias' => 'required|numeric|min:1',
            'ultimoKm' => 'required|numeric|min:0',
            'kmAtual' => 'required|numeric|min:0',
            'ultimaData' => 'required|date',
            'proximaData' => 'required|date|after:ultimaData',
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
                'intervalo_km' => $request->intervaloKm,
                'intervalo_dias' => $request->intervaloDias,
                'ultimo_km' => $request->ultimoKm,
                'km_atual' => $request->kmAtual,
                'ultima_data' => $request->ultimaData,
                'proxima_data' => $request->proximaData,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ];
            
            $plano = PlanoPreventivo::create($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($plano),
                'message' => 'Plano preventivo criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar plano preventivo: ' . $e->getMessage());
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
            $plano = PlanoPreventivo::where('tenant_id', $tenantId)->find($id);
            
            if (!$plano) {
                return response()->json([
                    'success' => false,
                    'error' => 'Plano preventivo não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($plano)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar plano preventivo: ' . $e->getMessage());
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
            $plano = PlanoPreventivo::where('tenant_id', $tenantId)->find($id);
            
            if (!$plano) {
                return response()->json([
                    'success' => false,
                    'error' => 'Plano preventivo não encontrado'
                ], 404);
            }
            
            $dados = [];
            if ($request->has('tipo')) $dados['tipo'] = $request->tipo;
            if ($request->has('intervaloKm')) $dados['intervalo_km'] = $request->intervaloKm;
            if ($request->has('intervaloDias')) $dados['intervalo_dias'] = $request->intervaloDias;
            if ($request->has('ultimoKm')) $dados['ultimo_km'] = $request->ultimoKm;
            if ($request->has('kmAtual')) $dados['km_atual'] = $request->kmAtual;
            if ($request->has('ultimaData')) $dados['ultima_data'] = $request->ultimaData;
            if ($request->has('proximaData')) $dados['proxima_data'] = $request->proximaData;
            if ($request->has('observacoes')) $dados['observacoes'] = $request->observacoes;
            
            $plano->update($dados);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($plano->fresh()),
                'message' => 'Plano preventivo atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar plano preventivo: ' . $e->getMessage());
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
            $plano = PlanoPreventivo::where('tenant_id', $tenantId)->find($id);
            
            if (!$plano) {
                return response()->json([
                    'success' => false,
                    'error' => 'Plano preventivo não encontrado'
                ], 404);
            }
            
            $plano->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Plano preventivo excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir plano preventivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}