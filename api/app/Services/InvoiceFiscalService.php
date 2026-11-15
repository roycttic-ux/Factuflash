<?php

namespace App\Services;

use App\Models\Recipient;
use App\Models\UserSetting;
use InvalidArgumentException;

class InvoiceFiscalService
{
    public const RATES = [3 => 0, 4 => 10.5, 5 => 21, 6 => 27, 8 => 5, 9 => 2.5];
    public const ANONYMOUS_CONSUMER_FINAL_LIMIT = 344488.0;
    public const RG5003_TEXT = 'El crédito fiscal discriminado en el presente comprobante, sólo podrá ser computado a efectos del Régimen de Sostenimiento e Inclusión Fiscal para Pequeños Contribuyentes de la Ley Nº 27.618';

    /**
     * Comprobante type rules (ARCA WSFEv1):
     *   1  = Factura A  — RI emitting to RI (cond 1) or Monotributista (cond 6, needs RG5003 legend)
     *   6  = Factura B  — RI emitting to CF (cond 5), Exento (cond 4), or any unregistered buyer
     *   11 = Factura C  — Monotributistas/Exentos emitting to anyone
     *   3  = Nota de Crédito A  (reverses type 1)
     *   8  = Nota de Crédito B  (reverses type 6)
     *   13 = Nota de Crédito C  (reverses type 11)
     *
     * condicion_iva_receptor values accepted by ARCA:
     *   1 = Responsable Inscripto
     *   4 = Exento
     *   5 = Consumidor Final
     *   6 = Monotributista
     */

    /** Maps invoice cbte_tipo to the letter prefix used in the invoice number. */
    public static function cbtePrefix(int $type): string
    {
        return match ($type) {
            1, 3    => 'A',
            6, 8    => 'B',
            11, 13  => 'C',
            default => throw new InvalidArgumentException("Tipo de comprobante {$type} no reconocido."),
        };
    }

    /**
     * Returns the Nota de Crédito type that must be used to reverse a given invoice type.
     * Throws if the invoice type cannot be reversed (e.g. a credit note itself).
     */
    public static function creditNoteType(int $invoiceCbteTipo): int
    {
        return match ($invoiceCbteTipo) {
            1  => 3,
            6  => 8,
            11 => 13,
            default => throw new InvalidArgumentException(
                "No es posible generar una Nota de Crédito para el tipo de comprobante {$invoiceCbteTipo}."
            ),
        };
    }

    /**
     * Asserts that the cbte_tipo is coherent with the condicion_iva_receptor.
     * ARCA will reject the request with error 10016 if these are inconsistent.
     *
     * Rules:
     *  - Factura A (1) / NC A (3) → receptor must be RI (1) or Monotributista (6)
     *  - Factura B (6) / NC B (8) → receptor must be CF (5) or Exento (4)
     *  - Factura C (11) / NC C (13) → any condition is valid
     */
    public static function assertTypeMatchesCondition(int $cbteTipo, int $condicion): void
    {
        if (in_array($cbteTipo, [1, 3], true) && !in_array($condicion, [1, 6], true)) {
            throw new InvalidArgumentException(
                'Una Factura A solo puede emitirse a Responsables Inscriptos o Monotributistas ' .
                "(condición recibida: {$condicion})."
            );
        }
        if (in_array($cbteTipo, [6, 8], true) && !in_array($condicion, [4, 5], true)) {
            throw new InvalidArgumentException(
                'Una Factura B solo puede emitirse a Consumidores Finales o Sujetos Exentos ' .
                "(condición recibida: {$condicion})."
            );
        }
    }

    /** Product prices are final ARS prices, including VAT when applicable. */
    public function calculate(UserSetting $settings, ?Recipient $recipient, array &$items, array $explicitTributos = []): array
    {
        $condition = $recipient ? $recipient->iva_condition_id : 5;
        if (!$condition || !in_array((int) $condition, [1, 4, 5, 6], true)) {
            throw new InvalidArgumentException('Configurá la condición frente al IVA del cliente antes de facturar.');
        }
        $registered = $settings->tax_condition === 'Responsable Inscripto';
        if (!$registered && !in_array($settings->tax_condition, ['Monotributista', 'Monotributo', 'Exento', 'IVA Exento'], true)) {
            throw new InvalidArgumentException('Configurá la condición fiscal de tu comercio.');
        }
        // RI emitting to RI(1) or Monotributista(6) → Factura A (cbte_tipo 1)
        // RI emitting to Exento(4) or CF(5)           → Factura B (cbte_tipo 6)
        // Monotributistas/Exentos                     → Factura C (cbte_tipo 11)
        $type = $registered ? (in_array((int) $condition, [1, 6], true) ? 1 : 6) : 11;
        $document = preg_replace('/\D/', '', (string) $recipient?->document_id);
        $docType = $document === '' ? 99 : (strlen($document) === 11 ? 80 : 96);
        if ($document !== '' && !in_array(strlen($document), [7, 8, 11], true)) {
            throw new InvalidArgumentException('El documento del cliente debe ser un DNI de 7 u 8 dígitos o CUIT de 11 dígitos.');
        }
        if (in_array((int) $condition, [1, 4, 6], true) && $docType !== 80) {
            throw new InvalidArgumentException('Ingresá el CUIT del cliente para su condición fiscal.');
        }
        if ($docType === 80 && !CuitValidator::isValid($document)) {
            throw new InvalidArgumentException("El CUIT del cliente ({$document}) no es válido (verificá los 11 dígitos y el dígito verificador).");
        }
        if ($settings->arca_cuit && !CuitValidator::isValid($settings->arca_cuit)) {
            throw new InvalidArgumentException("El CUIT de tu comercio configurado en ARCA ({$settings->arca_cuit}) no es válido.");
        }
        $totals = ['net' => 0, 'vat' => 0, 'exempt' => 0, 'untaxed' => 0, 'rates' => []];
        $groups = [];
        $grossSum = 0;
        // Both A and B preserve each product's VAT in WSFE. C has no VAT.
        // Provincial perceptions never change the VAT configured on products.
        $discriminateIva = $registered;
        foreach ($items as &$item) {
            $gross = (int) round((float) $item['unit_price_usd'] * $item['quantity'] * 100);
            $grossSum += $gross;
            $iva = $item['iva_id'] ?? null;
            if ($discriminateIva && ($iva === null || !in_array((int) $iva, [1, 2, 3, 4, 5, 6, 8, 9], true))) {
                throw new InvalidArgumentException('Configurá el IVA del producto ' . $item['description_snapshot'] . ' antes de emitir Factura A o B.');
            }
            $vat = 0;
            $net = $gross;
            if (!$discriminateIva) {
                // Factura C: no VAT is reported.
                $totals['net'] += $gross;
            } elseif ((int) $iva === 1) {
                $totals['untaxed'] += $gross;
            } elseif ((int) $iva === 2) {
                $totals['exempt'] += $gross;
            } else {
                $net = (int) round($gross / (1 + self::RATES[(int) $iva] / 100));
                $vat = $gross - $net;
                $totals['net'] += $net;
                $totals['vat'] += $vat;
                $groups[$iva] ??= ['Id' => (int) $iva, 'BaseImp' => 0, 'Importe' => 0];
                $groups[$iva]['BaseImp'] += $net;
                $groups[$iva]['Importe'] += $vat;
            }
            $item['net_amount'] = $net / 100;
            $item['vat_amount'] = $vat / 100;
        }
        unset($item);

        foreach (['net', 'vat', 'exempt', 'untaxed'] as $key) $totals[$key] /= 100;
        foreach ($groups as $group) {
            $group['BaseImp'] /= 100;
            $group['Importe'] /= 100;
            $totals['rates'][] = $group;
        }

        // Only explicitly entered tributes are billed. Reference rates, recipient
        // exemptions and legacy agent settings never generate a charge.
        $totals['tributos'] = $this->normalizeTributos($explicitTributos);
        $tributeCents = array_sum(array_map(fn ($t) => (int) round($t['importe'] * 100), $totals['tributos']));
        $totals['tributos_total'] = $tributeCents / 100;
        $totalLocal = ($grossSum + $tributeCents) / 100;
        if ($docType === 99 && $totalLocal >= self::ANONYMOUS_CONSUMER_FINAL_LIMIT) {
            throw new InvalidArgumentException(
                'Por disposición de ARCA, las ventas a Consumidor Final superiores a $' .
                number_format(self::ANONYMOUS_CONSUMER_FINAL_LIMIT, 2, ',', '.') .
                ' deben identificar obligatoriamente al comprador con DNI o CUIT.'
            );
        }

        $isMonotributoReceiver = ((int) $condition === 6);
        $rg5003Legend = ($type === 1 && $isMonotributoReceiver) ? self::RG5003_TEXT : null;

        return [
            'cbte_tipo' => $type, 'doc_tipo' => $docType, 'doc_nro' => (int) $document,
            'condicion_iva_receptor' => (int) $condition,
            'is_monotributo_receiver' => $isMonotributoReceiver,
            'rg5003_legend' => $rg5003Legend,
            'fiscal' => $totals,
            'total_local' => $totalLocal,
            'tributos_total' => $totals['tributos_total'],
            'recipient_name' => $recipient?->name ?? 'Consumidor Final',
            'recipient_address' => $recipient?->address,
            'issuer_name' => $settings->company_name, 'issuer_cuit' => $settings->arca_cuit,
            'issuer_address' => $settings->company_address, 'issuer_condition' => $settings->tax_condition,
            'issuer_fiscal_jurisdiction' => $settings->fiscal_jurisdiction,
            'issuer_business_activity' => $settings->business_activity,
            'issuer_iibb_number' => $settings->iibb_number,
            'issuer_iibb_condition' => $settings->iibb_condition,
            'issuer_default_iibb_alicuota' => $settings->default_iibb_alicuota,
        ];
    }

    /** Preserve manually entered amounts; optional rates and bases are metadata. */
    public function normalizeTributos(array $tributes): array
    {
        $validated = validator(['tributos' => $tributes], self::tributeRules())->validate();
        return array_map(function (array $t): array {
            $desc = trim($t['desc']);
            $jurisdiction = trim($t['jurisdiction']);
            if ($desc === '' || $jurisdiction === '') {
                throw new InvalidArgumentException('Cada tributo debe indicar su descripción y jurisdicción.');
            }
            return [
                'id' => (int) $t['id'],
                'desc' => $desc,
                'jurisdiction' => $jurisdiction,
                'padron_reference' => trim($t['padron_reference'] ?? '') ?: null,
                'base_imp' => isset($t['base_imp']) ? round((float) $t['base_imp'], 2) : null,
                'alicuota' => isset($t['alicuota']) ? round((float) $t['alicuota'], 2) : null,
                'importe' => round((float) $t['importe'], 2),
            ];
        }, $validated['tributos']);
    }

    public static function tributeRules(): array
    {
        return [
            'tributos' => 'nullable|array|max:50',
            'tributos.*' => 'required|array:id,desc,jurisdiction,padron_reference,base_imp,alicuota,importe',
            'tributos.*.id' => 'required|integer|min:1|max:32767',
            'tributos.*.desc' => 'required|string|max:80',
            'tributos.*.jurisdiction' => 'required|string|max:100',
            'tributos.*.padron_reference' => 'nullable|string|max:150',
            'tributos.*.alicuota' => 'nullable|numeric|min:0|max:999.99|decimal:0,2',
            'tributos.*.base_imp' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2',
            'tributos.*.importe' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
        ];
    }
}
