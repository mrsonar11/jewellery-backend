<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Mortgage extends Model
{
    protected $fillable = [
        'customer_id', 'item_description', 'weight', 'loan_amount',
        'interest_rate', 'pledge_date', 'due_date', 'status', 'notes'
    ];

    protected $casts = [
        'pledge_date' => 'date',
        'due_date' => 'date',
    ];

    // ✅ Include computed attributes in JSON
    protected $appends = ['paid_amount', 'interest_amount', 'total_payable', 'remaining_amount'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(MortgagePayment::class);
    }

    public function getPaidAmountAttribute()
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getInterestAmountAttribute()
    {
        $rate = (float) $this->interest_rate;
        if ($rate <= 0) return 0.0;

        $start = Carbon::parse($this->pledge_date);
        // For active loans, calculate interest up to today (or due date if earlier)
        if ($this->status === 'repaid') {
            $end = Carbon::parse($this->due_date);
        } else {
            $end = Carbon::today();
            // If due date is earlier than today, interest stops at due date
            $due = Carbon::parse($this->due_date);
            if ($due->lt($end)) $end = $due;
        }
        if ($end->lt($start)) $end = $start->copy();

        $months = $start->diffInMonths($end);
        if ($months < 1) $months = 1;

        $interest = ($this->loan_amount * ($rate / 100)) * $months;
        return round($interest, 2);
    }

    public function getTotalPayableAttribute()
    {
        return $this->loan_amount + $this->interest_amount;
    }

    public function getRemainingAmountAttribute()
    {
        $remaining = $this->total_payable - $this->paid_amount;
        return max(0, $remaining);
    }
}