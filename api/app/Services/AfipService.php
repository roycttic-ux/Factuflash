<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Storage;
use SoapClient;

class AfipService
{
    private string $cuit;
    private string $cert;
    private string $key;
    private bool $production;
    private string $fingerprint;

    public function __construct($cuit, $cert_path, $key_path)
    {
        $this->cuit = preg_replace('/\D/', '', (string) $cuit);
        if (strlen($this->cuit) !== 11) throw new Exception('ARCA: configurá un CUIT de 11 dígitos.');
        if (!$cert_path || !$key_path) throw new Exception('ARCA: vinculá tu certificado y clave privada en Configuración.');
        $disk = Storage::disk('local');
        if (!$disk->exists($cert_path) || !$disk->exists($key_path)) {
            throw new Exception('ARCA: no se encontró el certificado o la clave privada. Volvé a vincularlos.');
        }
        $this->cert = $disk->path($cert_path);
        $this->key = $disk->path($key_path);
        $this->production = (bool) config('afip.production', true);
        $certificate = @openssl_x509_read(file_get_contents($this->cert));
        $privateKey = @openssl_pkey_get_private(file_get_contents($this->key));
        if (!$certificate || !$privateKey || !openssl_x509_check_private_key($certificate, $privateKey)) {
            throw new Exception('ARCA: el certificado no corresponde a la clave privada. Usá el certificado del CSR generado aquí.');
        }
        $info = openssl_x509_parse($certificate);
        if (!$info || ($info['validFrom_time_t'] ?? PHP_INT_MAX) > time() || ($info['validTo_time_t'] ?? 0) <= time()) {
            throw new Exception('ARCA: el certificado está vencido o todavía no tiene vigencia. Renovalo antes de facturar.');
        }
        $this->fingerprint = openssl_x509_fingerprint($certificate, 'sha256');
    }

    protected function createClient(string $service): object
    {
        if (app()->environment('testing') && $this->production) {
            throw new Exception('ARCA: las pruebas automatizadas no pueden conectarse a producción.');
        }
        if (!class_exists(SoapClient::class)) throw new Exception('ARCA: falta habilitar la extensión SOAP en el servidor.');
        $environment = $this->production ? 'prod' : 'homo';
        $wsdl = __DIR__ . '/' . ($service === 'wsaa' ? 'wsaa' : 'wsfev1') . "_{$environment}.wsdl";
        $url = $service === 'wsaa'
            ? ($this->production ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms' : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms')
            : ($this->production ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx' : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx');
        return new SoapClient($wsdl, [
            'cache_wsdl' => WSDL_CACHE_NONE, 'trace' => false, 'exceptions' => true,
            'connection_timeout' => (int) config('afip.timeout', 30), 'location' => $url,
            'stream_context' => stream_context_create([
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
                'http' => ['timeout' => (int) config('afip.timeout', 30)],
            ]),
        ]);
    }

    protected function getAuth(): array
    {
        $directory = dirname($this->cert) . '/tokens';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new Exception('ARCA: no se pudo crear el almacenamiento privado de credenciales.');
        }
        $cachePath = $directory . '/' . hash('sha256', $this->cuit . $this->fingerprint . ($this->production ? 'prod' : 'homo') . 'wsfe') . '.json';
        $lock = fopen($cachePath . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            if ($lock) fclose($lock);
            throw new Exception('ARCA: no se pudo bloquear la renovación del acceso. Reintentá.');
        }
        @chmod($cachePath . '.lock', 0600);
        try {
            $cached = is_file($cachePath) ? json_decode(file_get_contents($cachePath), true) : null;
            if (($cached['expires'] ?? 0) > time() + 60 && !empty($cached['auth']['Token']) && !empty($cached['auth']['Sign'])) return $cached['auth'];
            $ticket = $this->requestToken();
            $expires = strtotime((string) $ticket->header->expirationTime);
            $auth = ['Token' => (string) $ticket->credentials->token, 'Sign' => (string) $ticket->credentials->sign, 'Cuit' => $this->cuit];
            if (!$expires || $expires <= time() + 60 || !$auth['Token'] || !$auth['Sign']) throw new Exception('ARCA: WSAA devolvió un acceso inválido o vencido.');
            if (file_put_contents($cachePath, json_encode(['expires' => $expires, 'auth' => $auth]), LOCK_EX) === false) throw new Exception('ARCA: no se pudo guardar el acceso de forma segura.');
            @chmod($cachePath, 0600);
            return $auth;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function requestToken(): \SimpleXMLElement
    {
        if (PHP_OS_FAMILY === 'Windows' && file_exists('C:/xampp/apache/conf/openssl.cnf')) putenv('OPENSSL_CONF=C:/xampp/apache/conf/openssl.cnf');
        $tra = '<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"><header><uniqueId>' . time() . '</uniqueId>'
            . '<generationTime>' . date('c', time() - 600) . '</generationTime><expirationTime>' . date('c', time() + 600) . '</expirationTime>'
            . '</header><service>wsfe</service></loginTicketRequest>';
        $input = tempnam(sys_get_temp_dir(), 'ff-tra-');
        $output = tempnam(sys_get_temp_dir(), 'ff-cms-');
        try {
            if (!$input || !$output || file_put_contents($input, $tra) === false) throw new Exception('ARCA: no se pudo preparar la solicitud de acceso.');
            @chmod($input, 0600);
            @chmod($output, 0600);
            $signed = @openssl_pkcs7_sign($input, $output, 'file://' . str_replace('\\', '/', $this->cert), ['file://' . str_replace('\\', '/', $this->key), ''], [], PKCS7_BINARY);
            if (!$signed) throw new Exception('ARCA: no se pudo firmar la solicitud. Revisá el certificado y su clave privada.');
            $parts = preg_split('/\r?\n\r?\n/', file_get_contents($output), 2);
            $cms = preg_replace('/\s+/', '', $parts[1] ?? '');
            if (!$cms || base64_decode($cms, true) === false) throw new Exception('ARCA: no se pudo preparar la firma de la solicitud.');
            $result = $this->createClient('wsaa')->loginCms(['in0' => $cms]);
            $previous = libxml_use_internal_errors(true);
            try {
                $ticket = simplexml_load_string($result->loginCmsReturn ?? '', \SimpleXMLElement::class, LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (!$ticket) throw new Exception('ARCA: WSAA devolvió una respuesta inválida.');
            return $ticket;
        } finally {
            if ($input && is_file($input)) unlink($input);
            if ($output && is_file($output)) unlink($output);
        }
    }

    /** Read-only authorization check: no invoice is issued. */
    public function validateConnection(int $pointOfSale): array
    {
        if ($pointOfSale < 1 || $pointOfSale > 99999) throw new Exception('ARCA: el punto de venta debe estar entre 1 y 99999.');
        $response = $this->createClient('wsfe')->FEParamGetPtosVenta(['Auth' => $this->getAuth()]);
        $result = $response->FEParamGetPtosVentaResult ?? null;
        $this->assertResponse($result, 'consulta de puntos de venta');
        $points = $result->ResultGet->PtoVenta ?? [];
        if (!is_array($points)) $points = [$points];
        foreach ($points as $point) {
            if ((int) ($point->Nro ?? 0) !== $pointOfSale) continue;
            $removed = preg_replace('/[^0-9]/', '', (string) ($point->FchBaja ?? ''));
            if (strtoupper((string) ($point->Bloqueado ?? '')) === 'S' || ($removed && trim($removed, '0'))) throw new Exception('ARCA: el punto de venta está bloqueado o dado de baja.');
            if (strtoupper((string) ($point->EmisionTipo ?? '')) !== 'CAE') throw new Exception('ARCA: seleccioná un punto de venta Web Services habilitado para CAE.');
            return ['connected' => true, 'point_of_sale' => $pointOfSale, 'environment' => $this->production ? 'production' : 'homologation'];
        }
        throw new Exception('ARCA: no se encontró ese punto de venta. Habilitalo para Web Services y autorizá wsfe en Administrador de Relaciones.');
    }

    public function createInvoice($data) { return $this->durableSubmit($data, false); }
    public function createCreditNote($data) { return $this->durableSubmit($data, true); }

    /** Current WSFE tribute identifiers; never guess an identifier from a label. */
    public function getTributeTypes(): array
    {
        try {
            $response = $this->createClient('wsfe')->FEParamGetTiposTributos(['Auth' => $this->getAuth()]);
        } catch (\Throwable $e) {
            throw \App\Exceptions\ArcaException::transient('ARCA: no se pudo consultar el catálogo de tributos.', $e);
        }
        $result = $response->FEParamGetTiposTributosResult ?? null;
        $this->assertResponse($result, 'catálogo de tributos');
        $entries = $result->ResultGet->TributoTipo ?? [];
        if (!is_array($entries)) $entries = [$entries];
        $today = now('America/Argentina/Buenos_Aires')->format('Ymd');
        $types = [];
        foreach ($entries as $entry) {
            $from = preg_replace('/\D/', '', (string) ($entry->FchDesde ?? ''));
            $until = preg_replace('/\D/', '', (string) ($entry->FchHasta ?? ''));
            if (($from && trim($from, '0') && $from > $today)
                || ($until && trim($until, '0') && $until < $today)) continue;
            $id = (int) ($entry->Id ?? 0);
            if ($id > 0) $types[$id] = ['id' => $id, 'description' => (string) ($entry->Desc ?? '')];
        }
        if (!$types) throw \App\Exceptions\ArcaException::transient('ARCA: no se recibieron tipos de tributo vigentes.');
        return array_values($types);
    }

    private function amount($value, string $label, float $maximum = 9999999999.99): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > $maximum) {
            throw new Exception("ARCA: {$label} debe ser un importe numérico no negativo dentro del rango permitido.");
        }
        return round((float) $value, 2);
    }

    /** Validate local arithmetic before making any request, including authentication. */
    private function fiscalDetail(?array $fiscal, float $total): array
    {
        $detail = [
            'ImpTotal' => $total,
            'ImpTotConc' => $this->amount($fiscal['untaxed'] ?? 0, 'el monto no gravado'),
            'ImpNeto' => $this->amount($fiscal['net'] ?? $total, 'el neto'),
            'ImpOpEx' => $this->amount($fiscal['exempt'] ?? 0, 'el monto exento'),
            'ImpIVA' => $this->amount($fiscal['vat'] ?? 0, 'el IVA'),
            'ImpTrib' => $this->amount($fiscal['tributos_total'] ?? 0, 'el total de tributos'),
        ];
        $tributes = [];
        $tributeCents = 0;
        foreach ($fiscal['tributos'] ?? [] as $tribute) {
            $id = $tribute['id'] ?? null;
            if (!is_numeric($id) || (int) $id != $id || (int) $id < 1 || (int) $id > 32767) {
                throw new Exception('ARCA: cada tributo debe tener un identificador válido del catálogo.');
            }
            $description = trim((string) ($tribute['desc'] ?? ''));
            $jurisdiction = trim((string) ($tribute['jurisdiction'] ?? ''));
            if ($description === '') throw new Exception('ARCA: cada tributo debe tener una descripción.');
            // WSFE has no jurisdiction field for Tributo. Preserve it in Desc (maximum 80 characters).
            $description = mb_substr(($jurisdiction !== '' ? $jurisdiction . ': ' : '') . $description, 0, 80);
            $amount = $this->amount($tribute['importe'] ?? null, 'el importe del tributo');
            $tributes[] = [
                'Id' => (int) $id, 'Desc' => $description,
                'BaseImp' => $this->amount($tribute['base_imp'] ?? 0, 'la base del tributo'),
                'Alic' => $this->amount($tribute['alicuota'] ?? 0, 'la alícuota del tributo', 999.99),
                'Importe' => $amount,
            ];
            $tributeCents += (int) round($amount * 100);
        }
        if ($tributeCents !== (int) round($detail['ImpTrib'] * 100)) {
            throw new Exception('ARCA: ImpTrib no coincide con la suma de los tributos del comprobante.');
        }
        $componentCents = 0;
        foreach (['ImpTotConc', 'ImpNeto', 'ImpOpEx', 'ImpIVA', 'ImpTrib'] as $key) {
            $componentCents += (int) round($detail[$key] * 100);
        }
        if ($componentCents !== (int) round($total * 100)) {
            throw new Exception('ARCA: el total no coincide con la suma de neto, IVA, exentos, no gravados y tributos.');
        }
        if (!empty($fiscal['rates'])) $detail['Iva'] = ['AlicIva' => $fiscal['rates']];
        if ($tributeCents > 0) $detail['Tributos'] = ['Tributo' => $tributes];
        return $detail;
    }

    private function matchesFiscalDetail(object $voucher, array $detail): bool
    {
        foreach (['ImpTotal', 'ImpTotConc', 'ImpNeto', 'ImpOpEx', 'ImpIVA', 'ImpTrib'] as $key) {
            if (!isset($voucher->$key) || !is_numeric($voucher->$key)
                || (int) round((float) $voucher->$key * 100) !== (int) round($detail[$key] * 100)) return false;
        }
        $actual = $voucher->Tributos->Tributo ?? [];
        if (!is_array($actual)) $actual = [$actual];
        $expected = $detail['Tributos']['Tributo'] ?? [];
        $canonical = function (array $entries): array {
            $rows = [];
            foreach ($entries as $entry) {
                $entry = (array) $entry;
                foreach (['Id', 'Desc', 'BaseImp', 'Alic', 'Importe'] as $key) {
                    if (!isset($entry[$key])) return ['invalid'];
                }
                $rows[] = json_encode([
                    (int) $entry['Id'], trim((string) $entry['Desc']),
                    (int) round((float) $entry['BaseImp'] * 100),
                    (int) round((float) $entry['Alic'] * 100),
                    (int) round((float) $entry['Importe'] * 100),
                ]);
            }
            sort($rows, SORT_STRING);
            return $rows;
        };
        return $canonical($actual) === $canonical($expected);
    }

    private function durableSubmit(array $data, bool $creditNote): array
    {
        $directory = dirname($this->cert) . '/operations';
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        $scope = $this->cuit . ':' . ($this->production ? 'prod' : 'homo');
        $lock = fopen($directory . '/' . hash('sha256', $scope) . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw \App\Exceptions\ArcaException::transient('ARCA: otra operación está en curso. Reintentá.', null, $data['request_key'] ?? null);
        }
        try {
            if (empty($data['request_key'])) throw new Exception('ARCA: falta la identificación de la operación.');
            $journal = $directory . '/' . hash('sha256', $scope . ':' . $data['request_key']) . '.json';
            $hash = hash('sha256', json_encode($data));
            $saved = is_file($journal) ? json_decode(file_get_contents($journal), true) : null;
            if ($saved && ($saved['hash'] ?? '') !== $hash) {
                throw \App\Exceptions\ArcaException::permanent('ARCA: hay una operación previa con otros datos. Verificá su estado antes de reintentar.');
            }
            if (!empty($saved['result'])) return $saved['result'];
            $data['_journal'] = $journal;
            $data['_hash'] = $hash;
            $data['_reserved'] = $saved['number'] ?? null;
            $result = $this->submit($data, $creditNote);
            $this->saveOperation($journal, ['hash' => $hash, 'number' => $result['numero'], 'result' => $result]);
            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function saveOperation(string $path, array $operation): void
    {
        if (file_put_contents($path, json_encode($operation), LOCK_EX) === false) {
            throw new Exception('ARCA: no se pudo guardar el registro de la operación. Consultá su estado antes de reintentar.');
        }
        @chmod($path, 0600);
    }

    private function submit(array $data, bool $creditNote): array
    {
        $type = (int) ($data['cbte_tipo'] ?? ($creditNote ? 13 : 11));
        if (!in_array($type, $creditNote ? [3, 8, 13] : [1, 6, 11], true)) throw new Exception('ARCA: tipo de comprobante inválido.');
        $total = $this->amount($data['total'] ?? null, 'el total');
        $point = (int) ($data['pto_vta'] ?? 1);
        if ($total <= 0 || $point < 1 || $point > 99999) throw new Exception('ARCA: verificá el importe y el punto de venta.');
        // Guard: cbte_tipo must be coherent with condicion_iva_receptor to avoid ARCA error 10016
        $condicionReceptor = (int) ($data['condicion_iva_receptor'] ?? 5);
        \App\Services\InvoiceFiscalService::assertTypeMatchesCondition($type, $condicionReceptor);
        $fiscal = $data['fiscal'] ?? null;
        if (!in_array($type, [11, 13], true) && !$fiscal) throw new Exception('ARCA: falta el desglose de IVA del comprobante. Configurá las alícuotas de los productos y la condición fiscal del cliente.');
        if ($creditNote && (!in_array((int) ($data['orig_cbte_tipo'] ?? 0), [1, 6, 11], true) || (int) ($data['orig_cbte_nro'] ?? 0) < 1)) throw new Exception('ARCA: falta identificar la factura original para la nota de crédito.');
        if ($creditNote && (InvoiceFiscalService::creditNoteType((int) $data['orig_cbte_tipo']) !== $type
            || (int) ($data['orig_pto_vta'] ?? 0) < 1 || (int) ($data['orig_pto_vta'] ?? 0) > 99999)) {
            throw new Exception('ARCA: la nota de crédito debe corresponder al tipo y punto de venta de la factura original.');
        }
        $fiscalDetail = $this->fiscalDetail($fiscal, $total);
        $auth = $this->getAuth();
        $client = $this->createClient('wsfe');
        if (!empty($data['_reserved'])) {
            try {
                $lookup = $client->FECompConsultar(['Auth' => $auth, 'FeCompConsReq' => [
                    'CbteTipo' => $type, 'PtoVta' => $point, 'CbteNro' => $data['_reserved'],
                ]])->FECompConsultarResult ?? null;
            } catch (\Throwable $e) {
                throw \App\Exceptions\ArcaException::transient('ARCA: no se pudo verificar el resultado previo: ' . $e->getMessage(), $e, $data['request_key'] ?? null);
            }
            $voucher = $lookup->ResultGet ?? null;
            if ($voucher) {
                if (($voucher->Resultado ?? '') !== 'A'
                    || !$this->matchesFiscalDetail($voucher, $fiscalDetail)
                    || (string) ($voucher->DocNro ?? '') !== (string) ($data['doc_nro'] ?? 0)
                    || (int) ($voucher->DocTipo ?? 0) !== (int) ($data['doc_tipo'] ?? 99)) {
                    throw \App\Exceptions\ArcaException::permanent('ARCA: el número reservado corresponde a otro comprobante. Revisá la operación; no se emitió un duplicado.');
                }
                return ['cae' => (string) $voucher->CodAutorizacion, 'vencimiento' => (string) $voucher->FchVto, 'numero' => $data['_reserved']];
            }
            $errors = $lookup->Errors->Err ?? [];
            if (!is_array($errors)) $errors = [$errors];
            if (!$errors || count(array_filter($errors, fn ($e) => (int) ($e->Code ?? 0) !== 602))) {
                throw \App\Exceptions\ArcaException::transient('ARCA: no se pudo verificar el resultado de la operación anterior. Reintentá más tarde.', null, $data['request_key'] ?? null);
            }
        }
        if (!empty($fiscalDetail['Tributos'])) {
            $validIds = array_column($this->getTributeTypes(), 'id');
            foreach ($fiscalDetail['Tributos']['Tributo'] as $tribute) {
                if (!in_array($tribute['Id'], $validIds, true)) {
                    throw new Exception('ARCA: el tipo de tributo ' . $tribute['Id'] . ' no está vigente en el catálogo.');
                }
            }
        }
        try {
            $lastResponse = $client->FECompUltimoAutorizado(['Auth' => $auth, 'PtoVta' => $point, 'CbteTipo' => $type]);
        } catch (\Throwable $e) {
            throw \App\Exceptions\ArcaException::transient('ARCA: no se pudo consultar el último comprobante: ' . $e->getMessage(), $e, $data['request_key'] ?? null);
        }
        $last = $lastResponse->FECompUltimoAutorizadoResult ?? null;
        $this->assertResponse($last, 'último comprobante', $data['request_key'] ?? null);
        if (!isset($last->CbteNro) || !is_numeric($last->CbteNro) || $last->CbteNro < 0) throw new Exception('ARCA: no se pudo determinar el último comprobante autorizado.');
        $next = $data['_reserved'] ?? ((int) $last->CbteNro + 1);
        if ($next !== (int) $last->CbteNro + 1) throw new Exception('ARCA: numeración desactualizada. Consultá la operación antes de reintentar.');
        $this->saveOperation($data['_journal'], ['hash' => $data['_hash'], 'number' => $next]);
        $detail = [
            'Concepto' => 1, 'DocTipo' => $data['doc_tipo'] ?? 99, 'DocNro' => $data['doc_nro'] ?? 0,
            'CbteDesde' => $next, 'CbteHasta' => $next, 'CbteFch' => now('America/Argentina/Buenos_Aires')->format('Ymd'),
            'MonId' => 'PES', 'MonCotiz' => 1, 'CondicionIVAReceptorId' => $data['condicion_iva_receptor'] ?? 5,
        ] + $fiscalDetail;
        if ($creditNote) $detail['CbtesAsoc'] = ['CbteAsoc' => ['Tipo' => $data['orig_cbte_tipo'], 'PtoVta' => $data['orig_pto_vta'], 'Nro' => $data['orig_cbte_nro']]];
        try {
            $response = $client->FECAESolicitar(['Auth' => $auth, 'FeCabReq' => ['CantReg' => 1, 'PtoVta' => $point, 'CbteTipo' => $type], 'FeDetReq' => ['FECAEDetRequest' => $detail]]);
        } catch (\Throwable $e) {
            throw \App\Exceptions\ArcaException::transient('ARCA no respondió o se agotó el tiempo de espera: ' . $e->getMessage(), $e, $data['request_key'] ?? null);
        }
        $result = $response->FECAESolicitarResult ?? null;
        $this->assertResponse($result, 'solicitud de CAE', $data['request_key'] ?? null);
        $authorized = $result->FeDetResp->FECAEDetResponse ?? null;
        if (is_array($authorized)) $authorized = $authorized[0] ?? null;
        if (($authorized->Resultado ?? '') === 'A' && preg_match('/^\d{14}$/', (string) ($authorized->CAE ?? '')) && preg_match('/^\d{8}$/', (string) ($authorized->CAEFchVto ?? ''))) {
            return ['cae' => (string) $authorized->CAE, 'vencimiento' => (string) $authorized->CAEFchVto, 'numero' => $next];
        }
        if (($authorized->Resultado ?? '') === 'R') unlink($data['_journal']);
        throw \App\Exceptions\ArcaException::permanent('ARCA rechazó el comprobante: ' . $this->messages($authorized->Observaciones ?? null));
    }

    private function assertResponse(?object $result, string $operation, ?string $requestKey = null): void
    {
        if (!$result) throw \App\Exceptions\ArcaException::transient("ARCA: respuesta inválida en {$operation}.", null, $requestKey);
        if (!empty($result->Errors)) {
            $msg = $this->messages($result->Errors);
            $entries = $result->Errors->Err ?? [];
            if (!is_array($entries)) $entries = [$entries];
            $transientCodes = [500, 501, 502, 600];
            $isTransient = collect($entries)->contains(fn ($e) => in_array((int) ($e->Code ?? 0), $transientCodes, true));
            if ($isTransient) {
                throw \App\Exceptions\ArcaException::transient("ARCA ({$operation}): {$msg}", null, $requestKey);
            }
            throw \App\Exceptions\ArcaException::permanent("ARCA ({$operation}): {$msg}");
        }
    }

    private function messages($errors): string
    {
        $entries = $errors->Err ?? $errors->Obs ?? [];
        if (!is_array($entries)) $entries = [$entries];
        $messages = array_filter(array_map(fn ($entry) => trim(($entry->Code ?? '') . ' ' . ($entry->Msg ?? '')), $entries));
        return $messages ? implode('; ', $messages) : 'no se recibió una autorización válida. Revisá la configuración y consultá el estado antes de reintentar.';
    }
}
