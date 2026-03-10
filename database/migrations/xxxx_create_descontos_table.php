<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('descontos', function (Blueprint $table) {
            $table->id();
            $table->string('motorista');
            $table->string('tipo');
            $table->text('descricao')->nullable();
            $table->decimal('valor', 10, 2);
            $table->date('data_desconto');
            $table->enum('status', ['pendente', 'aplicado'])->default('pendente');
            $table->string('criado_por');
            $table->string('tenant_id');
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }
    public function down() { Schema::dropIfExists('descontos'); }
};