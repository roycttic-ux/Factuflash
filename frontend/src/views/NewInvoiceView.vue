<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { invoicesApi, customersApi, productsApi, posDevicesApi, mpApi, settingsApi } from '@/api/services'

const router = useRouter()
const loading = ref(false)
const customers = ref<any[]>([])
const products = ref<any[]>([])
const error = ref('')
const success = ref(false)
const scannerCode = ref('')
const scannerInputRef = ref<HTMLInputElement | null>(null)
const lastScannedProduct = ref<any>(null)
const scannerStatus = ref<'idle' | 'success' | 'error'>('idle')
const posDevices = ref<any[]>([])
const selectedPosId = ref<string>('')
const isPayingWithPos = ref(false)
const posStatus = ref('')
let pollingInterval: any = null

const settings = ref<any>({})
const transferDetails = ref('')

const form = ref({
  recipient_id: '',
  notify_afip: false,
  payment_method: 'cash',
  is_multiple_payment: false,
  payments: [{ payment_method: 'cash', amount: 0, details: '' }],
  items: [{ product_id: '', quantity: 1, variant_index: null }],
})

const showChangeCalculator = ref(false)
const cashReceived = ref<number | null>(null)
const changeToReturn = computed(() => {
  if (!cashReceived.value || cashReceived.value < totalARS.value) return 0
  return cashReceived.value - totalARS.value
})

watch(() => form.value.payment_method, (newVal) => {
  if (newVal !== 'cash') {
    showChangeCalculator.value = false
    cashReceived.value = null
  }
})

onMounted(async () => {
  const [custRes, prodRes, posRes, settingsRes] = await Promise.all([
    customersApi.list(),
    productsApi.list(),
    posDevicesApi.list(),
    settingsApi.get()
  ])
  customers.value = custRes.data.customers
  products.value = prodRes.data.products
  posDevices.value = posRes.data.devices
  settings.value = settingsRes.data.settings || {}
  
  if (settings.value.arca_connected) {
    form.value.notify_afip = true
  }

  if (window.innerWidth > 768) {
    setTimeout(() => scannerInputRef.value?.focus(), 500)
  }
})

function addItem() {
  form.value.items.push({ product_id: '', quantity: 1, variant_index: null })
}
function removeItem(i: number) {
  if (form.value.items.length === 1) {
    form.value.items[0].product_id = ''
    form.value.items[0].quantity = 1
    form.value.items[0].variant_index = null
  } else {
    form.value.items.splice(i, 1)
  }
}

function getProduct(id: string) {
  return products.value.find(p => p.id == id)
}

const totalARS = computed(() =>
  form.value.items.reduce((acc, item) => {
    const p = getProduct(item.product_id)
    return acc + (p ? Number(p.cost_usd) * item.quantity : 0)
  }, 0)
)

const totalPayments = computed(() => 
  form.value.payments.reduce((acc, p) => acc + (p.amount || 0), 0)
)

const remainingToPay = computed(() => Math.max(0, totalARS.value - totalPayments.value))

function addPayment() {
  form.value.payments.push({ payment_method: 'card', amount: remainingToPay.value, details: '' })
}

function removePayment(i: number) {
  form.value.payments.splice(i, 1)
}

function toggleMultiplePayment() {
  form.value.is_multiple_payment = !form.value.is_multiple_payment
  if (form.value.is_multiple_payment) {
    form.value.payments = [{ payment_method: form.value.payment_method, amount: totalARS.value, details: '' }]
  } else {
    form.value.payments = [{ payment_method: 'cash', amount: 0, details: '' }]
  }
}

function playBeep(type: 'success' | 'error' = 'success') {
  try {
    const ctx = new (window.AudioContext || (window as any).webkitAudioContext)()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    
    if (type === 'success') {
      osc.frequency.value = 800
      gain.gain.setValueAtTime(0.1, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.1)
    } else {
      osc.frequency.value = 200
      gain.gain.setValueAtTime(0.1, ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.3)
    }
  } catch (e) { console.error('Audio error', e) }
}

function handleScan() {
  const code = scannerCode.value.trim()
  scannerCode.value = ''
  if (!code) return

  const product = products.value.find(p => p.sku === code)
  if (product) {
    if (product.stock <= 0 && !product.unlimited_stock) {
      error.value = `¡Sin Stock! ${product.name} no tiene unidades disponibles.`
      playBeep('error')
      scannerStatus.value = 'error'
      setTimeout(() => scannerStatus.value = 'idle', 1000)
      scannerCode.value = ''
      return
    }

    const existing = form.value.items.find(i => String(i.product_id) === String(product.id))
    if (existing) {
      existing.quantity++
    } else {
      const lastItem = form.value.items[form.value.items.length - 1]
      if (!lastItem.product_id) {
        lastItem.product_id = String(product.id)
        lastItem.quantity = 1
        lastItem.variant_index = null
      } else {
        form.value.items.push({ product_id: String(product.id), quantity: 1, variant_index: null })
      }
    }
    
    lastScannedProduct.value = product
    scannerStatus.value = 'success'
    playBeep('success')
    error.value = ''
    setTimeout(() => {
      scannerStatus.value = 'idle'
      lastScannedProduct.value = null
    }, 2000)
  } else {
    error.value = `Producto con código "${code}" no encontrado`
    playBeep('error')
    scannerStatus.value = 'error'
    setTimeout(() => {
      if (error.value.includes('no encontrado')) error.value = ''
      scannerStatus.value = 'idle'
    }, 3000)
  }
  if (window.innerWidth > 768) {
    scannerInputRef.value?.focus()
  }
}

// Keep focus on scanner (desktop only to prevent mobile keyboard popping)
function refocusScanner() {
  if (window.innerWidth <= 768) return
  setTimeout(() => {
    if (document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'SELECT' && document.activeElement?.tagName !== 'TEXTAREA') {
      scannerInputRef.value?.focus()
    }
  }, 500)
}
window.addEventListener('click', refocusScanner)
onUnmounted(() => window.removeEventListener('click', refocusScanner))

async function triggerPosPayment() {
  const device = posDevices.value.find(d => String(d.id) === String(selectedPosId.value))
  if (!device) return

  isPayingWithPos.value = true
  posStatus.value = 'Enviando orden al dispositivo...'

  try {
    if (device.type === 'mercadopago_point') {
      await mpApi.createPointIntent({
        device_id: device.external_id,
        amount: totalARS.value,
        invoice_id: 0
      })
      posStatus.value = 'Esperando confirmación en el dispositivo...'
    } else {
      posStatus.value = 'Esperando señal del Postnet...'
    }
    
    startPolling(device.external_id)
  } catch (e: any) {
    error.value = e.response?.data?.error ?? 'Error al conectar con el POS'
    isPayingWithPos.value = false
  }
}

function startPolling(externalId: string) {
  if (pollingInterval) clearInterval(pollingInterval)
  
  pollingInterval = setInterval(async () => {
    try {
      const res = await posDevicesApi.checkStatus(externalId)
      if (res.data.status === 'approved') {
        posStatus.value = '✅ ¡Pago aprobado! Emitiendo factura...'
        stopPolling()
        // Auto-submit con ARCA activado — sin confirmación manual
        form.value.notify_afip = true
        await submit()
      }
    } catch (e) {
      console.error('Polling error', e)
    }
  }, 3000)
}

function stopPolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval)
    pollingInterval = null
  }
  isPayingWithPos.value = false
}

import { onUnmounted } from 'vue'
onUnmounted(() => stopPolling())

async function submit() {
  error.value = ''
  if (form.value.items.some(i => !i.product_id)) { error.value = 'Completá todos los productos'; return }

  loading.value = true
  try {
    const payload = {
      recipient_id: form.value.recipient_id ? Number(form.value.recipient_id) : null,
      notify_afip: form.value.notify_afip,
      payment_method: form.value.is_multiple_payment ? form.value.payments[0].payment_method : form.value.payment_method,
      items: form.value.items.map(i => ({ 
        product_id: Number(i.product_id), 
        quantity: Number(i.quantity),
        variant_index: i.variant_index !== null ? Number(i.variant_index) : null
      })),
      payments: form.value.is_multiple_payment ? form.value.payments.map(p => ({
        payment_method: p.payment_method,
        amount: Number(p.amount),
        details: p.details
      })) : (form.value.payment_method === 'transfer' && transferDetails.value ? [{
        payment_method: 'transfer',
        amount: Number(totalARS.value),
        details: transferDetails.value
      }] : null)
    }
    const res = await invoicesApi.create(payload)
    success.value = true
    setTimeout(() => router.push(`/facturas/${res.data.invoice.id}`), 1200)
  } catch (e: any) {
    error.value = e?.response?.data?.error ?? 'Error al crear la factura'
  } finally {
    loading.value = false
  }
}

function copyToClipboard(text: string, label: string) {
  navigator.clipboard.writeText(text)
  alert(`${label} Copiado`)
}
</script>

<template>
  <div class="new-invoice">
    <div class="page-header">
      <div>
        <button class="btn btn-ghost btn-sm" @click="router.back()" style="margin-bottom:0.5rem;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Volver
        </button>
        <h1 class="page-title">Nueva Factura</h1>
        <p class="page-subtitle">Completá los datos para emitir la factura</p>
      </div>
    </div>

    <Transition name="fade">
      <div v-if="success" class="success-banner">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Factura creada exitosamente. Redirigiendo…
      </div>
    </Transition>

    <form @submit.prevent="submit" class="invoice-grid">
      <!-- Left: recipient + items -->
      <div class="flex flex-col gap-4">
        <!-- Customer -->
        <div class="card">
          <h2 class="section-title" style="margin-bottom:1rem;">Cliente (Opcional)</h2>
          <div class="form-group">
            <label class="form-label">Seleccioná un cliente</label>
            <select v-model="form.recipient_id" class="form-select">
              <option value="">— Consumidor Final (Sin Cliente) —</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">
                {{ c.name }} &mdash; CUIT {{ c.cuit }}
              </option>
            </select>
          </div>
        </div>

        <!-- Items -->
        <div class="card">
          <div class="flex items-center justify-between" style="margin-bottom:1rem;">
            <h2 class="section-title">Productos</h2>
            <div class="flex items-center gap-3">
              <div class="scanner-input-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="scanner-icon"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
                <input
                  ref="scannerInputRef"
                  v-model="scannerCode"
                  placeholder="Escaneá o escribí SKU..."
                  class="scanner-input"
                  :class="scannerStatus"
                  @keydown.enter.prevent="handleScan"
                />
                
                <!-- Floating Preview -->
                <Transition name="slide-up">
                  <div v-if="lastScannedProduct" class="scanned-preview">
                    <img v-if="lastScannedProduct.image" :src="lastScannedProduct.image" class="preview-img" />
                    <div class="preview-info">
                      <div class="p-name">{{ lastScannedProduct.name }}</div>
                      <div class="p-price">${{ Number(lastScannedProduct.cost_usd).toLocaleString('es-AR') }}</div>
                    </div>
                  </div>
                </Transition>
              </div>
              <button type="button" class="btn btn-ghost btn-sm" @click="addItem">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Agregar
              </button>
            </div>
          </div>

          <div class="items-list">
            <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
              <div class="form-group flex-1">
                <label class="form-label">Producto</label>
                <select v-model="item.product_id" class="form-select" @change="item.variant_index = null">
                  <option value="">— Elegir —</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">
                    {{ p.name }} (${{ Number(p.cost_usd).toLocaleString('es-AR') }})
                  </option>
                </select>
                <!-- Variant Selector -->
                <div v-if="getProduct(item.product_id)?.variants?.length" style="margin-top:0.5rem;">
                  <select v-model="item.variant_index" class="form-select text-xs" style="height:32px; padding:0 0.5rem;">
                    <option :value="null">— Seleccionar Variante —</option>
                    <option v-for="(v, vIdx) in getProduct(item.product_id).variants" :key="vIdx" :value="vIdx">
                      {{ v.name }}: {{ v.value }} (Stock: {{ v.stock ?? 0 }})
                    </option>
                  </select>
                </div>
              </div>
              <div class="form-group" style="width:90px;">
                <label class="form-label">Cant.</label>
                <input v-model.number="item.quantity" type="number" min="1" class="form-input" />
              </div>
              <div class="item-total">
                <span class="form-label">Subtotal</span>
                <span class="item-total-val">
                  $ {{ getProduct(item.product_id) ? Number(getProduct(item.product_id)!.cost_usd * item.quantity).toLocaleString('es-AR') : '—' }}
                </span>
              </div>
              <button
                v-if="form.items.length > 1 || item.product_id"
                type="button"
                class="btn btn-danger btn-sm btn-icon remove-btn"
                @click="removeItem(idx)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Payment Method -->
        <div class="card">
          <h2 class="section-title" style="margin-bottom:1rem;">Método de Pago</h2>
          <div v-if="!form.is_multiple_payment" class="payment-methods">
            <label class="p-method" :class="{ active: form.payment_method === 'cash' }">
              <input type="radio" v-model="form.payment_method" value="cash" />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
              Efectivo
            </label>
            <label class="p-method" :class="{ active: form.payment_method === 'card' }">
              <input type="radio" v-model="form.payment_method" value="card" />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              Tarjeta / POS
            </label>
            <label class="p-method" :class="{ active: form.payment_method === 'transfer' }">
              <input type="radio" v-model="form.payment_method" value="transfer" />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
              Transferencia
            </label>
          </div>

          <!-- POS Selection -->
          <div v-if="form.payment_method === 'card' && posDevices.length && !form.is_multiple_payment" class="pos-integration-box mt-3">
            <div class="flex items-center gap-3">
              <select v-model="selectedPosId" class="form-select flex-1">
                <option value="">— Elegir Postnet Integrado —</option>
                <option v-for="d in posDevices" :key="d.id" :value="d.id">{{ d.name }} ({{ d.type === 'mercadopago_point' ? 'MP Point' : 'Tradicional' }})</option>
              </select>
              <button 
                type="button" 
                class="btn btn-primary btn-sm" 
                :disabled="!selectedPosId || isPayingWithPos"
                @click="triggerPosPayment"
              >
                {{ isPayingWithPos ? 'Esperando...' : 'Cobrar en POS' }}
              </button>
            </div>
            <div v-if="isPayingWithPos" class="pos-status-msg mt-2 flex items-center justify-between gap-3 p-2 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div class="flex items-center gap-2 text-zinc-300">
                <span class="spinner spinner-xs"></span>
                <span>{{ posStatus }}</span>
              </div>
              <button type="button" class="btn btn-danger btn-xs px-2 py-1 font-bold text-xs" @click="stopPolling" style="background:#ef4444; border-color:#ef4444; font-weight:800; font-size:11px;">
                Cancelar
              </button>
            </div>
          </div>

          <!-- Transfer Details Box -->
          <div v-if="form.payment_method === 'transfer' && !form.is_multiple_payment" class="transfer-integration-box mt-3">
            <div v-if="settings.bank_name || settings.bank_alias || settings.bank_cbu || settings.bank_holder" class="bank-details-card mb-3 p-3 bg-zinc-900/60 border border-zinc-800 rounded-lg">
              <div class="bank-details-header mb-2 pb-2 border-b border-zinc-800 flex justify-between items-center">
                <strong style="color: var(--primary); font-size: 0.85rem;">Datos de Transferencia</strong>
                <span class="text-[10px] text-zinc-500">Copiá haciendo click</span>
              </div>
              <div class="bank-details-body grid grid-cols-2 gap-2 text-xs">
                <div v-if="settings.bank_name" class="detail-item">
                  <span class="detail-label block text-zinc-500 text-[10px]">Banco:</span>
                  <span class="detail-val font-semibold text-zinc-200">{{ settings.bank_name }}</span>
                </div>
                <div v-if="settings.bank_holder" class="detail-item">
                  <span class="detail-label block text-zinc-500 text-[10px]">Titular:</span>
                  <span class="detail-val font-semibold text-zinc-200">{{ settings.bank_holder }}</span>
                </div>
                <div v-if="settings.bank_cbu" class="detail-item col-span-2 cursor-pointer p-1.5 bg-zinc-950/80 hover:bg-zinc-900 border border-zinc-800 rounded transition-colors" @click="copyToClipboard(settings.bank_cbu, 'CBU')">
                  <span class="detail-label block text-zinc-500 text-[9px]">CBU/CVU:</span>
                  <span class="detail-val font-mono font-bold text-zinc-300 break-all">{{ settings.bank_cbu }}</span>
                </div>
                <div v-if="settings.bank_alias" class="detail-item col-span-2 cursor-pointer p-1.5 bg-zinc-950/80 hover:bg-zinc-900 border border-zinc-800 rounded transition-colors" @click="copyToClipboard(settings.bank_alias, 'Alias')">
                  <span class="detail-label block text-zinc-500 text-[9px]">Alias:</span>
                  <span class="detail-val font-mono font-bold text-zinc-100 break-all" style="color: var(--primary);">{{ settings.bank_alias }}</span>
                </div>
              </div>
            </div>
            <div v-else class="text-xs text-zinc-400 mb-3 bg-zinc-900/60 border border-zinc-800/80 p-3 rounded-lg flex items-center gap-2">
              <span>⚠️</span>
              <span>No tenés datos bancarios configurados en Configuración → Empresa.</span>
            </div>
            
            <div class="form-group">
              <label class="form-label text-xs">Nro. de Operación / Referencia (Opcional)</label>
              <input 
                v-model="transferDetails" 
                class="form-input text-xs" 
                placeholder="Ej: Transf. 9876 o últimos 4 dígitos" 
              />
            </div>
          </div>

          <!-- Multiple Payments UI -->
          <div v-if="form.is_multiple_payment" class="multiple-payments">
            <div v-for="(p, pIdx) in form.payments" :key="pIdx" class="payment-row">
              <select v-model="p.payment_method" class="form-select" style="width:140px;">
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta / POS</option>
                <option value="transfer">Transferencia</option>
              </select>
              <div class="input-with-symbol flex-1">
                <span class="symbol">$</span>
                <input v-model.number="p.amount" type="number" class="form-input" placeholder="Monto" />
              </div>
              <input v-model="p.details" class="form-input flex-1" placeholder="Detalles (opcional)" />
              <button type="button" class="btn btn-danger btn-sm btn-icon" @click="removePayment(pIdx)" v-if="form.payments.length > 1">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
            <div class="flex items-center gap-3 mt-2">
              <button type="button" class="btn btn-ghost btn-sm" @click="addPayment">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Agregar otro medio de pago
              </button>
              <button type="button" class="btn btn-sm text-xs font-bold px-3 py-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors" @click="toggleMultiplePayment" style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                Cancelar y usar pago único
              </button>
            </div>

            <div class="payment-status-bar mt-3" :class="{ 'ok': totalPayments === totalARS }">
              <span>Total Pagado: <strong>${{ totalPayments.toLocaleString('es-AR') }}</strong></span>
              <span v-if="remainingToPay > 0">Faltan: <strong>${{ remainingToPay.toLocaleString('es-AR') }}</strong></span>
              <span v-else class="flex items-center gap-1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Cubierto</span>
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <button type="button" class="btn btn-sm btn-ghost text-xs" @click="toggleMultiplePayment">
              {{ form.is_multiple_payment ? 'Usar medio de pago único' : 'Dividir pago (múltiples medios)' }}
            </button>
          </div>

          <div v-if="form.payment_method === 'cash' && !form.is_multiple_payment" class="change-calc-section">
            <button type="button" class="btn-toggle-calc" @click="showChangeCalculator = !showChangeCalculator">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              {{ showChangeCalculator ? 'Cerrar calculadora de vuelto' : 'Calcular vuelto' }}
            </button>

            <div v-if="showChangeCalculator" class="change-calc-body">
              <div class="form-group">
                <label class="form-label">El cliente entrega:</label>
                <div class="input-with-symbol">
                  <span class="symbol">$</span>
                  <input v-model.number="cashReceived" type="number" class="form-input" placeholder="0.00" @focus="($event.target as HTMLInputElement).select()" />
                </div>
              </div>
              <div class="change-display">
                <div class="change-item">
                  <span class="label">A devolver:</span>
                  <span class="value" :class="{ 'has-change': changeToReturn > 0 }">
                    {{ new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(changeToReturn) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: summary -->
      <div class="flex flex-col gap-4">
        <div class="card summary-card">
          <h2 class="section-title" style="margin-bottom:1.25rem;">Resumen</h2>

          <div class="summary-line">
            <span class="text-muted">Total</span>
            <span class="font-semibold" style="font-size:1.25rem;color:var(--primary);">
              {{ new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(totalARS) }}
            </span>
          </div>

          <div style="margin-top:1.5rem;" class="form-group">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.notify_afip" class="form-checkbox" />
              Notificar factura a AFIP (ARCA)
            </label>
          </div>

          <Transition name="fade">
            <div v-if="error" class="form-error">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              {{ error }}
            </div>
          </Transition>

          <button type="submit" class="btn btn-primary w-full" :disabled="loading" style="justify-content:center;margin-top:1rem;">
            <span v-if="loading" class="spinner"></span>
            <span v-else>Emitir Factura</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<style scoped>
.invoice-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 1.5rem;
  align-items: start;
}
@media (max-width: 900px) {
  .invoice-grid { grid-template-columns: 1fr; }
}

.section-title { font-size: 1rem; font-weight: 700; }

.items-list { display: flex; flex-direction: column; gap: 1rem; }
.item-row { display: flex; align-items: flex-end; gap: 0.75rem; }
.item-total { display: flex; flex-direction: column; gap: 0.4rem; min-width: 120px; }
.item-total-val { font-size: 0.88rem; font-weight: 600; color: var(--secondary); }
.remove-btn { margin-bottom: 2px; flex-shrink: 0; }

.summary-line { display: flex; justify-content: space-between; font-size: 0.9rem; padding: 0.35rem 0; }
.summary-divider { border-top: 1px solid var(--border); margin: 0.75rem 0; }
.summary-total { display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 800; color: var(--primary); }

.form-error {
  display: flex; align-items: center; gap: 0.5rem;
  background: rgba(248,113,113,0.1);
  border: 1px solid rgba(248,113,113,0.2);
  color: var(--danger);
  border-radius: var(--radius-sm);
  padding: 0.65rem 0.9rem;
  font-size: 0.82rem;
  margin-top: 0.75rem;
}

.success-banner {
  display: flex; align-items: center; gap: 0.75rem;
  background: rgba(52,211,153,0.1);
  border: 1px solid rgba(52,211,153,0.2);
  color: var(--success);
  border-radius: var(--radius-md);
  padding: 1rem 1.25rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
}

/* Payment Methods */
.payment-methods { display: flex; gap: 1rem; flex-wrap: wrap; }
.p-method {
  flex: 1; min-width: 140px;
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  padding: 0.85rem; border: 1px solid var(--border); border-radius: var(--radius-md);
  cursor: pointer; transition: all 0.2s; font-size: 0.88rem; font-weight: 600;
}
.p-method input { display: none; }
.p-method:hover { background: var(--bg-elevated); }
.p-method.active { background: rgba(99,102,241,0.1); border-color: var(--primary); color: var(--primary); }

/* Change Calculator */
.change-calc-section {
  margin-top: 1.25rem;
  padding-top: 1.25rem;
  border-top: 1px dashed var(--border);
}
.btn-toggle-calc {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: none;
  border: none;
  color: var(--primary);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0.5rem 0;
  transition: opacity 0.2s;
}
.btn-toggle-calc:hover { opacity: 0.8; }

.change-calc-body {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  align-items: flex-end;
  margin-top: 0.75rem;
  background: var(--bg-elevated);
  padding: 1rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
}
.input-with-symbol {
  position: relative;
  display: flex;
  align-items: center;
}
.input-with-symbol .symbol {
  position: absolute;
  left: 0.75rem;
  color: var(--text-muted);
  font-weight: 600;
}
.input-with-symbol .form-input {
  padding-left: 1.75rem;
}
.change-display {
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.change-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.change-item .label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.025em;
  color: var(--text-muted);
  font-weight: 700;
}
.change-item .value {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-muted);
}
.change-item .value.has-change {
  color: var(--success);
}

@media (max-width: 600px) {
  .change-calc-body {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
}

/* Scanner Styles */
.scanner-input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.scanner-icon {
  position: absolute;
  left: 10px;
  color: var(--text-muted);
  pointer-events: none;
}
.scanner-input {
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 0.4rem 0.75rem 0.4rem 2rem;
  font-size: 0.85rem;
  width: 200px;
  transition: all 0.2s;
}
.scanner-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
  width: 260px;
}
.scanner-input.success {
  border-color: var(--success);
  background: rgba(52,211,153,0.05);
}
.scanner-input.error {
  border-color: var(--danger);
  background: rgba(248,113,113,0.05);
}

.scanned-preview {
  position: absolute;
  top: -60px;
  left: 0;
  background: var(--bg-surface);
  border: 1px solid var(--primary);
  border-radius: var(--radius-md);
  padding: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 100;
  min-width: 200px;
}
.preview-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
.preview-info .p-name { font-size: 0.75rem; font-weight: 700; color: var(--text-primary); }
.preview-info .p-price { font-size: 0.7rem; color: var(--primary); font-weight: 600; }

.slide-up-enter-active, .slide-up-leave-active { transition: all 0.3s ease; }
.slide-up-enter-from { opacity: 0; transform: translateY(10px); }
.slide-up-leave-to { opacity: 0; transform: translateY(-10px); }

@media (max-width: 900px) {
  .invoice-grid { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
  .item-row { 
    flex-direction: column; 
    align-items: stretch; 
    gap: 0.5rem; 
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
    position: relative;
  }
  .item-row .form-group { width: 100% !important; }
  .item-total { 
    flex-direction: row; 
    justify-content: space-between; 
    align-items: center; 
    min-width: 0; 
  }
  .remove-btn {
    position: absolute;
    top: 0;
    right: 0;
  }
  .scanner-input:focus { width: 100%; }
  .scanner-input { width: 150px; }
  
  .payment-methods { flex-direction: column; }
  .p-method { width: 100%; }
}

/* Multiple Payments */
.multiple-payments {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.payment-row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  animation: slideDown 0.2s ease;
}
.payment-status-bar {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: var(--bg-elevated);
  border-radius: var(--radius-md);
  font-size: 0.85rem;
  border-left: 4px solid var(--danger);
}
.payment-status-bar.ok {
  border-left-color: var(--success);
  background: rgba(52,211,153,0.05);
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* POS Integration */
.pos-integration-box {
  background: rgba(99,102,241,0.05);
  border: 1px solid rgba(99,102,241,0.15);
  border-radius: 12px;
  padding: 1rem;
}
.pos-status-msg {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
</style>
