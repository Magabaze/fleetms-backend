<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('trelas', function (Blueprint $table) {
            $table->id();
            $table->string('matricula')->unique();
            $table->string('marca');
            $table->string('modelo');
            $table->integer('ano_fabricacao');
            $table->enum('tipo_trela', ['Reboque', 'Semi-reboque', 'Cisterna', 'Frigorífico', 'Plataforma']);
            $table->string('capacidade_carga');
            $table->integer('numero_eixos');
            $table->string('tara');
            $table->string('cmr');
            $table->date('seguro_validade');
            $table->date('inspecao_validade');
            $table->enum('estado', ['Operacional', 'Manutenção', 'Avariado', 'Fora de Serviço']);
            $table->string('camiao_associado')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('matricula');
            $table->index('camiao_associado');
        });
    }

    public function down()
    {
        Schema::dropIfExists('trelas');
    }
};