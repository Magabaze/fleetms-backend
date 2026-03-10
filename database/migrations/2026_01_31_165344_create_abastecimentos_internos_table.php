<?php
// database/migrations/[data]_create_abastecimentos_internos_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abastecimentos_internos', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            
            // Referências para outras tabelas
            $table->unsignedBigInteger('pedido_compra_id')->nullable();
            $table->unsignedBigInteger('posto_combustivel_id')->nullable();
            $table->unsignedBigInteger('veiculo_id')->nullable();
            $table->unsignedBigInteger('motorista_id')->nullable();
            $table->unsignedBigInteger('viagem_id')->nullable();
            $table->unsignedBigInteger('tanque_id')->nullable();
            
            // 🔥 Mudado de ENUM para STRING para aceitar valores livres
            $table->string('tipo_combustivel', 100)->default('diesel_s10');
            
            $table->decimal('quantidade', 10, 2);
            $table->string('unidade_medida')->default('litros');
            $table->decimal('preco_unitario', 10, 2)->default(0); // Interno = 0
            $table->decimal('valor_total', 12, 2)->default(0); // Interno = 0
            $table->enum('moeda', ['USD', 'EUR', 'MZN', 'ZAR'])->default('USD');
            $table->integer('odometro')->nullable();
            $table->date('data_abastecimento');
            $table->time('hora_abastecimento')->nullable();
            $table->string('responsavel');
            $table->text('observacoes')->nullable();
            
            $table->enum('status', [
                'pendente',
                'aprovado',
                'realizado',
                'cancelado'
            ])->default('pendente'); // Mudado para pendente como padrão
            
            $table->string('tenant_id');
            $table->timestamps();
            
            // Chaves estrangeiras
            $table->foreign('viagem_id')->references('id')->on('viagens')->onDelete('set null');
            $table->foreign('tanque_id')->references('id')->on('tanques')->onDelete('set null');
            $table->foreign('veiculo_id')->references('id')->on('camioes')->onDelete('set null');
            $table->foreign('motorista_id')->references('id')->on('motoristas')->onDelete('set null');
            
            // Índices
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'data_abastecimento']);
            $table->index('veiculo_id');
            $table->index('motorista_id');
            $table->index('viagem_id');
            $table->index('tanque_id');
            $table->index('pedido_compra_id');
            $table->index('posto_combustivel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abastecimentos_internos');
    }
};