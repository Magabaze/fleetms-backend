<?php
// database/migrations/2026_01_01_000001_create_fornecedores_combustivel_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fornecedores_combustivel', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('nif')->unique();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->text('endereco')->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores_combustivel');
    }
};