<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ordens', function (Blueprint $table) {
            // Adicionar campos que podem estar faltando
            if (!Schema::hasColumn('ordens', 'empresa')) {
                $table->string('empresa')->nullable()->after('criado_por');
            }
            
            if (!Schema::hasColumn('ordens', 'volume_total')) {
                $table->decimal('volume_total', 10, 2)->nullable()->after('peso_total');
            }
            
            if (!Schema::hasColumn('ordens', 'valor_fatura')) {
                $table->decimal('valor_fatura', 15, 2)->nullable()->after('moeda_fatura');
            }
            
            if (!Schema::hasColumn('ordens', 'aprovado_em')) {
                $table->timestamp('aprovado_em')->nullable()->after('aprovado_por');
            }
        });
    }

    public function down()
    {
        Schema::table('ordens', function (Blueprint $table) {
            $table->dropColumn(['empresa', 'volume_total', 'valor_fatura', 'aprovado_em']);
        });
    }
};