<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('ordens_trabalho', function (Blueprint $table) {
            // Campos para manutenção externa
            $table->unsignedBigInteger('fornecedor_id')->nullable()->after('tecnico');
            $table->string('fornecedor_nome')->nullable()->after('fornecedor_id');
            $table->unsignedBigInteger('orcamento_id')->nullable()->after('fornecedor_nome');
            
            // Campos para socorro
            $table->string('local_socorro')->nullable()->after('observacoes');
            $table->integer('km_socorro')->nullable()->after('local_socorro');
            
            // Campos comuns
            $table->decimal('custo', 12, 2)->nullable()->after('km_socorro');
            $table->date('data_inicio')->nullable()->after('data_prevista');
            $table->date('data_fim')->nullable()->after('data_inicio');
            
            // Índices
            $table->index(['fornecedor_id']);
            $table->index(['orcamento_id']);
        });

        // Atualizar o enum da coluna tipo para incluir 'externa' e 'socorro'
        DB::statement("ALTER TABLE ordens_trabalho MODIFY COLUMN tipo ENUM('preventiva', 'corretiva', 'inspecao', 'externa', 'socorro') NOT NULL DEFAULT 'preventiva'");
    }

    public function down()
    {
        Schema::table('ordens_trabalho', function (Blueprint $table) {
            $table->dropColumn([
                'fornecedor_id',
                'fornecedor_nome',
                'orcamento_id',
                'local_socorro',
                'km_socorro',
                'custo',
                'data_inicio',
                'data_fim'
            ]);
        });

        // Reverter o enum para o estado anterior
        DB::statement("ALTER TABLE ordens_trabalho MODIFY COLUMN tipo ENUM('preventiva', 'corretiva', 'inspecao') NOT NULL DEFAULT 'preventiva'");
    }
};