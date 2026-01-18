<?php
// database/migrations/xxxx_create_clientes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome_empresa');
            $table->enum('tipo_cliente', ['Consignee', 'Shipper', 'Invoice Party']);
            $table->string('pessoa_contato')->default('');
            $table->string('telefone')->default('');
            $table->string('email')->default('');
            $table->text('endereco')->default('');
            $table->string('nuit_nif')->unique(); // ⚠️ ADICIONE UNIQUE
            $table->string('iva')->default('');
            $table->string('pais')->default('Moçambique');
            $table->text('observacoes')->nullable();
            $table->string('criado_por');
            
            // ⚠️ CORREÇÃO: Adicione default
            $table->string('tenant_id')->default('default');
            
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('tipo_cliente');
            $table->index('nuit_nif');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clientes');
    }
};