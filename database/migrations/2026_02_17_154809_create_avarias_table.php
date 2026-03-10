<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avarias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('veiculo');
            $table->string('matricula');
            $table->text('descricao');
            $table->text('causa_raiz')->nullable();
            $table->string('reportado_por');
            $table->string('tecnico');
            $table->enum('status', ['aberta', 'em_diagnostico', 'em_reparacao', 'resolvida']);
            $table->enum('prioridade', ['normal', 'alta', 'urgente']);
            $table->date('data_reporte');
            $table->integer('horas_imobilizado')->default(0);
            $table->string('local_avaria')->nullable();
            $table->string('tenant_id')->default('default');
            $table->string('criado_por');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('prioridade');
            $table->index('matricula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avarias');
    }
};