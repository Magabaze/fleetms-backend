<?php
// database/migrations/2024_01_01_000003_create_pedidos_compra_combustivel_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_compra_combustivel', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            
            // ✅ FORNECEDOR - TEXTO LIVRE (NÃO É MAIS FOREIGN)
            $table->string('fornecedor', 255);
            
            // ✅ TIPO COMBUSTÍVEL - AGORA É STRING LIVRE (NÃO É MAIS ENUM)
            $table->string('tipo_combustivel', 100);
            
            // Quantidade e valores
            $table->decimal('quantidade', 12, 2);
            $table->enum('unidade_medida', ['litros', 'galoes', 'metros_cubicos'])->default('litros');
            $table->decimal('preco_unitario', 12, 2);
            $table->decimal('valor_total', 14, 2);
            
            // Moeda
            $table->enum('moeda', ['USD', 'EUR', 'MZN', 'ZAR', 'BRL'])->default('USD');
            
            // Datas
            $table->date('data_pedido');
            $table->date('data_entrega_prevista');
            $table->date('data_entrega_real')->nullable();
            
            // Status
            $table->enum('status', [
                'pendente', 
                'aprovado', 
                'rejeitado', 
                'entregue', 
                'cancelado'
            ])->default('pendente');
            
            // Observações e auditoria
            $table->text('observacoes')->nullable();
            $table->string('criado_por', 100);
            $table->string('aprovado_por', 100)->nullable();
            $table->timestamp('data_aprovacao')->nullable();
            $table->string('tenant_id', 50);
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('status');
            $table->index('data_pedido');
            $table->index('fornecedor');
            $table->index('tipo_combustivel'); // ← NOVO ÍNDICE
            $table->index('numero');
            
            // Índices compostos
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'data_pedido']);
            $table->index(['tenant_id', 'fornecedor']);
            $table->index(['tenant_id', 'tipo_combustivel']); // ← NOVO ÍNDICE
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_compra_combustivel');
    }
};