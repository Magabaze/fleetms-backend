<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pagamentos_motoristas', function (Blueprint $table) {
            $table->id();
            $table->string('motorista');
            $table->decimal('total_bonus', 10, 2)->default(0);
            $table->decimal('total_descontos', 10, 2)->default(0);
            $table->decimal('valor_liquido', 10, 2);
            $table->date('data_pagamento');
            $table->enum('status', ['pendente', 'pago'])->default('pendente');
            $table->text('observacoes')->nullable();
            $table->string('tenant_id');
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }
    public function down() { Schema::dropIfExists('pagamentos_motoristas'); }
};