<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('distancias', function (Blueprint $table) {
            $table->id();
            $table->string('origem');
            $table->string('destino');
            $table->string('distancia_total');
            $table->string('tempo_estimado');
            $table->text('pontos_parada')->nullable();
            $table->string('estrada_preferencial')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index(['origem', 'destino']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('distancias');
    }
};