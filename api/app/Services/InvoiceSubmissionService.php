<?php

namespace App\Services;

use App\Exceptions\ArcaException;
use App\Models\{Invoice, InvoiceItem, InvoiceSubmission, Product, Recipient, User, UserSetting};
use Illuminate\Support\Facades\DB;

class InvoiceSubmissionService
{
    public function submit(User $operator, array $payload, string $key, string $hash): Invoice
    {
        $attempt = DB::transaction(function () use ($operator, $payload, $key, $hash) {
            $ownerId = $operator->system_owner_id;
            User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
            $existing = Invoice::where('user_id', $ownerId)->where('submission_key', $key)->first();
            if ($existing) {
                abort_if($operator->role === 'cashier' && $existing->cashier_id !== $operator->id, 404);
                if ($existing->submission_hash && !hash_equals($existing->submission_hash, $hash)) {
                    throw ArcaException::permanent('Esta operación ya fue emitida con otros datos.');
                }
                return $existing;
            }
            $attempt = InvoiceSubmission::where('user_id', $ownerId)->where('submission_key', $key)->first();
            if ($attempt) {
                abort_if($operator->role === 'cashier' && $attempt->operator_id !== $operator->id, 404);
                if (!hash_equals($attempt->submission_hash, $hash)) throw ArcaException::permanent('El intento pendiente tiene otros datos. Recuperá la operación original.');
                if ($attempt->status === 'rejected') throw ArcaException::permanent($attempt->last_error ?: 'ARCA rechazó este intento. Creá una nueva operación.');
                return $attempt;
            }
            $pending = InvoiceSubmission::where('user_id', $ownerId)->where('operator_id', $operator->id)->where('status', 'pending')->first();
            if ($pending) throw ArcaException::transient('Tenés una emisión pendiente. Recuperala antes de iniciar otra.', null, $pending->submission_key);

            $settings = UserSetting::where('user_id', $ownerId)->first();
            if (!$settings || !$settings->arca_cert_path || !$settings->arca_key_path) {
                throw ArcaException::permanent('Debés vincular tu cuenta con AFIP/ARCA antes de emitir facturas.');
            }
            $manual = !empty($payload['manual_payment_confirmed']);
            $references = [];
            foreach ($manual ? ($payload['payments'] ?? []) : [] as $index => $payment) {
                if (!in_array($payment['payment_method'], ['card', 'mp'])) continue;
                $reference = trim($payment['reference'] ?? '');
                if ($reference === '') throw ArcaException::permanent('Ingresá el comprobante de cada cobro con tarjeta o Mercado Pago.');
                $transaction = InvoicePaymentService::manualTransaction($reference);
                if (in_array($transaction, $references, true)) throw ArcaException::permanent('El mismo comprobante no puede utilizarse en dos pagos.');
                app(InvoicePaymentService::class)->assertUnused($ownerId, $transaction);
                $references[$index] = $transaction;
            }
            $items = [];
            $totalUsd = 0;
            foreach ($payload['items'] as $item) {
                $product = Product::where('user_id', $ownerId)->whereKey($item['product_id'])->lockForUpdate()->first();
                if (!$product) throw ArcaException::permanent("Producto {$item['product_id']} no encontrado");
                $stock = ProductVariantService::reserve($product, $item);
                $items[] = [
                    'product_id' => $product->id, 'quantity' => $item['quantity'],
                    'unit_price_usd' => $product->cost_usd, 'description_snapshot' => $product->name,
                    'iva_id' => $product->iva_id, 'variant_index' => $item['variant_index'] ?? null,
                ] + $stock;
                $totalUsd += $product->cost_usd * $item['quantity'];
            }
            $recipient = !empty($payload['recipient_id']) ? Recipient::where('user_id', $ownerId)->findOrFail($payload['recipient_id']) : null;
            $fiscal = app(InvoiceFiscalService::class)->calculate($settings, $recipient, $items, $payload['tributos'] ?? []);
            $total = $fiscal['total_local'];
            if (!empty($payload['payments']) && array_sum(array_map(fn ($p) => (int) round($p['amount'] * 100), $payload['payments'])) !== (int) round($total * 100)) {
                throw ArcaException::permanent('El total de los pagos no coincide con el total de la factura.');
            }
            $snapshot = [
                'payload' => $payload, 'items' => $items, 'fiscal' => $fiscal, 'references' => $references,
                'total_usd' => $totalUsd, 'cashier_id' => $operator->role === 'cashier' ? $operator->id : null,
                'dollar_rate' => ($settings->dollar_rate_mode === 'manual' && $settings->dollar_rate_manual > 0)
                    ? (float) $settings->dollar_rate_manual : ((float) $settings->dollar_rate ?: 1250),
                'issuer' => ['cuit' => $settings->arca_cuit, 'cert_path' => $settings->arca_cert_path, 'key_path' => $settings->arca_key_path],
                'point' => $settings->arca_pto_vta ?: 1,
                'issued_date' => now('America/Argentina/Buenos_Aires')->format('Ymd'),
            ];
            return InvoiceSubmission::create([
                'user_id' => $ownerId, 'operator_id' => $operator->id, 'submission_key' => $key,
                'submission_hash' => $hash, 'snapshot' => $snapshot, 'status' => 'pending',
            ]);
        });

        if ($attempt instanceof Invoice) return $attempt->load(['recipient', 'items.product', 'payments']);
        return $this->resume($attempt);
    }

    public function resume(InvoiceSubmission $attempt): Invoice
    {
        try {
            // Reservation and snapshot have already committed. This lock serializes retries.
            return DB::transaction(function () use ($attempt) {
                User::whereKey($attempt->user_id)->lockForUpdate()->firstOrFail();
                $attempt = InvoiceSubmission::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($attempt->invoice_id) return Invoice::findOrFail($attempt->invoice_id)->load(['recipient', 'items.product', 'payments']);
                if ($attempt->status === 'rejected') throw ArcaException::permanent($attempt->last_error ?: 'Intento rechazado.');
                $s = $attempt->snapshot;
                $fiscal = $s['fiscal'];
                $payload = $s['payload'];
                $afip = app()->makeWith(AfipService::class, $s['issuer']);
                $result = $afip->createInvoice([
                    'request_key' => 'invoice:' . $attempt->user_id . ':' . $attempt->submission_key,
                    'fiscal' => $fiscal['fiscal'], 'condicion_iva_receptor' => $fiscal['condicion_iva_receptor'],
                    'total' => $fiscal['total_local'], 'cbte_tipo' => $fiscal['cbte_tipo'],
                    'doc_tipo' => $fiscal['doc_tipo'], 'doc_nro' => $fiscal['doc_nro'],
                    'pto_vta' => $s['point'], 'issued_date' => $s['issued_date'],
                ]);
                if (!preg_match('/^\d{14}$/', (string) ($result['cae'] ?? '')) || empty($result['numero'])
                    || !preg_match('/^\d{8}$/', (string) ($result['vencimiento'] ?? ''))) {
                    throw ArcaException::transient('ARCA devolvió una autorización incompleta. Recuperá este intento.');
                }
                $method = $payload['payment_method'] ?? ($payload['payments'][0]['payment_method'] ?? 'cash');
                $payments = $payload['payments'] ?? [];
                $manual = !empty($payload['manual_payment_confirmed']);
                $pendingPayment = !$manual && ($payments
                    ? collect($payments)->contains(fn ($p) => in_array($p['payment_method'], ['card', 'mp']) && $p['amount'] > 0)
                    : in_array($method, ['card', 'mp']));
                $invoice = Invoice::create([
                    'user_id' => $attempt->user_id, 'cashier_id' => $s['cashier_id'], 'recipient_id' => $payload['recipient_id'] ?? null,
                    'invoice_number' => InvoiceFiscalService::cbtePrefix($fiscal['cbte_tipo']) . '-' . str_pad($s['point'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($result['numero'], 8, '0', STR_PAD_LEFT),
                    'dollar_rate' => $s['dollar_rate'], 'total_usd' => $s['total_usd'], 'total_local' => $fiscal['total_local'],
                    'total_tributos' => $fiscal['tributos_total'] ?? 0, 'fiscal_snapshot' => $fiscal,
                    'status' => $pendingPayment ? 'pending' : 'paid', 'submission_key' => $attempt->submission_key,
                    'submission_hash' => $attempt->submission_hash, 'notify_afip' => true,
                    'payment_method' => $method, 'is_multiple_payment' => count($payments) > 1,
                    'cbte_tipo' => $fiscal['cbte_tipo'], 'pto_vta' => $s['point'],
                    'created_at' => \Illuminate\Support\Carbon::createFromFormat('Ymd', $s['issued_date'])->startOfDay(),
                ]);
                foreach ($payments as $index => $payment) {
                    $electronic = in_array($payment['payment_method'], ['card', 'mp']);
                    if ($electronic && !$manual) continue;
                    $invoice->payments()->create([
                        'payment_method' => $payment['payment_method'], 'amount' => $payment['amount'],
                        'details' => $electronic ? 'Confirmación manual por usuario ' . $attempt->operator_id . '. Comprobante: ' . trim($payment['reference']) : ($payment['details'] ?? null),
                        'transaction_id' => $s['references'][$index] ?? null,
                    ]);
                }
                if (!$payments && !$pendingPayment) $invoice->payments()->create(['payment_method' => $method, 'amount' => $fiscal['total_local']]);
                foreach ($s['items'] as $item) InvoiceItem::create($item + ['invoice_id' => $invoice->id]);
                $invoice->update(['cae' => $result['cae'], 'cae_expiry' => \Illuminate\Support\Carbon::createFromFormat('Ymd', $result['vencimiento'])->format('Y-m-d')]);
                $attempt->update(['status' => 'completed', 'invoice_id' => $invoice->id, 'last_error' => null]);
                return $invoice->load(['recipient', 'items.product', 'payments']);
            });
        } catch (\Throwable $e) {
            DB::transaction(function () use ($attempt, $e) {
                User::whereKey($attempt->user_id)->lockForUpdate()->firstOrFail();
                $attempt = InvoiceSubmission::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($attempt->status !== 'pending') return;
                $rejected = $e instanceof ArcaException && $e->isDefinitivelyRejected();
                if ($rejected) {
                    foreach ($attempt->snapshot['items'] as $item) {
                        $product = Product::whereKey($item['product_id'])->lockForUpdate()->firstOrFail();
                        ProductVariantService::restore($product, $item);
                    }
                }
                $attempt->update(['status' => $rejected ? 'rejected' : 'pending', 'last_error' => $e->getMessage()]);
            });
            if ($e instanceof ArcaException && $e->isDefinitivelyRejected()) throw $e;
            throw ArcaException::transient('La emisión sigue pendiente: ' . $e->getMessage(), $e, $attempt->submission_key);
        }
    }
}
