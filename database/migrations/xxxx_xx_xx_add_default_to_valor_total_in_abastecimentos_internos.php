<?php
// database/migrations/xxxx_xx_xx_add_default_to_valor_total_in_abastecimentos_internos.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abastecimentos_internos', function (Blueprint $table) {
            // Apenas definir valor padrão 0 para o campo existente
            $table->decimal('valor_total', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('abastecimentos_internos', function (Blueprint $table) {
            $table->decimal('valor_total', 12, 2)->default(null)->change();
        });
    }
};