<?php
// database/migrations/2024_xx_xx_xxxxxx_add_cliente_id_and_fatura_referencia_to_notas_fiscais.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            // Adicionar cliente_id (se não existir)
            if (!Schema::hasColumn('notas_fiscais', 'cliente_id')) {
                $table->unsignedBigInteger('cliente_id')->nullable()->after('tipo');
                $table->foreign('cliente_id')
                      ->references('id')
                      ->on('clientes')
                      ->onDelete('set null');
            }
            
            // Adicionar cliente_nome para cache (se não existir)
            if (!Schema::hasColumn('notas_fiscais', 'cliente_nome')) {
                $table->string('cliente_nome')->nullable()->after('cliente_id');
            }
            
            // Adicionar fatura_referencia (se não existir)
            if (!Schema::hasColumn('notas_fiscais', 'fatura_referencia')) {
                $table->string('fatura_referencia')->nullable()->after('data');
            }
        });
    }

    public function down()
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn(['cliente_id', 'cliente_nome', 'fatura_referencia']);
        });
    }
};