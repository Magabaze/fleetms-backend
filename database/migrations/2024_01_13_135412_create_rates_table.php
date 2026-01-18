<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rates', function (Blueprint $table) {
            $table->id();
            $table->integer('cliente_id');
            $table->string('cliente_nome');
            $table->integer('distancia_id');
            $table->string('distancia_rota');
            $table->enum('moeda', ['USD', 'EUR', 'BRL', 'MZN', 'ZAR']);
            $table->date('validade');
            $table->text('observacoes')->nullable();
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->string('criado_por');
            $table->string('aprovado_por')->nullable();
            $table->json('itens_carga'); // Array de itens de carga
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('cliente_id');
            $table->index('distancia_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rates');
    }
};