<?php

namespace Tests\Feature;

use App\Services\AfipService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AfipServiceSafetyTest extends TestCase
{
    private function service(): array
    {
        Storage::fake('local');
        config(['afip.production' => false]);
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        if (file_exists('C:/xampp/apache/conf/openssl.cnf')) $options['config'] = 'C:/xampp/apache/conf/openssl.cnf';
        $key = openssl_pkey_new($options);
        $csr = openssl_csr_new(['commonName' => 'FactuFlash test', 'organizationName' => 'AFIP'], $key, $options);
        $cert = openssl_csr_sign($csr, null, $key, 30, $options);
        openssl_pkey_export($key, $keyPem, null, $options);
        openssl_x509_export($cert, $certPem);
        Storage::disk('local')->put('test/key.pem', $keyPem);
        Storage::disk('local')->put('test/cert.pem', $certPem);
        $service = \Mockery::mock(AfipService::class, ['20451097025', 'test/cert.pem', 'test/key.pem'])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getAuth')->andReturn(['Token' => 'mock', 'Sign' => 'mock', 'Cuit' => '20451097025'])->byDefault();
        $soap = \Mockery::mock();
        $service->shouldReceive('createClient')->with('wsfe')->andReturn($soap)->byDefault();
        return [$service, $soap];
    }

    public function test_timeout_recovers_original_credit_note_without_issuing_again(): void
    {
        [$service, $soap] = $this->service();
        $soap->shouldReceive('FECompUltimoAutorizado')->once()->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 4]]);
        $soap->shouldReceive('FECAESolicitar')->once()->andThrow(new \RuntimeException('Timeout after remote acceptance'));
        $payload = ['request_key' => 'credit:1', 'cbte_tipo' => 13, 'pto_vta' => 1, 'total' => 121,
            'doc_tipo' => 99, 'doc_nro' => 0, 'orig_cbte_tipo' => 11, 'orig_pto_vta' => 1, 'orig_cbte_nro' => 9];
        try { $service->createCreditNote($payload); $this->fail('Expected timeout'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Timeout', $e->getMessage()); }
        $soap->shouldReceive('FECompConsultar')->once()->andReturn((object) ['FECompConsultarResult' => (object) [
            'ResultGet' => (object) ['Resultado' => 'A', 'ImpTotal' => 121,
                'ImpNeto' => 121, 'ImpTotConc' => 0, 'ImpOpEx' => 0, 'ImpIVA' => 0, 'ImpTrib' => 0,
                'DocNro' => 0, 'DocTipo' => 99,
                'CodAutorizacion' => '12345678901234', 'FchVto' => '20261231'],
        ]]);
        $first = $service->createCreditNote($payload);
        $this->assertSame(5, $first['numero']);
        $this->assertSame('12345678901234', $first['cae']);
        $this->assertSame($first, $service->createCreditNote($payload));
    }

    public function test_point_of_sale_must_be_active_and_cae_enabled(): void
    {
        [$service, $soap] = $this->service();
        $soap->shouldReceive('FEParamGetPtosVenta')->once()->andReturn((object) ['FEParamGetPtosVentaResult' => (object) [
            'ResultGet' => (object) ['PtoVenta' => (object) ['Nro' => 3, 'EmisionTipo' => 'CAE', 'Bloqueado' => 'N', 'FchBaja' => 'NULL']],
        ]]);
        $this->assertTrue($service->validateConnection(3)['connected']);
    }

    public function test_transient_error_throws_retryable_arca_exception(): void
    {
        [$service, $soap] = $this->service();
        $soap->shouldReceive('FECompUltimoAutorizado')->once()->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 4]]);
        $soap->shouldReceive('FECAESolicitar')->once()->andThrow(new \SoapFault('HTTP', 'Could not connect to host'));

        $payload = ['request_key' => 'invoice:test:1', 'cbte_tipo' => 11, 'pto_vta' => 1, 'total' => 100,
            'doc_tipo' => 99, 'doc_nro' => 0];
        try {
            $service->createInvoice($payload);
            $this->fail('Expected ArcaException');
        } catch (\App\Exceptions\ArcaException $e) {
            $this->assertTrue($e->isRetryable());
            $this->assertSame('invoice:test:1', $e->getSubmissionKey());
        }
    }

    public function test_permanent_rejection_throws_non_retryable_arca_exception(): void
    {
        [$service, $soap] = $this->service();
        $soap->shouldReceive('FECompUltimoAutorizado')->once()->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 4]]);
        $soap->shouldReceive('FECAESolicitar')->once()->andReturn((object) [
            'FECAESolicitarResult' => (object) [
                'FeDetResp' => (object) [
                    'FECAEDetResponse' => [
                        (object) [
                            'Resultado' => 'R',
                            'Observaciones' => (object) [
                                'Obs' => [(object) ['Code' => 10016, 'Msg' => 'El tipo de comprobante no corresponde a la condición de IVA del receptor']]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $payload = ['request_key' => 'invoice:test:2', 'cbte_tipo' => 11, 'pto_vta' => 1, 'total' => 100,
            'doc_tipo' => 99, 'doc_nro' => 0];
        try {
            $service->createInvoice($payload);
            $this->fail('Expected ArcaException');
        } catch (\App\Exceptions\ArcaException $e) {
            $this->assertFalse($e->isRetryable());
            $this->assertStringContainsString('10016', $e->getMessage());
        }
    }

    private function tributePayload(string $key = 'invoice:manual'): array
    {
        return [
            'request_key' => $key, 'cbte_tipo' => 6, 'pto_vta' => 1, 'total' => 124.5,
            'doc_tipo' => 99, 'doc_nro' => 0, 'condicion_iva_receptor' => 5,
            'fiscal' => [
                'net' => 100, 'vat' => 21, 'untaxed' => 0, 'exempt' => 0,
                'rates' => [['Id' => 5, 'BaseImp' => 100, 'Importe' => 21]],
                'tributos_total' => 3.5,
                'tributos' => [
                    ['id' => 2, 'jurisdiction' => 'Buenos Aires', 'desc' => 'Percepción IIBB', 'alicuota' => null, 'base_imp' => null, 'importe' => 2.5, 'padron_reference' => 'P-42'],
                    ['id' => 3, 'jurisdiction' => 'Rosario', 'desc' => 'Tasa municipal', 'alicuota' => 1, 'base_imp' => 100, 'importe' => 1],
                ],
            ],
        ];
    }

    private function catalog(object $soap): void
    {
        $soap->shouldReceive('FEParamGetTiposTributos')->once()->andReturn((object) [
            'FEParamGetTiposTributosResult' => (object) ['ResultGet' => (object) ['TributoTipo' => [
                (object) ['Id' => 2, 'Desc' => 'Impuestos provinciales', 'FchDesde' => '20100917', 'FchHasta' => 'NULL'],
                (object) ['Id' => 3, 'Desc' => 'Impuestos municipales', 'FchDesde' => '20100917', 'FchHasta' => 'NULL'],
            ]]],
        ]);
    }

    private function accepted(): object
    {
        return (object) ['FECAESolicitarResult' => (object) ['FeDetResp' => (object) [
            'FECAEDetResponse' => (object) ['Resultado' => 'A', 'CAE' => '12345678901234', 'CAEFchVto' => '20261231'],
        ]]];
    }

    public function test_manual_tributes_are_sent_in_soap_and_retries_do_not_resubmit(): void
    {
        [$service, $soap] = $this->service();
        $this->catalog($soap);
        $soap->shouldReceive('FECompUltimoAutorizado')->once()->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 4]]);
        $soap->shouldReceive('FECAESolicitar')->once()->withArgs(function (array $request) {
            $detail = $request['FeDetReq']['FECAEDetRequest'];
            $this->assertSame(124.5, $detail['ImpTotal']);
            $this->assertSame(100.0, $detail['ImpNeto']);
            $this->assertSame(21.0, $detail['ImpIVA']);
            $this->assertSame(3.5, $detail['ImpTrib']);
            $this->assertCount(2, $detail['Tributos']['Tributo']);
            $first = $detail['Tributos']['Tributo'][0];
            $this->assertSame('Buenos Aires: Percepción IIBB', $first['Desc']);
            $this->assertSame(0.0, $first['Alic']);
            $this->assertSame(0.0, $first['BaseImp']);
            $this->assertSame(2.5, $first['Importe']);
            return true;
        })->andReturn($this->accepted());
        $payload = $this->tributePayload();
        $result = $service->createInvoice($payload);
        $this->assertSame($result, $service->createInvoice($payload));
    }

    public function test_zero_tributes_are_retained_locally_but_omit_soap_group(): void
    {
        [$service, $soap] = $this->service();
        $payload = $this->tributePayload();
        $payload['total'] = 121;
        $payload['fiscal']['tributos_total'] = 0;
        foreach ($payload['fiscal']['tributos'] as &$tribute) $tribute['importe'] = 0;
        unset($tribute);
        $soap->shouldReceive('FEParamGetTiposTributos')->never();
        $soap->shouldReceive('FECompUltimoAutorizado')->once()->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 4]]);
        $soap->shouldReceive('FECAESolicitar')->once()->withArgs(function (array $request) {
            $detail = $request['FeDetReq']['FECAEDetRequest'];
            $this->assertSame(0.0, $detail['ImpTrib']);
            $this->assertArrayNotHasKey('Tributos', $detail);
            return true;
        })->andReturn($this->accepted());
        $service->createInvoice($payload);
    }

    public function test_invalid_arithmetic_and_negative_or_nonnumeric_amounts_fail_before_network(): void
    {
        [$service, $soap] = $this->service();
        $service->shouldReceive('getAuth')->never();
        $service->shouldReceive('createClient')->never();
        $invalid = [];
        foreach (['importe' => -1, 'base_imp' => -1, 'alicuota' => -1] as $field => $value) {
            $payload = $this->tributePayload();
            $payload['fiscal']['tributos'][0][$field] = $value;
            $invalid[] = $payload;
        }
        foreach (['not a number', null, INF] as $value) {
            $payload = $this->tributePayload();
            $payload['fiscal']['tributos'][0]['importe'] = $value;
            $invalid[] = $payload;
        }
        $payload = $this->tributePayload();
        $payload['fiscal']['tributos_total'] = 4;
        $invalid[] = $payload;
        $payload = $this->tributePayload();
        $payload['total'] = 125;
        $invalid[] = $payload;
        foreach ($invalid as $index => $payload) {
            $payload['request_key'] = 'invalid:' . $index;
            try {
                $service->createInvoice($payload);
                $this->fail('Invalid payload accepted');
            } catch (\Exception $e) {
                $this->assertStringStartsWith('ARCA:', $e->getMessage());
            }
        }
    }

    public function test_tribute_catalog_filters_expired_and_future_types(): void
    {
        [$service, $soap] = $this->service();
        $soap->shouldReceive('FEParamGetTiposTributos')->once()->andReturn((object) [
            'FEParamGetTiposTributosResult' => (object) ['ResultGet' => (object) ['TributoTipo' => [
                (object) ['Id' => 2, 'Desc' => 'Provinciales', 'FchDesde' => '20100917', 'FchHasta' => 'NULL'],
                (object) ['Id' => 77, 'Desc' => 'Vencido', 'FchDesde' => '20000101', 'FchHasta' => '20010101'],
                (object) ['Id' => 88, 'Desc' => 'Futuro', 'FchDesde' => '20990101', 'FchHasta' => 'NULL'],
            ]]],
        ]);
        $this->assertSame([['id' => 2, 'description' => 'Provinciales']], $service->getTributeTypes());
    }

    public function test_unknown_tribute_type_is_rejected_before_number_reservation(): void
    {
        [$service, $soap] = $this->service();
        $this->catalog($soap);
        $soap->shouldReceive('FECompUltimoAutorizado')->never();
        $soap->shouldReceive('FECAESolicitar')->never();
        $payload = $this->tributePayload();
        $payload['fiscal']['tributos'][0]['id'] = 77;
        $this->expectExceptionMessage('no está vigente');
        $service->createInvoice($payload);
    }

    public function test_credit_note_sends_original_positive_tributes_and_matching_association(): void
    {
        [$service, $soap] = $this->service();
        $this->catalog($soap);
        $soap->shouldReceive('FECompUltimoAutorizado')->once()->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 4]]);
        $soap->shouldReceive('FECAESolicitar')->once()->withArgs(function (array $request) {
            $this->assertSame(8, $request['FeCabReq']['CbteTipo']);
            $detail = $request['FeDetReq']['FECAEDetRequest'];
            $this->assertSame(['Tipo' => 6, 'PtoVta' => 1, 'Nro' => 9], $detail['CbtesAsoc']['CbteAsoc']);
            $this->assertSame(3.5, $detail['ImpTrib']);
            $this->assertSame([2.5, 1.0], array_column($detail['Tributos']['Tributo'], 'Importe'));
            return true;
        })->andReturn($this->accepted());
        $payload = $this->tributePayload('credit:manual');
        $payload['cbte_tipo'] = 8;
        $payload += ['orig_cbte_tipo' => 6, 'orig_pto_vta' => 1, 'orig_cbte_nro' => 9];
        $service->createCreditNote($payload);
    }

    public function test_credit_note_cannot_reverse_another_invoice_letter(): void
    {
        [$service, $soap] = $this->service();
        $service->shouldReceive('getAuth')->never();
        $payload = $this->tributePayload('credit:wrong-letter');
        $payload['cbte_tipo'] = 8;
        $payload += ['orig_cbte_tipo' => 11, 'orig_pto_vta' => 1, 'orig_cbte_nro' => 9];
        $this->expectExceptionMessage('debe corresponder');
        $service->createCreditNote($payload);
    }

    public function test_recovery_rejects_same_total_with_different_tribute_breakdown(): void
    {
        [$service, $soap] = $this->service();
        $this->catalog($soap);
        $soap->shouldReceive('FECompUltimoAutorizado')->once()->andReturn((object) ['FECompUltimoAutorizadoResult' => (object) ['CbteNro' => 4]]);
        $soap->shouldReceive('FECAESolicitar')->once()->andThrow(new \RuntimeException('Timeout'));
        $payload = $this->tributePayload();
        try {
            $service->createInvoice($payload);
            $this->fail('Expected timeout');
        } catch (\App\Exceptions\ArcaException $e) {
            $this->assertTrue($e->isRetryable());
        }
        $soap->shouldReceive('FECompConsultar')->once()->andReturn((object) ['FECompConsultarResult' => (object) [
            'ResultGet' => (object) [
                'Resultado' => 'A', 'ImpTotal' => 124.5, 'ImpNeto' => 100, 'ImpIVA' => 21, 'ImpTrib' => 3.5,
                'ImpTotConc' => 0, 'ImpOpEx' => 0, 'DocNro' => 0, 'DocTipo' => 99,
                'CodAutorizacion' => '12345678901234', 'FchVto' => '20261231',
                'Tributos' => (object) ['Tributo' => [
                    (object) ['Id' => 2, 'Desc' => 'Buenos Aires: Percepción IIBB', 'BaseImp' => 0, 'Alic' => 0, 'Importe' => 1.5],
                    (object) ['Id' => 3, 'Desc' => 'Rosario: Tasa municipal', 'BaseImp' => 100, 'Alic' => 1, 'Importe' => 2],
                ]],
            ],
        ]]);
        $this->expectExceptionMessage('corresponde a otro comprobante');
        $service->createInvoice($payload);
    }
}
