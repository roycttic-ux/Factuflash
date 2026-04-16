<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'cost_usd' => 'nullable|numeric|min:0',
            'margin_percent' => 'nullable|numeric|min:0',
            'recommended_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock_alert' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:50',
            'image' => $request->hasFile('image') ? 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120' : 'nullable',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path;
        }

        $validated['user_id'] = $request->user()->id;

        $product = Product::create($validated);

        return response()->json(['product' => $product], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        return response()->json(['product' => $product]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        $validated = $request->validate([
            'sku' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'cost_usd' => 'nullable|numeric|min:0',
            'margin_percent' => 'nullable|numeric|min:0',
            'recommended_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock_alert' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:50',
            'image' => $request->hasFile('image') ? 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:5120' : 'nullable',
        ]);

        \Log::info('Update Product ID: ' . $id);
        \Log::info('Has File "image": ' . ($request->hasFile('image') ? 'YES' : 'NO'));
        
        if ($request->hasFile('image')) {
            \Log::info('File Size: ' . $request->file('image')->getSize());
            $path = $request->file('image')->store('products', 'public');
            \Log::info('Stored Path: ' . $path);
            $validated['image_url'] = $path;
        }

        $product->update($validated);

        return response()->json(['product' => $product]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado']);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $products = Product::where('user_id', $request->user()->id)
            ->whereColumn('stock', '<=', 'min_stock_alert')
            ->get();

        return response()->json(['products' => $products]);
    }
}
