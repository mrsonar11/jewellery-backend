<?php

namespace App\Http\Controllers\API;

use App\Models\Mortgage;
use App\Models\MortgagePayment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class MortgageController extends Controller
{
    public function index(Request $request)
    {
        $query = Mortgage::with('customer', 'payments');
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('customer', function($q) use ($search) {
                $q->where('name', 'like', "%$search%")->orWhere('mobile', 'like', "%$search%");
            })->orWhere('item_description', 'like', "%$search%");
        }
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        $mortgages = $query->latest()->paginate(15);
        return response()->json($mortgages);
    }

    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'item_description' => 'required|string|max:255',
            'weight' => 'required|numeric|min:0',
            'loan_amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'pledge_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:pledge_date',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mortgage = Mortgage::create($request->all());
        return response()->json(['message' => 'Mortgage created', 'mortgage' => $mortgage], 201);
    }

    public function show($id)
    {
        $mortgage = Mortgage::with('customer', 'payments')->findOrFail($id);
        return response()->json($mortgage);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mortgage = Mortgage::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'item_description' => 'sometimes|string|max:255',
            'weight' => 'sometimes|numeric|min:0',
            'loan_amount' => 'sometimes|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'pledge_date' => 'sometimes|date',
            'due_date' => 'sometimes|date|after_or_equal:pledge_date',
            'status' => 'sometimes|in:active,repaid,released',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mortgage->update($request->all());
        return response()->json(['message' => 'Mortgage updated', 'mortgage' => $mortgage]);
    }

    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $mortgage = Mortgage::findOrFail($id);
        $mortgage->delete();
        return response()->json(['message' => 'Mortgage deleted']);
    }

    public function addPayment(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mortgage = Mortgage::findOrFail($id);
        $payment = $mortgage->payments()->create([
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'remarks' => $request->remarks,
        ]);

        // If after this payment the remaining amount becomes <=0, mark as repaid.
        if ($mortgage->remaining_amount <= 0) {
            $mortgage->status = 'repaid';
            $mortgage->save();
        }
        return response()->json(['message' => 'Payment recorded', 'payment' => $payment]);
    }
    public function release($id)
        {
            if (auth()->user()->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
            $mortgage = Mortgage::findOrFail($id);
            if ($mortgage->status === 'released') {
                return response()->json(['error' => 'Already released'], 400);
            }
            $mortgage->status = 'released';
            $mortgage->save();
            return response()->json(['message' => 'Item released successfully', 'mortgage' => $mortgage]);
        }
}