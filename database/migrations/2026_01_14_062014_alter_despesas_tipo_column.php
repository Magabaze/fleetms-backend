<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alterar a coluna tipo de ENUM para VARCHAR
        DB::statement("ALTER TABLE despesas MODIFY tipo VARCHAR(100) NOT NULL");
        
        // Remover o índice antigo e criar um novo
        Schema::table('despesas', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        // Reverter para ENUM (se necessário)
        DB::statement("ALTER TABLE despesas MODIFY tipo ENUM('Combustível', 'Alimentação', 'Hospedagem', 'Pedágio', 'Estacionamento', 'Manutenção', 'Outros') NOT NULL");
    }
};