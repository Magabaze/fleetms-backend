<?php
// database/migrations/2024_01_01_100001_create_notas_fiscais_table.php

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
        // Verificar se a tabela já existe antes de criar
        if (!Schema::hasTable('notas_fiscais')) {
            Schema::create('notas_fiscais', function (Blueprint $table) {
                $table->id();
                $table->string('numero')->unique();
                $table->enum('tipo', ['debito', 'credito']);
                $table->unsignedBigInteger('ordem_id')->nullable();
                $table->string('cliente');
                $table->decimal('valor', 15, 2);
                $table->string('motivo');
                $table->date('data');
                $table->text('observacoes')->nullable();
                $table->string('criado_por');
                $table->string('tenant_id');
                $table->timestamps();
                
                // Foreign keys (assumindo que as tabelas já existem)
                $table->foreign('ordem_id')
                      ->references('id')
                      ->on('ordens_faturacao')
                      ->onDelete('set null');
                
                // Índices
                $table->index('tenant_id');
                $table->index('tipo');
                $table->index('numero');
                $table->index('cliente');
            });
        } else {
            // Se a tabela já existe, apenas logar que foi pulada
            \Illuminate\Support\Facades\Log::info('Tabela notas_fiscais já existe. Migration pulada.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Só dropar se a tabela existir
        if (Schema::hasTable('notas_fiscais')) {
            Schema::dropIfExists('notas_fiscais');
        }
    }
};