<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('driver_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viagem_id')->constrained('viagens')->onDelete('cascade');
            $table->string('expense_head'); // Removido o relacionamento com tipo_despesa
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->string('driver_name');
            $table->text('payment_description')->nullable();
            $table->string('created_by');
            $table->foreignId('created_by_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'paid', 'settled', 'cancelled'])->default('pending');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('viagem_id');
            $table->index('expense_head'); // Adicionado índice para expense_head
            $table->index('status');
            $table->index('is_active');
            $table->index('created_by_id');
            $table->index('driver_name');
            $table->index(['viagem_id', 'status']);
            $table->index(['viagem_id', 'is_active']);
            $table->index(['expense_head', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('driver_expenses');
    }
};