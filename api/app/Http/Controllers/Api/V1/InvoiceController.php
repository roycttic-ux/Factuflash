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
        $invoices = Invoice::with(['recipient', 'cashier', 'payments'])
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
            'payment_method' => 'nullable|string|in:cash,card,transfer,mp',
            'payments' => 'nullable|array',
            'payments.*.payment_method' => 'required|string|in:cash,card,transfer,mp',
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.details' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_index' => 'nullable|integer',
        ]);

        $ownerId = $request->user()->system_owner_id;
        $cashierId = ($request->user()->role === 'cashier') ? $request->user()->id : null;
        $dollarRate = $this->getDollarRate($ownerId);

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

                    // Descontar stock (si no es ilimitado/servicio)
                    if (!$product->unlimited_stock) {
                        if (isset($item['variant_index']) && $item['variant_index'] !== null) {
                            $variants = $product->variants;
                            $idx = $item['variant_index'];
                            if (isset($variants[$idx])) {
                                $variants[$idx]['stock'] = max(0, ($variants[$idx]['stock'] ?? 0) - $item['quantity']);
                                $product->variants = $variants;
                            }
                        } else {
                            $product->stock = max(0, $product->stock - $item['quantity']);
                        }
                        $product->save();
                    }
                    
                    $itemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price_usd' => $unitPriceUsd,
                        'variant_index' => $item['variant_index'] ?? null,
                    ];
                }

                $totalLocal = $totalUsd;

                // VALIDACION DE PAGOS: Si hay pagos especificados, deben sumar el total
                if (!empty($validated['payments'])) {
                    $sumPayments = array_sum(array_column($validated['payments'], 'amount'));
                    // Permitimos una pequeña diferencia de centavos por redondeo
                    if (abs($sumPayments - $totalLocal) > 0.01) {
                        throw new \Exception("El total de los pagos ($sumPayments) no coincide con el total de la factura ($totalLocal)");
                    }
                }

                $cae = null;
                $vencimiento = null;
                $nextNumRaw = Invoice::where('user_id', $ownerId)->count() + 1;

                // --- LOGICA AFIP ---
                if (!empty($validated['notify_afip']) && $settings && $settings->arca_cert_path && $settings->arca_key_path) {
                    try {
                        $recipient = !empty($validated['recipient_id']) ? \App\Models\Recipient::find($validated['recipient_id']) : null;
                        
                        // Determinar tipo de comprobante
                        $cbteTipo = 11; // Por defecto Factura C (Monotributo)
                        if ($settings->tax_condition === 'Responsable Inscripto') {
                             // Si el receptor tiene CUIT, Factura A. Si no, Factura B.
                             // Idealmente el Recipient debería tener su propia tax_condition
                             $isCuit = $recipient && strlen(preg_replace('/[^0-9]/', '', $recipient->document_id)) === 11;
                             $cbteTipo = $isCuit ? 1 : 6;
                        }

                        // Determinar tipo de documento del receptor
                        $docTipo = 99; // Consumidor Final
                        $docNro = 0;
                        if ($recipient && $recipient->document_id) {
                            $cleanDoc = preg_replace('/[^0-9]/', '', $recipient->document_id);
                            $docNro = (float) $cleanDoc;
                            if (strlen($cleanDoc) === 11) $docTipo = 80; // CUIT
                            elseif (strlen($cleanDoc) === 8) $docTipo = 96; // DNI
                        }

                        $afip = app()->makeWith(AfipService::class, [
                            'cuit' => $settings->arca_cuit, 
                            'cert_path' => $settings->arca_cert_path, 
                            'key_path' => $settings->arca_key_path
                        ]);
                        $res = $afip->createInvoice([
                            'total' => $totalLocal,
                            'cbte_tipo' => $cbteTipo,
                            'doc_tipo' => $docTipo,
                            'doc_nro' => $docNro,
                            'pto_vta' => $settings->arca_pto_vta ?? 1
                        ]);
                        
                        $cae = !empty($res['cae']) ? $res['cae'] : null;
                        // AFIP devuelve la fecha como YYYYMMDD sin guiones.
                        // Convertimos a YYYY-MM-DD para que el cast 'date' de Eloquent funcione correctamente.
                        $rawVto = $res['vencimiento'] ?? null;
                        if ($rawVto && strlen($rawVto) === 8 && !str_contains($rawVto, '-')) {
                            $rawVto = substr($rawVto, 0, 4) . '-' . substr($rawVto, 4, 2) . '-' . substr($rawVto, 6, 2);
                        }
                        $vencimiento = $rawVto ?: null;
                        if (!empty($res['numero'])) {
                            $nextNumRaw = $res['numero'];
                        }
                    } catch (\Exception $e) {
                         throw new \Exception("Error AFIP: " . $e->getMessage());
                    }
                }

                // Generar número único corporativo: Prefijo - PtoVta - NumeroCeroRelleno
                $prefix = $settings->invoice_prefix ?? 'C';
                $ptoVtaVal = $settings->arca_pto_vta ?? 1;
                $ptoVta = str_pad($ptoVtaVal, 4, '0', STR_PAD_LEFT);
                $formattedNum = $prefix . '-' . $ptoVta . '-' . str_pad($nextNumRaw, 8, '0', STR_PAD_LEFT);

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
                    'payment_method' => $validated['payment_method'] ?? ($validated['payments'][0]['payment_method'] ?? 'cash'),
                    'is_multiple_payment' => !empty($validated['payments']),
                    'cae' => $cae,
                    'cae_expiry' => $vencimiento,
                    'cbte_tipo' => $cbteTipo ?? null,
                    'pto_vta' => $settings->arca_pto_vta ?? 1,
                ]);

                // Guardar pagos múltiples si existen
                if (!empty($validated['payments'])) {
                    foreach ($validated['payments'] as $p) {
                        $invoice->payments()->create([
                            'payment_method' => $p['payment_method'],
                            'amount' => $p['amount'],
                            'details' => $p['details'] ?? null,
                        ]);
                    }
                }

                foreach ($itemsData as $itemData) {
                    InvoiceItem::create(array_merge($itemData, ['invoice_id' => $invoice->id]));
                }

                return $invoice->load(['recipient', 'items.product', 'payments']);
            });

            return response()->json(['invoice' => $result], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $request->user()->system_owner_id;
        $invoice = Invoice::with(['recipient', 'items.product', 'user.settings', 'cashier', 'payments'])
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

        if ($invoice->status === 'annulled') {
            return response()->json(['error' => 'Esta factura ya está anulada'], 422);
        }

        // --- SI TIENE CAE, HACER NOTA DE CRÉDITO ---
        if ($invoice->cae && $invoice->notify_afip) {
            try {
                $settings = \App\Models\UserSetting::where('user_id', $ownerId)->first();
                if (!$settings || !$settings->arca_cert_path) {
                    throw new \Exception("Configuración de AFIP no encontrada para anular.");
                }

                $afip = app()->makeWith(AfipService::class, [
                    'cuit' => $settings->arca_cuit, 
                    'cert_path' => $settings->arca_cert_path, 
                    'key_path' => $settings->arca_key_path
                ]);
                
                // Mapeo Factura -> NC
                // 11 (C) -> 13
                // 1 (A) -> 3
                // 6 (B) -> 8
                $ncTipo = 13;
                if ($invoice->cbte_tipo == 1) $ncTipo = 3;
                if ($invoice->cbte_tipo == 6) $ncTipo = 8;

                // Extraer el número secuencial de AFIP del invoice_number
                // Formato: PREFIX-PTOVTA-NUMBER (ej: C-0001-00000005)
                $parts = explode('-', $invoice->invoice_number);
                $origNum = (int) end($parts);

                $res = $afip->createCreditNote([
                    'total' => $invoice->total_local,
                    'cbte_tipo' => $ncTipo,
                    'doc_tipo' => $invoice->recipient ? (strlen(preg_replace('/[^0-9]/', '', $invoice->recipient->document_id)) === 11 ? 80 : 96) : 99,
                    'doc_nro' => $invoice->recipient ? (float)preg_replace('/[^0-9]/', '', $invoice->recipient->document_id) : 0,
                    'pto_vta' => $invoice->pto_vta ?? 1,
                    'orig_cbte_tipo' => $invoice->cbte_tipo ?? 11,
                    'orig_pto_vta' => $invoice->pto_vta ?? 1,
                    'orig_cbte_nro' => $origNum
                ]);

                $invoice->status = 'annulled';
                $invoice->nc_cae = $res['cae'];
                $invoice->nc_number = $res['numero'];
                $invoice->save();

            } catch (\Exception $e) {
                return response()->json(['error' => "Error al anular en AFIP: " . $e->getMessage()], 422);
            }
        } else {
            // Si no tiene CAE, simplemente la borramos (o la marcamos como anulada localmente)
            // El usuario dijo "hace notas de credito si es una factura valida nomas"
            // Mantendremos el borrado físico si no tiene CAE para limpiar la base.
            $invoice->delete();
        }

        // Restaurar stock en cualquier caso (sea borrado o anulado)
        foreach ($invoice->items as $item) {
            $product = Product::find($item->product_id);
            if ($product && !$product->unlimited_stock) {
                if ($item->variant_index !== null) {
                    $variants = $product->variants;
                    $idx = $item->variant_index;
                    if (isset($variants[$idx])) {
                        $variants[$idx]['stock'] = ($variants[$idx]['stock'] ?? 0) + $item->quantity;
                        $product->variants = $variants;
                    }
                } else {
                    $product->stock += $item->quantity;
                }
                $product->save();
            }
        }

        return response()->json(['message' => ($invoice->nc_cae ? 'Nota de Crédito generada con éxito. Factura anulada.' : 'Factura eliminada con éxito.')]);
    }

    private function generateInvoiceNumber(int $userId): string
    {
        $count = Invoice::where('user_id', $userId)->count();
        return 'F-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT) . '-' . time();
    }

    private function getDollarRate(int $ownerId): float
    {
        $settings = \App\Models\UserSetting::where('user_id', $ownerId)->first();
        
        if ($settings) {
            if ($settings->dollar_rate_mode === 'manual' && $settings->dollar_rate_manual > 0) {
                return (float) $settings->dollar_rate_manual;
            }
            if ($settings->dollar_rate > 0) {
                return (float) $settings->dollar_rate;
            }
        }
        
        return 1250.0; // Fallback general
    }
}
