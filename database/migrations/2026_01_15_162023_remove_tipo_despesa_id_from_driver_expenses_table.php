<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Remover a foreign key constraint
        Schema::table('driver_expenses', function (Blueprint $table) {
            // Primeiro, remover a foreign key
            $table->dropForeign(['tipo_despesa_id']);
            
            // Depois, remover a coluna
            $table->dropColumn('tipo_despesa_id');
        });
    }

    public function down()
    {
        Schema::table('driver_expenses', function (Blueprint $table) {
            // Recriar a coluna
            $table->foreignId('tipo_despesa_id')->nullable()->constrained('tipos_despesa')->onDelete('cascade');
        });
    }
};