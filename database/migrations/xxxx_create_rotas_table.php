<?php
// database/migrations/xxxx_create_rotas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rotas', function (Blueprint $table) {
            $table->id();
            $table->string('rota');
            $table->string('origem');
            $table->string('destino');
            $table->decimal('distancia', 10, 2);
            $table->string('criado_por');
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('rota');
            $table->unique(['rota', 'tenant_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('rotas');
    }
};