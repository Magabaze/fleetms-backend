<?php
// routes/usuarios/usuarios.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserManagementController;

Route::prefix('usuarios')->group(function () {
    // =============== PERFIL ===============
    
    // Meu perfil detalhado (DEVE VIR ANTES de /{id})
    Route::get('/meu-perfil', [UserManagementController::class, 'meuPerfil']);
    
    // Atualizar minha senha
    Route::put('/atualizar-senha', [UserManagementController::class, 'atualizarMinhaSenha']);
    
    // =============== ROLES ===============
    
    // Listar roles disponíveis (DEVE VIR ANTES de /{id})
    Route::get('/roles', [UserManagementController::class, 'getRoles']);
    
    // Criar novo role
    Route::post('/roles', [UserManagementController::class, 'storeRole']);
    
    // Atualizar role
    Route::put('/roles/{id}', [UserManagementController::class, 'updateRole']);
    
    // Excluir role
    Route::delete('/roles/{id}', [UserManagementController::class, 'destroyRole']);
    
    // =============== PERMISSÕES ===============
    
    // Listar todas as permissões (agrupadas por módulo)
    Route::get('/permissions', [UserManagementController::class, 'getPermissions']);
    
    // Permissões de um role específico
    Route::get('/roles/{roleId}/permissions', [UserManagementController::class, 'getRolePermissions']);
    
    // Atualizar permissões de um role
    Route::put('/roles/{roleId}/permissions', [UserManagementController::class, 'updateRolePermissions']);
    
    // =============== USUÁRIOS ===============
    
    // Listar usuários (com paginação/filtro)
    Route::get('/', [UserManagementController::class, 'index']);
    
    // Criar novo usuário
    Route::post('/', [UserManagementController::class, 'store']);
    
    // Buscar usuário específico (ESTA DEVE VIR POR ÚLTIMO!)
    Route::get('/{id}', [UserManagementController::class, 'show']);
    
    // Atualizar usuário
    Route::put('/{id}', [UserManagementController::class, 'update']);
    
    // Excluir usuário
    Route::delete('/{id}', [UserManagementController::class, 'destroy']);
    
    // Redefinir senha do usuário
    Route::post('/{id}/reset-password', [UserManagementController::class, 'resetPassword']);
    
    // Alternar status do usuário (ativo/inativo)
    Route::patch('/{id}/status', [UserManagementController::class, 'toggleStatus']);
});