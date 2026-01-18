<?php
// database/migrations/xxxx_add_missing_fields_to_break_bulk_items.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Verificar se a tabela break_bulk_items existe
        if (Schema::hasTable('break_bulk_items')) {
            echo "ℹ️ Tabela 'break_bulk_items' encontrada.\n";
            
            // Adicionar peso_utilizado se não existir
            if (!Schema::hasColumn('break_bulk_items', 'peso_utilizado')) {
                Schema::table('break_bulk_items', function (Blueprint $table) {
                    $table->decimal('peso_utilizado', 10, 2)->default(0)->after('peso_total');
                });
                echo "✅ Coluna 'peso_utilizado' adicionada.\n";
            } else {
                echo "ℹ️ Coluna 'peso_utilizado' já existe.\n";
            }
            
            // Adicionar quantidade_utilizada se não existir
            if (!Schema::hasColumn('break_bulk_items', 'quantidade_utilizada')) {
                Schema::table('break_bulk_items', function (Blueprint $table) {
                    $table->integer('quantidade_utilizada')->default(0)->after('quantidade');
                });
                echo "✅ Coluna 'quantidade_utilizada' adicionada.\n";
            } else {
                echo "ℹ️ Coluna 'quantidade_utilizada' já existe.\n";
            }
            
            // Adicionar viagem_id se não existir
            if (!Schema::hasColumn('break_bulk_items', 'viagem_id')) {
                Schema::table('break_bulk_items', function (Blueprint $table) {
                    $table->foreignId('viagem_id')->nullable()->after('status');
                    
                    // Verificar se a tabela viagens existe antes de criar a constraint
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
            echo "❌ Tabela 'break_bulk_items' não existe!\n";
        }
    }

    public function down()
    {
        if (Schema::hasTable('break_bulk_items')) {
            // Remover viagem_id
            if (Schema::hasColumn('break_bulk_items', 'viagem_id')) {
                Schema::table('break_bulk_items', function (Blueprint $table) {
                    // Remover foreign key primeiro
                    $table->dropForeign(['viagem_id']);
                    $table->dropColumn('viagem_id');
                });
            }
            
            // Remover quantidade_utilizada
            if (Schema::hasColumn('break_bulk_items', 'quantidade_utilizada')) {
                Schema::table('break_bulk_items', function (Blueprint $table) {
                    $table->dropColumn('quantidade_utilizada');
                });
            }
            
            // Remover peso_utilizado
            if (Schema::hasColumn('break_bulk_items', 'peso_utilizado')) {
                Schema::table('break_bulk_items', function (Blueprint $table) {
                    $table->dropColumn('peso_utilizado');
                });
            }
        }
    }
};