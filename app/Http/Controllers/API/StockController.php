<?php
namespace App\Http\Controllers\API;

use App\Models\Product;
use App\Models\StockTransaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    public function stockIn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $product = Product::find($request->product_id);
        $product->increment('stock_quantity', $request->quantity);
        
        StockTransaction::create([
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => $request->quantity,
            'reference' => $request->reference
        ]);
        
        return response()->json(['message' => 'Stock added successfully']);
    }
    
    public function lowStockAlert()
    {
        $products = Product::where('stock_quantity', '<=', 5)->get(['id', 'product_name', 'stock_quantity']);
        return response()->json($products);
    }
}