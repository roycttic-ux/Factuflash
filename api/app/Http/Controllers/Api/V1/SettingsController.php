<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = UserSetting::where('user_id', $request->user()->system_owner_id)->first();

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $ownerId = $request->user()->system_owner_id;

        $validated = $request->validate([
            'slug' => 'nullable|string|max:50|unique:user_settings,slug,' . $ownerId . ',user_id|regex:/^[a-z0-9\-]+$/i',
            'company_name' => 'nullable|string|max:150',
            'company_logo' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email|max:150',
            'company_cuit' => 'nullable|string|max:20',
            'invoice_prefix' => 'nullable|string|max:10',
            'tax_condition' => 'nullable|string|max:50',
            'arca_cuit' => 'nullable|string|max:20',
            'arca_pto_vta' => 'nullable|integer|min:1',
            'dollar_rate' => 'nullable|numeric|min:0',
            'dollar_rate_mode' => 'nullable|string|max:20',
            'dollar_rate_manual' => 'nullable|numeric|min:0',
            'whatsapp_number' => 'nullable|string|max:20',
            'show_whatsapp_button' => 'nullable|boolean',
            'mp_access_token' => 'nullable|string|max:255',
            'mp_public_key' => 'nullable|string|max:255',
            'catalog_theme' => 'nullable|string|max:20',
            'catalog_title' => 'nullable|string|max:150',
            'catalog_description' => 'nullable|string',
            'catalog_instagram' => 'nullable|string|max:100',
            'catalog_facebook' => 'nullable|string|max:100',
            'catalog_tiktok' => 'nullable|string|max:100',
            'catalog_show_stock' => 'nullable|boolean',
            'catalog_show_price' => 'nullable|boolean',
            'catalog_grid_columns' => 'nullable|integer|min:1|max:4',
            'inflation_rate' => 'nullable|numeric|min:0',
            'bank_name' => 'nullable|string|max:100',
            'bank_cbu' => 'nullable|string|max:50',
            'bank_alias' => 'nullable|string|max:100',
            'bank_holder' => 'nullable|string|max:150',
            'catalog_video_url' => 'nullable|string|max:255',
            'pos_provider' => 'nullable|string|in:none,mercadopago,clover',
            'pos_clover_merchant_id' => 'nullable|string|max:255',
            'pos_clover_access_token' => 'nullable|string|max:255',
        ]);

        $owner = \App\Models\User::find($request->user()->system_owner_id);
        
        $settings = $owner->settings()->updateOrCreate(
            ['user_id' => $owner->id],
            $validated
        );

        return response()->json(['settings' => $settings]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        $owner = \App\Models\User::find($request->user()->system_owner_id);
        $path = $validated['logo']->store('logos', 'public');

        $owner->settings()->updateOrCreate(
            ['user_id' => $owner->id],
            ['company_logo' => $path]
        );

        return response()->json(['logo_url' => '/api/storage/' . $path], 201);
    }
}
