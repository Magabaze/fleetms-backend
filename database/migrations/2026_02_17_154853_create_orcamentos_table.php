<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orcamentos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('ordem_id');
            $table->string('veiculo');
            $table->string('matricula');
            $table->string('fornecedor');
            $table->text('descricao');
            $table->decimal('valor_orcado', 10, 2);
            $table->decimal('valor_final', 10, 2)->nullable();
            $table->enum('status', ['pendente', 'aprovado', 'rejeitado', 'concluido']);
            $table->date('data_emissao');
            $table->date('data_resposta')->nullable();
            $table->date('data_entrada')->nullable();
            $table->date('data_saida')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('tenant_id')->default('default');
            $table->string('criado_por');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('fornecedor');
            $table->index('ordem_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orcamentos');
    }
};