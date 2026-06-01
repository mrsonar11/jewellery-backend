<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'customer_id', 'user_id', 'invoice_date',
        'subtotal', 'making_charges_total', 'stone_charges_total', 'taxable_amount',
        'gst_amount', 'cgst_amount', 'sgst_amount', 'discount_type', 'discount_value',
        'discount_amount', 'round_off', 'grand_total', 'paid_amount', 'due_amount',
        'payment_status'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}