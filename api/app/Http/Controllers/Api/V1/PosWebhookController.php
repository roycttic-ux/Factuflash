<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\UserSetting;
use App\Models\User;
use App\Services\AfipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosWebhookController extends Controller
{
    /**
     * Webhook llamado por el agente FactuFlash POS Bridge.
     * Detecta un pago aprobado del postnet tradicional y crea la factura automáticamente.
     *
     * POST /api/v1/pos/webhook
     * Header: Authorization: Bearer {token}
     */
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'card_last4'  => 'nullable|string|size:4',
            'notify_afip' => 'nullable|boolean',
            'source'      => 'nullable|string',
            'detected_at' => 'nullable|string',
        ]);

        $user     = $request->user();
        $ownerId  = $user->system_owner_id;
        $settings = UserSetting::where('user_id', $ownerId)->first();

        Log::info('POS Webhook recibido', [
            'user_id' => $ownerId,
            'amount'  => $validated['amount'],
            'source'  => $validated['source'] ?? 'unknown',
        ]);

        try {
            $invoice = DB::transaction(function () use ($ownerId, $validated, $settings, $user) {
                $amount     = (float) $validated['amount'];
                $notifyAfip = $validated['notify_afip'] ?? true;

                $cae        = null;
                $vencimiento = null;
                $cbteTipo   = null;
                $nextNumRaw = Invoice::where('user_id', $ownerId)->count() + 1;

                // --- LOGICA ARCA ---
                if (!$settings || !$settings->arca_cert_path || !$settings->arca_key_path) {
                    throw new \Exception("Debés vincular tu cuenta con AFIP/ARCA antes de emitir facturas con POS.");
                }

                try {
                        // Sin recipient → Consumidor Final
                        $cbteTipo = 11; // Factura C (Monotributo por defecto)
                        if ($settings->tax_condition === 'Responsable Inscripto') {
                            $cbteTipo = 6; // Factura B a Consumidor Final
                        }

                        $afip = app()->makeWith(AfipService::class, [
                            'cuit'      => $settings->arca_cuit,
                            'cert_path' => $settings->arca_cert_path,
                            'key_path'  => $settings->arca_key_path,
                        ]);

                        $res = $afip->createInvoice([
                            'total'     => $amount,
                            'cbte_tipo' => $cbteTipo,
                            'doc_tipo'  => 99,  // Consumidor Final
                            'doc_nro'   => 0,
                            'pto_vta'   => $settings->arca_pto_vta ?? 1,
                        ]);

                        $cae         = $res['cae'] ?? null;
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

                        Log::info('ARCA notificada desde webhook POS', ['cae' => $cae]);
                    } catch (\Exception $e) {
                        Log::warning('Error al notificar ARCA desde webhook POS: ' . $e->getMessage());
                        // No bloqueamos la factura si ARCA falla — se crea igual localmente
                    }

                // Obtener o crear un producto genérico "Venta Gral / POS"
                $product = Product::where('user_id', $ownerId)->where('name', 'Venta Gral / POS')->first();
                if (!$product) {
                    $product = Product::create([
                        'user_id' => $ownerId,
                        'name' => 'Venta Gral / POS',
                        'sku' => 'POS-GENERIC',
                        'cost_usd' => 0.00,
                        'stock' => 0,
                        'unlimited_stock' => true,
                    ]);
                }

                // Generar número de factura
                $prefix       = $settings->invoice_prefix ?? 'C';
                $ptoVta       = str_pad($ownerId, 4, '0', STR_PAD_LEFT);
                $formattedNum = $prefix . '-' . $ptoVta . '-' . str_pad($nextNumRaw, 8, '0', STR_PAD_LEFT);

                $uniqueTry = $formattedNum;
                $counter   = 1;
                while (Invoice::where('invoice_number', $uniqueTry)->exists()) {
                    $uniqueTry = $formattedNum . '-' . $counter;
                    $counter++;
                }

                $invoice = Invoice::create([
                    'user_id'            => $ownerId,
                    'cashier_id'         => ($user->role === 'cashier') ? $user->id : null,
                    'recipient_id'       => null,
                    'invoice_number'     => $uniqueTry,
                    'dollar_rate'        => (float) ($settings->dollar_rate ?? 1),
                    'total_usd'          => $amount,
                    'total_local'        => $amount,
                    'status'             => 'paid',
                    'notify_afip'        => true,
                    'payment_method'     => 'card',
                    'is_multiple_payment'=> false,
                    'cae'                => $cae,
                    'cae_expiry'         => $vencimiento,
                    'cbte_tipo'          => $cbteTipo,
                    'pto_vta'            => $settings->arca_pto_vta ?? 1,
                ]);

                // Guardar pago
                $invoice->payments()->create([
                    'payment_method' => 'card',
                    'amount'         => $amount,
                    'details'        => 'Pago por Postnet' . ($validated['card_last4'] ? ' (*' . $validated['card_last4'] . ')' : ''),
                ]);

                // Guardar item
                InvoiceItem::create([
                    'invoice_id'     => $invoice->id,
                    'product_id'     => $product->id,
                    'quantity'       => 1,
                    'unit_price_usd' => $amount,
                ]);

                Log::info('Factura creada desde webhook POS', [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount'         => $amount,
                    'cae'            => $cae,
                ]);

                return $invoice;
            });

            return response()->json([
                'ok'      => true,
                'invoice' => [
                    'id'             => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'total_local'    => $invoice->total_local,
                    'cae'            => $invoice->cae,
                ],
                'message' => 'Factura creada automáticamente desde POS Bridge',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error en POS Webhook: ' . $e->getMessage());
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
