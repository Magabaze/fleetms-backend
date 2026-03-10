<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('manutencoes_externas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->unsignedBigInteger('ordem_id')->nullable();
            $table->string('ordem_codigo')->nullable();
            $table->string('veiculo');
            $table->string('matricula');
            $table->unsignedBigInteger('fornecedor_id');
            $table->string('fornecedor_nome');
            $table->unsignedBigInteger('orcamento_id');
            $table->string('orcamento_codigo')->nullable();
            $table->text('descricao');
            $table->enum('status', [
                'pendente', 
                'em_progresso', 
                'concluida', 
                'cancelada'
            ])->default('pendente');
            $table->enum('prioridade', [
                'baixa', 
                'media', 
                'alta', 
                'urgente'
            ])->default('media');
            $table->date('data_saida');
            $table->date('data_prevista_retorno');
            $table->date('data_retorno')->nullable();
            $table->decimal('valor_orcado', 12, 2);
            $table->decimal('valor_final', 12, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->string('tenant_id');
            $table->string('criado_por');
            $table->timestamps();
            
            // Índices
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'fornecedor_id']);
            $table->index(['tenant_id', 'data_saida']);
            $table->index(['tenant_id', 'data_prevista_retorno']);
            $table->index(['tenant_id', 'codigo']);
            $table->index(['tenant_id', 'ordem_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('manutencoes_externas');
    }
};