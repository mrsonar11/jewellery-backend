<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mortgages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');
            $table->string('item_description');
            $table->decimal('weight', 10, 3);
            $table->decimal('loan_amount', 12, 2);
            $table->decimal('interest_rate', 5, 2)->default(0); // percentage per month
            $table->date('pledge_date');
            $table->date('due_date');
            $table->enum('status', ['active', 'repaid', 'released'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mortgages');
    }
};