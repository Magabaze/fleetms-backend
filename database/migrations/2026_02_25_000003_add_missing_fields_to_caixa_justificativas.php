<?php
// database/migrations/2026_02_25_000003_add_missing_fields_to_caixa_justificativas.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('caixa_justificativas', function (Blueprint $table) {
            // Adicionar campos que estão faltando
            if (!Schema::hasColumn('caixa_justificativas', 'criado_por')) {
                $table->string('criado_por')->nullable()->after('observacoes');
            }
            
            if (!Schema::hasColumn('caixa_justificativas', 'turno_id')) {
                $table->unsignedBigInteger('turno_id')->nullable()->after('id');
                $table->foreign('turno_id')->references('id')->on('caixa_turnos');
            }
            
            if (!Schema::hasColumn('caixa_justificativas', 'despesas_ids')) {
                $table->json('despesas_ids')->nullable()->after('motorista_id');
            }
            
            if (!Schema::hasColumn('caixa_justificativas', 'tipo')) {
                $table->string('tipo')->default('justificativa')->after('despesas_ids');
            }
            
            if (!Schema::hasColumn('caixa_justificativas', 'moeda')) {
                $table->string('moeda', 3)->default('MZN')->after('tipo');
            }
            
            if (!Schema::hasColumn('caixa_justificativas', 'valor_despesas')) {
                $table->decimal('valor_despesas', 15, 2)->default(0)->after('moeda');
            }
            
            if (!Schema::hasColumn('caixa_justificativas', 'valor_devolvido')) {
                $table->decimal('valor_devolvido', 15, 2)->default(0)->after('valor_comprovantes');
            }
        });
    }

    public function down()
    {
        Schema::table('caixa_justificativas', function (Blueprint $table) {
            $table->dropForeign(['turno_id']);
            $table->dropColumn([
                'criado_por',
                'turno_id',
                'despesas_ids',
                'tipo',
                'moeda',
                'valor_despesas',
                'valor_devolvido'
            ]);
        });
    }
};