<?php
// database/migrations/xxxx_create_agentes_table.php - CORRIGIDA

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('agentes', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo');
            $table->string('local_atuacao')->nullable();
            $table->string('fronteira_associada')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('taxa_servico', 10, 2)->nullable();
            $table->string('moeda')->default('USD');
            $table->json('documentos')->nullable();
            $table->text('observacoes')->nullable();
            $table->enum('status', ['ativo', 'inativo', 'pendente'])->default('ativo');
            $table->string('criado_por');
            $table->string('tenant_id')->default('default');
            
            $table->timestamps();
            
            $table->index('tenant_id');
            $table->index('status');
            $table->index('email');
            $table->index('fronteira_associada');
        });
    }

    public function down()
    {
        Schema::dropIfExists('agentes');
    }
};