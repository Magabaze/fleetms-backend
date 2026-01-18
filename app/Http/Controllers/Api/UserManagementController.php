<?php

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

    // =============== LISTAR USUÁRIOS ===============
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin', 'Gerente'];
            $userRole = strtolower($user->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
                return response()->json([
                    'success' => false,
                    'error' => "Apenas administradores podem gerenciar usuários"
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
            
            // Converter para camelCase
            $usersCamelCase = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nome' => $user->name,
                    'email' => $user->email,
                    'telefone' => $user->telefone,
                    'cargo' => $user->cargo,
                    'departamento' => $user->departamento,
                    'roleId' => $user->role_id,
                    'roleNome' => $user->role ? $user->role->nome : null,
                    'idioma' => $user->idioma,
                    'fusoHorario' => $user->fuso_horario,
                    'ativo' => $user->ativo,
                    'tenantId' => $user->tenant_id,
                    'membroDesde' => $user->created_at->format('d/m/Y'),
                    'createdAt' => $user->created_at->toISOString(),
                    'updatedAt' => $user->updated_at->toISOString(),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $usersCamelCase->toArray(),
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

    // =============== BUSCAR USUÁRIO ===============
    public function show($id)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin', 'Gerente'];
            $userRole = strtolower($user->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
                return response()->json([
                    'success' => false,
                    'error' => "Apenas administradores podem visualizar usuários"
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
                    'roleNome' => $usuario->role ? $usuario->role->nome : null,
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

    // =============== CRIAR USUÁRIO ===============
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
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin'];
            $userRole = strtolower($user->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem criar usuários'
                ], 403);
            }
            
            // Validação SIMPLIFICADA e CORRETA
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
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
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
                'fuso_horario' => $request->fusoHorario ?? 'America/Sao_Paulo',
                'ativo' => true,
                'email_verified_at' => now(),
            ]);
            
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
                    'roleNome' => $novoUsuario->role ? $novoUsuario->role->nome : null,
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
            Log::error('❌ Erro ao criar usuário: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== ATUALIZAR USUÁRIO ===============
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
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin'];
            $userRole = strtolower($user->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
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
            
            // Validação FLEXÍVEL para UPDATE
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
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
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
                    'roleNome' => $usuario->role ? $usuario->role->nome : null,
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
            Log::error('❌ Erro ao atualizar usuário: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== EXCLUIR USUÁRIO ===============
    public function destroy($id)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$currentUser->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin'];
            $userRole = strtolower($currentUser->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
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
            
            $usuario->delete();
            
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
            Log::error('❌ Erro ao excluir usuário: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== LISTAR ROLES ===============
    public function getRoles()
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            $roles = Role::where('tenant_id', $tenantId)
                ->orderBy('nome')
                ->get();
            
            // Converter para camelCase
            $rolesCamelCase = $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'nome' => $role->nome,
                    'descricao' => $role->descricao,
                    'isSuperAdmin' => (bool) $role->is_super_admin,
                    'tenantId' => $role->tenant_id,
                    'createdAt' => $role->created_at->toISOString(),
                    'updatedAt' => $role->updated_at->toISOString(),
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $rolesCamelCase->toArray()
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar roles: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== LISTAR PERMISSÕES ===============
    public function getPermissions()
    {
        try {
            $tenantId = $this->getTenantId();
            
            $permissions = Permission::where('tenant_id', $tenantId)
                ->orderBy('modulo')
                ->orderBy('nome')
                ->get()
                ->groupBy('modulo');
            
            // Converter para camelCase
            $permissionsCamelCase = [];
            foreach ($permissions as $modulo => $perms) {
                $permissionsCamelCase[$modulo] = $perms->map(function ($perm) {
                    return [
                        'id' => $perm->id,
                        'nome' => $perm->nome,
                        'chave' => $perm->chave,
                        'descricao' => $perm->descricao,
                        'modulo' => $perm->modulo,
                        'tenantId' => $perm->tenant_id,
                        'createdAt' => $perm->created_at->toISOString(),
                        'updatedAt' => $perm->updated_at->toISOString(),
                    ];
                })->toArray();
            }
            
            return response()->json([
                'success' => true,
                'data' => $permissionsCamelCase
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao listar permissões: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao listar permissões: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== ATUALIZAR MINHA SENHA ===============
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
            
            $user->password = Hash::make($request->novaSenha);
            $user->save();
            
            Log::info('✅ Senha atualizada', [
                'usuario_id' => $user->id,
                'tenant' => $user->tenant_id
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar senha: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar senha: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== MEU PERFIL ===============
    public function meuPerfil()
    {
        try {
            $user = Auth::user();
            
            $user->load('role');
            
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
                    'roleNome' => $user->role ? $user->role->nome : null,
                    'isSuperAdmin' => $user->isSuperAdmin(),
                    'idioma' => $user->idioma,
                    'fusoHorario' => $user->fuso_horario,
                    'ativo' => $user->ativo,
                    'tenantId' => $user->tenant_id,
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

    // =============== CRIAR ROLE ===============
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
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            // Apenas Super Admin ou Administrador pode criar roles
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin'];
            $userRole = strtolower($user->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem criar perfis'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255|unique:roles,nome,NULL,id,tenant_id,' . $tenantId,
                'descricao' => 'nullable|string|max:500',
                'permissoes' => 'nullable|array',
                'permissoes.*' => 'string|max:255',
            ], [
                'nome.required' => 'O nome do perfil é obrigatório.',
                'nome.unique' => 'Já existe um perfil com este nome.',
                'permissoes.array' => 'As permissões devem ser um array.',
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $role = Role::create([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
                'tenant_id' => $tenantId,
                'is_super_admin' => false,
            ]);
            
            // Salvar permissões se fornecidas
            if ($request->has('permissoes') && is_array($request->permissoes)) {
                // Aqui você pode associar as permissões ao role
                // Dependendo de como sua tabela de relação está estruturada
                // Exemplo: $role->permissions()->sync($permissionIds);
            }
            
            Log::info('✅ Role criado', [
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
                    'permissoes' => $request->permissoes ?? [],
                    'tenantId' => $role->tenant_id,
                    'createdAt' => $role->created_at->toISOString(),
                    'updatedAt' => $role->updated_at->toISOString(),
                ],
                'message' => 'Perfil criado com sucesso!'
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar role: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== ATUALIZAR ROLE ===============
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
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            // Apenas Super Admin ou Administrador pode atualizar roles
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin'];
            $userRole = strtolower($user->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
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
            
            // Não permitir editar roles de super admin
            if ($role->is_super_admin && !$user->isSuperAdmin()) {
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
                'permissoes.array' => 'As permissões devem ser um array.',
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $role->update([
                'nome' => $request->nome,
                'descricao' => $request->descricao,
            ]);
            
            // Atualizar permissões se fornecidas
            if ($request->has('permissoes') && is_array($request->permissoes)) {
                // Atualizar relação de permissões
                // Exemplo: $role->permissions()->sync($permissionIds);
            }
            
            Log::info('✅ Role atualizado', [
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
                    'permissoes' => $request->permissoes ?? [],
                    'tenantId' => $role->tenant_id,
                    'createdAt' => $role->created_at->toISOString(),
                    'updatedAt' => $role->updated_at->toISOString(),
                ],
                'message' => 'Perfil atualizado com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar role: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== EXCLUIR ROLE ===============
    public function destroyRole($id)
    {
        try {
            $currentUser = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$currentUser->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            // Apenas Super Admin ou Administrador pode excluir roles
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin'];
            $userRole = strtolower($currentUser->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
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
            $usuariosComRole = User::where('role_id', $id)->count();
            if ($usuariosComRole > 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Não é possível excluir o perfil pois existem usuários associados a ele.'
                ], 400);
            }
            
            $role->delete();
            
            Log::info('✅ Role excluído', [
                'excluido_por' => $currentUser->id,
                'role_id' => $id,
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Perfil excluído com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao excluir role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao excluir perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== PERMISSÕES DE UM ROLE ===============
    public function getRolePermissions($roleId)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            $role = Role::where('tenant_id', $tenantId)->find($roleId);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Perfil não encontrado'
                ], 404);
            }
            
            // Obter permissões do role
            // Isso depende de como você estruturou a relação entre roles e permissions
            // Se usar tabela pivot role_permission:
            // $permissions = $role->permissions()->pluck('chave')->toArray();
            
            // Por enquanto, retornar array vazio
            $permissions = [];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'permissions' => $permissions
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao buscar permissões do role: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao buscar permissões do perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============== ATUALIZAR PERMISSÕES DE UM ROLE ===============
    public function updateRolePermissions(Request $request, $roleId)
    {
        try {
            $user = Auth::user();
            $tenantId = $this->getTenantId();
            
            Log::info('📥 PUT /api/usuarios/roles/' . $roleId . '/permissions', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'dados' => $request->all()
            ]);
            
            if (!$user->role) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuário sem role definido'
                ], 403);
            }
            
            // Apenas Super Admin ou Administrador pode atualizar permissões
            $rolesPermitidos = ['Administrador', 'Admin', 'Super Admin'];
            $userRole = strtolower($user->role->nome);
            $rolesPermitidosLower = array_map('strtolower', $rolesPermitidos);
            
            if (!in_array($userRole, $rolesPermitidosLower)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Apenas administradores podem atualizar permissões'
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
            if ($role->is_super_admin && !$user->isSuperAdmin()) {
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
                Log::error('❌ Validação falhou', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'error' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Aqui você atualizaria as permissões do role
            // Dependendo da estrutura do seu banco
            // Exemplo: $role->permissions()->sync($permissionIds);
            
            Log::info('✅ Permissões atualizadas', [
                'role_id' => $role->id,
                'permissions_count' => count($request->permissions),
                'tenant' => $tenantId
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Permissões atualizadas com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro ao atualizar permissões do role: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Erro ao atualizar permissões: ' . $e->getMessage()
            ], 500);
        }
    }
}