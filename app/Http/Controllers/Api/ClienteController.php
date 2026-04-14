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
            'createdAt' => $cliente->created_at ? $cliente->created_at->toISOString() : null,
            'updatedAt' => $cliente->updated_at ? $cliente->updated_at->toISOString() : null
        ];
    }

    /**
     * Lista clientes com paginação e filtros.
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

            // Filtro por tipo de cliente
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
            Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'error' => 'Erro interno no servidor: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exibe um cliente específico
     */
    public function show($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
        if (!$cliente) {
            return response()->json(['success' => false, 'error' => 'Cliente não encontrado'], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $this->formatarParaFrontend($cliente)
        ]);
    }

    /**
     * Cria um novo cliente
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('Dados recebidos para criar cliente:', $request->all());
        
        // Validação - NUIT NÃO É OBRIGATÓRIO
        $validator = Validator::make($request->all(), [
            'nomeEmpresa' => 'required|string|max:255',
            'tipoCliente' => 'required|in:Consignee,Shipper,Invoice Party',
            'nuitNif' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'pessoaContato' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:500',
            'iva' => 'nullable|string|max:50',
            'pais' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string',
        ], [
            'nomeEmpresa.required' => 'O nome da empresa é obrigatório.',
            'tipoCliente.required' => 'O tipo de cliente é obrigatório.',
            'tipoCliente.in' => 'Tipo de cliente inválido. Use: Consignee, Shipper ou Invoice Party',
            'email.email' => 'O email informado não é válido.',
        ]);
        
        if ($validator->fails()) {
            Log::warning('Validação falhou ao criar cliente:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            $cliente = Cliente::create([
                'nome_empresa' => $request->nomeEmpresa,
                'tipo_cliente' => $request->tipoCliente,
                'pessoa_contato' => $request->pessoaContato ?? '',
                'telefone' => $request->telefone ?? '',
                'email' => $request->email ?? '',
                'endereco' => $request->endereco ?? '',
                'nuit_nif' => $request->nuitNif ?? '',
                'iva' => $request->iva ?? '',
                'pais' => $request->pais ?? 'Moçambique',
                'observacoes' => $request->observacoes ?? '',
                'criado_por' => $user->name ?? 'Sistema',
                'tenant_id' => $tenantId,
            ]);
            
            Log::info('Cliente criado com sucesso:', ['id' => $cliente->id]);
            
            return response()->json([
                'success' => true,
                'data' => $this->formatarParaFrontend($cliente),
                'message' => 'Cliente criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Erro ao criar cliente: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar solicitação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualiza um cliente existente
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info("Atualizando cliente ID: {$id}", $request->all());
        
        $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
        if (!$cliente) {
            return response()->json(['success' => false, 'error' => 'Cliente não encontrado'], 404);
        }

        // Validação - NUIT NÃO É OBRIGATÓRIO
        $validator = Validator::make($request->all(), [
            'nomeEmpresa' => 'sometimes|string|max:255',
            'tipoCliente' => 'sometimes|in:Consignee,Shipper,Invoice Party',
            'nuitNif' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'pessoaContato' => 'nullable|string|max:255',
            'endereco' => 'nullable|string|max:500',
            'iva' => 'nullable|string|max:50',
            'pais' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            Log::warning('Validação falhou ao atualizar cliente:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $validator->errors()
            ], 422);
        }
        
        try {
            // Atualizar apenas os campos fornecidos
            $updateData = [];
            
            if ($request->has('nomeEmpresa')) $updateData['nome_empresa'] = $request->nomeEmpresa;
            if ($request->has('tipoCliente')) $updateData['tipo_cliente'] = $request->tipoCliente;
            if ($request->has('pessoaContato')) $updateData['pessoa_contato'] = $request->pessoaContato ?? '';
            if ($request->has('telefone')) $updateData['telefone'] = $request->telefone ?? '';
            if ($request->has('email')) $updateData['email'] = $request->email ?? '';
            if ($request->has('endereco')) $updateData['endereco'] = $request->endereco ?? '';
            if ($request->has('nuitNif')) $updateData['nuit_nif'] = $request->nuitNif ?? '';
            if ($request->has('iva')) $updateData['iva'] = $request->iva ?? '';
            if ($request->has('pais')) $updateData['pais'] = $request->pais ?? 'Moçambique';
            if ($request->has('observacoes')) $updateData['observacoes'] = $request->observacoes ?? '';
            
            Log::info('Dados a serem atualizados:', $updateData);
            
            $cliente->update($updateData);
            
            Log::info('Cliente atualizado com sucesso:', ['id' => $cliente->id]);
            
            return response()->json([
                'success' => true,
                'data' => $this->formatarParaFrontend($cliente->fresh()),
                'message' => 'Cliente atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar cliente: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar solicitação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove um cliente
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
        if (!$cliente) {
            return response()->json(['success' => false, 'error' => 'Cliente não encontrado'], 404);
        }
        
        try {
            $cliente->delete();
            
            Log::info('Cliente excluído com sucesso:', ['id' => $id]);
            
            return response()->json([
                'success' => true,
                'message' => 'Cliente excluído com sucesso!'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir cliente: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir cliente: ' . $e->getMessage()
            ], 500);
        }
    }
}