<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'mobile', 'address', 'email', 'gst_number'];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}