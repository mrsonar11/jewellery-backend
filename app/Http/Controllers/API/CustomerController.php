<?php

namespace App\Http\Controllers\API;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        // Start with a base query that selects customer fields
        $query = Customer::query();
        
        // Apply search filter (name or mobile)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('mobile', 'like', "%$search%");
            });
        }
        
        // Build a subquery to calculate total spent and total due per customer
        $subquery = DB::table('invoices')
            ->select('customer_id')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_spent')
            ->selectRaw('COALESCE(SUM(due_amount), 0) as total_due')
            ->groupBy('customer_id');
        
        // Join the subquery to the customers table
        $query->leftJoinSub($subquery, 'stats', function($join) {
            $join->on('customers.id', '=', 'stats.customer_id');
        });
        
        // Select customer columns plus the calculated totals
        $query->select('customers.*')
              ->selectRaw('COALESCE(stats.total_spent, 0) as total_spent')
              ->selectRaw('COALESCE(stats.total_due, 0) as total_due');
        
        // Apply pending due filter (if due_min is set and > 0)
        if ($request->has('due_min') && is_numeric($request->due_min) && $request->due_min > 0) {
            $query->whereRaw('COALESCE(stats.total_due, 0) >= ?', [$request->due_min]);
        }
        
        // Order by total spent descending
        $query->orderByRaw('COALESCE(stats.total_spent, 0) DESC');
        
        // Paginate (10 per page)
        $customers = $query->paginate(10);
        
        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'mobile' => 'required|string|max:20|unique:customers',
            'email' => 'nullable|email|unique:customers',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $customer = Customer::create($request->all());
        return response()->json(['message' => 'Customer added', 'customer' => $customer], 201);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'mobile' => 'sometimes|string|max:20|unique:customers,mobile,'.$id,
            'email' => 'nullable|email|unique:customers,email,'.$id,
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $customer->update($request->all());
        return response()->json(['message' => 'Customer updated', 'customer' => $customer]);
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        if ($customer->invoices()->count() > 0) {
            return response()->json(['error' => 'Cannot delete customer with existing invoices'], 400);
        }
        $customer->delete();
        return response()->json(['message' => 'Customer deleted']);
    }

    public function purchaseHistory($id)
    {
        $customer = Customer::findOrFail($id);
        $invoices = Invoice::where('customer_id', $id)->with('items.product')->get();
        return response()->json(['customer' => $customer, 'purchases' => $invoices]);
    }
}