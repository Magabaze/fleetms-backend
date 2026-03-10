<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('socorros', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->unsignedBigInteger('ordem_id')->nullable();
            $table->string('ordem_codigo')->nullable();
            $table->string('veiculo');
            $table->string('matricula');
            $table->string('motorista');
            $table->enum('tipo', [
                'avaria_mecanica', 
                'acidente', 
                'pneu', 
                'combustivel', 
                'eletrica', 
                'outro'
            ])->default('avaria_mecanica');
            $table->text('descricao');
            $table->enum('status', [
                'aberto', 
                'em_atendimento', 
                'concluido', 
                'cancelado'
            ])->default('aberto');
            $table->enum('prioridade', [
                'normal', 
                'alta', 
                'urgente'
            ])->default('normal');
            $table->dateTime('data_ocorrencia');
            $table->string('local');
            $table->integer('km');
            $table->string('tecnico_enviado')->nullable();
            $table->integer('tempo_resposta')->default(0)->comment('em minutos');
            $table->integer('tempo_reparo')->default(0)->comment('em minutos');
            $table->decimal('custo', 12, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->string('tenant_id');
            $table->string('criado_por');
            $table->timestamps();
            
            // Índices
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'data_ocorrencia']);
            $table->index(['tenant_id', 'veiculo']);
            $table->index(['tenant_id', 'motorista']);
            $table->index(['tenant_id', 'codigo']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('socorros');
    }
};