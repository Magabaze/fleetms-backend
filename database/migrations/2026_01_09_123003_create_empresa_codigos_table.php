<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('empresa_codigos', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // ID da empresa/tenant
            $table->string('codigo_prefixo', 10)->unique(); // Ex: TCM, ABC, XYZ
            $table->text('descricao')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('empresa_codigos');
    }
};