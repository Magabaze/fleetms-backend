<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('regras_bonus', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            // Campos Complexos
            $table->string('transit_type')->nullable();
            $table->string('load_status')->nullable();
            $table->string('cargo_nature')->nullable();
            // Financeiro
            $table->string('calculation_base')->default('fixed'); // fixed, per_100km
            $table->decimal('valor_bonus', 10, 2);
            // Controle
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->string('criado_por');
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            $table->index('tenant_id');
        });
    }
    public function down() { Schema::dropIfExists('regras_bonus'); }
};