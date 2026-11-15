<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Exception;

class ArcaController extends Controller
{
    public function tributeTypes(Request $request): JsonResponse
    {
        $settings = UserSetting::where('user_id', $request->user()->system_owner_id)->firstOrFail();
        try {
            $service = app()->makeWith(\App\Services\AfipService::class, [
                'cuit' => $settings->arca_cuit, 'cert_path' => $settings->arca_cert_path,
                'key_path' => $settings->arca_key_path,
            ]);
            return response()->json(['tribute_types' => $service->getTributeTypes()]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function getOpenSslArgs()
    {
        $opensslConfigArgs = [];
        $paths = [
            'C:/xampp/apache/conf/openssl.cnf', // XAMPP Windows
            '/etc/ssl/openssl.cnf',             // Ubuntu/Debian
            '/etc/pki/tls/openssl.cnf',         // CentOS/RHEL
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $opensslConfigArgs = ["config" => $path];
                break;
            }
        }
        
        return $opensslConfigArgs;
    }

    public function generateCsr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cuit' => 'required|string',
        ]);

        $cuitClean = preg_replace('/[^0-9]/', '', $validated['cuit']);
        if (strlen($cuitClean) !== 11 || !\App\Services\CuitValidator::isValid($cuitClean)) {
            return response()->json(['error' => 'El CUIT ingresado no es válido (verificá los 11 dígitos y el dígito verificador).'], 422);
        }

        if (!function_exists('openssl_pkey_new')) {
            return response()->json(['error' => 'OpenSSL no está disponible en este servidor.'], 500);
        }

        abort_if($request->user()->owner_id, 403, 'Solo el titular puede vincular ARCA.');
        $userId = $request->user()->id;
        $userDir = 'certs/users/' . $userId;
        if (UserSetting::where('user_id', $userId)->where('arca_connected', true)->exists()) return response()->json(['error' => 'Desvinculá el certificado actual antes de generar una nueva clave.'], 422);
        Storage::disk('local')->makeDirectory($userDir);
        $fullUploadDir = Storage::disk('local')->path($userDir);

        $opensslConfigArgs = $this->getOpenSslArgs();

        $privateKey = @openssl_pkey_new(array_merge([
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ], $opensslConfigArgs));

        if (!$privateKey) {
            return response()->json(['error' => 'Error al generar la clave privada de openSSL.'], 500);
        }

        openssl_pkey_export($privateKey, $privKeyStr, null, $opensslConfigArgs);
        Storage::disk('local')->put($userDir . '/privada.key', $privKeyStr);

        $companyName = $request->user()->company_name ?? 'Mi Empresa';
        $dn = [
            "countryName" => "AR",
            "organizationName" => $companyName,
            "commonName" => "FactuFlash-" . $userId,
            "serialNumber" => "CUIT " . $cuitClean
        ];

        $csr = @openssl_csr_new($dn, $privateKey, $opensslConfigArgs);
        if (!$csr) {
            return response()->json(['error' => 'Error al generar el pedido CSR.'], 500);
        }

        openssl_csr_export($csr, $csrStr);
        // Do not save CSR to disk, we can just return it. Actually let's return it.
        
        $settings = UserSetting::firstOrCreate(['user_id' => $userId]);
        $settings->update([
            'arca_cuit' => $cuitClean,
            'arca_key_path' => $userDir . '/privada.key',
            'arca_connected' => 0
        ]);

        return response()->json(['csr' => $csrStr, 'message' => 'CSR generado de forma segura.']);
    }

    public function uploadCert(Request $request): JsonResponse
    {
        $request->validate([
            'cert' => 'nullable|file|max:64',
            'cert_text' => 'nullable|string|max:65536',
        ]);

        $certContent = '';

        if ($request->hasFile('cert')) {
            $file = $request->file('cert');
            $certContent = file_get_contents($file->getRealPath());
        } elseif ($request->has('cert_text')) {
            $certContent = $request->input('cert_text');
        } else {
            return response()->json(['error' => 'Debes subir un archivo o pegar el texto del certificado.'], 422);
        }

        $cert = @openssl_x509_read($certContent);
        if (!$cert) {
            return response()->json(['error' => 'El archivo no es un certificado X509 digital válido.'], 422);
        }

        $certInfo = openssl_x509_parse($cert);
        
        $isAfipIssuer = false;
        foreach ($certInfo['issuer'] as $key => $val) {
            if (stripos($val, 'AFIP') !== false || stripos($val, 'Administracion Federal') !== false) {
                $isAfipIssuer = true;
                break;
            }
        }

        if (!$isAfipIssuer) {
            return response()->json(['error' => 'El certificado no fue emitido por AFIP/ARCA.'], 422);
        }

        openssl_x509_free($cert);

        abort_if($request->user()->owner_id, 403, 'Solo el titular puede vincular ARCA.');
        $userId = $request->user()->id;
        $userDir = 'certs/users/' . $userId;
        
        if (!Storage::disk('local')->exists($userDir . '/privada.key')) {
            return response()->json(['error' => 'No se encontró la clave privada. Generá el CSR primero.'], 422);
        }

        $path = $userDir . '/certificado-' . \Illuminate\Support\Str::uuid() . '.crt';
        Storage::disk('local')->put($path, $certContent);

        $settings = UserSetting::firstOrCreate(['user_id' => $userId]);
        try {
            $service = app()->makeWith(\App\Services\AfipService::class, ['cuit' => $settings->arca_cuit, 'cert_path' => $path, 'key_path' => $settings->arca_key_path]);
            $service->validateConnection((int) ($settings->arca_pto_vta ?? 1));
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $settings->update([
            'arca_cert_path' => $path,
            'arca_connected' => 1
        ]);

        return response()->json(['message' => 'Certificado vinculado con éxito.']);
    }

    public function status(Request $request): JsonResponse
    {
        $settings = UserSetting::where('user_id', $request->user()->system_owner_id)->firstOrFail();
        try {
            $service = app()->makeWith(\App\Services\AfipService::class, ['cuit' => $settings->arca_cuit, 'cert_path' => $settings->arca_cert_path, 'key_path' => $settings->arca_key_path]);
            $result = $service->validateConnection((int) ($settings->arca_pto_vta ?? 1));
            $settings->update(['arca_connected' => true]);
            return response()->json($result);
        } catch (\Throwable $e) {
            $settings->update(['arca_connected' => false]);
            return response()->json(['connected' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function reset(Request $request): JsonResponse
    {
        abort_if($request->user()->owner_id, 403, 'Solo el titular puede vincular ARCA.');
        $userId = $request->user()->id;
        $userDir = 'certs/users/' . $userId;

        foreach (Storage::disk('local')->files($userDir) as $file) { Storage::disk('local')->delete($file); }

        $settings = UserSetting::where('user_id', $userId)->first();
        if ($settings) {
            $settings->update([
                'arca_cert_path' => null,
                'arca_key_path' => null,
                'arca_connected' => 0
            ]);
        }

        return response()->json(['message' => 'Configuración de ARCA eliminada.']);
    }
}
