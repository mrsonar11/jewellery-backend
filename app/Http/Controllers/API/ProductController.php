<?php

namespace App\Http\Controllers\API;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category');   // load category

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('product_name', 'like', "%$search%")
                  ->orWhere('barcode_sku', 'like', "%$search%");
        }
        if ($request->has('category') && $request->category) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('name', $request->category);
            });
        }

        $products = $query->paginate(10);

        // Add computed per‑gram values to each product
        $products->getCollection()->transform(function ($product) {
            $product->making_charges_per_gram = $product->weight > 0
                ? round($product->making_charges / $product->weight, 2)
                : 0;
            $product->stone_charges_per_gram = $product->weight > 0
                ? round($product->stone_charges / $product->weight, 2)
                : 0;
            return $product;
        });

        return response()->json($products);
    }

    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string|max:200',
            'category_id' => 'required|exists:categories,id',
            'weight' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'barcode_sku' => 'nullable|unique:products',
            'product_image' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'product_name', 'category_id', 'design_name', 'hsn_code', 'purity',
            'weight', 'purchase_price', 'selling_price', 'stock_quantity', 'barcode_sku'
        ]);

        $data['making_charges'] = $request->making_charges ?? 0;
        $data['stone_charges'] = $request->stone_charges ?? 0;
        $data['wastage_percent'] = $request->wastage_percent ?? 0;
        $data['gst_percent'] = $request->gst_percent ?? 5;

        if ($request->hasFile('product_image')) {
            $path = $request->file('product_image')->store('products', 'public');
            $data['product_image'] = $path;
        }

        $product = Product::create($data);
        return response()->json(['message' => 'Product created', 'product' => $product], 201);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'product_name' => 'sometimes|string|max:200',
            'selling_price' => 'sometimes|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('product_image')) {
            if ($product->product_image) Storage::disk('public')->delete($product->product_image);
            $path = $request->file('product_image')->store('products', 'public');
            $product->product_image = $path;
        }

        $product->update($request->except('product_image'));
        return response()->json(['message' => 'Product updated', 'product' => $product]);
    }

    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $product = Product::findOrFail($id);
        if ($product->product_image) Storage::disk('public')->delete($product->product_image);
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    public function search(Request $request)
    {
        $term = $request->q;
        $products = Product::where('product_name', 'like', "%$term%")
                           ->orWhere('barcode_sku', 'like', "%$term%")
                           ->limit(10)
                           ->get();
        return response()->json($products);
    }
}