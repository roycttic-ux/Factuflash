<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PosDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = PosDevice::where('user_id', $request->user()->system_owner_id)->get();
        return response()->json(['devices' => $devices]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:mercadopago_point,traditional_pos',
            'external_id' => 'required|string|unique:pos_devices,external_id',
        ]);

        $device = PosDevice::create([
            'user_id' => $request->user()->system_owner_id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'external_id' => $validated['external_id'],
        ]);

        return response()->json(['device' => $device], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $device = PosDevice::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Dispositivo no encontrado'], 404);
        }

        $device->delete();
        return response()->json(['message' => 'Dispositivo eliminado']);
    }

    public function confirmPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => 'required|integer',
            'external_id' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01|decimal:0,2',
            'transaction_id' => 'required|string|max:150|regex:/\S/',
            'confirmed' => 'required|accepted',
            'payment_method' => 'nullable|in:cash,card,transfer,mp',
        ]);
        $owner = $request->user()->system_owner_id;
        $device = !empty($validated['external_id'])
            ? PosDevice::where('user_id', $owner)->where('external_id', $validated['external_id'])
                ->where('is_active', true)->firstOrFail()
            : null;
        $invoice = \App\Models\Invoice::where('user_id', $owner)->findOrFail($validated['invoice_id']);
        if ($invoice->point_request_key || $invoice->point_order_id) {
            return response()->json(['error' => 'Este cobro debe verificarse con Mercado Pago.'], 422);
        }
        $invoice = \Illuminate\Support\Facades\DB::transaction(function () use ($invoice, $device, $validated, $request) {
            $result = app(\App\Services\InvoicePaymentService::class)->confirm(
                $invoice,
                \App\Services\InvoicePaymentService::manualTransaction($validated['transaction_id']),
                (float) $validated['amount'],
                'Confirmación manual por usuario ' . $request->user()->id . '. Comprobante: ' . trim($validated['transaction_id']),
                $validated['payment_method'] ?? null
            );
            if ($device) {
                $result->update(['pos_device_id' => $device->id]);
            }
            return $result;
        });
        return response()->json(['invoice' => $invoice, 'status' => 'approved']);
    }

    public function checkStatus(Request $request, string $externalId): JsonResponse
    {
        $device = PosDevice::where('user_id', $request->user()->system_owner_id)
            ->where('external_id', $externalId)->firstOrFail();
        $validated = $request->validate(['invoice_id' => 'required|integer']);
        $invoice = \App\Models\Invoice::where('user_id', $device->user_id)
            ->where('pos_device_id', $device->id)->findOrFail($validated['invoice_id']);
        return response()->json(['status' => $invoice->status === 'paid' ? 'approved' : $invoice->status, 'invoice_id' => $invoice->id]);
    }
    public function downloadAgent(Request $request, string $platform): JsonResponse
    {
        return response()->json(['error' => 'El agente anterior fue retirado. Registrá el cobro desde el detalle de la factura.'], 410);
    }
}
