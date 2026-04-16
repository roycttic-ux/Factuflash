<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function show(int $userId): JsonResponse
    {
        $user = User::with('settings')->find($userId);

        if (!$user) {
            return response()->json(['error' => 'Vendedor no encontrado'], 404);
        }

        $products = Product::where('user_id', $userId)
            ->where('stock', '>', 0) // Only show items in stock
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'company' => [
                'name' => $user->settings->company_name ?? $user->name,
                'logo' => $user->settings->company_logo,
                'whatsapp' => $user->settings->whatsapp_number,
                'show_whatsapp' => (bool) $user->settings->show_whatsapp_button,
                'settings' => [
                    'catalog_theme' => $user->settings->catalog_theme ?? 'white',
                    'dollar_rate' => $user->settings->dollar_rate ?? 1000,
                    'whatsapp_number' => $user->settings->whatsapp_number,
                    'show_whatsapp_button' => (bool) $user->settings->show_whatsapp_button,
                ]
            ],
            'products' => $products
        ]);
    }
}
