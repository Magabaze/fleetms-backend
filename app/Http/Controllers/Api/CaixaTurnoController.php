<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaixaTurno;
use App\Models\CaixaMovimento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CaixaTurnoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function paraCamelCase($turno)
    {
        return [
            'id' => $turno->id,
            'operadorId' => $turno->operador_id,
            'operadorNome' => $turno->operador_nome,
            'saldos' => $turno->saldos ?? [],
            'status' => $turno->status,
            'dataAbertura' => $turno->data_abertura->toISOString(),
            'dataFechamento' => $turno->data_fechamento?->toISOString(),
            'observacoes' => $turno->observacoes,
            'createdAt' => $turno->created_at->toISOString(),
            'updatedAt' => $turno->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/caixa/turnos', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId
        ]);
        
        try {
            $query = CaixaTurno::where('tenant_id', $tenantId);
            
            // Filtro por status
            if ($request->has('status') && $request->status && $request->status !== 'todos') {
                $query->where('status', $request->status);
            }
            
            // Filtro por operador
            if ($request->has('operador_id') && $request->operador_id) {
                $query->where('operador_id', $request->operador_id);
            }
            
            // Busca
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('operador_nome', 'like', "%{$search}%")
                      ->orWhere('observacoes', 'like', "%{$search}%");
                });
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $turnos = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            $turnosCamelCase = $turnos->map(function ($turno) {
                return $this->paraCamelCase($turno);
            });
            
            return response()->json([
                'success' => true,
                'data' => $turnosCamelCase->toArray(),
                'pagination' => [
                    'page' => $turnos->currentPage(),
                    'limit' => $perPage,
                    'total' => $turnos->total(),
                    'totalPages' => $turnos->lastPage(),
                    'hasNextPage' => $turnos->hasMorePages(),
                    'hasPrevPage' => $turnos->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar turnos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verificarTurnoAberto(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $turnoAberto = CaixaTurno::where('tenant_id', $tenantId)
                ->where('operador_id', $user->id)
                ->where('status', 'aberto')
                ->first();
            
            return response()->json([
                'success' => true,
                'temTurnoAberto' => $turnoAberto ? true : false,
                'turno' => $turnoAberto ? $this->paraCamelCase($turnoAberto) : null
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao verificar turno: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/caixa/turnos', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        // Verificar se já existe turno aberto
        $turnoExistente = CaixaTurno::where('tenant_id', $tenantId)
            ->where('operador_id', $user->id)
            ->where('status', 'aberto')
            ->first();
            
        if ($turnoExistente) {
            return response()->json([
                'success' => false,
                'error' => 'Já existe um turno aberto para este operador'
            ], 422);
        }
        
        $validator = Validator::make($request->all(), [
            'saldos' => 'required|array|min:1',
            'saldos.*.moeda' => 'required|string|in:MZN,USD,ZAR,AOA',
            'saldos.*.valor' => 'required|numeric|min:0',
            'observacoes' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Preparar saldos para o formato JSON
            $saldos = [];
            foreach ($request->saldos as $saldo) {
                $saldos[] = [
                    'moeda' => $saldo['moeda'],
                    'saldoAbertura' => (float) $saldo['valor'],
                    'saldoAtual' => (float) $saldo['valor']
                ];
            }
            
            $dados = [
                'operador_id' => $user->id,
                'operador_nome' => $user->name ?? 'Operador',
                'saldos' => $saldos,
                'status' => 'aberto',
                'data_abertura' => now(),
                'observacoes' => $request->observacoes ?? '',
                'tenant_id' => $tenantId,
            ];
            
            $turno = CaixaTurno::create($dados);
            
            // Registrar movimento de abertura para CADA MOEDA
            foreach ($saldos as $saldo) {
                CaixaMovimento::create([
                    'turno_id' => $turno->id,
                    'tipo' => 'entrada',
                    'moeda' => $saldo['moeda'],
                    'valor' => $saldo['saldoAbertura'],
                    'descricao' => 'Abertura de caixa',
                    'data_movimento' => now(),
                    'saldo_anterior' => 0,
                    'saldo_posterior' => $saldo['saldoAbertura'],
                    'criado_por' => $user->name ?? 'Sistema',
                    'tenant_id' => $tenantId,
                ]);
            }
            
            Log::info('✅ Turno criado', ['id' => $turno->id]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($turno),
                'message' => 'Caixa aberto com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar turno: ' . $e->getMessage());
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
            $turno = CaixaTurno::where('tenant_id', $tenantId)->find($id);
            
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => 'Turno não encontrado'
                ], 404);
            }
            
            // Carregar movimentos do turno
            $movimentos = $turno->movimentos()->orderBy('data_movimento', 'desc')->get();
            
            $movimentosCamelCase = $movimentos->map(function ($mov) {
                return [
                    'id' => $mov->id,
                    'tipo' => $mov->tipo,
                    'moeda' => $mov->moeda,
                    'valor' => (float) $mov->valor,
                    'descricao' => $mov->descricao,
                    'data' => $mov->data_movimento->toISOString(),
                    'saldoAnterior' => (float) $mov->saldo_anterior,
                    'saldoPosterior' => (float) $mov->saldo_posterior,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'turno' => $this->paraCamelCase($turno),
                    'movimentos' => $movimentosCamelCase->toArray()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar turno: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    public function fechar(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            Log::info('📥 Fechando caixa', [
                'id' => $id,
                'data' => $request->all()
            ]);
            
            $turno = CaixaTurno::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->where('status', 'aberto')
                ->first();
            
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => 'Turno não encontrado ou já está fechado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'saldos_fisicos' => 'required|array',
                'saldos_fisicos.*.moeda' => 'required|string|in:MZN,USD,ZAR,AOA',
                'saldos_fisicos.*.valor' => 'required|numeric|min:0',
                'observacoes' => 'nullable|string|max:500',
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Calcular diferenças por moeda
            $diferencas = [];
            foreach ($request->saldos_fisicos as $fisico) {
                $saldoAtual = collect($turno->saldos)
                    ->firstWhere('moeda', $fisico['moeda'])['saldoAtual'] ?? 0;
                $diferenca = $fisico['valor'] - $saldoAtual;
                
                $diferencas[] = [
                    'moeda' => $fisico['moeda'],
                    'diferenca' => $diferenca
                ];
            }
            
            $turno->update([
                'status' => 'fechado',
                'data_fechamento' => now(),
                'observacoes' => $request->observacoes ?? $turno->observacoes,
            ]);
            
            Log::info('✅ Caixa fechado', ['diferencas' => $diferencas]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'turno' => $this->paraCamelCase($turno),
                    'diferencas' => $diferencas
                ],
                'message' => 'Caixa fechado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao fechar turno: ' . $e->getMessage());
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
            $turno = CaixaTurno::where('tenant_id', $tenantId)->find($id);
            
            if (!$turno) {
                return response()->json([
                    'success' => false,
                    'error' => 'Turno não encontrado'
                ], 404);
            }
            
            // Não permitir excluir turno aberto
            if ($turno->status === 'aberto') {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir um turno aberto'
                ], 422);
            }
            
            $turno->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Turno excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir turno: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}