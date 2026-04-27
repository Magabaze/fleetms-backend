<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['nome']);
            $table->dropUnique(['chave']);
            $table->unique(['chave', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['chave', 'tenant_id']);
            $table->unique('nome');
            $table->unique('chave');
        });
    }
};
