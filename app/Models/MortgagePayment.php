<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MortgagePayment extends Model
{
    protected $fillable = ['mortgage_id', 'amount', 'payment_date', 'remarks'];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function mortgage()
    {
        return $this->belongsTo(Mortgage::class);
    }
}