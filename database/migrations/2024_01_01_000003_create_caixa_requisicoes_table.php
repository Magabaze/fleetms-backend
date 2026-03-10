<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('caixa_requisicoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('viagem_id')->nullable();
            $table->string('motorista_nome');
            $table->unsignedBigInteger('motorista_id')->nullable();
            $table->decimal('valor', 15, 2);
            $table->string('descricao');
            $table->datetime('data_requisicao');
            $table->enum('status', ['pendente', 'aprovado', 'pago', 'rejeitado'])->default('pendente');
            $table->string('aprovado_por')->nullable();
            $table->datetime('data_aprovacao')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('tenant_id');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('viagem_id');
            $table->index('motorista_id');
            $table->foreign('viagem_id')->references('id')->on('viagens');
            $table->foreign('motorista_id')->references('id')->on('motoristas');
        });
    }

    public function down()
    {
        Schema::dropIfExists('caixa_requisicoes');
    }
};