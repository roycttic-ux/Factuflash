<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { invoicesApi } from '@/api/services'

const router = useRouter()
const route = useRoute()
const invoice = ref<any>(null)
const loading = ref(true)
const error = ref('')

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
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n)
}
function formatDate(d: string) {
  return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' })
}
function statusBadge(s: string) {
  return s === 'paid' ? 'badge-success' : s === 'pending' ? 'badge-warning' : 'badge-muted'
}
function statusLabel(s: string) {
  return s === 'paid' ? 'Pagada' : s === 'pending' ? 'Pendiente' : s
}

function printInvoice() {
  window.print()
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
      <button v-if="invoice" class="btn btn-ghost" @click="printInvoice">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="6 9 6 2 18 2 18 9"/>
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
          <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Imprimir
      </button>
    </div>

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
             <img :src="'/sistemafacturacion/api/public/storage/' + invoice.user.settings.company_logo" class="logo-image" />
          </div>
          <div v-else class="inv-logo">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
          <div>
            <div class="inv-company">{{ invoice.user?.settings?.company_name || 'Mi Empresa' }}</div>
            <div class="inv-details">
              <div>{{ invoice.user?.settings?.company_address || 'Dirección no configurada' }}</div>
              <div>CUIT: {{ invoice.user?.settings?.company_cuit || '00-00000000-0' }}</div>
              <div>IVA Responsable Monotributo</div>
            </div>
          </div>
        </div>
        <div class="afip-header-right">
          <div class="invoice-type-box">
            <div class="type-letter">C</div>
            <div class="type-code">COD. 011</div>
          </div>
          <div class="invoice-title-block">
            <h2 class="invoice-main-title">FACTURA</h2>
            <div class="invoice-num-lg">N° {{ String(invoice.invoice_number).padStart(8, '0') }}</div>
            <div class="invoice-date-lg">FECHA: {{ formatDate(invoice.created_at) }}</div>
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
              <div class="recipient-name">{{ invoice.recipient.name }}</div>
              <div class="recipient-detail"><strong>CUIT:</strong> {{ invoice.recipient.cuit }}</div>
              <div class="recipient-detail"><strong>Condición IVA:</strong> Consumidor Final</div>
              <div v-if="invoice.recipient.address" class="recipient-detail"><strong>Domicilio:</strong> {{ invoice.recipient.address }}</div>
            </template>
            <template v-else>
              <div class="recipient-name">Consumidor Final</div>
              <div class="recipient-detail">Sin datos fiscales declarados</div>
            </template>
          </div>
          <div class="payment-info">
            <div class="inv-section-title">Detalles de Venta</div>
            <div class="recipient-detail"><strong>Condición de venta:</strong> 
              {{ invoice.payment_method === 'cash' ? 'Contado / Efectivo' : 'Transferencia' }}
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
                  <div class="product-name">{{ item.product?.name ?? 'Producto General' }}</div>
                </td>
                <td style="text-align:right;">{{ item.quantity }}</td>
                <td style="text-align:right;">unidades</td>
                <td style="text-align:right;">{{ formatARS(item.unit_price_usd * invoice.dollar_rate) }}</td>
                <td style="text-align:right; font-weight:700;">{{ formatARS(item.unit_price_usd * item.quantity * invoice.dollar_rate) }}</td>
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
              <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=https://www.afip.gob.ar/fe/qr/?p=123456789'" alt="QR" style="width:80px;height:80px;"/>
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
              <span>Importe Neto / Subtotal:</span>
              <span>{{ formatARS(invoice.total_local) }}</span>
            </div>
            <div class="totals-row">
              <span>Importe Otros Tributos:</span>
              <span>$ 0,00</span>
            </div>
            <div class="totals-row grand-total">
              <span>Importe Total:</span>
              <span>{{ formatARS(invoice.total_local) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="cae-section" v-if="invoice.cae">
        <div class="cae-row"><strong>CAE:</strong> <span class="mono">{{ invoice.cae }}</span></div>
        <div class="cae-row"><strong>Fecha de Vto. de CAE:</strong> {{ formatDate(invoice.cae_expiry) }}</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.invoice-sheet { padding: 3rem; max-width: 900px; margin: 0 auto; background: #fff; color: #111; }

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

@media print {
  .invoice-sheet { border: none !important; padding: 0; width: 100% !important; max-width: none !important; }
  .page-header, .btn { display: none !important; }
}
</style>
