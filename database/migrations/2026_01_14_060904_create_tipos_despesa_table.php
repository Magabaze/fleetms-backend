<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tipos_despesa', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('cor', 7)->default('#0aca7d'); // Formato HEX
            $table->boolean('requer_comprovante')->default(false);
            $table->string('criado_por');
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('nome');
            $table->unique(['tenant_id', 'nome']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tipos_despesa');
    }
};