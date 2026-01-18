<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('viagens', function (Blueprint $table) {
            $table->id();
            $table->string('trip_number')->nullable();
            $table->string('trip_slno')->default('1');
            $table->string('order_number')->nullable();
            $table->string('customer_name');
            $table->string('from_station');
            $table->string('to_station');
            $table->string('truck_number');
            $table->string('trailer_number')->nullable();
            $table->string('driver');
            $table->string('container_no')->nullable();
            $table->string('bl_number')->nullable();
            $table->string('commodity');
            $table->string('cargo_type');
            $table->decimal('weight', 10, 2)->nullable();
            $table->string('status')->default('Pending');
            $table->string('current_status')->default('SCHEDULED');
            $table->date('schedule_date');
            $table->date('delivery_date')->nullable();
            $table->date('actual_delivery')->nullable();
            $table->date('pod_delivery_date')->nullable();
            $table->string('current_position')->nullable();
            $table->text('tracking_comments')->nullable();
            $table->date('border_arrival_date')->nullable();
            $table->integer('border_demurrage_days')->default(0);
            $table->date('offloading_arrival_date')->nullable();
            $table->integer('offloading_demurrage_days')->default(0);
            $table->boolean('is_empty_trip')->default(false);
            $table->boolean('is_company_owned')->default(true);
            $table->boolean('is_ready_for_invoice')->default(false);
            $table->string('invoice_number')->nullable();
            $table->string('transporter')->nullable();
            $table->string('order_owner')->nullable();
            $table->string('created_by');
            $table->string('tenant_id')->default('default');
            $table->timestamps();
            
            // Índices
            $table->index(['trip_number', 'trip_slno']);
            $table->index('truck_number');
            $table->index('driver');
            $table->index('status');
            $table->index('current_status');
            $table->index('tenant_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('viagens');
    }
};