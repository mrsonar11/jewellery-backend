<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;      // <-- Add this line
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('SET CONSTRAINTS ALL DEFERRED;');
    } else {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    }

    public function run()
    {
        // Truncate tables (order matters due to foreign keys)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Product::truncate();
        Category::truncate();
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Create admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@jewellery.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin'
        ]);

        // Create categories
        $categories = ['Gold', 'Silver', 'Diamond', 'Platinum'];
        foreach ($categories as $cat) {
            Category::create(['name' => $cat]);
        }

        // Create sample product
        Product::create([
            'product_name' => 'Gold Ring',
            'category_id' => 1,
            'design_name' => 'Floral',
            'hsn_code' => '711319',
            'purity' => '22K',
            'weight' => 5.0,
            'making_charges' => 500,
            'stone_charges' => 200,
            'gst_percent' => 3,
            'purchase_price' => 25000,
            'selling_price' => 32000,
            'stock_quantity' => 10,
            'barcode_sku' => 'GOLD001'
        ]);
    }
    if (DB::getDriverName() !== 'pgsql') {
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}