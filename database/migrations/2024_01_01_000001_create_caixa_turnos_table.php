<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('caixa_turnos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operador_id');
            $table->string('operador_nome');
            $table->decimal('saldo_abertura', 15, 2);
            $table->decimal('saldo_atual', 15, 2);
            $table->enum('status', ['aberto', 'fechado'])->default('aberto');
            $table->datetime('data_abertura');
            $table->datetime('data_fechamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('tenant_id');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('operador_id');
            $table->foreign('operador_id')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('caixa_turnos');
    }
};