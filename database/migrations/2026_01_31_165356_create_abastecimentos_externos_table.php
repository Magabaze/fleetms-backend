<?php
// database/migrations/[data]_create_abastecimentos_externos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abastecimentos_externos', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            
            // Referências para outras tabelas - use nullable para evitar erros
            $table->unsignedBigInteger('posto_id')->nullable();
            $table->unsignedBigInteger('veiculo_id')->nullable();
            $table->unsignedBigInteger('motorista_id')->nullable();
            
            $table->enum('tipo_combustivel', [
                'diesel_s10',
                'diesel_s500',
                'diesel_s50',
                'gasolina_95', 
                'gasolina_98',
            ])->default('diesel_s10');
            
            $table->decimal('quantidade', 10, 2);
            $table->string('unidade_medida')->default('litros');
            $table->decimal('preco_unitario', 10, 2);
            $table->decimal('valor_total', 12, 2);
            $table->enum('moeda', ['USD', 'EUR', 'MZN', 'ZAR'])->default('USD');
            $table->integer('odometro')->nullable();
            $table->date('data_abastecimento');
            $table->string('nota_fiscal')->nullable();
            $table->string('foto_nota_fiscal')->nullable();
            
            $table->enum('status', [
                'pendente',
                'aprovado',
                'rejeitado',
                'pago',
                'cancelado'
            ])->default('pendente');
            
            $table->text('observacoes')->nullable();
            $table->string('responsavel_registro');
            $table->string('aprovado_por')->nullable();
            $table->timestamp('data_aprovacao')->nullable();
            $table->string('pago_por')->nullable();
            $table->timestamp('data_pagamento')->nullable();
            
            $table->string('tenant_id');
            $table->timestamps();
            
            // Índices
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'data_abastecimento']);
            $table->index('veiculo_id');
            $table->index('motorista_id');
            $table->index('posto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abastecimentos_externos');
    }
};