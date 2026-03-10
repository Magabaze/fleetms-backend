<?php
// database/migrations/2024_01_01_000001_create_tanques_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tanques', function (Blueprint $table) {
            $table->id();
            
            // Identificação do tanque
            $table->string('nome');
            $table->string('codigo')->unique();
            
            // Combustível - AGORA É STRING LIVRE (NÃO É MAIS ENUM)
            $table->string('tipo_combustivel', 200)->default('');
            
            // Capacidade e nível
            $table->decimal('capacidade_total', 12, 2);
            $table->decimal('nivel_atual', 12, 2)->default(0);
            $table->enum('unidade_medida', ['litros', 'm3', 'galoes'])->default('litros');
            
            // Localização
            $table->string('localizacao')->nullable();
            
            // Status
            $table->enum('status', ['ativo', 'inativo', 'manutencao'])->default('ativo');
            
            // Alertas
            $table->integer('alerta_minimo')->default(20)->comment('Percentual para alerta');
            $table->integer('alerta_critico')->default(10)->comment('Percentual para crítico');
            
            // Observações
            $table->text('observacoes')->nullable();
            
            // Auditoria
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('status');
            $table->index('tipo_combustivel'); // 👈 ÍNDICE FUNCIONA COM STRING
            $table->index('codigo');
            $table->index('created_at');
            
            // Índice composto para buscas comuns
            $table->index(['tenant_id', 'status', 'tipo_combustivel']); // 👈 FUNCIONA COM STRING
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tanques');
    }
};