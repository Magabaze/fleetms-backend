<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cnpj')->nullable();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('website')->nullable();
            $table->text('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 10)->nullable();
            $table->string('setor')->nullable();
            $table->string('funcionarios')->nullable();
            $table->text('descricao')->nullable();
            $table->string('fundacao', 4)->nullable();
            $table->text('missao')->nullable();
            $table->text('visao')->nullable();
            $table->string('moeda_padrao')->default('BRL');
            $table->string('fuso_horario')->default('America/Sao_Paulo');
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            $table->index('tenant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('empresas');
    }
};