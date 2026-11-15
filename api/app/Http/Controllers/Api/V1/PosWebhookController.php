<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PosWebhookController extends Controller
{
    public function handle(): JsonResponse
    {
        return response()->json([
            'error' => 'El bridge anterior no verifica pagos. Abrí la factura pendiente y registrá el comprobante aprobado, o utilizá la integración con Mercado Pago.',
        ], 409);
    }
}