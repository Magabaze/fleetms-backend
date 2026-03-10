<?php
// database/migrations/xxxx_xx_xx_add_tanque_id_to_abastecimentos_internos_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('abastecimentos_internos', function (Blueprint $table) {
            if (!Schema::hasColumn('abastecimentos_internos', 'tanque_id')) {
                $table->unsignedBigInteger('tanque_id')->nullable()->after('observacoes');
                $table->foreign('tanque_id')->references('id')->on('tanques')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('abastecimentos_internos', function (Blueprint $table) {
            $table->dropForeign(['tanque_id']);
            $table->dropColumn('tanque_id');
        });
    }
};