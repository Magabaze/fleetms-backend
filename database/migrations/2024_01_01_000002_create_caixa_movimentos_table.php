<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('caixa_movimentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('turno_id');
            $table->enum('tipo', ['entrada', 'saida']);
            $table->decimal('valor', 15, 2);
            $table->string('descricao');
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('referencia_tipo')->nullable();
            $table->datetime('data_movimento');
            $table->decimal('saldo_anterior', 15, 2);
            $table->decimal('saldo_posterior', 15, 2);
            $table->string('tenant_id');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('turno_id');
            $table->index('tipo');
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->foreign('turno_id')->references('id')->on('caixa_turnos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('caixa_movimentos');
    }
};