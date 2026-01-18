<?php
// database/seeders/RolesPermissionsSeeder.php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use App\Models\Empresa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Criar empresa padrão
        Empresa::create([
            'nome' => 'Transportes Logística LTDA',
            'tenant_id' => 'default',
        ]);

        // Criar permissões
        $permissions = [
            // Módulo Empresa
            ['nome' => 'Visualizar Empresa', 'chave' => 'empresa.visualizar', 'modulo' => 'Empresa'],
            ['nome' => 'Editar Empresa', 'chave' => 'empresa.editar', 'modulo' => 'Empresa'],
            
            // Módulo Usuários
            ['nome' => 'Visualizar Usuários', 'chave' => 'usuarios.visualizar', 'modulo' => 'Usuários'],
            ['nome' => 'Criar Usuários', 'chave' => 'usuarios.criar', 'modulo' => 'Usuários'],
            ['nome' => 'Editar Usuários', 'chave' => 'usuarios.editar', 'modulo' => 'Usuários'],
            ['nome' => 'Excluir Usuários', 'chave' => 'usuarios.excluir', 'modulo' => 'Usuários'],
            ['nome' => 'Gerenciar Usuários', 'chave' => 'usuarios.gerenciar', 'modulo' => 'Usuários'],
            
            // Módulo Configurações
            ['nome' => 'Gerenciar Configurações', 'chave' => 'configuracoes.gerenciar', 'modulo' => 'Configurações'],
            
            // Módulo Clientes
            ['nome' => 'Visualizar Clientes', 'chave' => 'clientes.visualizar', 'modulo' => 'Clientes'],
            ['nome' => 'Criar Clientes', 'chave' => 'clientes.criar', 'modulo' => 'Clientes'],
            ['nome' => 'Editar Clientes', 'chave' => 'clientes.editar', 'modulo' => 'Clientes'],
            ['nome' => 'Excluir Clientes', 'chave' => 'clientes.excluir', 'modulo' => 'Clientes'],
            
            // Módulo Fretes
            ['nome' => 'Visualizar Fretes', 'chave' => 'fretes.visualizar', 'modulo' => 'Fretes'],
            ['nome' => 'Criar Fretes', 'chave' => 'fretes.criar', 'modulo' => 'Fretes'],
            ['nome' => 'Editar Fretes', 'chave' => 'fretes.editar', 'modulo' => 'Fretes'],
            
            // Módulo Dashboard
            ['nome' => 'Visualizar Dashboard', 'chave' => 'dashboard.visualizar', 'modulo' => 'Dashboard'],
        ];
        
        foreach ($permissions as $perm) {
            Permission::create(array_merge($perm, ['tenant_id' => 'default']));
        }

        // Criar roles
        $superAdminRole = Role::create([
            'nome' => 'Super Admin',
            'descricao' => 'Administrador com acesso total',
            'is_super_admin' => true,
            'tenant_id' => 'default'
        ]);
        
        $adminRole = Role::create([
            'nome' => 'Administrador',
            'descricao' => 'Administrador do sistema',
            'is_super_admin' => false,
            'tenant_id' => 'default'
        ]);
        
        $gerenteRole = Role::create([
            'nome' => 'Gerente',
            'descricao' => 'Gerente de operações',
            'is_super_admin' => false,
            'tenant_id' => 'default'
        ]);
        
        $operadorRole = Role::create([
            'nome' => 'Operador',
            'descricao' => 'Operador do sistema',
            'is_super_admin' => false,
            'tenant_id' => 'default'
        ]);
        
        $visualizadorRole = Role::create([
            'nome' => 'Visualizador',
            'descricao' => 'Apenas visualização',
            'is_super_admin' => false,
            'tenant_id' => 'default'
        ]);

        // Atribuir permissões ao role Administrador
        $adminPermissions = Permission::whereIn('chave', [
            'empresa.visualizar', 'empresa.editar',
            'usuarios.visualizar', 'usuarios.criar', 'usuarios.editar', 'usuarios.gerenciar',
            'configuracoes.gerenciar',
            'clientes.visualizar', 'clientes.criar', 'clientes.editar', 'clientes.excluir',
            'fretes.visualizar', 'fretes.criar', 'fretes.editar',
            'dashboard.visualizar'
        ])->get();
        
        $adminRole->permissions()->attach($adminPermissions);

        // Atribuir permissões ao role Gerente
        $gerentePermissions = Permission::whereIn('chave', [
            'empresa.visualizar',
            'clientes.visualizar', 'clientes.criar', 'clientes.editar',
            'fretes.visualizar', 'fretes.criar', 'fretes.editar',
            'dashboard.visualizar'
        ])->get();
        
        $gerenteRole->permissions()->attach($gerentePermissions);

        // Criar usuários de exemplo
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@empresa.com',
            'password' => Hash::make('admin123'),
            'role_id' => $superAdminRole->id,
            'telefone' => '+55 11 99999-9999',
            'cargo' => 'Super Administrador',
            'departamento' => 'TI',
            'tenant_id' => 'default',
        ]);
        
        $admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@empresa.com',
            'password' => Hash::make('admin123'),
            'role_id' => $adminRole->id,
            'telefone' => '+55 11 98888-8888',
            'cargo' => 'Administrador',
            'departamento' => 'TI',
            'tenant_id' => 'default',
        ]);
        
        $gerente = User::create([
            'name' => 'João Silva',
            'email' => 'gerente@empresa.com',
            'password' => Hash::make('senha123'),
            'role_id' => $gerenteRole->id,
            'telefone' => '+55 11 97777-7777',
            'cargo' => 'Gerente de Logística',
            'departamento' => 'Operações',
            'tenant_id' => 'default',
        ]);

        $this->command->info('✅ Sistema de roles e permissões criado!');
        $this->command->info('👑 Super Admin: superadmin@empresa.com / senha: admin123');
        $this->command->info('👑 Admin: admin@empresa.com / senha: admin123');
        $this->command->info('👔 Gerente: gerente@empresa.com / senha: senha123');
    }
}