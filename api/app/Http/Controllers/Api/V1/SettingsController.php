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

        if ($request->user()->owner_id || $request->user()->role === 'cashier') {
            // Cashiers need sales configuration, but cannot manage integrations or the catalog.
            return response()->json(['settings' => $settings?->only([
                'company_name', 'company_logo', 'company_address', 'company_phone', 'company_email',
                'company_cuit', 'tax_condition', 'arca_connected', 'arca_pto_vta', 'arca_cuit',
                'dollar_rate', 'dollar_rate_mode', 'dollar_rate_manual', 'fiscal_jurisdiction',
                'default_iibb_alicuota', 'bank_name', 'bank_cbu', 'bank_alias', 'bank_holder',
            ])]);
        }

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $ownerId = $request->user()->system_owner_id;

        $validated = $request->validate([
            'slug' => 'nullable|string|max:50|unique:user_settings,slug,' . $ownerId . ',user_id|regex:/^[a-z0-9\-]+$/i',
            'company_name' => 'nullable|string|max:150',
            'company_logo' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email|max:150',
            'company_cuit' => ['nullable', 'string', 'max:20', new \App\Rules\ValidCuit(true)],
            'invoice_prefix' => 'nullable|string|max:10',
            'tax_condition' => 'nullable|string|max:50',
            'arca_cuit' => ['nullable', 'string', 'max:20', new \App\Rules\ValidCuit(true)],
            'arca_pto_vta' => 'nullable|integer|min:1',
            'dollar_rate' => 'nullable|numeric|min:0',
            'dollar_rate_mode' => 'nullable|string|max:20',
            'dollar_rate_manual' => 'nullable|numeric|min:0',
            'whatsapp_number' => 'nullable|string|max:20',
            'show_whatsapp_button' => 'nullable|boolean',
            'mp_access_token' => 'nullable|string|max:255',
            'clear_mp_access_token' => 'sometimes|boolean',
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
            // Provider catalog is intentionally broader than automatic integrations.
            // Non-Point providers use the manual receipt workflow until their official API is enabled.
            'pos_provider' => 'nullable|string|in:none,mercadopago,payway,fiserv,getnet,uala_bis,naranja_x,nave,modo,clover',
            'pos_clover_merchant_id' => 'nullable|string|max:255',
            'pos_clover_access_token' => 'nullable|string|max:255',
            'clear_pos_clover_access_token' => 'sometimes|boolean',
            'fiscal_jurisdiction' => 'nullable|string|max:100',
            'business_activity' => 'nullable|string|max:150',
            'iibb_number' => 'nullable|string|max:50',
            'iibb_condition' => 'nullable|string|in:local,convenio_multilateral,exento,no_inscripto,otros',
            // Reference supplied by the merchant; never enables automatic perceptions.
            'default_iibb_alicuota' => 'nullable|numeric|min:0|max:100|decimal:0,2',
            'onboarding_complete' => 'nullable|boolean',
        ]);

        foreach (['mp_access_token', 'pos_clover_access_token'] as $secret) {
            // Empty password fields preserve existing credentials. Removal is explicit.
            if ($validated['clear_' . $secret] ?? false) {
                $validated[$secret] = null;
            } elseif (empty($validated[$secret])) {
                unset($validated[$secret]);
            }
            unset($validated['clear_' . $secret]);
        }

        $owner = \App\Models\User::find($request->user()->system_owner_id);
        
        $settings = $owner->settings()->updateOrCreate(
            ['user_id' => $owner->id],
            $validated
        );

        return response()->json(['settings' => $settings]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
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

    /**
     * Marca el onboarding como completado para el usuario propietario.
     * Se llama desde el último paso del wizard de setup inicial.
     */
    public function completeOnboarding(Request $request): JsonResponse
    {
        $this->authorizeManagement($request);
        $owner = \App\Models\User::find($request->user()->system_owner_id);
        $owner->settings()->updateOrCreate(
            ['user_id' => $owner->id],
            ['onboarding_complete' => true]
        );
        return response()->json(['ok' => true]);
    }

    private function authorizeManagement(Request $request): void
    {
        // "user" is the legacy owner role used before owner/cashier accounts existed.
        abort_unless(!$request->user()->owner_id && in_array($request->user()->role, ['owner', 'admin', 'user'], true), 403, 'Solo el titular puede cambiar la configuración.');
    }
}
