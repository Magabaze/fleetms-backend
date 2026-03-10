<?php
// database/migrations/2024_01_01_000002_create_postos_combustivel_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postos_combustivel', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('localizacao');
            $table->foreignId('fornecedor_id')->constrained('fornecedores_combustivel');
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index('fornecedor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postos_combustivel');
    }
};