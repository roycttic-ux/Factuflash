<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MercadoPagoController extends Controller
{
    public function createPreference(Request $request): JsonResponse
    {
        $data = $request->validate(['invoice_id' => 'required|integer|min:1']);
        $invoice = Invoice::where('user_id', $request->user()->system_owner_id)->findOrFail($data['invoice_id']);
        if ($invoice->status !== 'pending') return response()->json(['error' => 'La factura no está pendiente.'], 422);
        $mpToken = $this->merchantToken($invoice);
        if (!$mpToken) return response()->json(['error' => 'Configurá Mercado Pago para este comercio.'], 422);
        $preferenceData = [
            'items' => [[
                'title' => 'Factura ' . $invoice->invoice_number, 'quantity' => 1,
                'unit_price' => round((float) $invoice->total_local - (float) $invoice->payments()->sum('amount'), 2),
                'currency_id' => 'ARS',
            ]],
            'external_reference' => 'invoice_' . $invoice->id,
            'notification_url' => route('mp.webhook', ['invoice_id' => $invoice->id]),
        ];
        $response = Http::withToken($mpToken)
            ->post('https://api.mercadopago.com/checkout/preferences', $preferenceData);

        if ($response->failed()) {
            return response()->json(['error' => 'Error al crear preferencia'], 500);
        }

        return response()->json($response->json());
    }

    public function createSubscriptionPreference(Request $request): JsonResponse
    {
        // Keep old clients on the same verified subscription flow and prices.
        if ($request->input('plan') === 'yearly') $request->merge(['plan' => 'annual']);
        return app(SubscriptionController::class)->createCheckout($request);
    }

    public function createPointPaymentIntent(Request $request): JsonResponse
    {
        $data = $request->validate(['invoice_id' => 'required|integer|min:1', 'device_id' => 'required|string']);
        $owner = $request->user()->system_owner_id;
        $device = \App\Models\PosDevice::where('user_id', $owner)->where('external_id', $data['device_id'])
            ->where('type', 'mercadopago_point')->where('is_active', true)->firstOrFail();
        $invoice = Invoice::where('user_id', $owner)->findOrFail($data['invoice_id']);
        $token = $this->merchantToken($invoice);
        if (!$token) return response()->json(['error' => 'Configurá el access token de Mercado Pago de este comercio.'], 422);

        // Persist the retry key before contacting the provider, including ambiguous timeouts.
        \Illuminate\Support\Facades\DB::transaction(function () use ($invoice, $device) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending', 422, 'La factura no está pendiente.');
            abort_if($locked->pos_device_id && $locked->pos_device_id !== $device->id, 422, 'La factura ya tiene otro dispositivo vinculado.');
            $locked->update(['pos_device_id' => $device->id, 'point_request_key' => $locked->point_request_key ?: (string) \Illuminate\Support\Str::uuid()]);
        });
        $invoice->refresh();
        if ($invoice->point_order_id) return response()->json(['id' => $invoice->point_order_id, 'invoice_id' => $invoice->id]);
        $amount = (float) $invoice->total_local - (float) $invoice->payments()->sum('amount');
        $response = Http::withToken($token)->timeout(20)
            ->withHeaders(['X-Idempotency-Key' => $invoice->point_request_key])
            ->post('https://api.mercadopago.com/v1/orders', [
                'type' => 'point',
                'external_reference' => 'invoice_' . $invoice->id,
                'transactions' => ['payments' => [['amount' => number_format($amount, 2, '.', '')]]],
                'description' => 'Factura ' . $invoice->invoice_number,
                'config' => ['point' => ['terminal_id' => $device->external_id, 'print_on_terminal' => 'no_ticket']],
            ]);
        if (!$response->successful() || !$response->json('id')) {
            return response()->json(['error' => 'No se confirmó el envío al Point. Consultá el estado antes de volver a cobrar.'], 502);
        }
        $invoice->update(['point_order_id' => $response->json('id')]);
        return response()->json(['id' => $invoice->point_order_id, 'invoice_id' => $invoice->id]);
    }

    private function merchantToken(Invoice $invoice): ?string
    {
        // Never use the platform subscription credentials to collect a merchant's sales.
        return UserSetting::where('user_id', $invoice->user_id)->value('mp_access_token');
    }

    private function applyPayment(Invoice $invoice, array $payment): bool
    {
        if (($payment['status'] ?? '') !== 'approved' || ($payment['currency_id'] ?? '') !== 'ARS'
            || ($payment['external_reference'] ?? '') !== 'invoice_' . $invoice->id
            || empty($payment['id']) || !isset($payment['transaction_amount'])
            || (float) ($payment['transaction_amount_refunded'] ?? 0) > 0) return false;
        app(\App\Services\InvoicePaymentService::class)->confirm(
            $invoice, 'mp:' . $payment['id'], (float) $payment['transaction_amount'],
            'Verificado en Mercado Pago. Pago ' . $payment['id']
        );
        return true;
    }

    private function applyOrder(Invoice $invoice, array $order): bool
    {
        if (($order['type'] ?? '') !== 'point' || ($order['country_code'] ?? '') !== 'ARG'
            || ($order['external_reference'] ?? '') !== 'invoice_' . $invoice->id
            || ($order['status'] ?? '') !== 'processed' || ($order['status_detail'] ?? '') !== 'processed'
            || ($order['id'] ?? '') !== $invoice->point_order_id) return false;
        $payments = data_get($order, 'transactions.payments', []);
        if (count($payments) !== 1) return false;
        $payment = $payments[0];
        if (($payment['status'] ?? '') !== 'processed' || ($payment['status_detail'] ?? '') !== 'accredited'
            || empty($payment['id']) || !isset($payment['amount'])) return false;
        app(\App\Services\InvoicePaymentService::class)->confirm(
            $invoice, 'mp:' . $payment['id'], (float) $payment['amount'], 'Verificado en Mercado Pago Point. Orden ' . $order['id']
        );
        return true;
    }

    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::where('user_id', $request->user()->system_owner_id)->findOrFail($id);
        if ($invoice->status === 'pending') {
            $token = $this->merchantToken($invoice);
            if (!$token) return response()->json(['error' => 'Mercado Pago no está configurado para este comercio.'], 422);
            if ($invoice->point_order_id) {
                $response = Http::withToken($token)->timeout(15)->get('https://api.mercadopago.com/v1/orders/' . rawurlencode($invoice->point_order_id));
                if (!$response->successful()) return response()->json(['error' => 'No se pudo consultar Mercado Pago. La factura sigue pendiente.'], 502);
                $this->applyOrder($invoice, $response->json());
                return response()->json(['invoice' => $invoice->fresh()->load('payments'), 'provider_status' => $response->json('status')]);
            }
            $response = Http::withToken($token)->timeout(15)->get('https://api.mercadopago.com/v1/payments/search', [
                'external_reference' => 'invoice_' . $invoice->id,
                'sort' => 'date_created', 'criteria' => 'desc', 'limit' => 100,
            ]);
            if (!$response->successful()) return response()->json(['error' => 'No se pudo consultar Mercado Pago. La factura sigue pendiente.'], 502);
            foreach ($response->json('results', []) as $payment) {
                if ($this->applyPayment($invoice, $payment)) break;
            }
        }
        return response()->json(['invoice' => $invoice->fresh()->load('payments')]);
    }

    public function webhook(Request $request): JsonResponse
    {
        // The notification is only a hint. Only the authenticated provider API can approve a payment.
        $type = $request->input('type', $request->input('topic'));
        if ($type === 'order') {
            $data = $request->validate(['data.id' => 'required|string|regex:/^[A-Za-z0-9]+$/']);
            $invoice = Invoice::where('point_order_id', $data['data']['id'])->first();
            if (!$invoice) return response()->json(['error' => 'Orden aún no registrada. Reintentar notificación.'], 503);
            $token = $this->merchantToken($invoice);
            if (!$token) return response()->json(['error' => 'Comercio sin configurar'], 422);
            $response = Http::withToken($token)->timeout(15)->get('https://api.mercadopago.com/v1/orders/' . rawurlencode($invoice->point_order_id));
            if (!$response->successful()) return response()->json(['error' => 'No se pudo verificar la orden'], 502);
            $this->applyOrder($invoice, $response->json());
            return response()->json(['status' => 'ok']);
        }
        if ($type !== 'payment') return response()->json(['status' => 'ignored']);
        $data = $request->validate([
            'invoice_id' => 'required_without:merchant_id|integer|min:1',
            'merchant_id' => 'required_without:invoice_id|integer|min:1',
            'data.id' => 'required|regex:/^[0-9]+$/',
        ]);
        $invoice = isset($data['invoice_id']) ? Invoice::findOrFail($data['invoice_id']) : null;
        $owner = $invoice?->user_id ?? $data['merchant_id'];
        $token = UserSetting::where('user_id', $owner)->value('mp_access_token');
        if (!$token) return response()->json(['error' => 'Comercio sin configurar'], 422);
        $response = Http::withToken($token)->timeout(15)->get('https://api.mercadopago.com/v1/payments/' . $data['data']['id']);
        if (!$response->successful()) return response()->json(['error' => 'No se pudo verificar el pago'], 502);
        if (!$invoice) {
            $reference = (string) $response->json('external_reference');
            if (!preg_match('/^invoice_([0-9]+)$/', $reference, $matches)) return response()->json(['status' => 'ignored']);
            $invoice = Invoice::where('user_id', $owner)->findOrFail($matches[1]);
        }
        $this->applyPayment($invoice, $response->json());
        return response()->json(['status' => 'ok']);
    }
}
