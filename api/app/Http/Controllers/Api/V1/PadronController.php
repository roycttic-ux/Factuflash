<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use App\Services\AfipPadronService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PadronController extends Controller
{
    public function lookup(Request $request, string $cuit): JsonResponse
    {
        $ownerId = $request->user()->system_owner_id;
        $settings = UserSetting::where('user_id', $ownerId)->first();

        try {
            $service = app(AfipPadronService::class);
            $data = $service->lookup($cuit, $settings);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'No se pudo consultar el padrón de ARCA: ' . $e->getMessage(),
            ], 500);
        }
    }
}
