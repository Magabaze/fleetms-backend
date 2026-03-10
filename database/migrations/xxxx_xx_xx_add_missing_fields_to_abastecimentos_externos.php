<?php
// database/migrations/xxxx_xx_xx_add_missing_fields_to_abastecimentos_externos.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abastecimentos_externos', function (Blueprint $table) {
            // Adicionar TODOS os campos que o frontend está enviando
            
            if (!Schema::hasColumn('abastecimentos_externos', 'viagem_id')) {
                $table->unsignedBigInteger('viagem_id')->nullable()->after('numero');
                $table->foreign('viagem_id')->references('id')->on('viagens')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('abastecimentos_externos', 'numero_viagem')) {
                $table->string('numero_viagem', 50)->nullable()->after('viagem_id');
            }
            
            if (!Schema::hasColumn('abastecimentos_externos', 'distancia_percorrida')) {
                $table->decimal('distancia_percorrida', 10, 2)->nullable()->after('numero_viagem');
            }
            
            if (!Schema::hasColumn('abastecimentos_externos', 'veiculo_matricula')) {
                $table->string('veiculo_matricula', 50)->nullable()->after('veiculo_id');
            }
            
            if (!Schema::hasColumn('abastecimentos_externos', 'motorista_nome')) {
                $table->string('motorista_nome', 255)->nullable()->after('motorista_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('abastecimentos_externos', function (Blueprint $table) {
            $table->dropForeign(['viagem_id']);
            $table->dropColumn([
                'viagem_id',
                'numero_viagem',
                'distancia_percorrida',
                'veiculo_matricula',
                'motorista_nome'
            ]);
        });
    }
};