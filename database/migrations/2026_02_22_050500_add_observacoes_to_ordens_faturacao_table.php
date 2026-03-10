<?php
// database/migrations/xxxx_xx_xx_add_observacoes_to_ordens_faturacao_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ordens_faturacao', function (Blueprint $table) {
            if (!Schema::hasColumn('ordens_faturacao', 'observacoes')) {
                $table->text('observacoes')->nullable()->after('status');
            }
        });
    }

    public function down()
    {
        Schema::table('ordens_faturacao', function (Blueprint $table) {
            $table->dropColumn('observacoes');
        });
    }
};