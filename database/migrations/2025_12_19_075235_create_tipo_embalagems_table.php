<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tipo_embalagems', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->decimal('peso_padrao', 10, 2)->nullable();
            $table->string('unidade')->nullable();
            $table->json('dimensoes_padrao')->nullable();
            $table->decimal('volume_padrao', 10, 2)->nullable();
            $table->boolean('empilhavel')->default(false);
            $table->integer('max_empilhamento')->nullable();
            $table->text('instrucoes_manuseio')->nullable();
            $table->decimal('capacidade_maxima', 10, 2)->nullable();
            $table->string('criado_por')->nullable();
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tipo_embalagems');
    }
};
