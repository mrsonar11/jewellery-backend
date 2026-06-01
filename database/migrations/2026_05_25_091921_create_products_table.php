<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name', 200);
            $table->foreignId('category_id')->constrained()->onDelete('restrict');
            $table->string('design_name', 200)->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->string('purity', 20)->nullable();
            $table->decimal('weight', 10, 3);
            $table->decimal('making_charges', 10, 2)->default(0.00);
            $table->decimal('wastage_percent', 5, 2)->default(0.00);
            $table->decimal('stone_charges', 10, 2)->default(0.00);
            $table->decimal('gst_percent', 5, 2)->default(5.00);
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->string('barcode_sku', 100)->unique()->nullable();
            $table->string('product_image', 255)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('category_id', 'idx_category');
            $table->index('barcode_sku', 'idx_barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
