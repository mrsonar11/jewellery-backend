<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'mobile', 'address', 'email', 'gst_number', 'id_proof_path'];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}