<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Só adicionar colunas que NÃO existem
            if (!Schema::hasColumn('users', 'telefone')) {
                $table->string('telefone')->nullable()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'cargo')) {
                $table->string('cargo')->nullable()->after('telefone');
            }
            
            if (!Schema::hasColumn('users', 'departamento')) {
                $table->string('departamento')->nullable()->after('cargo');
            }
            
            if (!Schema::hasColumn('users', 'endereco')) {
                $table->text('endereco')->nullable()->after('departamento');
            }
            
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('endereco');
            }
            
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('bio');
            }
            
            if (!Schema::hasColumn('users', 'idioma')) {
                $table->string('idioma')->default('pt')->after('is_admin');
            }
            
            if (!Schema::hasColumn('users', 'fuso_horario')) {
                $table->string('fuso_horario')->default('America/Sao_Paulo')->after('idioma');
            }
            
            if (!Schema::hasColumn('users', 'tenant_id')) {
                $table->string('tenant_id')->default('default')->after('fuso_horario');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telefone',
                'cargo',
                'departamento',
                'endereco',
                'bio',
                'is_admin',
                'idioma',
                'fuso_horario',
                'tenant_id'
            ]);
        });
    }
};