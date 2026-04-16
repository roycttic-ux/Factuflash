<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Exception;
use SoapClient;

class AfipService
{
    private $cuit;
    private $cert;
    private $key;
    private $is_production = false;

    public function __construct($cuit, $cert_path, $key_path)
    {
        $this->cuit = $cuit;
        $this->cert = Storage::path($cert_path);
        $this->key = Storage::path($key_path);
    }

    /**
     * Get Token and Sign from WSAA
     */
    private function getAuth()
    {
        $cachePath = storage_path('app/private/certs/token_cache.json');
        $cacheDir = dirname($cachePath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        
        if (file_exists($cachePath)) {
            $data = json_decode(file_get_contents($cachePath), true);
            if (isset($data['expires']) && time() < $data['expires']) {
                return $data['auth'];
            }
        }

        $ta = $this->requestToken();
        $auth = [
            'Token' => (string)$ta->credentials->token,
            'Sign' => (string)$ta->credentials->sign,
            'Cuit' => $this->cuit
        ];

        file_put_contents($cachePath, json_encode([
            'expires' => time() + 36000, // 10 hours
            'auth' => $auth
        ]));

        return $auth;
    }

    private function requestToken()
    {
        $url = "https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl";
        if (!$this->is_production) {
            $url = "https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl";
        }

        // Asegurar configuración de OpenSSL en Windows
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $opensslConfig = 'C:/xampp/apache/conf/openssl.cnf';
            if (file_exists($opensslConfig)) {
                putenv("OPENSSL_CONF=$opensslConfig");
            }
        }

        $tra = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
        <loginTicketRequest version=\"1.0\">
          <header>
            <uniqueId>" . time() . "</uniqueId>
            <generationTime>" . date('c', time() - 600) . "</generationTime>
            <expirationTime>" . date('c', time() + 600) . "</expirationTime>
          </header>
          <service>wsfe</service>
        </loginTicketRequest>";

        $tempFile = tempnam(sys_get_temp_dir(), 'tra');
        file_put_contents($tempFile, $tra);
        
        $cms = tempnam(sys_get_temp_dir(), 'cms');
        
        // Normalizar rutas para Windows
        $certPath = str_replace('\\', '/', $this->cert);
        $keyPath = str_replace('\\', '/', $this->key);

        if (!file_exists($certPath)) throw new Exception("No existe el certificado en: $certPath");
        if (!file_exists($keyPath)) throw new Exception("No existe la clave en: $keyPath");

        // Sign the TRA
        $status = @openssl_pkcs7_sign($tempFile, $cms, "file://" . $certPath, ["file://" . $keyPath, ""], [], PKCS7_BINARY);
        
        if (!$status) {
            $errors = "";
            while ($msg = openssl_error_string()) $errors .= $msg . " ";
            throw new Exception("Error al firmar TRA: " . $errors);
        }

        $data = file_get_contents($cms);
        $data = explode("\n\n", $data);
        $data = $data[1]; // Get only the CMS part
        
        $client = new SoapClient($url);
        $res = $client->loginCms(['in0' => $data]);
        
        return simplexml_load_string($res->loginCmsReturn);
    }

    public function createInvoice($data)
    {
        $auth = $this->getAuth();
        $url = "https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL";
        if (!$this->is_production) {
            $url = "https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL";
        }

        $client = new SoapClient($url);
        
        // 1. Get last voucher number
        $last = $client->FECompUltimoAutorizado([
            'Auth' => $auth,
            'PtoVta' => 1,
            'CbteTipo' => 11 // C default for trial/monotributo
        ]);
        
        $nextNum = $last->FECompUltimoAutorizadoResult->CbteNro + 1;

        // 2. Prepare Invoice
        $params = [
            'Auth' => $auth,
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => 1,
                    'CbteTipo' => 11
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => [
                        'Concepto' => 1, // Products
                        'DocTipo' => 99, // Final consumer
                        'DocNro' => 0,
                        'CbteDesde' => $nextNum,
                        'CbteHasta' => $nextNum,
                        'CbteFch' => date('Ymd'),
                        'ImpTotal' => $data['total'],
                        'ImpTotConc' => 0,
                        'ImpNeto' => $data['total'],
                        'ImpOpEx' => 0,
                        'ImpIVA' => 0,
                        'ImpTrib' => 0,
                        'MonId' => 'PES',
                        'MonCotiz' => 1
                    ]
                ]
            ]
        ];

        $res = $client->FECAESolicitar($params);
        \Log::info("Respuesta AFIP Cruda: " . json_encode($res));

        if (isset($res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE)) {
            return [
                'cae' => (string)$res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE,
                'vencimiento' => (string)$res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAEFchVto,
                'numero' => $nextNum
            ];
        }

        throw new Exception("Error AFIP: " . json_encode($res->FECAESolicitarResult->Errors));
    }
}
