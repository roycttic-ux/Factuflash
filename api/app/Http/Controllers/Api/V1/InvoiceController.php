<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AfipService;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::with('recipient')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['invoices' => $invoices]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => 'nullable|integer',
            'notify_afip' => 'nullable|boolean',
            'payment_method' => 'nullable|string|in:cash,card,transfer',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $userId = $request->user()->id;
        $dollarRate = $this->getDollarRate();

        try {
            $result = DB::transaction(function () use ($userId, $validated, $dollarRate) {
                $settings = \App\Models\UserSetting::where('user_id', $userId)->first();
                $invoiceNumber = $this->generateInvoiceNumber($userId);

                $totalUsd = 0;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('user_id', $userId)
                        ->first();

                    if (!$product) {
                        throw new \Exception("Producto {$item['product_id']} no encontrado");
                    }

                    $unitPriceUsd = $product->cost_usd;
                    $itemTotalUsd = $unitPriceUsd * $item['quantity'];
                    $totalUsd += $itemTotalUsd;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price_usd' => $unitPriceUsd,
                    ];

                    // Descontar stock
                    $product->stock = max(0, $product->stock - $item['quantity']);
                    $product->save();
                }

                $totalLocal = $totalUsd * $dollarRate;
                $cae = null;
                $vencimiento = null;
                $nextNum = Invoice::where('user_id', $userId)->max('invoice_number') + 1;

                // --- LOGICA AFIP ---
                if (!empty($validated['notify_afip']) && $settings && $settings->arca_cert_path && $settings->arca_key_path) {
                    try {
                        $afip = new AfipService($settings->arca_cuit, $settings->arca_cert_path, $settings->arca_key_path);
                        $res = $afip->createInvoice(['total' => $totalLocal]);
                        $cae = $res['cae'];
                        $vencimiento = $res['vencimiento'];
                        $nextNum = $res['numero'];
                    } catch (\Exception $e) {
                         throw new \Exception("Error AFIP: " . $e->getMessage());
                    }
                }

                $invoice = Invoice::create([
                    'user_id' => $userId,
                    'recipient_id' => $validated['recipient_id'] ?? null,
                    'invoice_number' => $nextNum,
                    'dollar_rate' => $dollarRate,
                    'total_usd' => $totalUsd,
                    'total_local' => $totalLocal,
                    'status' => 'paid',
                    'notify_afip' => $validated['notify_afip'] ?? false,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'cae' => $cae,
                    'cae_expiry' => $vencimiento
                ]);

                foreach ($itemsData as $itemData) {
                    InvoiceItem::create(array_merge($itemData, ['invoice_id' => $invoice->id]));
                }

                return $invoice->load(['recipient', 'items.product']);
            });

            return response()->json(['invoice' => $result], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with(['recipient', 'items.product', 'user.settings'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$invoice) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }

        return response()->json(['invoice' => $invoice]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$invoice) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }

        // Restaurar stock de productos
        foreach ($invoice->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->stock += $item->quantity;
                $product->save();
            }
        }

        $invoice->delete();

        return response()->json(['message' => 'Factura eliminada']);
    }

    private function generateInvoiceNumber(int $userId): string
    {
        $count = Invoice::where('user_id', $userId)->count();
        return 'F-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT) . '-' . time();
    }

    private function getDollarRate(): float
    {
        // Implementar con DollarService real después
        return 1240.0;
    }
}
