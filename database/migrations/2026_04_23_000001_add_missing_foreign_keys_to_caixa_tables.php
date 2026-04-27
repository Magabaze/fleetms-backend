<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addForeignKeyIfPossible(
            'caixa_requisicoes',
            'caixa_requisicoes_viagem_id_foreign',
            'viagem_id',
            'viagens'
        );

        $this->addForeignKeyIfPossible(
            'caixa_requisicoes',
            'caixa_requisicoes_motorista_id_foreign',
            'motorista_id',
            'motoristas'
        );

        $this->addForeignKeyIfPossible(
            'caixa_justificativas',
            'caixa_justificativas_viagem_id_foreign',
            'viagem_id',
            'viagens'
        );

        $this->addForeignKeyIfPossible(
            'caixa_justificativas',
            'caixa_justificativas_motorista_id_foreign',
            'motorista_id',
            'motoristas'
        );
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('caixa_requisicoes', 'caixa_requisicoes_viagem_id_foreign');
        $this->dropForeignKeyIfExists('caixa_requisicoes', 'caixa_requisicoes_motorista_id_foreign');
        $this->dropForeignKeyIfExists('caixa_justificativas', 'caixa_justificativas_viagem_id_foreign');
        $this->dropForeignKeyIfExists('caixa_justificativas', 'caixa_justificativas_motorista_id_foreign');
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraintName,
        string $column,
        string $referencedTable
    ): void {
        if (
            !Schema::hasTable($table) ||
            !Schema::hasColumn($table, $column) ||
            !Schema::hasTable($referencedTable) ||
            $this->foreignKeyExists($constraintName)
        ) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $referencedTable) {
            $tableBlueprint->foreign($column)->references('id')->on($referencedTable);
        });
    }

    private function dropForeignKeyIfExists(string $table, string $constraintName): void
    {
        if (!Schema::hasTable($table) || !$this->foreignKeyExists($constraintName)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($constraintName) {
            $tableBlueprint->dropForeign($constraintName);
        });
    }

    private function foreignKeyExists(string $constraintName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND CONSTRAINT_TYPE = ?
               AND CONSTRAINT_NAME = ?
             LIMIT 1',
            [$database, 'FOREIGN KEY', $constraintName]
        );

        return $result !== null;
    }
};
