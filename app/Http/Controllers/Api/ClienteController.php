<?php
// app/Http/Controllers/Api/ClienteController.php - CORREÇÃO COMPLETA

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

    // Função auxiliar para converter snake_case para camelCase
    private function paraCamelCase($cliente)
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
            'tenantId' => $cliente->tenant_id, // Adicionado para debug
            'createdAt' => $cliente->created_at->toISOString(),
            'updatedAt' => $cliente->updated_at->toISOString()
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? 'default';
        
        Log::info('📥 GET /api/clientes', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'query' => $request->all()
        ]);
        
        try {
            $query = Cliente::where('tenant_id', $tenantId);
            
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nome_empresa', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('nuit_nif', 'like', "%{$search}%")
                      ->orWhere('pessoa_contato', 'like', "%{$search}%")
                      ->orWhere('telefone', 'like', "%{$search}%");
                });
            }
            
            $perPage = $request->get('limit', 10);
            $page = $request->get('page', 1);
            
            $clientes = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            // Converter todos os itens para camelCase
            $clientesCamelCase = $clientes->map(function ($cliente) {
                return $this->paraCamelCase($cliente);
            });
            
            Log::info('✅ Clientes listados', [
                'total' => $clientes->total(),
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $clientesCamelCase->toArray(),
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
            Log::error('❌ Erro ao listar clientes: ' . $e->getMessage());
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
        
        Log::info('📥 POST /api/clientes', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        $validator = Validator::make($request->all(), [
            'nomeEmpresa' => 'required|string|max:255',
            'tipoCliente' => 'required|in:Consignee,Shipper,Invoice Party',
            'nuitNif' => 'required|string|size:9|unique:clientes,nuit_nif,NULL,id,tenant_id,' . $tenantId,
            'email' => 'nullable|email|unique:clientes,email,NULL,id,tenant_id,' . $tenantId,
        ], [
            'nuitNif.unique' => 'Já existe um cliente com este NUIT/NIF',
            'nuitNif.size' => 'NUIT/NIF deve ter exatamente 9 dígitos',
            'email.unique' => 'Já existe um cliente com este email',
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
            $dados = [
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
            ];
            
            Log::info('💾 Salvando cliente', $dados);
            
            $cliente = Cliente::create($dados);
            
            Log::info('✅ Cliente criado', [
                'id' => $cliente->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($cliente),
                'message' => 'Cliente criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar cliente: ' . $e->getMessage());
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
            $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
            
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($cliente)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar cliente: ' . $e->getMessage());
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
        
        Log::info('📥 PUT /api/clientes/' . $id, [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'dados' => $request->all()
        ]);
        
        try {
            $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
            
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente não encontrado'
                ], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'nomeEmpresa' => 'required|string|max:255',
                'tipoCliente' => 'required|in:Consignee,Shipper,Invoice Party',
                'nuitNif' => 'required|string|size:9|unique:clientes,nuit_nif,' . $id . ',id,tenant_id,' . $tenantId,
                'email' => 'nullable|email|unique:clientes,email,' . $id . ',id,tenant_id,' . $tenantId,
            ], [
                'nuitNif.unique' => 'Já existe um cliente com este NUIT/NIF',
                'nuitNif.size' => 'NUIT/NIF deve ter exatamente 9 dígitos',
                'email.unique' => 'Já existe um cliente com este email',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $cliente->update([
                'nome_empresa' => $request->nomeEmpresa,
                'tipo_cliente' => $request->tipoCliente,
                'pessoa_contato' => $request->pessoaContato ?? $cliente->pessoa_contato,
                'telefone' => $request->telefone ?? $cliente->telefone,
                'email' => $request->email ?? $cliente->email,
                'endereco' => $request->endereco ?? $cliente->endereco,
                'nuit_nif' => $request->nuitNif,
                'iva' => $request->iva ?? $cliente->iva,
                'pais' => $request->pais ?? $cliente->pais,
                'observacoes' => $request->observacoes ?? $cliente->observacoes,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $this->paraCamelCase($cliente->fresh()),
                'message' => 'Cliente atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar cliente: ' . $e->getMessage());
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
            $cliente = Cliente::where('tenant_id', $tenantId)->find($id);
            
            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente não encontrado'
                ], 404);
            }
            
            $cliente->delete();
            
            Log::info('✅ Cliente excluído', [
                'id' => $id,
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Cliente excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir cliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }
}