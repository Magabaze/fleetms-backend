<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('camioes', function (Blueprint $table) {
            $table->id();
            $table->string('matricula')->unique();
            $table->string('marca');
            $table->string('modelo');
            $table->integer('ano_fabricacao');
            $table->string('capacidade_carga');
            $table->enum('tipo_combustivel', ['Diesel', 'Gasolina', 'Elétrico', 'Híbrido']);
            $table->string('consumo_medio');
            $table->integer('numero_eixos');
            $table->string('tara');
            $table->string('cmr');
            $table->date('seguro_validade');
            $table->date('inspecao_validade');
            $table->enum('estado', ['Operacional', 'Manutenção', 'Avariado', 'Fora de Serviço']);
            $table->string('localizacao');
            $table->text('observacoes')->nullable();
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('matricula');
            $table->index('estado');
        });
    }

    public function down()
    {
        Schema::dropIfExists('camioes');
    }
};