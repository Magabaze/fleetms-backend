<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pecas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nome');
            $table->string('categoria');
            $table->integer('stock_atual')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->string('unidade')->default('un');
            $table->decimal('preco_unitario', 10, 2);
            $table->string('fornecedor');
            $table->date('ultima_entrada');
            $table->text('observacoes')->nullable();
            $table->string('tenant_id')->default('default');
            $table->string('criado_por');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('categoria');
            $table->index('fornecedor');
            $table->index('stock_atual');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pecas');
    }
};
