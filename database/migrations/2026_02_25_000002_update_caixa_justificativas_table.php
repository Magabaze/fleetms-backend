<?php
// database/migrations/2026_02_25_000002_update_caixa_justificativas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('caixa_justificativas', function (Blueprint $table) {
            // Adicionar campos que estão faltando
            $table->unsignedBigInteger('turno_id')->nullable()->after('id');
            $table->json('despesas_ids')->nullable()->after('motorista_id');
            $table->string('tipo')->default('justificativa')->after('despesas_ids');
            $table->string('moeda', 3)->default('MZN')->after('tipo');
            $table->decimal('valor_despesas', 15, 2)->default(0)->after('moeda');
            $table->decimal('valor_devolvido', 15, 2)->default(0)->after('valor_despesas');
            
            // Tornar data_justificativa nullable com default atual
            $table->datetime('data_justificativa')->nullable()->default(now())->change();
        });
    }

    public function down()
    {
        Schema::table('caixa_justificativas', function (Blueprint $table) {
            $table->dropColumn(['turno_id', 'despesas_ids', 'tipo', 'moeda', 'valor_despesas', 'valor_devolvido']);
            $table->datetime('data_justificativa')->nullable(false)->change();
        });
    }
};