<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoicePaymentService
{
    public static function manualTransaction(string $reference): string
    {
        return 'manual:' . hash('sha256', mb_strtolower(trim($reference)));
    }

    public function assertUnused(int $ownerId, string $transaction): void
    {
        if (Invoice::where('user_id', $ownerId)->where('payment_transaction_id', $transaction)->exists()
            || InvoicePayment::where('transaction_id', $transaction)
                ->whereHas('invoice', fn ($query) => $query->where('user_id', $ownerId))->exists()) {
            throw ValidationException::withMessages(['transaction_id' => 'Este comprobante ya se registró en otra factura.']);
        }
    }

    public function confirm(Invoice $invoice, string $transaction, float $amount, string $details, ?string $method = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $transaction, $amount, $details, $method) {
            // Serialize confirmations for this merchant, including duplicate transaction checks.
            User::whereKey($invoice->user_id)->lockForUpdate()->firstOrFail();
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (str_starts_with($transaction, 'manual:') && ($invoice->point_request_key || $invoice->point_order_id)) {
                throw ValidationException::withMessages(['payment' => 'Este cobro debe verificarse con Mercado Pago.']);
            }
            if ($invoice->status === 'paid' && $invoice->payment_transaction_id === $transaction) {
                $payment = $invoice->payments()->where('transaction_id', $transaction)->first();
                if ($payment && ((int) round((float) $payment->amount * 100) !== (int) round($amount * 100)
                    || ($method !== null && $payment->payment_method !== $method))) {
                    throw ValidationException::withMessages(['payment' => 'El comprobante ya fue registrado con otro importe o medio de pago.']);
                }
                return $invoice->load('payments');
            }
            $remaining = (float) $invoice->total_local - (float) $invoice->payments()->sum('amount');
            if ($invoice->status !== 'pending' || (int) round($remaining * 100) !== (int) round($amount * 100)) {
                throw ValidationException::withMessages(['payment' => 'La factura no está pendiente o el importe no coincide con el saldo.']);
            }
            $this->assertUnused($invoice->user_id, $transaction);
            $invoice->payments()->create([
                'payment_method' => $method ?? ($invoice->payment_method === 'mp' ? 'mp' : 'card'),
                'amount' => $amount,
                'details' => $details,
                'transaction_id' => $transaction,
            ]);
            $invoice->update(['status' => 'paid', 'payment_transaction_id' => $transaction]);
            return $invoice->load('payments');
        });
    }
}
