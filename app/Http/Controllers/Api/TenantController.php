<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company as Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantApiController extends Controller
{
    /**
     * Exibir lista de tenants (apenas admin central)
     */
    public function index(Request $request)
    {
        // Verificar se é admin central (não é um tenant específico)
        $user = $request->user();
        
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso não autorizado'
            ], 403);
        }
        
        $tenants = Tenant::with('domains')->get();
        
        return response()->json([
            'success' => true,
            'data' => $tenants
        ]);
    }
    
    /**
     * Salvar novo tenant (apenas admin central)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso não autorizado'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|string|unique:tenants,id|max:255',
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:domains,domain',
            'email' => 'nullable|email|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $tenant = Tenant::createWithDomain([
                'id' => $request->tenant_id,
                'name' => $request->name,
                'email' => $request->email,
            ], $request->domain);
            
            // Inicializar tenant para criar dados iniciais
            tenancy()->initialize($tenant);
            
            // Aqui você pode criar dados iniciais para o tenant
            // como roles, permissões, usuário admin, etc.
            
            tenancy()->end();
            
            DB::commit();
            
            Log::info('Novo tenant criado', [
                'tenant_id' => $tenant->id,
                'domain' => $request->domain,
                'created_by' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $tenant,
                'message' => 'Tenant criado com sucesso!'
            ], 201);
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erro ao criar tenant: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar tenant'
            ], 500);
        }
    }
    
    /**
     * Exibir tenant específico
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso não autorizado'
            ], 403);
        }
        
        $tenant = Tenant::with('domains')->find($id);
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant não encontrado'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $tenant
        ]);
    }
    
    /**
     * Atualizar tenant
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso não autorizado'
            ], 403);
        }
        
        $tenant = Tenant::find($id);
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant não encontrado'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $tenant->name = $request->name;
        $tenant->email = $request->email;
        $tenant->save();
        
        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => 'Tenant atualizado com sucesso!'
        ]);
    }
    
    /**
     * Remover tenant
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user || !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => 'Acesso não autorizado'
            ], 403);
        }
        
        $tenant = Tenant::find($id);
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant não encontrado'
            ], 404);
        }
        
        DB::beginTransaction();
        
        try {
            // Primeiro deletar os dados do tenant
            tenancy()->initialize($tenant);
            
            // Aqui você deletaria todas as tabelas do tenant
            // Depende da sua implementação
            
            tenancy()->end();
            
            // Depois deletar o tenant
            $tenant->domains()->delete();
            $tenant->delete();
            
            DB::commit();
            
            Log::info('Tenant removido', [
                'tenant_id' => $id,
                'removed_by' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Tenant removido com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erro ao remover tenant: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao remover tenant'
            ], 500);
        }
    }
}