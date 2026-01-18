<?php
// database/migrations/2024_01_01_000001_create_ordens_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tabela principal de ordens
        Schema::create('ordens', function (Blueprint $table) {
            $table->id();
            $table->string('order_numero')->unique();
            $table->enum('tipo_transito', [
                'Import', 
                'Export', 
                'Local Import', 
                'Local Export', 
                'Shutting', 
                'Socorro', 
                'Interno'
            ]);
            
            // Relacionamentos
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('consignee_id')->nullable()->constrained('clientes');
            $table->foreignId('expedidor_id')->nullable()->constrained('clientes');
            $table->foreignId('taxa_cliente_id')->nullable()->constrained('rates');
            
            // Rota
            $table->string('origem');
            $table->string('destino');
            
            // Detalhes da carga
            $table->string('commodity');
            $table->enum('tipo_carga', [
                'Container', 
                'Break Bulk', 
                'Bulk Loose', 
                'General Cargo'
            ]);
            
            // Status
            $table->enum('status', [
                'pending', 
                'approved', 
                'completed', 
                'cancelled'
            ])->default('pending');
            
            // Datas
            $table->date('created_date');
            $table->date('previsao_carregamento')->nullable();
            $table->date('previsao_entrega')->nullable();
            
            // Documentação
            $table->string('numero_bl')->nullable();
            $table->string('shipping_line')->nullable();
            
            // Fronteira
            $table->string('fronteira')->nullable();
            $table->string('agente_fronteira')->nullable();
            
            // Financeiro
            $table->enum('moeda_fatura', ['USD', 'EUR', 'MZN', 'ZAR'])->default('USD');
            $table->decimal('valor_fatura', 15, 2)->nullable();
            
            // Pesos e volumes
            $table->decimal('peso_total', 10, 2)->nullable()->comment('Em toneladas');
            $table->decimal('volume_total', 10, 2)->nullable()->comment('Em m³');
            
            // Dimensões
            $table->string('dimensoes')->nullable();
            
            // Transporte
            $table->string('tipo_transporte')->nullable()->comment('Rodoviário, Ferroviário, Marítimo');
            $table->string('numero_transporte')->nullable();
            $table->string('motorista')->nullable();
            $table->string('veiculo')->nullable();
            
            // Outros
            $table->text('observacoes')->nullable();
            $table->text('instrucoes_especiais')->nullable();
            $table->boolean('empty_container')->default(false);
            $table->boolean('perigosa')->default(false);
            $table->string('classe_perigosa')->nullable();
            
            // Audit
            $table->string('criado_por');
            $table->string('aprovado_por')->nullable();
            $table->timestamp('aprovado_em')->nullable();
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('order_numero');
            $table->index('status');
            $table->index('tipo_carga');
            $table->index('cliente_id');
            $table->index(['origem', 'destino']);
            $table->index('created_date');
            $table->index('tipo_transito');
        });

        // Tabela de containers (para ordens do tipo Container)
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Informações do container
            $table->string('tipo_recipiente')->comment('20" Dry, 40" Dry, 20" Reefer, etc');
            $table->enum('tipo_carga', ['FCL', 'LCL', 'CO-LOAD'])->default('FCL');
            $table->string('unidade')->default('EM TONELADAS MÉTRICAS');
            
            // Pesos
            $table->decimal('peso_liquido', 10, 2)->comment('Peso da carga');
            $table->decimal('peso_container', 10, 2)->nullable()->comment('Peso do container vazio');
            $table->decimal('peso_total', 10, 2)->comment('Peso total (liquido + container)');
            
            // Identificação
            $table->string('numero_container')->unique();
            $table->string('selo')->nullable();
            $table->string('aterramento_ref')->nullable();
            
            // Documentação
            $table->date('data_validade_do')->nullable();
            $table->string('drop_off_details');
            $table->string('deposito_contentores');
            
            // Status do container
            $table->enum('status', [
                'pending', 
                'loaded', 
                'in_transit', 
                'delivered', 
                'returned'
            ])->default('pending');
            
            // Localização atual
            $table->string('localizacao_atual')->nullable();
            $table->timestamp('data_carregamento')->nullable();
            $table->timestamp('data_descarga')->nullable();
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('numero_container');
            $table->index('status');
            $table->index('tipo_recipiente');
        });

        // Tabela de itens Break Bulk
        Schema::create('break_bulk_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Tipo de embalagem
            $table->string('tipo_embalagem')->comment('Big Bag, Pallet, Saco, etc');
            $table->string('descricao_embalagem')->nullable();
            
            // Quantidades
            $table->integer('quantidade');
            $table->string('unidades_embalagem')->comment('camião, pallet, saco, etc');
            
            // Pesos
            $table->decimal('peso_por_unidade', 10, 3);
            $table->decimal('peso_total', 10, 2);
            
            // Dimensões
            $table->string('dimensoes')->nullable()->comment('Largura x Altura x Comprimento');
            $table->decimal('volume_por_unidade', 10, 3)->nullable();
            $table->decimal('volume_total', 10, 3)->nullable();
            
            // Detalhes específicos
            $table->boolean('empilhavel')->default(true);
            $table->string('classe_perigosa')->nullable();
            $table->string('numero_onu')->nullable();
            $table->string('instrucoes_manuseio')->nullable();
            
            // Status
            $table->enum('status', [
                'pending', 
                'packed', 
                'loaded', 
                'delivered'
            ])->default('pending');
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('tipo_embalagem');
            $table->index('status');
        });

        // Tabela de tipos de embalagem (para referência)
        Schema::create('tipos_embalagem', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('descricao')->nullable();
            $table->decimal('peso_padrao', 10, 3)->comment('Peso padrão em toneladas');
            $table->string('unidade');
            $table->json('dimensoes_padrao')->nullable()->comment('Dimensões padrão em JSON');
            $table->decimal('volume_padrao', 10, 3)->nullable()->comment('Volume padrão em m³');
            $table->boolean('empilhavel')->default(true);
            $table->integer('max_empilhamento')->default(1);
            $table->text('instrucoes_manuseio')->nullable();
            $table->decimal('capacidade_maxima', 10, 3)->nullable()->comment('Capacidade máxima em toneladas');
            
            // Audit
            $table->string('criado_por');
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('tenant_id');
            $table->index('nome');
        });

        // Tabela de eventos/status tracking das ordens
        Schema::create('ordem_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Evento
            $table->string('event_type')->comment('status_change, note, document, etc');
            $table->string('status_from')->nullable();
            $table->string('status_to')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable()->comment('Dados adicionais em JSON');
            
            // Localização (se aplicável)
            $table->string('localizacao')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            
            // Documentos relacionados
            $table->string('documento_tipo')->nullable()->comment('BL, Invoice, DO, etc');
            $table->string('documento_numero')->nullable();
            $table->string('documento_url')->nullable();
            
            // Responsável
            $table->string('responsavel');
            $table->string('responsavel_tipo')->nullable()->comment('system, user, driver, etc');
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('event_type');
            $table->index('created_at');
            $table->index(['ordem_id', 'event_type']);
        });

        // Tabela de documentos das ordens
        Schema::create('ordem_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Informações do documento
            $table->string('tipo_documento')->comment('BL, Invoice, Packing List, DO, Certificate, etc');
            $table->string('numero_documento')->nullable();
            $table->string('referencia')->nullable();
            
            // Arquivo
            $table->string('nome_arquivo');
            $table->string('caminho_arquivo');
            $table->string('mime_type')->nullable();
            $table->integer('tamanho')->nullable()->comment('Tamanho em bytes');
            
            // Validade
            $table->date('data_emissao')->nullable();
            $table->date('data_validade')->nullable();
            
            // Status
            $table->boolean('aprovado')->default(false);
            $table->string('aprovado_por')->nullable();
            $table->timestamp('aprovado_em')->nullable();
            
            // Detalhes
            $table->text('observacoes')->nullable();
            $table->json('metadata')->nullable();
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('tipo_documento');
            $table->index('numero_documento');
            $table->index('aprovado');
        });

        // Tabela de custos adicionais das ordens
        Schema::create('ordem_custos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Tipo de custo
            $table->string('tipo_custo')->comment('freight, handling, storage, customs, etc');
            $table->string('descricao');
            
            // Valores
            $table->decimal('valor', 15, 2);
            $table->string('moeda')->default('USD');
            $table->decimal('taxa_cambio', 10, 4)->nullable()->default(1);
            $table->decimal('valor_local', 15, 2)->nullable();
            
            // Taxas
            $table->decimal('taxa', 5, 2)->nullable()->default(0);
            $table->decimal('valor_taxa', 15, 2)->nullable();
            
            // Status
            $table->boolean('faturado')->default(false);
            $table->string('numero_fatura')->nullable();
            $table->date('data_fatura')->nullable();
            $table->date('data_vencimento')->nullable();
            
            // Pagamento
            $table->boolean('pago')->default(false);
            $table->date('data_pagamento')->nullable();
            $table->string('metodo_pagamento')->nullable();
            $table->string('comprovante_pagamento')->nullable();
            
            // Responsável
            $table->string('responsavel')->nullable();
            $table->text('observacoes')->nullable();
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('tipo_custo');
            $table->index('faturado');
            $table->index('pago');
        });

        // Tabela de notas/observações das ordens
        Schema::create('ordem_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Conteúdo
            $table->text('conteudo');
            $table->enum('tipo', ['note', 'warning', 'info', 'important'])->default('note');
            $table->boolean('privado')->default(false)->comment('Visível apenas para usuários internos');
            
            // Responsável
            $table->string('criado_por');
            $table->foreignId('criado_por_id')->nullable()->constrained('users');
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('tipo');
            $table->index('privado');
            $table->index('created_at');
        });

        // Tabela de pontos de parada (waypoints)
        Schema::create('ordem_waypoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Informações do ponto
            $table->string('tipo')->comment('pickup, delivery, stop, border, port');
            $table->integer('sequencia')->default(1);
            $table->string('localizacao');
            $table->string('endereco')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 10, 8)->nullable();
            
            // Contato
            $table->string('contato_nome')->nullable();
            $table->string('contato_telefone')->nullable();
            $table->string('contato_email')->nullable();
            
            // Horários
            $table->timestamp('data_planejada')->nullable();
            $table->timestamp('data_estimada')->nullable();
            $table->timestamp('data_real')->nullable();
            
            // Status
            $table->enum('status', [
                'pending', 
                'in_progress', 
                'completed', 
                'delayed', 
                'cancelled'
            ])->default('pending');
            
            // Detalhes
            $table->text('instrucoes')->nullable();
            $table->json('documentos_requeridos')->nullable();
            $table->text('observacoes')->nullable();
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('tipo');
            $table->index('status');
            $table->index(['ordem_id', 'sequencia']);
        });

        // Tabela de histórico de preços das ordens
        Schema::create('ordem_historicos_precos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordem_id')->constrained('ordens')->onDelete('cascade');
            
            // Preços
            $table->decimal('preco_frete', 15, 2);
            $table->decimal('preco_combustivel', 15, 2)->nullable();
            $table->decimal('preco_portuario', 15, 2)->nullable();
            $table->decimal('preco_alfandega', 15, 2)->nullable();
            $table->decimal('preco_outros', 15, 2)->nullable();
            $table->decimal('preco_total', 15, 2);
            
            // Moeda
            $table->string('moeda')->default('USD');
            $table->decimal('taxa_cambio', 10, 4)->default(1);
            
            // Validade
            $table->date('validade_inicio');
            $table->date('validade_fim')->nullable();
            
            // Status
            $table->boolean('ativo')->default(true);
            $table->boolean('aprovado')->default(false);
            $table->string('aprovado_por')->nullable();
            $table->timestamp('aprovado_em')->nullable();
            
            // Detalhes
            $table->text('observacoes')->nullable();
            $table->string('criado_por');
            
            // Tenancy
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index('tenant_id');
            $table->index('ordem_id');
            $table->index('ativo');
            $table->index('aprovado');
            $table->index('validade_inicio');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ordem_historicos_precos');
        Schema::dropIfExists('ordem_waypoints');
        Schema::dropIfExists('ordem_notas');
        Schema::dropIfExists('ordem_custos');
        Schema::dropIfExists('ordem_documentos');
        Schema::dropIfExists('ordem_events');
        Schema::dropIfExists('tipos_embalagem');
        Schema::dropIfExists('break_bulk_items');
        Schema::dropIfExists('containers');
        Schema::dropIfExists('ordens');
    }
};
