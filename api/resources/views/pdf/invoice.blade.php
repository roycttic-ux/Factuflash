<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Comprobante {{ $invoice->invoice_number }}</title>
<style>
  @page { margin: 12mm 14mm 16mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #1a1a2e; background: #fff; }
  .header { display: table; width: 100%; border-bottom: 2.5pt solid #4f46e5; padding-bottom: 8pt; margin-bottom: 10pt; }
  .header-left { display: table-cell; width: 60%; vertical-align: middle; }
  .header-right { display: table-cell; width: 40%; vertical-align: middle; text-align: right; }
  .company-name { font-size: 14pt; font-weight: bold; color: #4f46e5; line-height: 1.2; }
  .company-meta, .party-meta { font-size: 7.5pt; color: #555; margin-top: 3pt; line-height: 1.6; }
  .voucher-box { display: inline-block; border: 2pt solid #4f46e5; border-radius: 6pt; padding: 6pt 12pt; text-align: center; }
  .voucher-type { font-size: 22pt; font-weight: bold; color: #4f46e5; line-height: 1; }
  .voucher-label { font-size: 7pt; color: #777; text-transform: uppercase; }
  .voucher-number { font-size: 10pt; font-weight: bold; color: #333; margin-top: 4pt; }
  .voucher-date { font-size: 7.5pt; color: #555; margin-top: 2pt; }
  .parties { display: table; width: 100%; margin-bottom: 10pt; }
  .party { display: table-cell; width: 50%; vertical-align: top; padding: 7pt 8pt; border: 1pt solid #ddd; }
  .party:last-child { border-left: none; background: #fafafa; }
  .section-title { font-size: 8pt; font-weight: bold; color: #4f46e5; margin-bottom: 5pt; }
  .party-title { font-size: 7pt; font-weight: bold; color: #4f46e5; text-transform: uppercase; margin-bottom: 4pt; }
  .party-name { font-size: 9.5pt; font-weight: bold; }
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; font-size: 8pt; table-layout: fixed; }
  .items-table thead { display: table-header-group; }
  .items-table tr { page-break-inside: avoid; }
  .items-table th { background: #4f46e5; color: #fff; padding: 5pt 5pt; text-align: left; font-size: 7pt; }
  .items-table th.num { text-align: right; }
  .items-table td { padding: 5pt; border-bottom: 0.5pt solid #eee; vertical-align: top; overflow-wrap: break-word; }
  .items-table tr:nth-child(even) td { background: #f8f8ff; }
  .td-num { text-align: right; white-space: nowrap; }
  .td-qty { text-align: center; }
  .detail { color: #666; font-size: 7pt; line-height: 1.5; }
  .totals-wrap { display: table; width: 100%; margin-bottom: 10pt; page-break-inside: avoid; }
  .totals-spacer { display: table-cell; width: 50%; }
  .totals-box { display: table-cell; width: 50%; }
  .totals-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
  .totals-table td { padding: 3pt 6pt; }
  .totals-table .lbl { color: #555; }
  .totals-table .val { text-align: right; font-weight: bold; }
  .total-final td { background: #4f46e5; color: #fff !important; font-size: 10pt; font-weight: bold; }
  .cae-section { border-top: 1.5pt solid #e5e7eb; padding-top: 8pt; margin-top: 4pt; page-break-inside: avoid; }
  .cae-label { font-size: 7pt; font-weight: bold; color: #4f46e5; text-transform: uppercase; }
  .cae-value { font-size: 11pt; font-weight: bold; letter-spacing: 1pt; }
  .cae-expiry { font-size: 7.5pt; color: #555; margin-top: 2pt; }
  .cae-valid { color: #065f46; font-size: 7pt; font-weight: bold; }
  .footer { margin-top: 8pt; font-size: 7pt; color: #888; text-align: center; border-top: 0.5pt solid #eee; padding-top: 5pt; }
  .bank-section { margin: 8pt 0; font-size: 7.5pt; color: #555; background: #f9f9f9; padding: 5pt 8pt; page-break-inside: avoid; }
  .nc-notice { background: #fef3c7; border: 1pt solid #f59e0b; padding: 5pt 8pt; margin-bottom: 8pt; font-size: 8pt; color: #92400e; }
</style>
</head>
<body>
@php
  $snap = $invoice->fiscal_snapshot ?? [];
  $fiscal = $snap['fiscal'] ?? null;
  // A stored null is historical data too: never replace it with today's configuration.
  $historical = static fn ($key, $fallback = null) => array_key_exists($key, $snap) ? $snap[$key] : $fallback;
  $issuerName = $historical('issuer_name', $settings?->company_name ?? $invoice->user?->name) ?: 'Emisor';
  $issuerCuit = $historical('issuer_cuit', $settings?->arca_cuit ?? $settings?->company_cuit);
  $issuerAddress = $historical('issuer_address', $settings?->company_address);
  $issuerCondition = $historical('issuer_condition', $settings?->tax_condition);
  $issuerJurisdiction = $historical('issuer_fiscal_jurisdiction', $settings?->fiscal_jurisdiction);
  $issuerActivity = $historical('issuer_business_activity', $settings?->business_activity);
  $issuerIibbNumber = $historical('issuer_iibb_number', $settings?->iibb_number);
  $issuerIibbCondition = $historical('issuer_iibb_condition', $settings?->iibb_condition);
  $iibbLabels = ['local' => 'Local', 'convenio_multilateral' => 'Convenio Multilateral', 'exento' => 'Exento', 'no_inscripto' => 'No inscripto', 'otros' => 'Otros'];
  $ivaLabels = [1 => 'No gravado', 2 => 'Exento', 3 => '0%', 4 => '10,5%', 5 => '21%', 6 => '27%', 8 => '5%', 9 => '2,5%'];
  $tipo = (int) ($invoice->cbte_tipo ?? $snap['cbte_tipo'] ?? 11);
  $letter = match ($tipo) { 1, 2, 3 => 'A', 6, 7, 8 => 'B', 11, 12, 13 => 'C', default => 'X' };
  $typeName = match ($tipo) { 1, 6, 11 => 'Factura', 3, 8, 13 => 'Nota de Crédito', default => 'Comprobante' };
  $parts = explode('-', $invoice->invoice_number);
  $nroDisplay = count($parts) >= 2 ? str_pad($invoice->pto_vta ?? 1, 4, '0', STR_PAD_LEFT) . '-' . str_pad(end($parts), 8, '0', STR_PAD_LEFT) : $invoice->invoice_number;
  $tributeRows = $fiscal['tributos'] ?? [];
  $tributesTotal = (float) ($fiscal['tributos_total'] ?? $invoice->total_tributos ?? 0);
  $total = (float) ($snap['total_local'] ?? $invoice->total_local);
  $recipientName = $historical('recipient_name', $invoice->recipient?->name ?? 'Consumidor Final');
  $recipientAddress = $historical('recipient_address', $invoice->recipient?->address);
  $recipientDocument = $historical('doc_nro', $invoice->recipient?->document_id);
  $recipientConditionId = $historical('condicion_iva_receptor', $invoice->recipient?->iva_condition_id);
  $recipientConditions = [1 => 'Responsable Inscripto', 4 => 'Exento', 5 => 'Consumidor Final', 6 => 'Monotributista'];
@endphp

<div class="header">
  <div class="header-left">
    @if($settings?->company_logo && file_exists(storage_path('app/public/' . $settings->company_logo)))
      <img src="{{ storage_path('app/public/' . $settings->company_logo) }}" style="max-height:45pt; max-width:160pt; margin-bottom:4pt;" />
    @endif
    <div class="company-name">{{ $issuerName }}</div>
    <div class="company-meta">
      CUIT: {{ $issuerCuit ?: '-' }}<br>
      Condición IVA: {{ $issuerCondition ?: 'Sin datos históricos' }}<br>
      @if($issuerAddress) {{ $issuerAddress }}<br>@endif
      @if($issuerJurisdiction) Jurisdicción: {{ $issuerJurisdiction }}<br>@endif
      @if($issuerActivity) Actividad: {{ $issuerActivity }}<br>@endif
      @if($issuerIibbNumber) Ingresos Brutos: {{ $issuerIibbNumber }}<br>@endif
      @if($issuerIibbCondition) Condición IIBB: {{ $iibbLabels[$issuerIibbCondition] ?? $issuerIibbCondition }}@endif
    </div>
  </div>
  <div class="header-right">
    <div class="voucher-box">
      <div class="voucher-type">{{ $letter }}</div>
      <div class="voucher-label">{{ $typeName }}</div>
      <div class="voucher-number">N° {{ $nroDisplay }}</div>
      <div class="voucher-date">{{ \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y') }}</div>
    </div>
  </div>
</div>

@if($invoice->nc_cae)
<div class="nc-notice">Este comprobante fue anulado mediante Nota de Crédito N° {{ $invoice->nc_number }} - CAE: {{ $invoice->nc_cae }}</div>
@endif

<div class="parties">
  <div class="party">
    <div class="party-title">Emisor</div>
    <div class="party-name">{{ $issuerName }}</div>
    <div class="party-meta">
      CUIT: {{ $issuerCuit ?: '-' }}<br>
      Pto. Venta: {{ str_pad($invoice->pto_vta ?? 1, 4, '0', STR_PAD_LEFT) }}<br>
      {{ $issuerCondition ?: 'Sin datos históricos' }}
    </div>
  </div>
  <div class="party">
    <div class="party-title">Receptor</div>
    <div class="party-name">{{ $recipientName }}</div>
    <div class="party-meta">
      @if($recipientDocument) CUIT/DNI: {{ $recipientDocument }}<br>@endif
      @if(isset($recipientConditions[$recipientConditionId])) Cond. IVA: {{ $recipientConditions[$recipientConditionId] }}<br>@endif
      @if($recipientAddress) {{ $recipientAddress }}@endif
    </div>
  </div>
</div>

<table class="items-table">
  <thead><tr>
    <th style="width:5%">#</th><th style="width:39%">Descripción</th><th class="num" style="width:9%">Cant.</th>
    <th class="num" style="width:16%">P. Unit.</th><th class="num" style="width:13%">IVA</th><th class="num" style="width:18%">Subtotal</th>
  </tr></thead>
  <tbody>
  @foreach($invoice->items as $i => $item)
    @php
      $unitPrice = $item->unit_price_local ?? (float) ($item->unit_price_usd ?? 0) * (float) ($invoice->dollar_rate ?? 1);
      $subtotal = round($unitPrice * $item->quantity, 2);
      $ivaLabel = in_array($tipo, [11, 12, 13], true) ? 'No discrimina' : ($ivaLabels[$item->iva_id] ?? 'Sin datos');
    @endphp
    <tr>
      <td>{{ $i + 1 }}</td>
      <td><strong>{{ $item->description_snapshot ?? $item->product_name ?? $item->product?->name ?? '-' }}</strong>
        @if($item->variant_label)<br><span class="detail">{{ $item->variant_label }}</span>@endif
      </td>
      <td class="td-qty">{{ $item->quantity }}</td>
      <td class="td-num">$ {{ number_format($unitPrice, 2, ',', '.') }}</td>
      <td class="td-num">{{ $ivaLabel }}</td>
      <td class="td-num">$ {{ number_format($subtotal, 2, ',', '.') }}</td>
    </tr>
  @endforeach
  </tbody>
</table>

@if(count($tributeRows))
<div class="section-title">Percepciones y tributos</div>
<table class="items-table">
  <thead><tr>
    <th style="width:49%">Tipo, descripción y jurisdicción</th>
    <th class="num" style="width:18%">Base imponible</th>
    <th class="num" style="width:13%">Alícuota</th>
    <th class="num" style="width:20%">Importe</th>
  </tr></thead>
  <tbody>
  @foreach($tributeRows as $tribute)
    <tr>
      <td>
        <strong>{{ $tribute['desc'] }}</strong><br>
        <span class="detail">Tipo ARCA: {{ $tribute['id'] }}
        @if(!empty($tribute['jurisdiction']))<br>Jurisdicción: {{ $tribute['jurisdiction'] }}@endif
        @if(!empty($tribute['padron_reference']))<br>Padrón / referencia: {{ $tribute['padron_reference'] }}@endif
        </span>
      </td>
      <td class="td-num">{{ isset($tribute['base_imp']) ? '$ ' . number_format((float) $tribute['base_imp'], 2, ',', '.') : '-' }}</td>
      <td class="td-num">{{ isset($tribute['alicuota']) ? number_format((float) $tribute['alicuota'], 2, ',', '.') . '%' : '-' }}</td>
      <td class="td-num">$ {{ number_format((float) $tribute['importe'], 2, ',', '.') }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
@endif

<div class="totals-wrap">
  <div class="totals-spacer"></div>
  <div class="totals-box">
    <table class="totals-table">
      @if(is_array($fiscal))
      <tr><td class="lbl">{{ in_array($tipo, [11, 12, 13], true) ? 'Neto (sin IVA)' : 'Neto gravado' }}</td><td class="val">$ {{ number_format((float) ($fiscal['net'] ?? 0), 2, ',', '.') }}</td></tr>
      @if(($fiscal['exempt'] ?? 0) > 0)<tr><td class="lbl">Exento</td><td class="val">$ {{ number_format((float) $fiscal['exempt'], 2, ',', '.') }}</td></tr>@endif
      @if(($fiscal['untaxed'] ?? 0) > 0)<tr><td class="lbl">No gravado</td><td class="val">$ {{ number_format((float) $fiscal['untaxed'], 2, ',', '.') }}</td></tr>@endif
      <tr><td class="lbl">IVA{{ in_array($tipo, [11, 12, 13], true) ? ' (no corresponde)' : '' }}</td><td class="val">$ {{ number_format((float) ($fiscal['vat'] ?? 0), 2, ',', '.') }}</td></tr>
      @else
      <tr><td class="lbl">Subtotal (sin desglose histórico de IVA)</td><td class="val">$ {{ number_format($total - $tributesTotal, 2, ',', '.') }}</td></tr>
      @endif
      <tr><td class="lbl">Percepciones y tributos</td><td class="val">$ {{ number_format($tributesTotal, 2, ',', '.') }}</td></tr>
      <tr class="total-final"><td>TOTAL</td><td class="val">$ {{ number_format($total, 2, ',', '.') }}</td></tr>
    </table>
  </div>
</div>

@if(!empty($snap['rg5003_legend']))<p class="detail" style="margin-bottom:8pt">{{ $snap['rg5003_legend'] }}</p>@endif

@if($settings && ($settings->bank_cbu || $settings->bank_alias))
<div class="bank-section">
  <div class="party-title">Datos para transferencia</div>
  @if($settings->bank_holder) Titular: {{ $settings->bank_holder }} - @endif
  @if($settings->bank_name) {{ $settings->bank_name }} - @endif
  @if($settings->bank_cbu) CBU: {{ $settings->bank_cbu }} @endif
  @if($settings->bank_alias) / Alias: {{ $settings->bank_alias }}@endif
</div>
@endif

@if($invoice->cae)
<div class="cae-section">
  <div class="cae-label">Código de Autorización Electrónica (CAE)</div>
  <div class="cae-value">{{ $invoice->cae }}</div>
  <div class="cae-expiry">
    @php
      $exp = $invoice->cae_expiry;
      if ($exp && strlen((string) $exp) === 8 && !str_contains((string) $exp, '-')) {
        $exp = substr($exp, 0, 4) . '-' . substr($exp, 4, 2) . '-' . substr($exp, 6, 2);
      }
    @endphp
    Vencimiento CAE: {{ $exp ? \Carbon\Carbon::parse($exp)->format('d/m/Y') : '-' }}
    &nbsp;<span class="cae-valid">Autorizado por ARCA/AFIP</span>
  </div>
</div>
@else
<div class="detail">Este comprobante no fue enviado a ARCA/AFIP (sin CAE).</div>
@endif

<div class="footer">Comprobante generado por FactuFlash - Sistema de Facturación Electrónica<br>Fecha de impresión: {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
