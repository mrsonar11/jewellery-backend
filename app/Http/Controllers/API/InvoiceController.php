<?php
namespace App\Http\Controllers\API;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\DailyRate;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('customer', 'user')->latest()->paginate(15);
        return response()->json($invoices);
    }

    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'customer_id' => 'required|exists:customers,id',
        'items' => 'required|array|min:1',
        'items.*.product_id' => 'required|exists:products,id',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.unit_price' => 'required|numeric|min:0',
        'items.*.weight' => 'nullable|numeric|min:0',
        'items.*.product_name' => 'nullable|string|max:200',
        'items.*.making_charges_total' => 'nullable|numeric',
        'items.*.stone_charges_total' => 'nullable|numeric',
        'items.*.gst_percent' => 'nullable|numeric',
        'payment_method' => 'required|in:cash,card,upi,mixed',
        'paid_amount' => 'required|numeric|min:0',
        'discount_type' => 'nullable|in:percentage,flat',
        'discount_value' => 'nullable|numeric|min:0'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    DB::beginTransaction();
    try {
        $subtotal = 0;
        $totalMaking = 0;
        $totalStone = 0;
        $totalGST = 0;
        $itemsData = [];

        // First pass: calculate totals
        foreach ($request->items as $item) {
    $product = Product::find($item['product_id']);
    $unitPrice = $item['unit_price'];
    $makingLine = $item['making_charges_total'] ?? 0;
    $stoneLine = $item['stone_charges_total'] ?? 0;
    $gstPercent = isset($item['gst_percent']) ? (float)$item['gst_percent'] : $product->gst_percent;
    $weight = $item['weight'] ?? $product->weight;

    $itemTotal = $unitPrice * $item['quantity'];
    $subtotal += $itemTotal;
    $totalMaking += $makingLine;   // for display only
    $totalStone += $stoneLine;     // for display only
    $totalGST += $itemTotal * ($gstPercent / 100);   // ✅ GST on itemTotal only

    $itemsData[] = [
        'product_id' => $product->id,
        'product_name' => $item['product_name'] ?? null,
        'quantity' => $item['quantity'],
        'weight' => $weight,
        'unit_price' => $unitPrice,
        'making_charges' => $makingLine,
        'stone_charges' => $stoneLine,
        'gst_percent' => $gstPercent,
        'total' => $itemTotal,
    ];
}

        $taxableAmount = $subtotal;
        $discountAmount = 0;
        if ($request->discount_type == 'percentage') {
            $discountAmount = ($taxableAmount + $totalGST) * ($request->discount_value / 100);
        } elseif ($request->discount_type == 'flat') {
            $discountAmount = $request->discount_value;
        }

        $grandTotal = $taxableAmount + $totalGST - $discountAmount;
        $roundOff = round($grandTotal) - $grandTotal;
        $grandTotal = round($grandTotal);
        $dueAmount = $grandTotal - $request->paid_amount;
        $paymentStatus = $dueAmount <= 0 ? 'paid' : ($request->paid_amount > 0 ? 'partial' : 'unpaid');

        // Create invoice object
        $invoice = new Invoice();
        $invoice->invoice_number = 'INV-' . time() . rand(100, 999);
        $invoice->customer_id = $request->customer_id;
        $invoice->user_id = auth()->id();
        $invoice->invoice_date = now()->toDateString();
        $invoice->subtotal = $subtotal;
        $invoice->making_charges_total = $totalMaking;
        $invoice->stone_charges_total = $totalStone;
        $invoice->taxable_amount = $taxableAmount;
        $invoice->gst_amount = $totalGST;
        $invoice->cgst_amount = $totalGST / 2;
        $invoice->sgst_amount = $totalGST / 2;
        $invoice->discount_type = $request->discount_type;
        $invoice->discount_value = $request->discount_value;
        $invoice->discount_amount = $discountAmount;
        $invoice->round_off = $roundOff;
        $invoice->grand_total = $grandTotal;
        $invoice->paid_amount = $request->paid_amount;
        $invoice->due_amount = $dueAmount;
        $invoice->payment_status = $paymentStatus;

        // Rates snapshot
        $todayRates = DailyRate::where('rate_date', Carbon::today())->get()->keyBy('category');
        $ratesSnapshot = [];
        foreach (['Gold', 'Silver', 'Diamond', 'Platinum'] as $cat) {
            $ratesSnapshot[$cat] = $todayRates[$cat]->rate_per_10gm ?? null;
        }
        $invoice->rates_snapshot = json_encode($ratesSnapshot);
        $invoice->save();

        foreach ($itemsData as $itemData) {
    $itemData['invoice_id'] = $invoice->id;
    InvoiceItem::create($itemData);

    $product = Product::find($itemData['product_id']);
    $product->decrement('stock_quantity', $itemData['quantity']);
    StockTransaction::create([
        'product_id' => $product->id,
        'type' => 'out',
        'quantity' => $itemData['quantity'],
        'reference' => $invoice->invoice_number
    ]);
}

// Save payment
Payment::create([
    'invoice_id' => $invoice->id,
    'amount' => $request->paid_amount,
    'payment_method' => $request->payment_method
]);

DB::commit();
return response()->json(['message' => 'Invoice created', 'invoice' => $invoice], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    
    public function show($id)
        {
            $invoice = Invoice::with('customer', 'items.product.category')->findOrFail($id);
            return response()->json($invoice);
        }

    public function recordPayment(Request $request, $id)
{
    $request->validate([
        'amount' => 'required|numeric|min:0.01'
    ]);

    $invoice = Invoice::findOrFail($id);
    $newPaid = $invoice->paid_amount + $request->amount;

    if ($newPaid > $invoice->grand_total) {
        return response()->json(['error' => 'Amount exceeds due amount'], 422);
    }

    $invoice->paid_amount = $newPaid;
    $invoice->due_amount = $invoice->grand_total - $newPaid;
    $invoice->payment_status = $invoice->due_amount <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
    $invoice->save();

    Payment::create([
        'invoice_id' => $invoice->id,
        'amount' => $request->amount,
        'payment_method' => 'cash'   // you can add a dropdown later
    ]);

    return response()->json(['message' => 'Payment recorded', 'invoice' => $invoice]);
}
}