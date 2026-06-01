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
            $invoice->invoice_number = 'INV-' . time() . rand(100,999);
            $invoice->customer_id = $request->customer_id;
            $invoice->user_id = auth()->id();
            $invoice->invoice_date = now()->toDateString();
            
            $subtotal = 0;
            $totalMaking = 0;
            $totalStone = 0;
            $itemsData = [];
            
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                // Check stock
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->product_name}");
                }
                
                $itemTotal = $product->selling_price * $item['quantity'];
                $subtotal += $itemTotal;
                $totalMaking += $product->making_charges * $item['quantity'];
                $totalStone += $product->stone_charges * $item['quantity'];
                
                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->selling_price,
                    'making_charges' => $product->making_charges,
                    'stone_charges' => $product->stone_charges,
                    'gst_percent' => $product->gst_percent,
                    'total' => $itemTotal
                ];
            }
            
            $taxableAmount = $subtotal + $totalMaking + $totalStone;
            $gstAmount = $taxableAmount * ($itemsData[0]['gst_percent'] / 100); // assuming same GST for simplicity
            $cgst = $sgst = $gstAmount / 2;
            
            $discountAmount = 0;
            if ($request->discount_type == 'percentage') {
                $discountAmount = ($taxableAmount + $gstAmount) * ($request->discount_value / 100);
            } elseif ($request->discount_type == 'flat') {
                $discountAmount = $request->discount_value;
            }
            
            $grandTotal = $taxableAmount + $gstAmount - $discountAmount;
            $roundOff = round($grandTotal) - $grandTotal;
            $grandTotal = round($grandTotal);
            
            $dueAmount = $grandTotal - $request->paid_amount;
            $paymentStatus = $dueAmount <= 0 ? 'paid' : ($request->paid_amount > 0 ? 'partial' : 'unpaid');
            
            $invoice->subtotal = $subtotal;
            $invoice->making_charges_total = $totalMaking;
            $invoice->stone_charges_total = $totalStone;
            $invoice->taxable_amount = $taxableAmount;
            $invoice->gst_amount = $gstAmount;
            $invoice->cgst_amount = $cgst;
            $invoice->sgst_amount = $sgst;
            $invoice->discount_type = $request->discount_type;
            $invoice->discount_value = $request->discount_value;
            $invoice->discount_amount = $discountAmount;
            $invoice->round_off = $roundOff;
            $invoice->grand_total = $grandTotal;
            $invoice->paid_amount = $request->paid_amount;
            $invoice->due_amount = $dueAmount;
            $todayRates = \App\Models\DailyRate::where('rate_date', \Carbon\Carbon::today())->get()->keyBy('category');
            $ratesSnapshot = [];
            foreach (['Gold', 'Silver', 'Diamond', 'Platinum'] as $cat) {
                $ratesSnapshot[$cat] = $todayRates[$cat]->rate_per_10gm ?? null;
            }
            $invoice->rates_snapshot = json_encode($ratesSnapshot);
            $invoice->payment_status = $paymentStatus;
            $invoice->save();
            
            // Save items and deduct stock
            foreach ($itemsData as $index => $data) {
                $data['invoice_id'] = $invoice->id;
                InvoiceItem::create($data);
                
                $product = Product::find($data['product_id']);
                $product->decrement('stock_quantity', $request->items[$index]['quantity']);
                
                // Record stock out
                StockTransaction::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $request->items[$index]['quantity'],
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
            return response()->json(['message' => 'Invoice created', 'invoice' => $invoice->load('items.product')], 201);
            
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