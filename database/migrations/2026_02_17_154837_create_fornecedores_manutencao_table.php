<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores_manutencao', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('tipo');
            $table->json('especialidade');
            $table->string('contacto');
            $table->string('email')->nullable();
            $table->string('morada')->nullable();
            $table->decimal('avaliacao', 2, 1)->default(5.0);
            $table->integer('total_servicos')->default(0);
            $table->date('ultimo_servico')->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->string('tempo_medio_resposta')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('tenant_id')->default('default');
            $table->string('criado_por');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('tipo');
            $table->index('avaliacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores_manutencao');
    }
};