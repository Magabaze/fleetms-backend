<?php
// database/migrations/2025_12_16_create_despesas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('despesas')) {
            Schema::create('despesas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('distancia_id');
                $table->string('tipo', 100); // MUDAR DE ENUM PARA STRING
                $table->string('descricao')->nullable();
                $table->decimal('valor_estimado', 10, 2);
                $table->enum('moeda', ['MZN', 'USD', 'EUR', 'ZAR', 'BRL']);
                $table->boolean('requer_comprovante')->default(false);
                $table->string('criado_por');
                $table->string('tenant_id')->default('default');
                $table->timestamps();
                
                $table->index('tenant_id');
                $table->index('distancia_id');
                $table->index('tipo');
                
                $table->foreign('distancia_id')
                    ->references('id')
                    ->on('distancias')
                    ->onDelete('cascade');
            });
        } else {
            // Se a tabela já existe, alterar a coluna tipo
            Schema::table('despesas', function (Blueprint $table) {
                // Alterar a coluna tipo de ENUM para VARCHAR
                DB::statement("ALTER TABLE despesas MODIFY tipo VARCHAR(100)");
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('despesas');
    }
};