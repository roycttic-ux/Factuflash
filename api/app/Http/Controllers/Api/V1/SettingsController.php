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
        $settings = UserSetting::where('user_id', $request->user()->id)->first();

        return response()->json(['settings' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'nullable|string|max:150',
            'company_address' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email|max:150',
            'company_cuit' => 'nullable|string|max:20',
            'arca_cuit' => 'nullable|string|max:20',
            'dollar_rate' => 'nullable|numeric|min:0',
            'whatsapp_number' => 'nullable|string|max:20',
            'show_whatsapp_button' => 'nullable|boolean',
            'mp_access_token' => 'nullable|string|max:255',
            'mp_public_key' => 'nullable|string|max:255',
            'catalog_theme' => 'nullable|string|max:20',
            'inflation_rate' => 'nullable|numeric|min:0',
        ]);

        $settings = UserSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return response()->json(['settings' => $settings]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logo' => 'required|image|max:2048',
        ]);

        $userId = $request->user()->id;
        $path = $validated['logo']->store('logos', 'public');

        UserSetting::updateOrCreate(
            ['user_id' => $userId],
            ['company_logo' => $path]
        );

        return response()->json(['logo_url' => Storage::url($path)], 201);
    }
}
