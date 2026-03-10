<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspecoes', function (Blueprint $table) {
            $table->id();
            $table->string('veiculo');
            $table->string('matricula');
            $table->string('tipo');
            $table->string('entidade');
            $table->date('data_ultima');
            $table->date('data_validade');
            $table->enum('status', ['valido', 'alerta', 'vencido', 'agendado'])->default('valido');
            $table->enum('resultado', ['aprovado', 'reprovado', 'pendente']);
            $table->text('observacoes')->nullable();
            $table->string('tenant_id')->default('default');
            $table->string('criado_por');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('matricula');
            $table->index('data_validade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspecoes');
    }
};