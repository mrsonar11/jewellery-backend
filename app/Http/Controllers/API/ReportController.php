<?php
namespace App\Http\Controllers\API;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dailySales(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $sales = Invoice::whereDate('invoice_date', $date)->sum('grand_total');
        $invoices = Invoice::with('customer')->whereDate('invoice_date', $date)->get();
        return response()->json(['date' => $date, 'total_sales' => $sales, 'invoices' => $invoices]);
    }
    
    public function monthlySales(Request $request)
    {
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $start = Carbon::parse($month)->startOfMonth();
        $end = Carbon::parse($month)->endOfMonth();
        $sales = Invoice::whereBetween('invoice_date', [$start, $end])->sum('grand_total');
        return response()->json(['month' => $month, 'total_sales' => $sales]);
    }
    
    public function productSales(Request $request)
    {
        $productSales = InvoiceItem::select('product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total) as total_amount'))
            ->with('product')
            ->groupBy('product_id')
            ->get();
        return response()->json($productSales);
    }
    
    public function profitReport(Request $request)
    {
        // Calculate profit = (selling_price - purchase_price - making_charges - stone_charges) * quantity sold
        $profit = DB::table('invoice_items')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->select(DB::raw('SUM((invoice_items.unit_price - products.purchase_price - products.making_charges - products.stone_charges) * invoice_items.quantity) as total_profit'))
            ->first();
        
        return response()->json(['profit' => $profit->total_profit ?? 0]);
    }

    public function gstReport(Request $request)
    {
        $from = $request->get('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->get('to', Carbon::now()->endOfMonth()->toDateString());
        
        $gstData = Invoice::whereBetween('invoice_date', [$from, $to])
            ->select(
                DB::raw('SUM(cgst_amount) as total_cgst'),
                DB::raw('SUM(sgst_amount) as total_sgst'),
                DB::raw('SUM(gst_amount) as total_gst')
            )
            ->first();
        
        return response()->json([
            'from' => $from,
            'to' => $to,
            'gst' => $gstData
        ]);
    }
}