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
        $products = Product::where('user_id', $request->user()->system_owner_id)
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
            'unlimited_stock' => 'nullable|boolean',
            'min_stock_alert' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string',
            'variants' => 'nullable|string', // Se espera JSON string desde FormData
            'image' => $request->hasFile('image') ? 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:2048' : 'nullable',
            'extra_images' => 'nullable|array|max:5',
            'extra_images.*' => 'file|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path;
        }

        if ($request->hasFile('extra_images')) {
            $extraPaths = [];
            foreach ($request->file('extra_images') as $file) {
                $extraPaths[] = $file->store('products', 'public');
            }
            $validated['images'] = $extraPaths;
        }

        if ($request->has('variants')) {
            $validated['variants'] = json_decode($request->variants, true);
        }

        $validated['user_id'] = $request->user()->system_owner_id;

        $product = Product::create($validated);

        return response()->json(['product' => $product], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        return response()->json(['product' => $product]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
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
            'unlimited_stock' => 'nullable|boolean',
            'min_stock_alert' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string',
            'variants' => 'nullable|string', // Se espera JSON string
            'existing_images' => 'nullable|string', // JSON array de strings (paths existentes a mantener)
            'image' => $request->hasFile('image') ? 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:2048' : 'nullable',
            'extra_images' => 'nullable|array|max:5',
            'extra_images.*' => 'file|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path;
        }

        // Sincronización de imágenes extra
        $extraPaths = [];
        if ($request->has('existing_images')) {
            $extraPaths = json_decode($request->existing_images, true) ?? [];
        } else {
            // Si no se envía existing_images, mantenemos las que estaban (retrocompatibilidad)
            $extraPaths = $product->images ?? [];
        }

        if ($request->hasFile('extra_images')) {
            foreach ($request->file('extra_images') as $file) {
                if (count($extraPaths) < 10) { // Ampliamos un poco el límite si es necesario
                    $extraPaths[] = $file->store('products', 'public');
                }
            }
        }
        $validated['images'] = $extraPaths;

        if ($request->has('variants')) {
            $validated['variants'] = json_decode($request->variants, true);
        }

        $product->update($validated);

        return response()->json(['product' => $product]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = Product::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado']);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $products = Product::where('user_id', $request->user()->system_owner_id)
            ->whereColumn('stock', '<=', 'min_stock_alert')
            ->get();

        return response()->json(['products' => $products]);
    }

    public function applyInflation(Request $request): JsonResponse
    {
        $request->validate([
            'percentage' => 'required|numeric|min:0',
            'category' => 'nullable|string'
        ]);

        $multiplier = 1 + ($request->percentage / 100);

        $query = Product::where('user_id', $request->user()->system_owner_id);

        if ($request->has('category') && $request->category !== '' && $request->category !== 'Todas') {
            $query->where('tags', 'LIKE', '%' . $request->category . '%');
        }

        $query->update([
            'cost_usd' => \DB::raw("ROUND(cost_usd * {$multiplier})")
        ]);

        return response()->json(['message' => 'Precios actualizados de acuerdo a la inflación']);
    }

    public function getTags(Request $request): JsonResponse
    {
        $ownerId = $request->user()->system_owner_id;
        $tagsString = Product::where('user_id', $ownerId)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->implode(',');

        $tagsArray = array_unique(array_filter(array_map('trim', explode(',', $tagsString))));
        
        return response()->json(['tags' => array_values($tagsArray)]);
    }
}
