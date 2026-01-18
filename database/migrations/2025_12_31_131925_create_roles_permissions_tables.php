<?php
// database/migrations/xxxx_create_roles_permissions_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tabela de roles (cargos)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('descricao')->nullable();
            $table->boolean('is_super_admin')->default(false);
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            $table->index('tenant_id');
        });

        // Tabela de permissões
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('chave')->unique();
            $table->string('modulo');
            $table->string('descricao')->nullable();
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            $table->index(['tenant_id', 'modulo']);
        });

        // Tabela pivô role_permission
        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['role_id', 'permission_id']);
        });

        // Atualizar tabela users - APENAS adicionar a foreign key
        Schema::table('users', function (Blueprint $table) {
            // A coluna role_id JÁ EXISTE, então apenas adicionamos a foreign key
            // Primeiro, garanta que a coluna existe (já deve existir)
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->after('is_admin');
            }
            
            // Adicionar a foreign key
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
            
            // Remover is_admin se existir
            if (Schema::hasColumn('users', 'is_admin')) {
                $table->dropColumn('is_admin');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Remover a foreign key
            $table->dropForeign(['role_id']);
            
            // NÃO remover a coluna role_id (ela faz parte da estrutura da tabela users)
            // Apenas adicionar is_admin novamente se foi removido
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false);
            }
        });
        
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};