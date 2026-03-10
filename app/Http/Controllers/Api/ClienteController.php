<?php
// app/Http/Controllers/Api/ClienteController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ClienteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Formata o modelo para o padrão CamelCase esperado pelo Frontend.
     */
    private function formatarParaFrontend($cliente)
    {
        return [
            'id' => $cliente->id,
            'nomeEmpresa' => $cliente->nome_empresa,
            'tipoCliente' => $cliente->tipo_cliente,
            'pessoaContato' => $cliente->pessoa_contato,
            'telefone' => $cliente->telefone,
            'email' => $cliente->email,
            'endereco' => $cliente->endereco,
            'nuitNif' => $cliente->nuit_nif,
            'iva' => $cliente->iva,
            'pais' => $cliente->pais,
            'observacoes' => $cliente->observacoes,
            'criadoPor' => $cliente->criado_por,
            'createdAt' => $cliente->created_at->toISOString(),
            'updatedAt' => $cliente->updated_at->toISOString()
        ];
    }

    /**
     * Lista clientes com paginação e filtros.
     * O filtro por tipo é feito no banco de dados para garantir performance e paginação corretas.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        try {
            $query = Cliente::where('tenant_id', $tenantId);
            
            // Filtro de busca textual
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome_empresa', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('nuit_nif', 'like', "%{$search}%")
                      ->orWhere('pessoa_contato', 'like', "%{$search}%")
                      ->orWhere('telefone', 'like', "%{$search}%");
                });
            }

            // Filtro por tipo de cliente (otimizado no DB)
            if ($request->filled('tipo') && $request->tipo !== 'todos') {
                $query->where('tipo_cliente', $request->tipo);
            }
            
            $perPage = $request->get('limit', 10);
            $clientes = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $clientes->map(fn($c) => $this->formatarParaFrontend($c)),
                'pagination' => [
                    'page' => $clientes->currentPage(),
                    'limit' => $perPage,
                    'total' => $clientes->total(),
                    'totalPages' => $clientes->lastPage(),
                    'hasNextPage' => $clientes->hasMorePages(),
                    'hasPrevPage' => $clientes->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao listar clientes: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro interno no servidor'], 500);
        }
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $validator = Validator::make($request->all(), [
            'nomeEmpresa' => 'required|string|max:255',
            'tipoCliente' => 'required|in:Consignee,Shipper,Invoice Party',
            'nuitNif' => 'required|string|size:9|unique:clientes,nuit_nif,NULL,id,tenant_id,' . $tenantId,
            'email' => 'nullable|email|unique:clientes,email,NULL,id,tenant_id,' . $tenantId,
        ], [
            'nuitNif.unique' => 'Já existe um cliente com este NUIT/NIF.',
            'nuitNif.size' => 'NUIT/NIF deve ter exatamente 9 dígitos.',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        try {
            $cliente = Cliente::create([
                'nome_empresa' => $request->nomeEmpresa,
                'tipo_cliente' => $request->tipoCliente,
                'pessoa_contato' => $request->pessoaContato ?? '',
                'telefone' => $request->telefone ?? '',
                'email' => $request->email ?? '',
                'endereco' => $request->endereco ?? '',
                'nuit_nif' => $request->nuitNif,
                'iva' => $request->iva ?? '',
                'pais' => $request->pais ?? 'Moçambique',
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->formatarParaFrontend($cliente),
                'message' => 'Cliente criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Erro ao criar cliente: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Erro ao processar solicitação'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
        if (!$cliente) {
            return response()->json(['success' => false, 'error' => 'Cliente não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nomeEmpresa' => 'required|string|max:255',
            'tipoCliente' => 'required|in:Consignee,Shipper,Invoice Party',
            'nuitNif' => 'required|string|size:9|unique:clientes,nuit_nif,' . $id . ',id,tenant_id,' . $tenantId,
            'email' => 'nullable|email|unique:clientes,email,' . $id . ',id,tenant_id,' . $tenantId,
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        $cliente->update([
            'nome_empresa' => $request->nomeEmpresa,
            'tipo_cliente' => $request->tipoCliente,
            'pessoa_contato' => $request->pessoaContato,
            'telefone' => $request->telefone,
            'email' => $request->email,
            'endereco' => $request->endereco,
            'nuit_nif' => $request->nuitNif,
            'iva' => $request->iva,
            'pais' => $request->pais,
            'observacoes' => $request->observacoes,
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $this->formatarParaFrontend($cliente->fresh()),
            'message' => 'Cliente atualizado com sucesso!'
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
        if (!$cliente) {
            return response()->json(['success' => false, 'error' => 'Cliente não encontrado'], 404);
        }
        
        $cliente->delete();
        return response()->json(['success' => true, 'message' => 'Cliente excluído com sucesso!']);
    }
}