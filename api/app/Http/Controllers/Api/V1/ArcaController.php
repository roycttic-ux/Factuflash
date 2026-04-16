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
        if (strlen($cuitClean) !== 11) {
            return response()->json(['error' => 'El CUIT debe tener 11 dígitos.'], 422);
        }

        if (!function_exists('openssl_pkey_new')) {
            return response()->json(['error' => 'OpenSSL no está disponible en este servidor.'], 500);
        }

        $userId = $request->user()->id;
        $userDir = 'certs/users/' . $userId;
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
            "commonName" => "FacturaYa-" . $userId,
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
            'cert' => 'nullable|file',
            'cert_text' => 'nullable|string',
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

        $userId = $request->user()->id;
        $userDir = 'certs/users/' . $userId;
        
        if (!Storage::disk('local')->exists($userDir . '/privada.key')) {
            return response()->json(['error' => 'No se encontró la clave privada. Generá el CSR primero.'], 422);
        }

        $path = $userDir . '/certificado.crt';
        Storage::disk('local')->put($path, $certContent);

        $settings = UserSetting::firstOrCreate(['user_id' => $userId]);
        $settings->update([
            'arca_cert_path' => $path,
            'arca_connected' => 1
        ]);

        return response()->json(['message' => 'Certificado vinculado con éxito.']);
    }

    public function reset(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $userDir = 'certs/users/' . $userId;

        Storage::disk('local')->deleteDirectory($userDir);

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
