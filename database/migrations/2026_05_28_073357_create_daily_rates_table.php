<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_rates', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // Gold, Silver, Diamond, Platinum
            $table->decimal('rate_per_10gm', 10, 2);
            $table->date('rate_date');
            $table->timestamps();
            $table->unique(['category', 'rate_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_rates');
    }
};