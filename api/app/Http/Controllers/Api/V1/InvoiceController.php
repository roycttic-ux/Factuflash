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
        $ownerId = $request->user()->system_owner_id;
        $invoices = Invoice::with('recipient', 'cashier')
            ->where('user_id', $ownerId)
            ->when($request->user()->role === 'cashier', function($q) use ($request) {
                return $q->where('cashier_id', $request->user()->id);
            })
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

        $ownerId = $request->user()->system_owner_id;
        $cashierId = ($request->user()->role === 'cashier') ? $request->user()->id : null;
        $dollarRate = $this->getDollarRate();

        try {
            $result = DB::transaction(function () use ($ownerId, $cashierId, $validated, $dollarRate) {
                $settings = \App\Models\UserSetting::where('user_id', $ownerId)->first();

                $totalUsd = 0;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('user_id', $ownerId)
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
                $nextNumRaw = Invoice::where('user_id', $ownerId)->count() + 1;

                // --- LOGICA AFIP ---
                if (!empty($validated['notify_afip']) && $settings && $settings->arca_cert_path && $settings->arca_key_path) {
                    try {
                        $afip = new AfipService($settings->arca_cuit, $settings->arca_cert_path, $settings->arca_key_path);
                        $res = $afip->createInvoice(['total' => $totalLocal]);
                        $cae = !empty($res['cae']) ? $res['cae'] : null;
                        $vencimiento = !empty($res['vencimiento']) ? $res['vencimiento'] : null;
                        if (!empty($res['numero'])) {
                            $nextNumRaw = $res['numero'];
                        }
                    } catch (\Exception $e) {
                         throw new \Exception("Error AFIP: " . $e->getMessage());
                    }
                }

                // Generar número único corporativo: IDUsuario - NumeroCeroRelleno (Ej: 0001-00000005)
                // Usaremos un punto de venta ficticio (0001) para cuando no hay AFIP
                $ptoVta = str_pad($ownerId, 4, '0', STR_PAD_LEFT);
                $formattedNum = 'C' . '-' . $ptoVta . '-' . str_pad($nextNumRaw, 8, '0', STR_PAD_LEFT);

                // Evitar posibles colisiones a la fuerza si algún mock repite el número
                $uniqueTry = $formattedNum;
                $counter = 1;
                while(Invoice::where('invoice_number', $uniqueTry)->exists()){
                    $uniqueTry = $formattedNum . '-' . $counter;
                    $counter++;
                }

                $invoice = Invoice::create([
                    'user_id' => $ownerId,
                    'cashier_id' => $cashierId,
                    'recipient_id' => $validated['recipient_id'] ?? null,
                    'invoice_number' => $uniqueTry,
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
        $ownerId = $request->user()->system_owner_id;
        $invoice = Invoice::with(['recipient', 'items.product', 'user.settings', 'cashier'])
            ->where('id', $id)
            ->where('user_id', $ownerId)
            ->first();

        if (!$invoice) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }

        return response()->json(['invoice' => $invoice]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $ownerId = $request->user()->system_owner_id;
        $invoice = Invoice::where('id', $id)
            ->where('user_id', $ownerId)
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
