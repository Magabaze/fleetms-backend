<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ordens') || !Schema::hasTable('rates')) {
            return;
        }

        if (!Schema::hasColumn('ordens', 'rate_id')) {
            Schema::table('ordens', function (Blueprint $table) {
                $table->unsignedBigInteger('rate_id')->nullable()->after('taxa_cliente_id');
            });
        }

        $this->populateRateId();

        Schema::table('ordens', function (Blueprint $table) {
            $table->index('rate_id');
            $table->foreign('rate_id')
                ->references('id')
                ->on('rates')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ordens') || !Schema::hasColumn('ordens', 'rate_id')) {
            return;
        }

        Schema::table('ordens', function (Blueprint $table) {
            $table->dropForeign(['rate_id']);
            $table->dropIndex(['rate_id']);
            $table->dropColumn('rate_id');
        });
    }

    private function populateRateId(): void
    {
        $hasOrdensWithoutRate = DB::table('ordens')->whereNull('rate_id')->exists();

        if (!$hasOrdensWithoutRate) {
            return;
        }

        $rateExistente = DB::table('rates')
            ->where('status', 'aprovado')
            ->first();

        if (!$rateExistente) {
            $rateExistenteId = DB::table('rates')->insertGetId([
                'cliente_id' => 1,
                'cliente_nome' => 'Sistema - Migracao',
                'distancia_rota' => 'Default',
                'moeda' => 'USD',
                'preco_unitario' => 0,
                'unidade_medida' => 'N/A',
                'validade' => now()->addYear(),
                'status' => 'aprovado',
                'criado_por' => 'Sistema',
                'tenant_id' => 'default',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $rateExistenteId = $rateExistente->id;
        }

        DB::table('ordens')->whereNull('rate_id')->update(['rate_id' => $rateExistenteId]);
    }
};
