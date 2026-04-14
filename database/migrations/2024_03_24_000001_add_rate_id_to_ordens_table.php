// database/migrations/2024_03_24_000001_add_rate_id_to_ordens_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Passo 1: Adicionar a coluna como nullable primeiro
        Schema::table('ordens', function (Blueprint $table) {
            $table->unsignedBigInteger('rate_id')->nullable()->after('taxa_cliente_id');
        });

        // Passo 2: Popular os registros existentes com um rate padrão
        // Verificar se existem ordens na tabela
        $existeOrdens = DB::table('ordens')->exists();
        
        if ($existeOrdens) {
            // Buscar um rate aprovado existente
            $rateExistente = DB::table('rates')
                ->where('status', 'aprovado')
                ->first();
            
            if ($rateExistente) {
                // Usar rate existente
                DB::table('ordens')->whereNull('rate_id')->update(['rate_id' => $rateExistente->id]);
                echo "✅ Usado rate existente ID: {$rateExistente->id}\n";
            } else {
                // Criar um rate padrão temporário
                $novoRateId = DB::table('rates')->insertGetId([
                    'cliente_id' => 1,
                    'cliente_nome' => 'Sistema - Migração',
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
                
                DB::table('ordens')->whereNull('rate_id')->update(['rate_id' => $novoRateId]);
                echo "⚠️ Criado rate temporário ID: {$novoRateId}\n";
            }
        }

        // Passo 3: Tornar a coluna NOT NULL
        Schema::table('ordens', function (Blueprint $table) {
            $table->unsignedBigInteger('rate_id')->nullable(false)->change();
        });

        // Passo 4: Adicionar o índice
        Schema::table('ordens', function (Blueprint $table) {
            $table->index('rate_id');
        });

        // Passo 5: Adicionar a foreign key
        Schema::table('ordens', function (Blueprint $table) {
            $table->foreign('rate_id')
                  ->references('id')
                  ->on('rates')
                  ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('ordens', function (Blueprint $table) {
            $table->dropForeign(['rate_id']);
            $table->dropColumn('rate_id');
        });
    }
};