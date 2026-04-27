<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('caixa_justificativas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('viagem_id')->nullable();
            $table->string('motorista_nome');
            $table->unsignedBigInteger('motorista_id')->nullable();
            $table->decimal('valor_recebido', 15, 2)->default(0);
            $table->decimal('valor_comprovantes', 15, 2)->default(0);
            $table->decimal('diferenca', 15, 2)->default(0);
            $table->datetime('data_justificativa');
            $table->text('observacoes')->nullable();
            $table->string('tenant_id');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('viagem_id');
            $table->index('motorista_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('caixa_justificativas');
    }
};
