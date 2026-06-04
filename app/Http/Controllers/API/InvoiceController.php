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
            'items.*.unit_price' => 'required|numeric|min:0',          // from frontend
            'items.*.making_charges_total' => 'nullable|numeric',       // total making for this line
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
            $invoice = new Invoice();
            $invoice->invoice_number = 'INV-' . time() . rand(100, 999);
            $invoice->customer_id = $request->customer_id;
            $invoice->user_id = auth()->id();
            $invoice->invoice_date = now()->toDateString();

            $subtotal = 0;
            $totalMaking = 0;
            $totalStone = 0;
            $totalGST = 0;   // ✅ DECLARE THE VARIABLE

            $itemsData = [];
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                // Use values from frontend, fallback to product defaults if not sent
                $unitPrice = $item['unit_price'] ?? $product->selling_price;
                $makingLine = $item['making_charges_total'] ?? 0;
                $stoneLine = $item['stone_charges_total'] ?? 0;
                $gstPercent = $item['gst_percent'] ?? $product->gst_percent;

                $itemTotal = $unitPrice * $item['quantity'];
                $subtotal += $itemTotal;
                $totalMaking += $makingLine;
                $totalStone += $stoneLine;
                $totalGST += ($itemTotal + $makingLine + $stoneLine) * ($gstPercent / 100);

                $itemsData[] = [
                    'invoice_id' => $invoice->id, // will be set after invoice save
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'making_charges' => $makingLine,
                    'stone_charges' => $stoneLine,
                    'gst_percent' => $gstPercent,
                    'total' => $itemTotal,
                ];
            }

            $taxableAmount = $subtotal + $totalMaking + $totalStone;
            $cgst = $sgst = $totalGST / 2;

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

            $invoice->subtotal = $subtotal;
            $invoice->making_charges_total = $totalMaking;
            $invoice->stone_charges_total = $totalStone;
            $invoice->taxable_amount = $taxableAmount;
            $invoice->gst_amount = $totalGST;
            $invoice->cgst_amount = $cgst;
            $invoice->sgst_amount = $sgst;
            $invoice->discount_type = $request->discount_type;
            $invoice->discount_value = $request->discount_value;
            $invoice->discount_amount = $discountAmount;
            $invoice->round_off = $roundOff;
            $invoice->grand_total = $grandTotal;
            $invoice->paid_amount = $request->paid_amount;
            $invoice->due_amount = $dueAmount;
            $invoice->payment_status = $paymentStatus;

            // Save rates snapshot
            $todayRates = DailyRate::where('rate_date', Carbon::today())->get()->keyBy('category');
            $ratesSnapshot = [];
            foreach (['Gold', 'Silver', 'Diamond', 'Platinum'] as $cat) {
                $ratesSnapshot[$cat] = $todayRates[$cat]->rate_per_10gm ?? null;
            }
            $invoice->rates_snapshot = json_encode($ratesSnapshot);

            $invoice->save();

            // Save invoice items and deduct stock
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

            // Record payment
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
            $invoice = Invoice::with('customer', 'items.product.category', 'payments')->findOrFail($id);
            return response()->json($invoice);
        }
}