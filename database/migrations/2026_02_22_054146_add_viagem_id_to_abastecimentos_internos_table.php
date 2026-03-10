<?php
// database/migrations/xxxx_xx_xx_add_viagem_id_to_abastecimentos_internos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('abastecimentos_internos', function (Blueprint $table) {
            if (!Schema::hasColumn('abastecimentos_internos', 'viagem_id')) {
                $table->unsignedBigInteger('viagem_id')->nullable()->after('numero');
                $table->foreign('viagem_id')->references('id')->on('viagens')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('abastecimentos_internos', function (Blueprint $table) {
            $table->dropForeign(['viagem_id']);
            $table->dropColumn('viagem_id');
        });
    }
};