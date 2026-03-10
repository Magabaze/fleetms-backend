<?php
// database/migrations/2024_01_01_000001_create_carteiras_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tabela de carteiras (saldo atual por motorista)
        Schema::create('carteiras', function (Blueprint $table) {
            $table->id();
            $table->string('motorista');
            $table->decimal('saldo', 10, 2)->default(0);
            $table->decimal('total_bonus', 10, 2)->default(0);
            $table->decimal('total_divida', 10, 2)->default(0);
            $table->timestamp('ultimo_movimento')->nullable();
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->unique(['motorista', 'tenant_id']);
            $table->index('tenant_id');
        });

        // Tabela de movimentos da carteira
        Schema::create('carteira_movimentos', function (Blueprint $table) {
            $table->id();
            $table->string('motorista');
            $table->morphs('origem'); // bonus_id, desconto_id, pagamento_id
            $table->enum('tipo', ['credito', 'debito']);
            $table->enum('origem_tipo', ['bonus', 'desconto', 'pagamento']);
            $table->string('descricao');
            $table->decimal('valor', 10, 2);
            $table->decimal('saldo_anterior', 10, 2);
            $table->decimal('saldo_posterior', 10, 2);
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index(['motorista', 'tenant_id']);
            $table->index('created_at');
        });

        // Tabela de histórico de pagamentos
        Schema::create('carteira_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->string('motorista');
            $table->decimal('valor', 10, 2);
            $table->decimal('desconto_aplicado', 10, 2)->default(0);
            $table->string('tipo_pagamento'); // Desconto Parcial (30%), Quitação Total, etc
            $table->text('observacoes')->nullable();
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index(['motorista', 'tenant_id']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('carteiras');
        Schema::dropIfExists('carteira_movimentos');
        Schema::dropIfExists('carteira_pagamentos');
    }
};