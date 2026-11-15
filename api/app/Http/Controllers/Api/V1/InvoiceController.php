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
use App\Services\InvoicePaymentService;
use App\Services\InvoiceFiscalService;

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
            'recipient_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('recipients', 'id')->where('user_id', $request->user()->system_owner_id)],
            'notify_afip' => 'nullable|boolean',
            'payment_method' => 'nullable|string|in:cash,card,transfer,mp',
            'submission_key' => 'nullable|uuid|required_if:manual_payment_confirmed,true|required_with:tributos',
            'manual_payment_confirmed' => 'nullable|boolean',
            'payments' => 'nullable|array|min:1|max:20|required_if:manual_payment_confirmed,true',
            'payments.*.payment_method' => 'required|string|in:cash,card,transfer,mp',
            'payments.*.amount' => 'required|numeric|min:0.01|decimal:0,2',
            'payments.*.details' => 'nullable|string|max:200',
            'payments.*.reference' => 'nullable|string|max:150',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_index' => 'nullable|integer|min:0',
        ] + InvoiceFiscalService::tributeRules());

        $ownerId = $request->user()->system_owner_id;
        $operatorId = $request->user()->id;
        $cashierId = ($request->user()->role === 'cashier') ? $request->user()->id : null;
        $dollarRate = $this->getDollarRate($ownerId);
        $submissionKey = $validated['submission_key'] ?? (string) \Illuminate\Support\Str::uuid();
        $validated['tributos'] = app(InvoiceFiscalService::class)->normalizeTributos($validated['tributos'] ?? []);
        $submissionHash = $this->submissionHash($validated);

        try {
            $result = DB::transaction(function () use ($ownerId, $operatorId, $cashierId, $validated, $dollarRate, $submissionKey, $submissionHash) {
                // A retry must never request a second fiscal voucher or reserve stock twice.
                \App\Models\User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
                $existing = Invoice::where('user_id', $ownerId)->where('submission_key', $submissionKey)->first();
                if ($existing) {
                    if ($existing->submission_hash && !hash_equals($existing->submission_hash, $submissionHash)) {
                        throw \App\Exceptions\ArcaException::permanent('Esta operación ya fue emitida con otros datos. No se modificó la factura autorizada.');
                    }
                    return $existing->load(['recipient', 'items.product', 'payments']);
                }
                $manual = !empty($validated['manual_payment_confirmed']);
                $references = [];
                if ($manual) {
                    foreach ($validated['payments'] as $index => $payment) {
                        if (!in_array($payment['payment_method'], ['card', 'mp'])) continue;
                        $reference = trim($payment['reference'] ?? '');
                        if ($reference === '') {
                            throw new \Exception('Ingresá el comprobante de cada cobro con tarjeta o Mercado Pago.');
                        }
                        $transaction = InvoicePaymentService::manualTransaction($reference);
                        if (in_array($transaction, $references, true)) {
                            throw new \Exception('El mismo comprobante no puede utilizarse en dos pagos.');
                        }
                        app(InvoicePaymentService::class)->assertUnused($ownerId, $transaction);
                        $references[$index] = $transaction;
                    }
                }
                $settings = \App\Models\UserSetting::where('user_id', $ownerId)->first();
                if (!$settings || !$settings->arca_cert_path || !$settings->arca_key_path) {
                    throw new \Exception('Debés vincular tu cuenta con AFIP/ARCA antes de emitir facturas.');
                }

                $totalUsd = 0;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::where('id', $item['product_id'])
                        ->where('user_id', $ownerId)
                        ->lockForUpdate()
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
                            if (!isset($variants[$idx]) || ($variants[$idx]['stock'] ?? 0) < $item['quantity']) {
                                throw new \Exception("Variante inválida o stock insuficiente para {$product->name}");
                            }
                            if (isset($variants[$idx])) {
                                $variants[$idx]['stock'] -= $item['quantity'];
                                $product->variants = $variants;
                            }
                        } else {
                            if ($product->stock < $item['quantity']) {
                                throw new \Exception("Stock insuficiente para {$product->name}");
                            }
                            $product->stock -= $item['quantity'];
                        }
                        $product->save();
                    }
                    
                    $itemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price_usd' => $unitPriceUsd,
                        'description_snapshot' => $product->name,
                        'iva_id' => $product->iva_id,
                        'variant_index' => $item['variant_index'] ?? null,
                    ];
                }

                $recipient = !empty($validated['recipient_id']) ? \App\Models\Recipient::find($validated['recipient_id']) : null;
                $fiscalSnapshot = app(\App\Services\InvoiceFiscalService::class)->calculate($settings, $recipient, $itemsData, $validated['tributos'] ?? []);
                $cbteTipo = $fiscalSnapshot['cbte_tipo'];
                $docTipo = $fiscalSnapshot['doc_tipo'];
                $docNro = $fiscalSnapshot['doc_nro'];
                $totalLocal = $fiscalSnapshot['total_local'];
                $totalTributos = $fiscalSnapshot['tributos_total'] ?? 0;

                // VALIDACION DE PAGOS: Si hay pagos especificados, deben sumar el total (incluyendo percepciones)
                if (!empty($validated['payments'])) {
                    $sumPayments = array_sum(array_map(fn ($payment) => (int) round($payment['amount'] * 100), $validated['payments']));
                    if ($sumPayments !== (int) round($totalLocal * 100)) {
                        $sumPayments /= 100;
                        throw new \Exception("El total de los pagos ($sumPayments) no coincide con el total de la factura ($totalLocal)");
                    }
                }

                $cae = null;
                $vencimiento = null;
                $nextNumRaw = Invoice::where('user_id', $ownerId)->count() + 1;

                // --- LOGICA AFIP ---
                if (!$settings || !$settings->arca_cert_path || !$settings->arca_key_path) {
                    throw new \Exception("Debés vincular tu cuenta con AFIP/ARCA antes de emitir facturas.");
                }

                try {
                        $afip = app()->makeWith(AfipService::class, [
                            'cuit' => $settings->arca_cuit, 
                            'cert_path' => $settings->arca_cert_path, 
                            'key_path' => $settings->arca_key_path
                        ]);
                        $res = $afip->createInvoice([
                            'request_key' => 'invoice:' . $ownerId . ':' . $submissionKey,
                            'fiscal' => $fiscalSnapshot['fiscal'],
                            'condicion_iva_receptor' => $fiscalSnapshot['condicion_iva_receptor'],
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
                    } catch (\App\Exceptions\ArcaException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        throw new \Exception("Error AFIP: " . $e->getMessage(), 0, $e);
                    }

                // Generar número único corporativo: Prefijo - PtoVta - NumeroCeroRelleno
                $prefix = \App\Services\InvoiceFiscalService::cbtePrefix($cbteTipo);
                $ptoVtaVal = $settings->arca_pto_vta ?? 1;
                $ptoVta = str_pad($ptoVtaVal, 4, '0', STR_PAD_LEFT);
                $formattedNum = $prefix . '-' . $ptoVta . '-' . str_pad($nextNumRaw, 8, '0', STR_PAD_LEFT);

                // Fiscal numbers belong to the issuer; never append a fictitious sequence.
                $uniqueTry = $formattedNum;

                $method = $validated['payment_method'] ?? ($validated['payments'][0]['payment_method'] ?? 'cash');
                $payments = $validated['payments'] ?? [];
                $pending = !$manual && ($payments
                    ? collect($payments)->contains(fn ($p) => in_array($p['payment_method'], ['card', 'mp']) && $p['amount'] > 0)
                    : in_array($method, ['card', 'mp']));
                $invoice = Invoice::create([
                    'user_id' => $ownerId,
                    'cashier_id' => $cashierId,
                    'recipient_id' => $validated['recipient_id'] ?? null,
                    'invoice_number' => $uniqueTry,
                    'dollar_rate' => $dollarRate,
                    'total_usd' => $totalUsd,
                    'total_local' => $totalLocal,
                    'total_tributos' => $totalTributos,
                    'fiscal_snapshot' => $fiscalSnapshot,
                    'status' => $pending ? 'pending' : 'paid',
                    'submission_key' => $submissionKey,
                    'submission_hash' => $submissionHash,
                    'notify_afip' => true,
                    'payment_method' => $validated['payment_method'] ?? ($validated['payments'][0]['payment_method'] ?? 'cash'),
                    'is_multiple_payment' => count($payments) > 1,
                    // Freeze after inserting the original lines in this transaction.
                    'cae' => null,
                    'cae_expiry' => null,
                    'cbte_tipo' => $cbteTipo ?? null,
                    'pto_vta' => $settings->arca_pto_vta ?? 1,
                ]);

                // Guardar pagos múltiples si existen
                if (!empty($validated['payments'])) {
                    foreach ($validated['payments'] as $index => $p) {
                        $electronic = in_array($p['payment_method'], ['card', 'mp']);
                        if ($electronic && !$manual) continue;
                        $invoice->payments()->create([
                            'payment_method' => $p['payment_method'],
                            'amount' => $p['amount'],
                            'details' => $electronic
                                ? 'Confirmación manual por usuario ' . $operatorId . '. Comprobante: ' . trim($p['reference'])
                                : ($p['details'] ?? null),
                            'transaction_id' => $references[$index] ?? null,
                        ]);
                    }
                } elseif (!$pending) {
                    $invoice->payments()->create(['payment_method' => $method, 'amount' => $totalLocal]);
                }

                foreach ($itemsData as $itemData) {
                    InvoiceItem::create(array_merge($itemData, ['invoice_id' => $invoice->id]));
                }
                $invoice->update(['cae' => $cae, 'cae_expiry' => $vencimiento]);

                return $invoice->load(['recipient', 'items.product', 'payments']);
            });

            return response()->json(['invoice' => $result], 201);
        } catch (\App\Exceptions\ArcaException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'retryable' => $e->isRetryable(),
                'submission_key' => $submissionKey,
            ], $e->isRetryable() ? 409 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'retryable' => false,
            ], 422);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ownerId = $request->user()->system_owner_id;
        $invoice = Invoice::with(['recipient', 'items.product', 'user.settings', 'cashier', 'payments', 'tributeAudits.user:id,name'])
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
        return DB::transaction(function () use ($ownerId, $id) {
        \App\Models\User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
        $invoice = Invoice::with('items')->where('id', $id)
            ->where('user_id', $ownerId)
            ->lockForUpdate()->first();

        if (!$invoice) {
            return response()->json(['error' => 'Factura no encontrada'], 404);
        }

        if ($invoice->status === 'annulled') {
            return response()->json(['error' => 'Esta factura ya está anulada'], 422);
        }

        // --- SI TIENE CAE, HACER NOTA DE CRÉDITO ---
        if ($invoice->cae) {
            try {
                $settings = \App\Models\UserSetting::where('user_id', $ownerId)->first();
                if (!$settings || !$settings->arca_cert_path) {
                    throw new \Exception("Configuración de AFIP no encontrada para anular.");
                }
                $issuerCuit = $invoice->fiscal_snapshot['issuer_cuit'] ?? $settings->arca_cuit;
                if (preg_replace('/\D/', '', (string) $issuerCuit) !== preg_replace('/\D/', '', (string) $settings->arca_cuit)) {
                    throw new \Exception('Para anular debés vincular el CUIT que emitió la factura original.');
                }
                if ((float) $invoice->total_tributos > 0 && empty($invoice->fiscal_snapshot['fiscal']['tributos'])) {
                    throw new \Exception('Falta el detalle histórico de tributos de la factura original. No se puede emitir una nota de crédito incompleta.');
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
                $ncTipo = \App\Services\InvoiceFiscalService::creditNoteType((int) ($invoice->cbte_tipo ?? 11));

                // Extraer el número secuencial de AFIP del invoice_number
                // Formato: PREFIX-PTOVTA-NUMBER (ej: C-0001-00000005)
                $parts = explode('-', $invoice->invoice_number);
                $origNum = (int) ($parts[2] ?? end($parts));

                $res = $afip->createCreditNote([
                    'request_key' => 'credit:' . $ownerId . ':' . $invoice->id,
                    'fiscal' => $invoice->fiscal_snapshot['fiscal'] ?? null,
                    'condicion_iva_receptor' => $invoice->fiscal_snapshot['condicion_iva_receptor'] ?? 5,
                    'total' => $invoice->total_local,
                    'cbte_tipo' => $ncTipo,
                    'doc_tipo' => $invoice->fiscal_snapshot['doc_tipo'] ?? ($invoice->recipient ? (strlen(preg_replace('/[^0-9]/', '', $invoice->recipient->document_id)) === 11 ? 80 : 96) : 99),
                    'doc_nro' => $invoice->fiscal_snapshot['doc_nro'] ?? ($invoice->recipient ? (float)preg_replace('/[^0-9]/', '', $invoice->recipient->document_id) : 0),
                    'pto_vta' => $invoice->pto_vta ?? 1,
                    'orig_cbte_tipo' => $invoice->cbte_tipo ?? 11,
                    'orig_pto_vta' => $invoice->pto_vta ?? 1,
                    'orig_cbte_nro' => $origNum
                ]);

                $invoice->status = 'annulled';
                $invoice->nc_cae = $res['cae'];
                $invoice->nc_number = $res['numero'];
                $invoice->nc_cae_expiry = \Illuminate\Support\Carbon::createFromFormat('Ymd', $res['vencimiento'])->format('Y-m-d');
                $invoice->nc_issued_at = now();
                $invoice->save();

            } catch (\App\Exceptions\ArcaException $e) {
                return response()->json([
                    'error' => "Error al anular en AFIP: " . $e->getMessage(),
                    'retryable' => $e->isRetryable(),
                ], $e->isRetryable() ? 409 : 422);
            } catch (\Throwable $e) {
                return response()->json(['error' => "Error al anular en AFIP: " . $e->getMessage(), 'retryable' => false], 422);
            }
        } else {
            // Si no tiene CAE, simplemente la borramos (o la marcamos como anulada localmente)
            // El usuario dijo "hace notas de credito si es una factura valida nomas"
            // Mantendremos el borrado físico si no tiene CAE para limpiar la base.
            $invoice->update(['status' => 'annulled']);
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

        return response()->json(['message' => ($invoice->nc_cae ? 'Nota de Crédito generada con éxito. Factura anulada.' : 'Comprobante sin CAE anulado localmente.')]);
        });
    }

    private function submissionHash(array $payload): string
    {
        unset($payload['submission_key']);
        $sort = function (array $value) use (&$sort): array {
            if (!array_is_list($value)) ksort($value);
            foreach ($value as &$child) if (is_array($child)) $child = $sort($child);
            return $value;
        };
        return hash('sha256', json_encode($sort($payload), JSON_THROW_ON_ERROR));
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

    // ─────────────────────────────────────────────────────────────────────────
    // BLOQUE 3 — PDF + EXPORTACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera y descarga el PDF de una factura individual.
     * GET /invoices/{id}/pdf
     */
    public function downloadPdf(Request $request, int $id): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $ownerId = $request->user()->system_owner_id;
        $invoice = Invoice::with(['recipient', 'items.product', 'user.settings'])
            ->where('user_id', $ownerId)
            ->findOrFail($id);

        $settings = \App\Models\UserSetting::where('user_id', $ownerId)->first();

        // Intentar usar DomPDF si está instalado; si no, generar HTML básico descargable
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                'invoice'  => $invoice,
                'settings' => $settings,
            ])->setPaper('a4', 'portrait');

            $filename = 'factura-' . str_replace(['/', ' '], '-', $invoice->invoice_number) . '.pdf';
            return $pdf->download($filename);
        }

        // Fallback: HTML como descarga (DomPDF aún no instalado)
        $html = view('pdf.invoice', ['invoice' => $invoice, 'settings' => $settings])->render();
        $filename = 'factura-' . str_replace(['/', ' '], '-', $invoice->invoice_number) . '.html';
        return response($html, 200, [
            'Content-Type'        => 'text/html',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Exporta facturas en formato Excel (CSV con BOM, compatible con Excel/LibreOffice).
     * GET /invoices/export/excel?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $ownerId = $request->user()->system_owner_id;

        $query = Invoice::with(['recipient'])
            ->where('user_id', $ownerId)
            ->orderBy('created_at');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $invoices = $query->get();

        $label = 'facturas';
        if ($request->filled('from') || $request->filled('to')) {
            $label .= '_' . ($request->from ?? 'inicio') . '_a_' . ($request->to ?? 'hoy');
        }

        return response()->streamDownload(function () use ($invoices) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para compatibilidad con Excel en Windows
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, [
                'Fecha', 'Nro Comprobante', 'Tipo', 'Cliente', 'CUIT/DNI',
                'Condición IVA', 'Subtotal Neto', 'IVA', 'Tributos', 'Total', 'CAE', 'Venc. CAE', 'Estado',
            ], ';');

            foreach ($invoices as $inv) {
                $tipo = match ((int)($inv->cbte_tipo ?? 11)) {
                    1  => 'Factura A',
                    6  => 'Factura B',
                    11 => 'Factura C',
                    3  => 'NC A',
                    8  => 'NC B',
                    13 => 'NC C',
                    default => 'Comprobante',
                };
                $snap = $inv->fiscal_snapshot ?? [];
                $neto = $snap['neto_gravado'] ?? ($inv->total_local - ($snap['iva_total'] ?? 0) - ($inv->total_tributos ?? 0));
                $iva  = $snap['iva_total'] ?? 0;

                fputcsv($out, [
                    $inv->created_at->format('d/m/Y'),
                    $inv->invoice_number,
                    $tipo,
                    $inv->recipient?->name ?? 'Consumidor Final',
                    $inv->recipient?->document_id ?? '',
                    $inv->recipient?->iva_condition ?? '',
                    number_format((float) $neto, 2, ',', '.'),
                    number_format((float) $iva, 2, ',', '.'),
                    number_format((float) ($inv->total_tributos ?? 0), 2, ',', '.'),
                    number_format((float) $inv->total_local, 2, ',', '.'),
                    $inv->cae ?? '',
                    $inv->cae_expiry ? \Carbon\Carbon::parse($inv->cae_expiry)->format('d/m/Y') : '',
                    $inv->status ?? 'emitida',
                ], ';');
            }

            fclose($out);
        }, $label . '.csv', [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Cache-Control'       => 'no-cache',
        ]);
    }

    /**
     * Exporta un ZIP con los PDFs de las facturas del período.
     * GET /invoices/export/zip?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function exportZip(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        if (!class_exists(\ZipArchive::class)) {
            return response()->json(['error' => 'La extensión ZIP no está habilitada en el servidor.'], 501);
        }

        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $ownerId = $request->user()->system_owner_id;
        $settings = \App\Models\UserSetting::where('user_id', $ownerId)->first();

        $query = Invoice::with(['recipient', 'items.product', 'user.settings'])
            ->where('user_id', $ownerId)
            ->orderBy('created_at');

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $invoices = $query->get();

        if ($invoices->isEmpty()) {
            return response()->json(['error' => 'No hay facturas en el período seleccionado.'], 404);
        }

        $tmpDir = sys_get_temp_dir() . '/ff_zip_' . uniqid();
        mkdir($tmpDir, 0755, true);
        $zipPath = $tmpDir . '/facturas.zip';

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);

        foreach ($invoices as $invoice) {
            if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                // Fallback HTML si dompdf no está
                $html = view('pdf.invoice', ['invoice' => $invoice, 'settings' => $settings])->render();
                $fname = 'factura-' . str_replace(['/', ' '], '-', $invoice->invoice_number) . '.html';
                $zip->addFromString($fname, $html);
            } else {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                    'invoice'  => $invoice,
                    'settings' => $settings,
                ])->setPaper('a4', 'portrait');
                $fname = 'factura-' . str_replace(['/', ' '], '-', $invoice->invoice_number) . '.pdf';
                $zip->addFromString($fname, $pdf->output());
            }
        }

        $zip->close();
        $zipContent = file_get_contents($zipPath);
        @unlink($zipPath);
        @rmdir($tmpDir);

        $label = 'facturas';
        if ($request->filled('from') || $request->filled('to')) {
            $label .= '_' . ($request->from ?? 'inicio') . '_a_' . ($request->to ?? 'hoy');
        }

        return response($zipContent, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $label . '.zip"',
            'Cache-Control'       => 'no-cache',
        ]);
    }
}
