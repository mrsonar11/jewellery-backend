<?php

namespace App\Http\Controllers\API;

use App\Models\DailyRate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RateController extends Controller
{
    public function today()
    {
        $rates = DailyRate::where('rate_date', Carbon::today())->get()->keyBy('category');
        return response()->json($rates);
    }

    public function todayWithComparison()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayRates = DailyRate::where('rate_date', $today)->get()->keyBy('category');
        $yesterdayRates = DailyRate::where('rate_date', $yesterday)->get()->keyBy('category');

        $categories = ['Gold', 'Silver', 'Diamond', 'Platinum'];
        $result = [];

        foreach ($categories as $cat) {
            $todayRate = $todayRates[$cat]->rate_per_10gm ?? null;
            $yesterdayRate = $yesterdayRates[$cat]->rate_per_10gm ?? null;
            $result[$cat] = [
                'today' => $todayRate,
                'yesterday' => $yesterdayRate,
                'change' => $todayRate && $yesterdayRate ? $todayRate - $yesterdayRate : null,
                'percentage' => $yesterdayRate && $todayRate ? (($todayRate - $yesterdayRate) / $yesterdayRate * 100) : null
            ];
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rates' => 'required|array',
            'rates.*.category' => 'required|string|in:Gold,Silver,Diamond,Platinum',
            'rates.*.rate_per_10gm' => 'required|numeric|min:0'
        ]);

        foreach ($data['rates'] as $rate) {
            DailyRate::updateOrCreate(
                ['category' => $rate['category'], 'rate_date' => Carbon::today()],
                ['rate_per_10gm' => $rate['rate_per_10gm']]
            );
        }

        return response()->json(['message' => 'Rates saved successfully']);
    }
}