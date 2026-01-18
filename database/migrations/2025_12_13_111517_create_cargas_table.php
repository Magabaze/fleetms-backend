<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cargas', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_carga', ['General Cargo', 'Hazardous', 'Especial', 'Refrigerada', 'Líquida', 'Seca']);
            $table->string('descricao');
            $table->string('valor')->nullable();
            $table->string('peso')->nullable();
            $table->string('volume')->nullable();
            $table->string('dimensoes')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('tipo_carga');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cargas');
    }
};