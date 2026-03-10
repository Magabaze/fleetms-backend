<?php
// database/migrations/2024_01_01_000000_create_viagem_break_bulk_pivot_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('viagem_break_bulk')) {
            Schema::create('viagem_break_bulk', function (Blueprint $table) {
                $table->id();
                $table->foreignId('viagem_id')->constrained('viagens')->onDelete('cascade');
                $table->foreignId('break_bulk_item_id')->constrained('break_bulk_items')->onDelete('cascade');
                $table->decimal('peso_utilizado', 10, 2);
                $table->integer('quantidade_utilizada');
                $table->string('tenant_id')->nullable();
                $table->timestamps();
                
                $table->unique(['viagem_id', 'break_bulk_item_id', 'tenant_id']);
                
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('viagem_break_bulk');
    }
};