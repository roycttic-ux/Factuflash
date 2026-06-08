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
    public function show($identifier): JsonResponse
    {
        if (is_numeric($identifier)) {
            $user = User::with('settings')->find((int)$identifier);
        } else {
            $settings = UserSetting::where('slug', $identifier)->with('user.settings')->first();
            $user = $settings ? $settings->user : null;
        }

        if (!$user) {
            return response()->json(['error' => 'Vendedor no encontrado'], 404);
        }

        $products = Product::where('user_id', $user->id)
            ->where(function($q) {
                $q->where('stock', '>', 0)
                  ->orWhere('unlimited_stock', true);
            })
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
                    'catalog_title' => $user->settings->catalog_title,
                    'catalog_description' => $user->settings->catalog_description,
                    'catalog_instagram' => $user->settings->catalog_instagram,
                    'catalog_facebook' => $user->settings->catalog_facebook,
                    'catalog_tiktok' => $user->settings->catalog_tiktok,
                    'catalog_show_stock' => (bool) $user->settings->catalog_show_stock,
                    'catalog_show_price' => (bool) $user->settings->catalog_show_price,
                    'dollar_rate' => $user->settings->dollar_rate ?? 1000,
                    'whatsapp_number' => $user->settings->whatsapp_number,
                    'show_whatsapp_button' => (bool) $user->settings->show_whatsapp_button,
                    'catalog_grid_columns' => (int) ($user->settings->catalog_grid_columns ?? 1),
                    'catalog_video_url' => $user->settings->catalog_video_url,
                ]
            ],
            'products' => $products
        ]);
    }
}
