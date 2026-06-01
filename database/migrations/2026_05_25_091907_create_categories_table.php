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
       Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name', 50)->unique();
        $table->timestamps();
    });

    // Insert default categories
    DB::table('categories')->insert([
        ['name' => 'Gold'],
        ['name' => 'Silver'],
        ['name' => 'Diamond'],
        ['name' => 'Platinum'],
    ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
