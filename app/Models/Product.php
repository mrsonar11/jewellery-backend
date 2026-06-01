<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_name', 'category_id', 'design_name', 'hsn_code', 'purity',
        'weight', 'making_charges', 'wastage_percent', 'stone_charges',
        'gst_percent', 'purchase_price', 'selling_price', 'stock_quantity',
        'barcode_sku', 'product_image'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}