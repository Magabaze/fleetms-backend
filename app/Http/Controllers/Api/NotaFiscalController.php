<?php
// app/Http/Controllers/Api/NotaFiscalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaFiscal;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class NotaFiscalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Gerar número automático da nota
     */
    private function gerarNumeroNota($tipo, $tenantId)
    {
        $ano = date('Y');
        $prefixo = $tipo === 'debito' ? 'ND' : 'NC';
        
        // Buscar a última nota do mesmo tipo no ano atual
        $ultimaNota = NotaFiscal::where('tenant_id', $tenantId)
            ->where('tipo', $tipo)
            ->whereYear('created_at', $ano)
            ->orderBy('id', 'desc')
            ->first();
        
        if ($ultimaNota && $ultimaNota->numero) {
            // Extrair o número sequencial do formato "ND-2024-001"
            $partes = explode('-', $ultimaNota->numero);
            $ultimoNumero = isset($partes[2]) ? intval($partes[2]) : 0;
            $novoNumero = str_pad($ultimoNumero + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $novoNumero = '001';
        }
        
        return "{$prefixo}-{$ano}-{$novoNumero}";
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:debito,credito'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Tipo de nota é obrigatório'
            ], 400);
        }
        
        try {
            $query = NotaFiscal::where('tenant_id', $tenantId)
                ->where('tipo', $request->tipo);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                      ->orWhere('cliente_nome', 'like', "%{$search}%")
                      ->orWhere('motivo', 'like', "%{$search}%")
                      ->orWhere('fatura_referencia', 'like', "%{$search}%");
                });
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $notas = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Formatar dados para o frontend (camelCase)
            $dadosFormatados = collect($notas->items())->map(function ($nota) {
                return [
                    'id' => $nota->id,
                    'numero' => $nota->numero,
                    'tipo' => $nota->tipo,
                    'clienteId' => $nota->cliente_id,
                    'cliente' => $nota->cliente_nome,
                    'valor' => floatval($nota->valor),
                    'motivo' => $nota->motivo,
                    'data' => $nota->data->format('Y-m-d'),
                    'faturaReferencia' => $nota->fatura_referencia,
                    'ordemId' => $nota->ordem_id,
                    'observacoes' => $nota->observacoes,
                    'criadoPor' => $nota->criado_por,
                    'createdAt' => $nota->created_at ? $nota->created_at->toISOString() : null,
                    'updatedAt' => $nota->updated_at ? $nota->updated_at->toISOString() : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $dadosFormatados,
                'pagination' => [
                    'page' => $notas->currentPage(),
                    'limit' => $perPage,
                    'total' => $notas->total(),
                    'totalPages' => $notas->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar notas: ' . $e->getMessage());
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
        
        Log::info('📥 Criando nota fiscal', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        // VALIDAÇÃO - 'numero' NÃO é obrigatório (será gerado)
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|in:debito,credito',
            'clienteId' => 'required|exists:clientes,id',
            'cliente' => 'required|string',
            'valor' => 'required|numeric|min:0.01',
            'motivo' => 'required|string',
            'data' => 'required|date',
            'faturaReferencia' => 'nullable|string',
            'ordemId' => 'nullable|exists:ordens_faturacao,id',
            'criadoPor' => 'required|string',
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
            // Buscar cliente (opcional, apenas para confirmar)
            $cliente = Cliente::find($request->clienteId);
            
            // GERAR NÚMERO AUTOMATICAMENTE
            $numeroNota = $this->gerarNumeroNota($request->tipo, $tenantId);
            
            // Verificar se o número já existe (por segurança)
            $tentativas = 0;
            while (NotaFiscal::where('numero', $numeroNota)->exists() && $tentativas < 10) {
                $partes = explode('-', $numeroNota);
                $ultimoNumero = intval($partes[2]);
                $novoNumero = str_pad($ultimoNumero + 1, 3, '0', STR_PAD_LEFT);
                $numeroNota = "{$partes[0]}-{$partes[1]}-{$novoNumero}";
                $tentativas++;
            }
            
            $nota = NotaFiscal::create([
                'numero' => $numeroNota, // GERADO PELO BACKEND
                'tipo' => $request->tipo,
                'cliente_id' => $request->clienteId,
                'cliente_nome' => $request->cliente,
                'ordem_id' => $request->ordemId,
                'valor' => $request->valor,
                'motivo' => $request->motivo,
                'data' => $request->data,
                'fatura_referencia' => $request->faturaReferencia,
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $request->criadoPor,
                'tenant_id' => $tenantId,
            ]);
            
            Log::info('✅ Nota criada com sucesso', [
                'id' => $nota->id,
                'numero' => $nota->numero
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $nota->id,
                    'numero' => $nota->numero,
                    'tipo' => $nota->tipo,
                    'clienteId' => $nota->cliente_id,
                    'cliente' => $nota->cliente_nome,
                    'valor' => floatval($nota->valor),
                    'motivo' => $nota->motivo,
                    'data' => $nota->data->format('Y-m-d'),
                    'faturaReferencia' => $nota->fatura_referencia,
                    'ordemId' => $nota->ordem_id,
                    'observacoes' => $nota->observacoes,
                    'criadoPor' => $nota->criado_por,
                    'createdAt' => $nota->created_at ? $nota->created_at->toISOString() : null,
                    'updatedAt' => $nota->updated_at ? $nota->updated_at->toISOString() : null,
                ],
                'message' => 'Nota criada com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar nota: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
            $nota = NotaFiscal::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
            
            if (!$nota) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nota não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $nota->id,
                    'numero' => $nota->numero,
                    'tipo' => $nota->tipo,
                    'clienteId' => $nota->cliente_id,
                    'cliente' => $nota->cliente_nome,
                    'valor' => floatval($nota->valor),
                    'motivo' => $nota->motivo,
                    'data' => $nota->data->format('Y-m-d'),
                    'faturaReferencia' => $nota->fatura_referencia,
                    'ordemId' => $nota->ordem_id,
                    'observacoes' => $nota->observacoes,
                    'criadoPor' => $nota->criado_por,
                    'createdAt' => $nota->created_at ? $nota->created_at->toISOString() : null,
                    'updatedAt' => $nota->updated_at ? $nota->updated_at->toISOString() : null,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar nota: ' . $e->getMessage());
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
            $nota = NotaFiscal::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
                
            if (!$nota) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nota não encontrada'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'motivo' => 'sometimes|string',
                'valor' => 'sometimes|numeric|min:0.01',
                'observacoes' => 'sometimes|nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $dadosAtualizacao = [];
            
            if ($request->has('motivo')) {
                $dadosAtualizacao['motivo'] = $request->motivo;
            }
            
            if ($request->has('valor')) {
                $dadosAtualizacao['valor'] = $request->valor;
            }
            
            if ($request->has('observacoes')) {
                $dadosAtualizacao['observacoes'] = $request->observacoes;
            }
            
            $nota->update($dadosAtualizacao);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $nota->id,
                    'numero' => $nota->numero,
                    'tipo' => $nota->tipo,
                    'clienteId' => $nota->cliente_id,
                    'cliente' => $nota->cliente_nome,
                    'valor' => floatval($nota->valor),
                    'motivo' => $nota->motivo,
                    'data' => $nota->data->format('Y-m-d'),
                    'faturaReferencia' => $nota->fatura_referencia,
                    'ordemId' => $nota->ordem_id,
                    'observacoes' => $nota->observacoes,
                    'criadoPor' => $nota->criado_por,
                    'createdAt' => $nota->created_at ? $nota->created_at->toISOString() : null,
                    'updatedAt' => $nota->updated_at ? $nota->updated_at->toISOString() : null,
                ],
                'message' => 'Nota atualizada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar nota: ' . $e->getMessage());
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
            $nota = NotaFiscal::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->first();
                
            if (!$nota) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nota não encontrada'
                ], 404);
            }
            
            $nota->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Nota excluída com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir nota: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}