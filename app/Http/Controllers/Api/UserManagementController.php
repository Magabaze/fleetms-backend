<?php
// app/Http/Controllers/Api/UserManagementController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    protected function getTenantId()
    {
        $user = Auth::user();
        return $user ? $user->tenant_id : null;
    }

    /**
     * Verificar se o usuário tem permissões de admin
     */
    protected function isAdmin($user)
    {
        if (!$user->role) {
            return false;
        }
        
        // Super admin tem todas as permissões
        if ($user->role->is_super_admin) {
            return true;
        }
        
        // Verificar roles de admin
        $rolesAdmin = ['administrador', 'admin', 'super admin', 'gerente'];
        $roleNome = strtolower($user->role->nome);
        
        return in_array($roleNome, $rolesAdmin);
    }

    /**
     * Verificar permissão específica
     */
    protected function hasPermission($user, $permissionKey)
    {
        if (!$user->role) {
            return false;
        }
        
        // Super admin tem todas as permissões
        if ($user->role->is_super_admin) {
            return true;
        }
        
        return $user->role->hasPermission($permissionKey);
    }

    // ==================== USUÁRIOS ====================

    /**
     * Listar usuários
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            // Verificar permissão
            if (!$this->hasPermission($user, 'usuarios.gerenciar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem gerenciar usuários'
                ], 403);
            }
            
            $query = User::with('role')->where('tenant_id', $tenantId);
            
            // Busca
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('telefone', 'like', "%{$search}%");
                });
            }
            
            // Filtro por role
            if ($request->has('role_id') && $request->role_id) {
                $query->where('role_id', $request->role_id);
            }
            
            // Filtro por status
            if ($request->has('ativo') && $request->ativo !== null) {
                $query->where('ativo', $request->ativo);
            }
            
            // Paginação
            $perPage = $request->get('per_page', $request->get('limit', 10));
            $page = $request->get('page', 1);
            $users = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'data' => $users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'nome' => $user->name,
                        'email' => $user->email,
                        'telefone' => $user->telefone,
                        'cargo' => $user->cargo,
                        'departamento' => $user->departamento,
                        'roleId' => $user->role_id,
                        'role' => $user->role ? [
                            'id' => $user->role->id,
                            'nome' => $user->role->nome,
                            'isSuperAdmin' => (bool) $user->role->is_super_admin,
                        ] : null,
                        'isSuperAdmin' => $user->isSuperAdmin(),
                        'idioma' => $user->idioma,
                        'fusoHorario' => $user->fuso_horario,
                        'ativo' => $user->ativo,
                        'tenantId' => $user->tenant_id,
                        'membroDesde' => $user->created_at->format('d/m/Y'),
                        'createdAt' => $user->created_at->toISOString(),
                        'updatedAt' => $user->updated_at->toISOString(),
                    ];
                }),
                'pagination' => [
                    'page' => $users->currentPage(),
                    'limit' => $perPage,
                    'total' => $users->total(),
                    'totalPages' => $users->lastPage(),
                    'hasNextPage' => $users->hasMorePages(),
                    'hasPrevPage' => $users->currentPage() > 1,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar usuários: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar usuários: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar usuário por ID
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->hasPermission($user, 'usuarios.gerenciar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem visualizar usuários'
                ], 403);
            }
            
            $usuario = User::with('role')->where('tenant_id', $tenantId)->find($id);
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário não encontrado'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $usuario->id,
                    'nome' => $usuario->name,
                    'email' => $usuario->email,
                    'telefone' => $usuario->telefone,
                    'cargo' => $usuario->cargo,
                    'departamento' => $usuario->departamento,
                    'roleId' => $usuario->role_id,
                    'role' => $usuario->role ? [
                        'id' => $usuario->role->id,
                        'nome' => $usuario->role->nome,
                        'isSuperAdmin' => (bool) $usuario->role->is_super_admin,
                    ] : null,
                    'isSuperAdmin' => $usuario->isSuperAdmin(),
                    'idioma' => $usuario->idioma,
                    'fusoHorario' => $usuario->fuso_horario,
                    'ativo' => $usuario->ativo,
                    'tenantId' => $usuario->tenant_id,
                    'membroDesde' => $usuario->created_at->format('d/m/Y'),
                    'createdAt' => $usuario->created_at->toISOString(),
                    'updatedAt' => $usuario->updated_at->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar usuário: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar novo usuário
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            Log::info('📥 POST /api/usuarios', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            if (!$this->hasPermission($user, 'usuarios.criar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem criar usuários'
                ], 403);
            }
            
            // Validação
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'senha' => 'required|string|min:6',
                'confirmarSenha' => 'required|string|same:senha',
                'telefone' => 'nullable|string|max:20',
                'cargo' => 'nullable|string|max:255',
                'departamento' => 'nullable|string|max:255',
                'roleId' => 'required|exists:roles,id',
                'idioma' => 'nullable|string|max:10',
                'fusoHorario' => 'nullable|string|max:50',
            ], [
                'nome.required' => 'O campo nome é obrigatório.',
                'email.required' => 'O campo e-mail é obrigatório.',
                'email.email' => 'Digite um e-mail válido.',
                'email.unique' => 'Este e-mail já está em uso.',
                'senha.required' => 'O campo senha é obrigatório.',
                'senha.min' => 'A senha deve ter no mínimo 6 caracteres.',
                'confirmarSenha.required' => 'A confirmação da senha é obrigatória.',
                'confirmarSenha.same' => 'A confirmação da senha não corresponde.',
                'roleId.required' => 'O campo perfil é obrigatório.',
                'roleId.exists' => 'O perfil selecionado não existe.',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Verificar se o role pertence ao tenant
            $role = Role::where('id', $request->roleId)
                        ->where('tenant_id', $tenantId)
                        ->first();
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Perfil não encontrado ou não pertence ao seu tenant'
                ], 404);
            }
            
            // Apenas super admin pode criar outro super admin
            if ($role->is_super_admin && !$user->role?->is_super_admin) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas super administradores podem criar usuários com perfil de super admin'
                ], 403);
            }
            
            DB::beginTransaction();
            
            $novoUsuario = User::create([
                'name' => $request->nome,
                'email' => $request->email,
                'password' => Hash::make($request->senha),
                'telefone' => $request->telefone,
                'cargo' => $request->cargo,
                'departamento' => $request->departamento,
                'role_id' => $request->roleId,
                'tenant_id' => $tenantId,
                'idioma' => $request->idioma ?? 'pt',
                'fuso_horario' => $request->fusoHorario ?? 'Africa/Maputo',
                'ativo' => true,
                'email_verified_at' => now(),
            ]);
            
            DB::commit();
            
            Log::info('✅ Usuário criado', [
                'criado_por' => $user->id,
                'novo_usuario_id' => $novoUsuario->id,
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $novoUsuario->id,
                    'nome' => $novoUsuario->name,
                    'email' => $novoUsuario->email,
                    'telefone' => $novoUsuario->telefone,
                    'cargo' => $novoUsuario->cargo,
                    'departamento' => $novoUsuario->departamento,
                    'roleId' => $novoUsuario->role_id,
                    'role' => $role ? [
                        'id' => $role->id,
                        'nome' => $role->nome,
                        'isSuperAdmin' => (bool) $role->is_super_admin,
                    ] : null,
                    'idioma' => $novoUsuario->idioma,
                    'fusoHorario' => $novoUsuario->fuso_horario,
                    'ativo' => $novoUsuario->ativo,
                    'tenantId' => $novoUsuario->tenant_id,
                    'createdAt' => $novoUsuario->created_at->toISOString(),
                    'updatedAt' => $novoUsuario->updated_at->toISOString(),
                ],
                'message' => 'Usuário criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar usuário: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            Log::info('📥 PUT /api/usuarios/' . $id, [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            if (!$this->hasPermission($user, 'usuarios.editar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem atualizar usuários'
                ], 403);
            }
            
            $usuario = User::where('tenant_id', $tenantId)->find($id);
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário não encontrado'
                ], 404);
            }
            
            // Validação para UPDATE
            $regrasValidacao = [
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
                'telefone' => 'nullable|string|max:20',
                'cargo' => 'nullable|string|max:255',
                'departamento' => 'nullable|string|max:255',
                'roleId' => 'required|exists:roles,id',
                'idioma' => 'nullable|string|max:10',
                'fusoHorario' => 'nullable|string|max:50',
                'ativo' => 'nullable|boolean',
            ];
            
            $mensagens = [
                'nome.required' => 'O campo nome é obrigatório.',
                'email.required' => 'O campo e-mail é obrigatório.',
                'email.email' => 'Digite um e-mail válido.',
                'email.unique' => 'Este e-mail já está em uso por outro usuário.',
                'roleId.required' => 'O campo perfil é obrigatório.',
                'roleId.exists' => 'O perfil selecionado não existe.',
            ];
            
            // Senha é OPCIONAL no update
            if ($request->has('senha') && !empty($request->senha)) {
                $regrasValidacao['senha'] = 'required|string|min:6';
                $regrasValidacao['confirmarSenha'] = 'required|string|same:senha';
                $mensagens['senha.required'] = 'O campo senha é obrigatório quando for preenchido.';
                $mensagens['senha.min'] = 'A senha deve ter no mínimo 6 caracteres.';
                $mensagens['confirmarSenha.required'] = 'A confirmação da senha é obrigatória.';
                $mensagens['confirmarSenha.same'] = 'A confirmação da senha não corresponde.';
            }
            
            $validator = Validator::make($request->all(), $regrasValidacao, $mensagens);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Verificar se o role pertence ao tenant
            $role = Role::where('id', $request->roleId)
                        ->where('tenant_id', $tenantId)
                        ->first();
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Perfil não encontrado ou não pertence ao seu tenant'
                ], 404);
            }
            
            // Apenas super admin pode alterar para super admin
            if ($role->is_super_admin && !$user->role?->is_super_admin && $usuario->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas super administradores podem atribuir perfil de super admin'
                ], 403);
            }
            
            DB::beginTransaction();
            
            // Preparar dados para atualização
            $dadosAtualizacao = [
                'name' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'cargo' => $request->cargo,
                'departamento' => $request->departamento,
                'role_id' => $request->roleId,
                'idioma' => $request->idioma ?? $usuario->idioma,
                'fuso_horario' => $request->fusoHorario ?? $usuario->fuso_horario,
                'ativo' => $request->has('ativo') ? $request->ativo : $usuario->ativo,
            ];
            
            // Atualizar senha apenas se fornecida
            if ($request->has('senha') && !empty($request->senha)) {
                $dadosAtualizacao['password'] = Hash::make($request->senha);
            }
            
            $usuario->update($dadosAtualizacao);
            
            DB::commit();
            
            Log::info('✅ Usuário atualizado', [
                'id' => $usuario->id,
                'tenant_id' => $usuario->tenant_id
            ]);
            
            // Recarregar o usuário atualizado com role
            $usuario->refresh();
            $usuario->load('role');
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $usuario->id,
                    'nome' => $usuario->name,
                    'email' => $usuario->email,
                    'telefone' => $usuario->telefone,
                    'cargo' => $usuario->cargo,
                    'departamento' => $usuario->departamento,
                    'roleId' => $usuario->role_id,
                    'role' => $usuario->role ? [
                        'id' => $usuario->role->id,
                        'nome' => $usuario->role->nome,
                        'isSuperAdmin' => (bool) $usuario->role->is_super_admin,
                    ] : null,
                    'idioma' => $usuario->idioma,
                    'fusoHorario' => $usuario->fuso_horario,
                    'ativo' => $usuario->ativo,
                    'tenantId' => $usuario->tenant_id,
                    'createdAt' => $usuario->created_at->toISOString(),
                    'updatedAt' => $usuario->updated_at->toISOString(),
                ],
                'message' => 'Usuário atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao atualizar usuário: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir usuário
     */
    public function destroy($id)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->hasPermission($currentUser, 'usuarios.excluir') && !$this->isAdmin($currentUser)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem excluir usuários'
                ], 403);
            }
            
            $usuario = User::where('tenant_id', $tenantId)->find($id);
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário não encontrado'
                ], 404);
            }
            
            if ($usuario->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Você não pode excluir seu próprio usuário'
                ], 400);
            }
            
            // Verificar se é o último admin
            if ($usuario->role && $this->isAdmin($usuario)) {
                $adminsCount = User::where('tenant_id', $tenantId)
                    ->whereHas('role', function($q) {
                        $q->where('is_super_admin', true)
                          ->orWhereIn('nome', ['Administrador', 'Admin', 'Super Admin', 'Gerente']);
                    })
                    ->count();
                
                if ($adminsCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Não é possível excluir o último administrador do sistema'
                    ], 400);
                }
            }
            
            DB::beginTransaction();
            $usuario->delete();
            DB::commit();
            
            Log::info('✅ Usuário excluído', [
                'excluido_por' => $currentUser->id,
                'usuario_id' => $id,
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Usuário excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao excluir usuário: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== ROLES / PERFIS ====================

    /**
     * Listar roles/perfis
     */
    public function getRoles()
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->hasPermission($user, 'perfis.gerenciar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem visualizar perfis'
                ], 403);
            }
            
            $roles = Role::where('tenant_id', $tenantId)
                ->with('permissions')
                ->withCount('users')
                ->orderBy('nome')
                ->get()
                ->map(function ($role) use ($user) {
                    return [
                        'id' => $role->id,
                        'nome' => $role->nome,
                        'descricao' => $role->descricao,
                        'isSuperAdmin' => (bool) $role->is_super_admin,
                        'tenantId' => $role->tenant_id,
                        'usersCount' => $role->users_count,
                        'permissoes' => $role->permissions->pluck('chave')->toArray(),
                        'createdAt' => $role->created_at->toISOString(),
                        'updatedAt' => $role->updated_at->toISOString(),
                        'canEdit' => $user->role && ($user->role->is_super_admin || !$role->is_super_admin),
                        'canDelete' => $user->role && ($user->role->is_super_admin || !$role->is_super_admin) && $role->users_count === 0,
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar perfis: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Criar novo role/perfil
     */
    public function storeRole(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            Log::info('📥 POST /api/usuarios/roles', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            if (!$this->hasPermission($user, 'perfis.criar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem criar perfis'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255|unique:roles,nome,NULL,id,tenant_id,' . $tenantId,
                'descricao' => 'nullable|string|max:500',
                'is_super_admin' => 'nullable|boolean',
                'permissoes' => 'nullable|array',
                'permissoes.*' => 'string|max:255',
            ], [
                'nome.required' => 'O nome do perfil é obrigatório.',
                'nome.unique' => 'Já existe um perfil com este nome.',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Apenas super admin pode criar role super admin
            if ($request->is_super_admin && !$user->role?->is_super_admin) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas super administradores podem criar perfis de super admin'
                ], 403);
            }
            
            DB::beginTransaction();
            
            $role = Role::create([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'tenant_id' => $tenantId,
                'is_super_admin' => $request->is_super_admin ?? false,
            ]);
            
            // Associar permissões se fornecidas e NÃO for super admin
            if ($request->has('permissoes') && is_array($request->permissoes) && !$request->is_super_admin) {
                // Buscar permissões pelo CHAVE (string) e pelo tenant
                $permissions = Permission::whereIn('chave', $request->permissoes)
                    ->where('tenant_id', $tenantId)
                    ->get();
                
                // Verificar se todas as permissões foram encontradas
                if ($permissions->count() !== count($request->permissoes)) {
                    $encontradas = $permissions->pluck('chave')->toArray();
                    $naoEncontradas = array_diff($request->permissoes, $encontradas);
                    
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => 'Algumas permissões não existem',
                        'errors' => [
                            'permissoes' => ['As seguintes permissões são inválidas: ' . implode(', ', $naoEncontradas)]
                        ]
                    ], 422);
                }
                
                // Sincronizar usando IDs
                $permissionIds = $permissions->pluck('id')->toArray();
                $role->permissions()->sync($permissionIds);
            }
            
            DB::commit();
            
            Log::info('✅ Perfil criado', [
                'criado_por' => $user->id,
                'role_id' => $role->id,
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $role->id,
                    'nome' => $role->nome,
                    'descricao' => $role->descricao,
                    'isSuperAdmin' => (bool) $role->is_super_admin,
                    'permissoes' => $role->permissions()->pluck('chave')->toArray(),
                    'tenantId' => $role->tenant_id,
                    'createdAt' => $role->created_at->toISOString(),
                    'updatedAt' => $role->updated_at->toISOString(),
                ],
                'message' => 'Perfil criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao criar perfil: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar role/perfil
     */
    public function updateRole(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            Log::info('📥 PUT /api/usuarios/roles/' . $id, [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            if (!$this->hasPermission($user, 'perfis.editar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem atualizar perfis'
                ], 403);
            }
            
            $role = Role::where('tenant_id', $tenantId)->find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Perfil não encontrado'
                ], 404);
            }
            
            // Não permitir editar roles de super admin (exceto para super admin)
            if ($role->is_super_admin && !$user->role?->is_super_admin) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível editar perfis de super administrador'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255|unique:roles,nome,' . $id . ',id,tenant_id,' . $tenantId,
                'descricao' => 'nullable|string|max:500',
                'permissoes' => 'nullable|array',
                'permissoes.*' => 'string|max:255',
            ], [
                'nome.required' => 'O nome do perfil é obrigatório.',
                'nome.unique' => 'Já existe um perfil com este nome.',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            $role->update([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
            ]);
            
            // Atualizar permissões se fornecidas
            if ($request->has('permissoes')) {
                // Buscar permissões pelo CHAVE (string) e pelo tenant
                $permissions = Permission::whereIn('chave', $request->permissoes)
                    ->where('tenant_id', $tenantId)
                    ->get();
                
                // Verificar se todas as permissões foram encontradas
                if ($permissions->count() !== count($request->permissoes)) {
                    $encontradas = $permissions->pluck('chave')->toArray();
                    $naoEncontradas = array_diff($request->permissoes, $encontradas);
                    
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => 'Algumas permissões não existem',
                        'errors' => [
                            'permissoes' => ['As seguintes permissões são inválidas: ' . implode(', ', $naoEncontradas)]
                        ]
                    ], 422);
                }
                
                // Sincronizar usando IDs
                $permissionIds = $permissions->pluck('id')->toArray();
                $role->permissions()->sync($permissionIds);
            }
            
            DB::commit();
            
            Log::info('✅ Perfil atualizado', [
                'role_id' => $role->id,
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $role->id,
                    'nome' => $role->nome,
                    'descricao' => $role->descricao,
                    'isSuperAdmin' => (bool) $role->is_super_admin,
                    'permissoes' => $role->fresh()->permissions()->pluck('chave')->toArray(),
                    'tenantId' => $role->tenant_id,
                    'createdAt' => $role->created_at->toISOString(),
                    'updatedAt' => $role->updated_at->toISOString(),
                ],
                'message' => 'Perfil atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao atualizar perfil: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir role/perfil
     */
    public function destroyRole($id)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->hasPermission($currentUser, 'perfis.excluir') && !$this->isAdmin($currentUser)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem excluir perfis'
                ], 403);
            }
            
            $role = Role::where('tenant_id', $tenantId)->find($id);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Perfil não encontrado'
                ], 404);
            }
            
            // Não permitir excluir roles de super admin
            if ($role->is_super_admin) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir perfis de super administrador'
                ], 403);
            }
            
            // Verificar se há usuários usando este role
            $usuariosComRole = $role->users()->count();
            if ($usuariosComRole > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir o perfil pois existem usuários associados a ele.'
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Remover relações com permissões
            $role->permissions()->detach();
            $role->delete();
            
            DB::commit();
            
            Log::info('✅ Perfil excluído', [
                'excluido_por' => $currentUser->id,
                'role_id' => $id,
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Perfil excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao excluir perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== PERMISSÕES ====================

    /**
     * Listar todas as permissões disponíveis
     */
    public function getPermissions()
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->hasPermission($user, 'permissoes.gerenciar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem visualizar permissões'
                ], 403);
            }
            
            // SEM A COLUNA 'ordem' - usando apenas modulo e id
            $permissions = Permission::where('tenant_id', $tenantId)
                ->orderBy('modulo')
                ->orderBy('id') // Usar id como fallback em vez de 'ordem'
                ->get()
                ->groupBy('modulo')
                ->map(function ($perms) {
                    return $perms->map(function ($perm) {
                        return [
                            'id' => $perm->id,
                            'nome' => $perm->nome,
                            'chave' => $perm->chave,
                            'descricao' => $perm->descricao,
                            'modulo' => $perm->modulo,
                            'tipo' => $perm->tipo ?? 'botao',
                            'icone' => $perm->icone ?? null,
                            'tenantId' => $perm->tenant_id,
                            'createdAt' => $perm->created_at->toISOString(),
                            'updatedAt' => $perm->updated_at->toISOString(),
                        ];
                    })->values();
                });
            
            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar permissões: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar permissões: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar permissões de um role específico
     */
    public function getRolePermissions($roleId)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->hasPermission($user, 'permissoes.gerenciar') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem visualizar permissões'
                ], 403);
            }
            
            $role = Role::where('tenant_id', $tenantId)->find($roleId);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Perfil não encontrado'
                ], 404);
            }
            
            $permissions = $role->permissions()->pluck('chave')->toArray();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'roleId' => $role->id,
                    'roleNome' => $role->nome,
                    'permissions' => $permissions
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar permissões do perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar permissões do perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar permissões de um role
     */
    public function updateRolePermissions(Request $request, $roleId)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            Log::info('📥 PUT /api/usuarios/roles/' . $roleId . '/permissoes', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            if (!$this->hasPermission($user, 'permissoes.atribuir') && !$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem atribuir permissões'
                ], 403);
            }
            
            $role = Role::where('tenant_id', $tenantId)->find($roleId);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Perfil não encontrado'
                ], 404);
            }
            
            // Não permitir editar permissões de roles de super admin
            if ($role->is_super_admin && !$user->role?->is_super_admin) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível editar permissões de perfis de super administrador'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'permissions' => 'required|array',
                'permissions.*' => 'string|max:255',
            ], [
                'permissions.required' => 'As permissões são obrigatórias.',
                'permissions.array' => 'As permissões devem ser um array.',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            // Buscar permissões pelo CHAVE (string) e pelo tenant
            $permissions = Permission::whereIn('chave', $request->permissions)
                ->where('tenant_id', $tenantId)
                ->get();
            
            // Verificar se todas as permissões foram encontradas
            if ($permissions->count() !== count($request->permissions)) {
                $encontradas = $permissions->pluck('chave')->toArray();
                $naoEncontradas = array_diff($request->permissions, $encontradas);
                
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Algumas permissões não existem',
                    'errors' => [
                        'permissions' => ['As seguintes permissões são inválidas: ' . implode(', ', $naoEncontradas)]
                    ]
                ], 422);
            }
            
            $permissionIds = $permissions->pluck('id')->toArray();
            $role->permissions()->sync($permissionIds);
            
            DB::commit();
            
            Log::info('✅ Permissões atualizadas', [
                'role_id' => $role->id,
                'permissions_count' => count($request->permissions),
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Permissões atualizadas com sucesso!',
                'data' => [
                    'permissions' => $role->fresh()->permissions()->pluck('chave')->toArray()
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao atualizar permissões do perfil: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar permissões: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== PERFIL DO USUÁRIO LOGADO ====================

    /**
     * Buscar perfil do usuário logado
     */
    public function meuPerfil()
    {
        try {
            $user = Auth::user();
            
            $user->load('role');
            
            // Buscar permissões do usuário
            $permissoes = [];
            if ($user->role) {
                if ($user->role->is_super_admin) {
                    // Super admin tem todas as permissões
                    $permissoes = Permission::where('tenant_id', $user->tenant_id)
                        ->pluck('chave')
                        ->toArray();
                } else {
                    $permissoes = $user->role->permissions()->pluck('chave')->toArray();
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'cargo' => $user->cargo,
                    'departamento' => $user->departamento,
                    'roleId' => $user->role_id,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'nome' => $user->role->nome,
                        'isSuperAdmin' => (bool) $user->role->is_super_admin,
                    ] : null,
                    'isSuperAdmin' => $user->isSuperAdmin(),
                    'idioma' => $user->idioma,
                    'fusoHorario' => $user->fuso_horario,
                    'ativo' => $user->ativo,
                    'tenantId' => $user->tenant_id,
                    'permissoes' => $permissoes,
                    'membroDesde' => $user->created_at->format('d/m/Y'),
                    'createdAt' => $user->created_at->toISOString(),
                    'updatedAt' => $user->updated_at->toISOString(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar perfil do usuário logado
     */
    public function atualizarMeuPerfil(Request $request)
    {
        try {
            $user = Auth::user();
            
            Log::info('📥 PUT /api/usuarios/meu-perfil', [
                'user_id' => $user->id,
                'dados' => $request->all()
            ]);
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'telefone' => 'nullable|string|max:20',
                'cargo' => 'nullable|string|max:255',
                'departamento' => 'nullable|string|max:255',
                'idioma' => 'nullable|string|max:10',
                'fusoHorario' => 'nullable|string|max:50',
            ], [
                'nome.required' => 'O campo nome é obrigatório.',
                'email.required' => 'O campo e-mail é obrigatório.',
                'email.email' => 'Digite um e-mail válido.',
                'email.unique' => 'Este e-mail já está em uso.',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::beginTransaction();
            
            $user->update([
                'name' => $request->nome,
                'email' => $request->email,
                'telefone' => $request->telefone,
                'cargo' => $request->cargo,
                'departamento' => $request->departamento,
                'idioma' => $request->idioma ?? $user->idioma,
                'fuso_horario' => $request->fusoHorario ?? $user->fuso_horario,
            ]);
            
            DB::commit();
            
            Log::info('✅ Perfil atualizado', [
                'usuario_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'cargo' => $user->cargo,
                    'departamento' => $user->departamento,
                    'idioma' => $user->idioma,
                    'fusoHorario' => $user->fuso_horario,
                ],
                'message' => 'Perfil atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao atualizar perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar senha do usuário logado
     */
    public function atualizarMinhaSenha(Request $request)
    {
        try {
            $user = Auth::user();
            
            Log::info('📥 PUT /api/usuarios/minha-senha', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id
            ]);
            
            $validator = Validator::make($request->all(), [
                'senhaAtual' => 'required|string',
                'novaSenha' => 'required|string|min:6',
                'confirmarNovaSenha' => 'required|string|same:novaSenha',
            ], [
                'senhaAtual.required' => 'A senha atual é obrigatória.',
                'novaSenha.required' => 'A nova senha é obrigatória.',
                'novaSenha.min' => 'A nova senha deve ter no mínimo 6 caracteres.',
                'confirmarNovaSenha.required' => 'A confirmação da nova senha é obrigatória.',
                'confirmarNovaSenha.same' => 'A confirmação da nova senha não corresponde.',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            if (!Hash::check($request->senhaAtual, $user->password)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Senha atual incorreta'
                ], 400);
            }
            
            DB::beginTransaction();
            
            $user->password = Hash::make($request->novaSenha);
            $user->save();
            
            DB::commit();
            
            Log::info('✅ Senha atualizada', [
                'usuario_id' => $user->id,
                'tenant' => $user->tenant_id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao atualizar senha: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar senha: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar se usuário tem uma permissão específica
     */
    public function verificarPermissao($permissionKey)
    {
        try {
            $user = Auth::user();
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido',
                    'hasPermission' => false
                ], 403);
            }
            
            $hasPermission = $this->hasPermission($user, $permissionKey);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'permission' => $permissionKey,
                    'hasPermission' => $hasPermission
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao verificar permissão: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao verificar permissão: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar todas as permissões do usuário logado
     */
    public function minhasPermissoes()
    {
        try {
            $user = Auth::user();
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido',
                    'data' => []
                ], 403);
            }
            
            if ($user->role->is_super_admin) {
                $permissions = Permission::where('tenant_id', $user->tenant_id)
                    ->pluck('chave')
                    ->toArray();
            } else {
                $permissions = $user->role->getPermissionKeys();
            }
            
            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar minhas permissões: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar permissões: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alternar status do usuário (ativar/desativar)
     */
    public function toggleStatus($id)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->hasPermission($currentUser, 'usuarios.editar') && !$this->isAdmin($currentUser)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem alterar status de usuários'
                ], 403);
            }
            
            $usuario = User::where('tenant_id', $tenantId)->find($id);
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário não encontrado'
                ], 404);
            }
            
            if ($usuario->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Você não pode alterar seu próprio status'
                ], 400);
            }
            
            DB::beginTransaction();
            
            $usuario->ativo = !$usuario->ativo;
            $usuario->save();
            
            DB::commit();
            
            Log::info('✅ Status do usuário alterado', [
                'alterado_por' => $currentUser->id,
                'usuario_id' => $id,
                'novo_status' => $usuario->ativo
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $usuario->id,
                    'ativo' => $usuario->ativo
                ],
                'message' => 'Status do usuário alterado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Erro ao alterar status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao alterar status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estatísticas de usuários
     */
    public function estatisticas()
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$this->isAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem ver estatísticas'
                ], 403);
            }
            
            $totalUsuarios = User::where('tenant_id', $tenantId)->count();
            $usuariosAtivos = User::where('tenant_id', $tenantId)->where('ativo', true)->count();
            $usuariosInativos = User::where('tenant_id', $tenantId)->where('ativo', false)->count();
            
            $usuariosPorRole = User::where('tenant_id', $tenantId)
                ->with('role')
                ->get()
                ->groupBy(function($user) {
                    return $user->role ? $user->role->nome : 'Sem Perfil';
                })
                ->map(function($group) {
                    return $group->count();
                });
            
            $novosUsuariosMes = User::where('tenant_id', $tenantId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'totalUsuarios' => $totalUsuarios,
                    'usuariosAtivos' => $usuariosAtivos,
                    'usuariosInativos' => $usuariosInativos,
                    'novosUsuariosMes' => $novosUsuariosMes,
                    'usuariosPorRole' => $usuariosPorRole
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar estatísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}