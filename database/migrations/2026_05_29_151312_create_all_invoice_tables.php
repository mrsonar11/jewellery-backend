<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Invoices table (must be first)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->date('invoice_date');
            
            $table->decimal('subtotal', 10, 2);
            $table->decimal('making_charges_total', 10, 2)->default(0);
            $table->decimal('stone_charges_total', 10, 2)->default(0);
            $table->decimal('taxable_amount', 10, 2);
            $table->decimal('gst_amount', 10, 2);
            $table->decimal('cgst_amount', 10, 2);
            $table->decimal('sgst_amount', 10, 2);
            
            $table->enum('discount_type', ['percentage', 'flat'])->nullable();
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('round_off', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);
            
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->json('rates_snapshot')->nullable();   // store rates at time of billing
            $table->enum('payment_status', ['paid', 'partial', 'unpaid'])->default('unpaid');
            
            $table->timestamps();
            
            $table->index('invoice_date');
            $table->index('customer_id');
        });

        // 2. Invoice items table
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('making_charges', 10, 2)->default(0);
            $table->decimal('stone_charges', 10, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(5);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });

        // 3. Payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'card', 'upi', 'mixed']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};