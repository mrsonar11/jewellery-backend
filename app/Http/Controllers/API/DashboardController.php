<?php

namespace App\Http\Controllers\API;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $yearStart = Carbon::now()->startOfYear();
        
        // Sales
        $todaySales = Invoice::whereDate('invoice_date', $today)->sum('grand_total');
        $monthlySales = Invoice::where('invoice_date', '>=', $monthStart)->sum('grand_total');
        $yearlySales = Invoice::where('invoice_date', '>=', $yearStart)->sum('grand_total');
        
        // Due (Pending) Amounts
        $todayDue = Invoice::whereDate('invoice_date', $today)->sum('due_amount');
        $monthlyDue = Invoice::where('invoice_date', '>=', $monthStart)->sum('due_amount');
        $yearlyDue = Invoice::where('invoice_date', '>=', $yearStart)->sum('due_amount');
        
        // Profit calculations
        $profitQuery = DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id');
        
        $todayProfit = (clone $profitQuery)
            ->whereDate('invoices.invoice_date', $today)
            ->select(DB::raw('COALESCE(SUM((invoice_items.unit_price - products.purchase_price - products.making_charges - products.stone_charges) * invoice_items.quantity), 0) as profit'))
            ->first()->profit ?? 0;
        
        $monthlyProfit = (clone $profitQuery)
            ->where('invoices.invoice_date', '>=', $monthStart)
            ->select(DB::raw('COALESCE(SUM((invoice_items.unit_price - products.purchase_price - products.making_charges - products.stone_charges) * invoice_items.quantity), 0) as profit'))
            ->first()->profit ?? 0;
        
        $yearlyProfit = (clone $profitQuery)
            ->where('invoices.invoice_date', '>=', $yearStart)
            ->select(DB::raw('COALESCE(SUM((invoice_items.unit_price - products.purchase_price - products.making_charges - products.stone_charges) * invoice_items.quantity), 0) as profit'))
            ->first()->profit ?? 0;
        
        $totalCustomers = Customer::count();
        $totalProducts = Product::count();
        
        $lowStock = Product::where('stock_quantity', '<=', 5)->get(['id', 'product_name', 'stock_quantity']);
        $recentBills = Invoice::with('customer')->latest()->take(5)->get();
        
        return response()->json([
            'today_sales' => $todaySales,
            'monthly_sales' => $monthlySales,
            'yearly_sales' => $yearlySales,
            'today_profit' => $todayProfit,
            'monthly_profit' => $monthlyProfit,
            'yearly_profit' => $yearlyProfit,
            'today_due' => $todayDue,
            'monthly_due' => $monthlyDue,
            'yearly_due' => $yearlyDue,
            'total_customers' => $totalCustomers,
            'total_products' => $totalProducts,
            'low_stock' => $lowStock,
            'recent_bills' => $recentBills
        ]);
    }
}