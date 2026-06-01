<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyRate extends Model
{
    protected $fillable = ['category', 'rate_per_10gm', 'rate_date'];
}