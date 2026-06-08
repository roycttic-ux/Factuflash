<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoController extends Controller
{
    public function createPreference(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->settings;

        // Prioridad: token del .env > token guardado en BD
        $mpToken = env('MP_ACCESS_TOKEN') ?? $settings?->mp_access_token;

        if (!$mpToken) {
            return response()->json(['error' => 'Mercado Pago no está configurado'], 422);
        }

        $items = $request->input('items', []);
        $invoiceId = $request->input('invoice_id');

        $preferenceData = [
            'items' => array_map(function($item) {
                return [
                    'title' => $item['name'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['price_ars'],
                    'currency_id' => 'ARS'
                ];
            }, $items),
            'external_reference' => (string) $invoiceId,
            'notification_url' => route('mp.webhook'),
            'back_urls' => [
                'success' => url('/#/facturas/' . $invoiceId),
                'failure' => url('/#/facturas/' . $invoiceId),
            ],
            'auto_return' => 'approved',
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
        $user = $request->user();
        $mpToken = env('MP_ACCESS_TOKEN');

        if (!$mpToken) {
            return response()->json(['error' => 'Mercado Pago no está configurado en el servidor'], 422);
        }

        $plan = $request->input('plan'); // 'monthly' o 'yearly'
        
        $planData = [
            'monthly' => ['title' => 'Plan Mensual FactuFlash', 'price' => 35000.00],
            'yearly'  => ['title' => 'Plan Anual FactuFlash (2 meses gratis)', 'price' => 310000.00]
        ];

        if (!isset($planData[$plan])) {
            return response()->json(['error' => 'Plan inválido'], 422);
        }

        $preferenceData = [
            'items' => [
                [
                    'title' => $planData[$plan]['title'],
                    'quantity' => 1,
                    'unit_price' => $planData[$plan]['price'],
                    'currency_id' => 'ARS'
                ]
            ],
            'external_reference' => 'SUB_' . $user->id . '_' . $plan . '_' . time(),
            'notification_url' => route('mp.webhook'),
            'back_urls' => [
                'success' => url('/#/configuracion?payment=success'),
                'failure' => url('/#/configuracion?payment=failure'),
            ],
            'auto_return' => 'approved',
        ];

        $response = Http::withToken($mpToken)
            ->post('https://api.mercadopago.com/checkout/preferences', $preferenceData);

        if ($response->failed()) {
            return response()->json(['error' => 'Error al crear preferencia'], 500);
        }

        return response()->json($response->json());
    }

    public function createPointPaymentIntent(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $user->settings;
        $mpToken = env('MP_ACCESS_TOKEN') ?? $settings?->mp_access_token;

        if (!$mpToken) {
            return response()->json(['error' => 'Mercado Pago no está configurado'], 422);
        }

        $deviceId = $request->input('device_id');
        $amount = (float) $request->input('amount');
        $invoiceId = $request->input('invoice_id');

        $intentData = [
            'amount' => $amount,
            'description' => 'Pago Factura #' . $invoiceId,
            'external_reference' => (string) $invoiceId,
            'notification_url' => route('mp.webhook'),
        ];

        // API de Point: https://www.mercadopago.com.ar/developers/es/reference/integrations_api/_point_integration-api_devices_device-id_payment-intents/post
        $response = Http::withToken($mpToken)
            ->post("https://api.mercadopago.com/point/integration-api/devices/{$deviceId}/payment-intents", $intentData);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Error al enviar orden al dispositivo',
                'details' => $response->json()
            ], $response->status());
        }

        return response()->json($response->json());
    }

    public function webhook(Request $request): JsonResponse
    {
        $data = $request->all();
        Log::info("MP Webhook received:", $data);
        
        // Manejar notificaciones de Point y Checkout
        if (isset($data['action']) && $data['action'] === 'payment.created') {
            // Es una notificación de un pago creado (posiblemente desde Point)
            // Aquí se debería consultar el estado final del pago y marcar la factura
        }

        return response()->json(['status' => 'ok']);
    }
}
