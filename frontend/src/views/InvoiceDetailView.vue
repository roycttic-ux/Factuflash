<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { invoicesApi } from '@/api/services'
import InvoicePaymentPanel from '@/components/InvoicePaymentPanel.vue'

const router = useRouter()
const route = useRoute()
const invoice = ref<any>(null)
const loading = ref(true)
const showingCredit = ref(false)
const ivaLabels: Record<number, string> = { 1: 'Responsable Inscripto', 4: 'Exento', 5: 'Consumidor Final', 6: 'Monotributista' }
const error = ref('')
const fiscal = computed(() => invoice.value?.fiscal_snapshot?.fiscal || {})
const historicalTributes = computed<any[]>(() => fiscal.value.tributos || [])
const tributeTotal = computed(() => Number(fiscal.value.tributos_total ?? invoice.value?.fiscal_snapshot?.tributos_total ?? invoice.value?.total_tributos ?? 0))
const iibbLabels: Record<string, string> = { local: 'Contribuyente local', convenio_multilateral: 'Convenio Multilateral', exento: 'Exento', no_inscripto: 'No inscripto', otros: 'Otros' }
const auditLabels: Record<string, string> = { added: 'Tributos agregados', modified: 'Tributos modificados', reversed: 'Tributos revertidos por nota de crédito' }

onMounted(async () => {
  try {
    const res = await invoicesApi.get(Number(route.params.id))
    invoice.value = res.data.invoice
  } catch {
    error.value = 'No se pudo cargar la factura'
  } finally {
    loading.value = false
  }
})

function formatARS(n: number) {
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}
function formatDate(d: string) {
  if (!d) return ''
  // AFIP returns cae_expiry as YYYYMMDD without dashes
  if (d.length === 8 && !d.includes('-')) {
    const yyyy = d.substring(0, 4)
    const mm = d.substring(4, 6)
    const dd = d.substring(6, 8)
    d = `${yyyy}-${mm}-${dd}T00:00:00`
  }
  return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' })
}
function printInvoice() {
  window.print()
}

const downloadingPdf = ref(false)
async function downloadPdf() {
  if (!invoice.value) return
  downloadingPdf.value = true
  try {
    const res = await invoicesApi.downloadPdf(invoice.value.id)
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = `factura-${invoice.value.invoice_number}.pdf`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  } catch (e) {
    alert('No se pudo generar el PDF. Intentá de nuevo.')
  } finally {
    downloadingPdf.value = false
  }
}

function getAfipQrUrl(inv: any) {
  if (!inv || !inv.cae) return ''
  
  // Extract sequential invoice number from the invoice_number string (e.g. "C-0001-00000005" -> 5)
  const parts = inv.invoice_number.split('-')
  const nroCmp = parseInt(parts[2] || parts[parts.length - 1]) || 1
  
  // Clean CUIT
  const cuitClean = Number(inv.fiscal_snapshot?.issuer_cuit || inv.user?.settings?.arca_cuit || 0)
  
  // Recipient details
  let tipoDocRec = 99
  let nroDocRec = 0
  if (inv.recipient && inv.recipient.document_id) {
    const cleanDoc = inv.recipient.document_id.replace(/[^0-9]/g, '')
    nroDocRec = parseInt(cleanDoc) || 0
    if (cleanDoc.length === 11) tipoDocRec = 80 // CUIT
    else if (cleanDoc.length === 8) tipoDocRec = 96 // DNI
  }
  
  // Format date to YYYY-MM-DD
  const dateObj = new Date(showingCredit.value ? inv.nc_issued_at : inv.created_at)
  const yyyy = dateObj.getFullYear()
  const mm = String(dateObj.getMonth() + 1).padStart(2, '0')
  const dd = String(dateObj.getDate()).padStart(2, '0')
  const fecha = `${yyyy}-${mm}-${dd}`
  
  const qrData = {
    ver: 1,
    fecha,
    cuit: cuitClean,
    ptoVta: inv.pto_vta || 1,
    tipoCmp: showingCredit.value ? ({ 1: 3, 6: 8, 11: 13 } as Record<number, number>)[inv.cbte_tipo || 11] : inv.cbte_tipo || 11,
    nroCmp: showingCredit.value ? Number(inv.nc_number) : nroCmp,
    importe: Number(inv.total_local),
    moneda: "PES",
    cotiz: 1,
    tipoDocRec: inv.fiscal_snapshot?.doc_tipo ?? tipoDocRec,
    nroDocRec: inv.fiscal_snapshot?.doc_nro ?? nroDocRec,
    tipoCodAut: "E",
    codAut: Number(showingCredit.value ? inv.nc_cae : inv.cae)
  }
  
  const jsonStr = JSON.stringify(qrData)
  const base64Str = btoa(unescape(encodeURIComponent(jsonStr)))
  return `https://www.afip.gob.ar/fe/qr/?p=${base64Str}`
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="page-header">
      <div>
        <button class="btn btn-ghost btn-sm" @click="router.back()" style="margin-bottom:0.5rem;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Volver
        </button>
        <h1 class="page-title">Detalle de Factura</h1>
      </div>
      <div class="flex gap-2">
        <button v-if="invoice" class="btn btn-ghost" @click="printInvoice" id="btn-print-invoice">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
          </svg>
          Imprimir
        </button>
        <button v-if="invoice" class="btn btn-primary" @click="downloadPdf" :disabled="downloadingPdf" id="btn-download-pdf">
          <span v-if="downloadingPdf" class="spinner"></span>
          <template v-else>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Descargar PDF
          </template>
        </button>
      </div>
    </div>

    <div v-if="invoice?.nc_cae" class="card" style="margin-bottom:1rem">
      <strong>Factura anulada mediante Nota de Crédito N° {{ invoice.nc_number }} · CAE {{ invoice.nc_cae }}</strong>
      <p>La anulación fiscal no devuelve automáticamente el cobro. Gestioná la devolución en el medio de pago original.</p>
      <button class="btn btn-ghost" @click="showingCredit = !showingCredit">{{ showingCredit ? 'Ver factura original' : 'Ver e imprimir nota de crédito' }}</button>
    </div>
    <InvoicePaymentPanel v-if="invoice" :invoice="invoice" @updated="invoice = $event" />

    <!-- Loading -->
    <div v-if="loading" style="display:flex;justify-content:center;padding:4rem;">
      <span class="spinner" style="width:32px;height:32px;color:var(--primary);"></span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <p>{{ error }}</p>
    </div>

    <!-- Invoice -->
    <div v-else-if="invoice" class="invoice-sheet card" id="invoice-print">
      <!-- Top Section -->
      <div class="invoice-topbar">
        <div class="invoice-brand">
          <div v-if="invoice.user?.settings?.company_logo" class="inv-logo-img">
             <img 
               :src="invoice.user.settings.company_logo.startsWith('http') ? invoice.user.settings.company_logo : '/api/storage/' + invoice.user.settings.company_logo.replace('/api/storage/', '')" 
               class="logo-image" 
               @error="(e) => (e.target as HTMLImageElement).style.display = 'none'"
             />
          </div>
          <div v-else class="inv-logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
          <div>
            <div class="inv-company">{{ invoice.fiscal_snapshot?.issuer_name ?? invoice.user?.settings?.company_name ?? 'Mi Empresa' }}</div>
            <div class="inv-details">
              <div>{{ invoice.fiscal_snapshot?.issuer_address ?? invoice.user?.settings?.company_address }}</div>
              <div>CUIT: {{ invoice.fiscal_snapshot?.issuer_cuit ?? invoice.user?.settings?.company_cuit ?? invoice.user?.settings?.arca_cuit ?? '00-00000000-0' }}</div>
              <div>{{ invoice.fiscal_snapshot?.issuer_condition ?? invoice.user?.settings?.tax_condition ?? 'IVA Responsable Inscripto' }}</div>
              <div v-if="invoice.fiscal_snapshot?.issuer_fiscal_jurisdiction">Jurisdicción: {{ invoice.fiscal_snapshot.issuer_fiscal_jurisdiction }}</div>
              <div v-if="invoice.fiscal_snapshot?.issuer_business_activity">Actividad: {{ invoice.fiscal_snapshot.issuer_business_activity }}</div>
              <div v-if="invoice.fiscal_snapshot?.issuer_iibb_number">Ingresos Brutos: {{ invoice.fiscal_snapshot.issuer_iibb_number }}</div>
              <div v-if="invoice.fiscal_snapshot?.issuer_iibb_condition">Condición IIBB: {{ iibbLabels[invoice.fiscal_snapshot.issuer_iibb_condition] || invoice.fiscal_snapshot.issuer_iibb_condition }}</div>
            </div>
          </div>
        </div>
        <div class="afip-header-right">
          <div class="invoice-type-box">
            <div class="type-letter">{{ invoice.cbte_tipo === 1 ? 'A' : invoice.cbte_tipo === 6 ? 'B' : 'C' }}</div>
            <div class="type-code">COD. {{ String(showingCredit ? ({ 1: 3, 6: 8, 11: 13 } as Record<number, number>)[invoice.cbte_tipo || 11] : invoice.cbte_tipo || 11).padStart(3, '0') }}</div>
          </div>
          <div class="invoice-title-block">
            <h2 class="invoice-main-title">{{ showingCredit ? 'NOTA DE CRÉDITO' : 'FACTURA' }}</h2>
            <div class="invoice-num-lg">N° {{ showingCredit ? String(invoice.pto_vta).padStart(5, '0') + '-' + String(invoice.nc_number).padStart(8, '0') : invoice.invoice_number }}</div>
            <div class="invoice-date-lg">FECHA: {{ formatDate(showingCredit ? invoice.nc_issued_at : invoice.created_at) }}</div>
          </div>
        </div>
      </div>

      <!-- Recipient -->
      <div class="inv-section recipient-section">
        <div class="section-divider"></div>
        <div class="recipient-grid">
          <div class="recipient-info">
            <div class="inv-section-title">Destinatario</div>
            <template v-if="invoice.recipient">
              <div class="recipient-name">{{ invoice.fiscal_snapshot?.recipient_name || invoice.recipient.name }}</div>
              <div class="recipient-detail"><strong>Documento:</strong> {{ invoice.fiscal_snapshot?.doc_nro || invoice.recipient.document_id }}</div>
              <div class="recipient-detail"><strong>Condición IVA:</strong> {{ ivaLabels[invoice.fiscal_snapshot?.condicion_iva_receptor || invoice.recipient.iva_condition_id] || 'Sin dato histórico' }}</div>
              <div v-if="invoice.recipient.address" class="recipient-detail"><strong>Domicilio:</strong> {{ invoice.recipient.address }}</div>
            </template>
            <template v-else>
              <div class="recipient-name">Consumidor Final</div>
              <div class="recipient-detail">Sin datos fiscales declarados</div>
            </template>
          </div>
          <div class="payment-info">
            <div class="inv-section-title">Detalles de Venta</div>
            <div v-if="!invoice.is_multiple_payment" class="recipient-detail"><strong>Condición de venta:</strong> 
              {{ invoice.payment_method === 'cash' ? 'Contado / Efectivo' : (invoice.payment_method === 'card' ? 'Tarjeta / POS' : 'Transferencia') }}
            </div>
            <div v-else class="recipient-detail">
              <strong>Condición de venta:</strong> Pago Dividido
              <div v-for="p in invoice.payments" :key="p.id" class="p-split-row">
                - {{ p.payment_method === 'cash' ? 'Efectivo' : (p.payment_method === 'card' ? 'Tarjeta' : 'Transferencia') }}: 
                <strong>{{ formatARS(p.amount) }}</strong>
                <span v-if="p.details" class="p-details"> ({{ p.details }})</span>
              </div>
            </div>
            <div class="recipient-detail"><strong>Moneda:</strong> Pesos Argentinos (ARS)</div>
          </div>
        </div>
        <div class="section-divider"></div>
      </div>

      <!-- Items -->
      <div class="inv-section items-section">
        <div class="table-wrap">
          <table class="afip-table">
            <thead>
              <tr>
                <th>Código / Descripción</th>
                <th style="text-align:right;">Cantidad</th>
                <th style="text-align:right;">Uni.</th>
                <th style="text-align:right;">Precio Unit. Pesos</th>
                <th style="text-align:right;">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in invoice.items" :key="item.id">
                <td>
                  <div class="product-name">{{ item.description_snapshot ?? item.product?.name ?? 'Producto General' }}</div>
                </td>
                <td style="text-align:right;">{{ item.quantity }}</td>
                <td style="text-align:right;">unidades</td>
                <td style="text-align:right;">{{ formatARS(item.unit_price_usd) }}</td>
                <td style="text-align:right; font-weight:700;">{{ formatARS(item.unit_price_usd * item.quantity) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="historicalTributes.length" class="inv-section">
        <h3 style="font-size:0.95rem;margin-bottom:0.75rem;">Percepciones y tributos</h3>
        <p v-if="showingCredit" style="font-size:0.8rem;">Esta nota de crédito revierte los siguientes tributos de la factura original.</p>
        <div class="table-wrap">
          <table class="afip-table tribute-table">
            <thead><tr><th>Tipo / Descripción</th><th>Jurisdicción / Padrón</th><th style="text-align:right;">Base</th><th style="text-align:right;">Alícuota</th><th style="text-align:right;">Importe</th></tr></thead>
            <tbody>
              <tr v-for="(tribute, index) in historicalTributes" :key="index">
                <td><strong>{{ tribute.desc }}</strong><div>Tipo ARCA {{ tribute.id }}</div></td>
                <td>{{ tribute.jurisdiction || '—' }}<div v-if="tribute.padron_reference">Padrón: {{ tribute.padron_reference }}</div></td>
                <td style="text-align:right;">{{ tribute.base_imp == null ? '—' : formatARS(Number(tribute.base_imp)) }}</td>
                <td style="text-align:right;">{{ tribute.alicuota == null ? '—' : `${tribute.alicuota}%` }}</td>
                <td style="text-align:right;font-weight:700;">{{ formatARS(Number(tribute.importe)) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Totals & ARCA Footer -->
      <div class="invoice-footer">
        <div class="footer-left">
          <div v-if="invoice.cae" class="afip-qr-placeholder">
            <div class="qr-box">
              <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' + encodeURIComponent(getAfipQrUrl(invoice))" alt="QR" style="width:80px;height:80px;"/>
            </div>
            <div class="afip-logo-footer">
              <div class="arca-logo-text">ARCA</div>
              <div style="font-size: 0.6rem; font-weight:bold; color:#005cbb; margin-top:2px;">Comprobante Autorizado</div>
            </div>
          </div>
        </div>
        <div class="footer-right">
          <div class="totals-area">
            <div class="totals-row">
              <span>Importe Neto:</span>
              <span>{{ formatARS(fiscal.net ?? Number(invoice.total_local) - tributeTotal) }}</span>
            </div>
            <div class="totals-row">
              <span>Importe Otros Tributos:</span>
              <span>{{ formatARS(tributeTotal) }}</span>
            </div>
            <div class="totals-row"><span>IVA total:</span><span>{{ formatARS(Number(fiscal.vat ?? 0)) }}</span></div>
            <template v-if="invoice.fiscal_snapshot?.fiscal && invoice.cbte_tipo !== 11">
              <div v-for="rate in fiscal.rates || []" :key="rate.Id" class="totals-row"><span>IVA {{ ({ 3: 0, 4: 10.5, 5: 21, 6: 27, 8: 5, 9: 2.5 } as Record<number, number>)[rate.Id] }}% (incluido):</span><span>{{ formatARS(rate.Importe) }}</span></div>
              <div class="totals-row"><span>Exento:</span><span>{{ formatARS(fiscal.exempt ?? 0) }}</span></div>
              <div class="totals-row"><span>No gravado:</span><span>{{ formatARS(fiscal.untaxed ?? 0) }}</span></div>
            </template>
            <div class="totals-row grand-total">
              <span>Importe Total:</span>
              <span>{{ formatARS(invoice.total_local) }}</span>
            </div>
          </div>
        </div>
      </div>

      <p v-if="showingCredit">Comprobante asociado: Factura {{ invoice.invoice_number }}</p>

      <!-- Leyenda RG 5003 para Factura A a Monotributistas -->
      <div v-if="invoice.fiscal_snapshot?.rg5003_legend || (invoice.cbte_tipo === 1 && (invoice.fiscal_snapshot?.condicion_iva_receptor === 6 || invoice.recipient?.iva_condition_id === 6))" class="rg5003-banner">
        <strong>Nota fiscal (RG 5003/2021):</strong> {{ invoice.fiscal_snapshot?.rg5003_legend || 'El crédito fiscal discriminado en el presente comprobante, sólo podrá ser computado a efectos del Régimen de Sostenimiento e Inclusión Fiscal para Pequeños Contribuyentes de la Ley Nº 27.618' }}
      </div>

      <div class="cae-section" v-if="invoice.cae">
        <div class="cae-row"><strong>CAE:</strong> <span class="mono">{{ showingCredit ? invoice.nc_cae : invoice.cae }}</span></div>
        <div class="cae-row"><strong>Fecha de Vto. de CAE:</strong> {{ formatDate(showingCredit ? invoice.nc_cae_expiry : invoice.cae_expiry) }}</div>
      </div>
    </div>
    <div v-if="invoice?.tribute_audits?.length" class="card tribute-audit" style="margin-top:1rem;">
      <h2 style="font-size:1rem;margin-bottom:0.75rem;">Historial de percepciones y tributos</h2>
      <p v-if="invoice.cae" class="text-muted" style="font-size:0.8rem;margin-bottom:0.75rem;">Comprobante autorizado: los tributos y sus importes históricos no se pueden modificar.</p>
      <div v-for="audit in invoice.tribute_audits" :key="audit.id" style="font-size:0.85rem;margin-bottom:0.5rem;">
        {{ auditLabels[audit.action] || audit.action }} · {{ audit.user?.name || `Usuario ${audit.user_id}` }} · {{ new Date(audit.created_at).toLocaleString('es-AR') }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.tribute-table { table-layout: fixed; }
.tribute-table td { overflow-wrap: anywhere; font-size: 0.8rem; }
@media print { .tribute-audit { display: none !important; } }
.invoice-sheet { padding: 3rem; max-width: 900px; margin: 0 auto; background: #fff; color: #111; }
.rg5003-banner {
  margin: 1.5rem 0 1rem;
  padding: 0.75rem 1rem;
  border: 1px dashed #555;
  background: #fbfbfb;
  font-size: 0.75rem;
  line-height: 1.45;
  color: #222;
  border-radius: 4px;
}

.invoice-topbar {
  display: flex; justify-content: space-between; align-items: stretch;
  margin-bottom: 2rem; border: 2px solid #000;
}
.invoice-brand { padding: 1.5rem; flex: 1; display: flex; align-items: center; gap: 1rem; border-right: 1px solid #000; }
.inv-logo-img { width: 60px; height: 60px; border-radius: 8px; overflow: hidden; }
.logo-image { width: 100%; height: 100%; object-fit: contain; }
.inv-logo { width: 50px; height: 50px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.inv-company { font-size: 1.3rem; font-weight: 850; text-transform: uppercase; margin-bottom: 0.25rem; }
.inv-details { font-size: 0.75rem; line-height: 1.4; color: #444; }

.afip-header-right { flex: 1; display: flex; align-items: stretch; }
.invoice-type-box {
  width: 60px; border-right: 1px solid #000; border-left: 1px solid #000;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  background: #f9f9f9;
}
.type-letter { font-size: 2.5rem; font-weight: 900; line-height: 1; }
.type-code { font-size: 0.6rem; font-weight: 700; margin-top: 0.25rem; }

.invoice-title-block { flex: 1; padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; }
.invoice-main-title { font-size: 1.5rem; font-weight: 900; margin: 0; letter-spacing: 2px; }
.invoice-num-lg { font-size: 1.1rem; font-weight: 700; margin-top: 0.5rem; }
.invoice-date-lg { font-size: 0.85rem; font-weight: 600; margin-top: 0.25rem; }

.section-divider { border-top: 1px solid #000; margin: 0; }
.recipient-grid { display: grid; grid-template-columns: 1.5fr 1fr; padding: 1rem 1.5rem; gap: 2rem; }
.inv-section-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: #666; margin-bottom: 0.5rem; }
.recipient-name { font-size: 1.1rem; font-weight: 800; margin-bottom: 0.5rem; }
.recipient-detail { font-size: 0.85rem; margin-bottom: 0.2rem; }

.afip-table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.afip-table th { background: #f0f0f0; border: 1px solid #000; padding: 0.6rem; font-size: 0.75rem; text-transform: uppercase; }
.afip-table td { border: 1px solid #000; padding: 0.8rem 0.6rem; font-size: 0.85rem; }
.product-name { font-weight: 600; }

.invoice-footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 2rem; }
.afip-qr-placeholder { display: flex; align-items: center; gap: 1rem; }
.qr-box { padding: 0.5rem; border: 1px solid #eee; background: #fff; }
.afip-logo-footer { display: flex; flex-direction: column; gap: 0.25rem; }

.totals-area { min-width: 280px; }
.totals-row { display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.95rem; font-weight: 600; }
.grand-total { font-size: 1.3rem; font-weight: 900; border-top: 2px solid #000; margin-top: 0.5rem; padding-top: 0.75rem; }

.cae-section { margin-top: 2rem; padding-top: 1rem; border-top: 1px dashed #ccc; text-align: right; }
.cae-row { font-size: 0.9rem; margin-bottom: 0.25rem; }
.arca-logo-text { color: #005cbb; font-size: 1.8rem; font-weight: 900; letter-spacing: -1px; line-height: 1; }
.mono { font-family: 'Courier New', monospace; font-weight: 700; }

.p-split-row { font-size: 0.75rem; color: #444; margin-top: 2px; }
.p-details { font-style: italic; color: #666; font-size: 0.7rem; }

@media print {
  .invoice-sheet { border: none !important; padding: 0; width: 100% !important; max-width: none !important; }
  .page-header, .btn { display: none !important; }
}
</style>
