<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_motoristas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('motoristas', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo');
            $table->string('numero_carta')->unique();
            $table->string('numero_passaporte')->nullable();
            $table->string('nacionalidade')->default('Moçambicana');
            $table->string('telefone');
            $table->string('telefone_alternativo')->nullable();
            $table->string('email')->nullable();
            $table->text('endereco')->nullable();
            $table->enum('tipo_licenca', ['A', 'B', 'C', 'D', 'E']);
            $table->date('validade_licenca');
            $table->date('validade_passaporte')->nullable();
            $table->enum('status', ['Ativo', 'Inativo', 'Férias', 'Licença']);
            $table->text('observacoes')->nullable();
            $table->string('foto_url')->nullable();
            $table->string('foto_carta_url')->nullable();
            $table->string('foto_passaporte_url')->nullable();
            $table->json('documentos')->nullable();
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('numero_carta');
            $table->index('status');
            $table->index('nacionalidade');
            $table->index('numero_passaporte');
        });
    }

    public function down()
    {
        Schema::dropIfExists('motoristas');
    }
};