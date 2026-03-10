<?php
// database/migrations/2026_02_25_000001_update_caixa_turnos_add_saldos_json.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('caixa_turnos', function (Blueprint $table) {
            // Adicionar coluna JSON para saldos
            $table->json('saldos')->nullable()->after('operador_nome');
        });

        // Migrar dados existentes (se houver)
        $turnos = DB::table('caixa_turnos')->get();
        foreach ($turnos as $turno) {
            $saldos = json_encode([
                [
                    'moeda' => 'MZN',
                    'saldoAbertura' => (float) $turno->saldo_abertura,
                    'saldoAtual' => (float) $turno->saldo_atual
                ]
            ]);
            
            DB::table('caixa_turnos')
                ->where('id', $turno->id)
                ->update(['saldos' => $saldos]);
        }

        // Agora podemos tornar as colunas antigas nullable
        Schema::table('caixa_turnos', function (Blueprint $table) {
            $table->decimal('saldo_abertura', 15, 2)->nullable()->change();
            $table->decimal('saldo_atual', 15, 2)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('caixa_turnos', function (Blueprint $table) {
            $table->dropColumn('saldos');
            $table->decimal('saldo_abertura', 15, 2)->nullable(false)->change();
            $table->decimal('saldo_atual', 15, 2)->nullable(false)->change();
        });
    }
};