<?php
// database/migrations/2024_01_01_100000_create_ordens_faturacao_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ordens_faturacao', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->unsignedBigInteger('viagem_id')->nullable();
            $table->string('cliente');
            $table->string('motorista');
            $table->string('origem');
            $table->string('destino');
            $table->decimal('valor', 15, 2)->default(0);
            $table->date('data_viagem');
            $table->enum('status', ['pendente', 'processado', 'cancelado'])->default('pendente');
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            
            // Só adicionar foreign key se a tabela viagens já existir
            // $table->foreign('viagem_id')->references('id')->on('viagens')->onDelete('set null');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('codigo');
            $table->index('cliente');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ordens_faturacao');
    }
};