<?php
// database/migrations/2024_xx_xx_xxxxxx_fix_notas_fiscais_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            // Remover campo 'cliente' antigo se existir
            if (Schema::hasColumn('notas_fiscais', 'cliente')) {
                $table->dropColumn('cliente');
            }
            
            // Garantir que cliente_nome existe
            if (!Schema::hasColumn('notas_fiscais', 'cliente_nome')) {
                $table->string('cliente_nome')->nullable()->after('cliente_id');
            }
            
            // Garantir que cliente_id existe
            if (!Schema::hasColumn('notas_fiscais', 'cliente_id')) {
                $table->unsignedBigInteger('cliente_id')->nullable()->after('tipo');
                $table->foreign('cliente_id')
                      ->references('id')
                      ->on('clientes')
                      ->onDelete('set null');
            }
            
            // Garantir que fatura_referencia existe
            if (!Schema::hasColumn('notas_fiscais', 'fatura_referencia')) {
                $table->string('fatura_referencia')->nullable()->after('data');
            }
        });
    }

    public function down()
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            // Reverter as alterações se necessário
            if (!Schema::hasColumn('notas_fiscais', 'cliente')) {
                $table->string('cliente')->nullable();
            }
        });
    }
};