<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_trabalho', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('veiculo');
            $table->string('matricula');
            $table->enum('tipo', ['preventiva', 'corretiva', 'inspecao']);
            $table->text('descricao');
            $table->string('tecnico');
            $table->enum('status', ['pendente', 'em_progresso', 'concluida', 'cancelada']);
            $table->enum('prioridade', ['baixa', 'media', 'alta', 'urgente']);
            $table->date('data_criacao');
            $table->date('data_prevista');
            $table->text('observacoes')->nullable();
            $table->string('tenant_id')->default('default');
            $table->string('criado_por');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('prioridade');
            $table->index('matricula');
            $table->index('data_prevista');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordens_trabalho');
    }
};