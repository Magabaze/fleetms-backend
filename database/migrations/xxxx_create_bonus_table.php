<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bonus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viagem_id')->nullable()->constrained('viagens')->onDelete('set null');
            $table->string('motorista');
            $table->text('descricao')->nullable();
            $table->decimal('valor', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->string('tenant_id');
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'motorista']);
        });
    }
    public function down() { Schema::dropIfExists('bonus'); }
};