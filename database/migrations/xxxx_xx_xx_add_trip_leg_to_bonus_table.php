<?php
// database/migrations/xxxx_xx_xx_add_trip_leg_to_bonus_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus', function (Blueprint $table) {
            // Apenas adiciona se ainda não existirem
            if (!Schema::hasColumn('bonus', 'trip_number')) {
                $table->string('trip_number')->nullable()->after('viagem_id');
            }
            if (!Schema::hasColumn('bonus', 'leg_number')) {
                $table->string('leg_number')->nullable()->after('trip_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bonus', function (Blueprint $table) {
            $table->dropColumn(['trip_number', 'leg_number']);
        });
    }
};