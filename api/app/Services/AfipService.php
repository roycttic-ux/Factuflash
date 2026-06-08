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
        // Validar que las credenciales vengan de la base de datos del usuario (nunca del .env)
        if (empty($cuit) || strlen(preg_replace('/[^0-9]/', '', $cuit)) !== 11) {
            throw new Exception("AFIP: CUIT inválido o no configurado. Configurá tu CUIT en Ajustes → AFIP / ARCA.");
        }
        if (empty($cert_path) || empty($key_path)) {
            throw new Exception("AFIP: Certificado o clave privada no configurados. Vinculá tu certificado en Ajustes → AFIP / ARCA.");
        }

        $this->cuit = $cuit;
        $this->cert = Storage::path($cert_path);
        $this->key = Storage::path($key_path);

        if (!file_exists($this->cert)) {
            throw new Exception("AFIP: No se encontró el archivo de certificado. Volvé a vincular tu certificado en Ajustes → AFIP / ARCA.");
        }
        if (!file_exists($this->key)) {
            throw new Exception("AFIP: No se encontró la clave privada. Generá el CSR nuevamente en Ajustes → AFIP / ARCA.");
        }
        
        // Producción siempre — las credenciales reales del usuario solo funcionan en producción
        $this->is_production = true;
    }

    private function getWsdlPath($service)
    {
        $base = __DIR__;
        if ($service === 'wsaa') {
            return $this->is_production
                ? $base . '/wsaa_prod.wsdl'
                : $base . '/wsaa_homo.wsdl';
        }
        return $this->is_production
            ? $base . '/wsfev1_prod.wsdl'
            : $base . '/wsfev1_homo.wsdl';
    }

    /**
     * Get Token and Sign from WSAA
     */
    private function getAuth()
    {
        $cachePath = storage_path('app/private/certs/token_' . $this->cuit . '.json');
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
        $wsdl = $this->getWsdlPath('wsaa');

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
        
        $wsaaUrl = $this->is_production
            ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms'
            : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';

        $client = new SoapClient($wsdl, [
            'cache_wsdl'       => WSDL_CACHE_NONE,
            'trace'            => 1,
            'connection_timeout' => 30,
            'location'         => $wsaaUrl,
            'stream_context'   => stream_context_create(['ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                'ciphers'           => 'DEFAULT@SECLEVEL=0',
            ]])
        ]);
        $res = $client->loginCms(['in0' => $data]);
        
        return simplexml_load_string($res->loginCmsReturn);
    }

    public function createInvoice($data)
    {
        $auth = $this->getAuth();
        $wsdl = $this->getWsdlPath('wsfe');

        $wsfeUrl = $this->is_production
            ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'
            : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

        $client = new SoapClient($wsdl, [
            'cache_wsdl'       => WSDL_CACHE_NONE,
            'trace'            => 1,
            'connection_timeout' => 30,
            'location'         => $wsfeUrl,
            'stream_context'   => stream_context_create(['ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                'ciphers'           => 'DEFAULT@SECLEVEL=0',
            ]])
        ]);
        
        $ptoVta = $data['pto_vta'] ?? 1;
        $cbteTipo = $data['cbte_tipo'] ?? 11; // 11 = Factura C default
        $docTipo = $data['doc_tipo'] ?? 99;   // 99 = Sin identificar / Consumidor Final
        $docNro = $data['doc_nro'] ?? 0;

        // 1. Get last voucher number
        $last = $client->FECompUltimoAutorizado([
            'Auth' => $auth,
            'PtoVta' => $ptoVta,
            'CbteTipo' => $cbteTipo
        ]);
        
        if (isset($last->FECompUltimoAutorizadoResult->Errors)) {
             throw new Exception("Error AFIP (LastNum): " . json_encode($last->FECompUltimoAutorizadoResult->Errors));
        }

        $nextNum = $last->FECompUltimoAutorizadoResult->CbteNro + 1;

        // 2. Prepare Invoice
        $params = [
            'Auth' => $auth,
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $ptoVta,
                    'CbteTipo' => $cbteTipo
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => [
                        'Concepto' => 1, // Products
                        'DocTipo' => $docTipo, 
                        'DocNro' => $docNro,
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
                        'MonCotiz' => 1,
                        'CondicionIVAReceptorId' => $data['condicion_iva_receptor'] ?? 5, // 5 = Consumidor Final default
                    ]
                ]
            ]
        ];

        \Log::info("Enviando a AFIP:", $params);
        $res = $client->FECAESolicitar($params);
        \Log::info("Respuesta AFIP Cruda: " . json_encode($res));

        if (!empty($res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE)) {
            return [
                'cae' => (string)$res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE,
                'vencimiento' => (string)$res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAEFchVto,
                'numero' => $nextNum
            ];
        }

        $errors = $res->FECAESolicitarResult->Errors ?? null;
        $observations = $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->Observaciones ?? null;
        
        $errMsg = "";
        if ($errors) $errMsg .= "Errores: " . json_encode($errors) . " ";
        if ($observations) $errMsg .= "Observaciones: " . json_encode($observations);
        
        if (empty($errMsg)) $errMsg = "Error desconocido";
        
        throw new Exception("Error AFIP (Solicitud): " . $errMsg);
    }

    public function createCreditNote($data)
    {
        $auth = $this->getAuth();
        $wsdl = $this->getWsdlPath('wsfe');

        $wsfeUrl = $this->is_production
            ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'
            : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

        $client = new SoapClient($wsdl, [
            'cache_wsdl'       => WSDL_CACHE_NONE,
            'trace'            => 1,
            'connection_timeout' => 30,
            'location'         => $wsfeUrl,
            'stream_context'   => stream_context_create(['ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                'ciphers'           => 'DEFAULT@SECLEVEL=0',
            ]])
        ]);
        
        $ptoVta = $data['pto_vta'] ?? 1;
        $cbteTipo = $data['cbte_tipo']; // Obligatorio para NC
        $docTipo = $data['doc_tipo'] ?? 99;
        $docNro = $data['doc_nro'] ?? 0;

        // 1. Get last voucher number
        $last = $client->FECompUltimoAutorizado([
            'Auth' => $auth,
            'PtoVta' => $ptoVta,
            'CbteTipo' => $cbteTipo
        ]);
        
        $nextNum = $last->FECompUltimoAutorizadoResult->CbteNro + 1;

        // 2. Prepare Credit Note
        $params = [
            'Auth' => $auth,
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $ptoVta,
                    'CbteTipo' => $cbteTipo
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => [
                        'Concepto' => 1,
                        'DocTipo' => $docTipo, 
                        'DocNro' => $docNro,
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
                        'MonCotiz' => 1,
                        'CondicionIVAReceptorId' => $data['condicion_iva_receptor'] ?? 5,
                        // ESTO ES CLAVE: Comprobantes asociados
                        'CbtesAsoc' => [
                            'CbteAsoc' => [
                                'Tipo' => $data['orig_cbte_tipo'],
                                'PtoVta' => $data['orig_pto_vta'],
                                'Nro' => $data['orig_cbte_nro']
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $res = $client->FECAESolicitar($params);

        if (!empty($res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE)) {
            return [
                'cae' => (string)$res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAE,
                'vencimiento' => (string)$res->FECAESolicitarResult->FeDetResp->FECAEDetResponse->CAEFchVto,
                'numero' => $nextNum
            ];
        }

        $errMsg = isset($res->FECAESolicitarResult->Errors) ? json_encode($res->FECAESolicitarResult->Errors) : "Error desconocido";
        throw new Exception("Error AFIP (NC): " . $errMsg);
    }
}
