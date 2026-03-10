<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos_preventivos', function (Blueprint $table) {
            $table->id();
            $table->string('veiculo');
            $table->string('matricula');
            $table->string('tipo');
            $table->integer('intervalo_km');
            $table->integer('intervalo_dias');
            $table->integer('ultimo_km');
            $table->integer('km_atual');
            $table->date('ultima_data');
            $table->date('proxima_data');
            $table->enum('status', ['ok', 'alerta', 'vencido'])->default('ok');
            $table->text('observacoes')->nullable();
            $table->string('tenant_id')->default('default');
            $table->string('criado_por');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('matricula');
            $table->index('proxima_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planos_preventivos');
    }
};
