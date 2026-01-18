<?php
// database/migrations/xxxx_fix_containers_table_add_missing_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // ========== CORRIGIR TABELA CONTAINERS ==========
        if (Schema::hasTable('containers')) {
            echo "🔧 Corrigindo tabela 'containers'...\n";
            
            // 1. Adicionar is_available se não existir
            if (!Schema::hasColumn('containers', 'is_available')) {
                Schema::table('containers', function (Blueprint $table) {
                    $table->boolean('is_available')->default(true)->after('status');
                });
                echo "✅ Coluna 'is_available' adicionada à tabela containers.\n";
            } else {
                echo "ℹ️ Coluna 'is_available' já existe.\n";
            }
            
            // 2. Adicionar viagem_id se não existir
            if (!Schema::hasColumn('containers', 'viagem_id')) {
                Schema::table('containers', function (Blueprint $table) {
                    $table->foreignId('viagem_id')->nullable()->after('is_available');
                    
                    // Verificar se a tabela viagens existe
                    if (Schema::hasTable('viagens')) {
                        $table->foreign('viagem_id')->references('id')->on('viagens')->onDelete('set null');
                        echo "✅ Coluna 'viagem_id' adicionada com foreign key.\n";
                    } else {
                        echo "⚠️ Tabela 'viagens' não existe. 'viagem_id' adicionado sem foreign key.\n";
                    }
                });
            } else {
                echo "ℹ️ Coluna 'viagem_id' já existe.\n";
            }
        } else {
            echo "❌ Tabela 'containers' não existe!\n";
        }
        
        echo "----------------------------------------\n";
    }

    public function down()
    {
        // ========== REMOVER CAMPOS DA TABELA CONTAINERS ==========
        if (Schema::hasTable('containers')) {
            // Remover viagem_id
            if (Schema::hasColumn('containers', 'viagem_id')) {
                Schema::table('containers', function (Blueprint $table) {
                    // Tentar remover foreign key
                    try {
                        $table->dropForeign(['viagem_id']);
                    } catch (\Exception $e) {
                        // Foreign key não existe, continuar
                    }
                    $table->dropColumn('viagem_id');
                });
            }
            
            // Remover is_available
            if (Schema::hasColumn('containers', 'is_available')) {
                Schema::table('containers', function (Blueprint $table) {
                    $table->dropColumn('is_available');
                });
            }
        }
    }
};