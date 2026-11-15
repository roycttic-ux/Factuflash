<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { invoicesApi, customersApi, productsApi, settingsApi, arcaApi } from '@/api/services'
import { ANONYMOUS_CONSUMER_FINAL_LIMIT } from '@/utils/validators'

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

const settings = ref<any>({})
const transferDetails = ref('')
const manualReceipt = ref('')
const manualAmount = ref(0)
const manualConfirmed = ref(false)
const submissionKey = ref(crypto.randomUUID())
const isRetryable = ref(false)
const pendingPayload = ref<any>(null)
type ManualTribute = {
  id: number
  desc: string
  jurisdiction: string
  padron_reference: string
  base_imp: string | number
  alicuota: string | number
  importe: string | number
}
const tributos = ref<ManualTribute[]>([])
const tributeTypes = ref<{ id: number; description: string }[]>([])
const loadingTributeTypes = ref(false)
const tributeTypesError = ref('')
const ivaRates: Record<number, number> = { 3: 0, 4: 10.5, 5: 21, 6: 27, 8: 5, 9: 2.5 }

function formatARS(value: number) {
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value)
}

async function addTribute() {
  if (loadingTributeTypes.value || loading.value || isRetryable.value || tributos.value.length >= 50) return
  tributeTypesError.value = ''
  if (!tributeTypes.value.length) {
    loadingTributeTypes.value = true
    try {
      const res = await arcaApi.tributeTypes()
      tributeTypes.value = (res.data.tribute_types || []).map((type: any) => ({ id: Number(type.id), description: type.description }))
      if (!tributeTypes.value.length) throw new Error('Catálogo vacío')
    } catch {
      tributeTypesError.value = 'No se pudo cargar el catálogo de tributos de ARCA. Reintentá para agregar un tributo; podés emitir una factura sin tributos si corresponde.'
      return
    } finally {
      loadingTributeTypes.value = false
    }
  }
  const type = tributeTypes.value.find(t => t.id === 7) || tributeTypes.value.find(t => t.id === 2) || tributeTypes.value[0]!
  tributos.value.push({
    id: type.id,
    desc: type.id === 7 ? 'Percepción de Ingresos Brutos' : type.description.slice(0, 80),
    jurisdiction: settings.value.fiscal_jurisdiction || '',
    padron_reference: '',
    base_imp: '',
    alicuota: settings.value.default_iibb_alicuota ?? '',
    importe: '',
  })
}

function validAmount(value: string | number, optional = false) {
  if (value === '') return optional
  const amount = Number(value)
  return Number.isFinite(amount) && amount >= 0 && Math.abs(amount * 100 - Math.round(amount * 100)) < 0.000001
}

const arcaConnected = ref(false)

const form = ref({
  recipient_id: '',
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
  try {
    const [custRes, prodRes, settingsRes] = await Promise.all([
      customersApi.list(),
      productsApi.list(),
      settingsApi.get(),
    ])
    customers.value = custRes.data.recipients || custRes.data.customers || []
    products.value = prodRes.data.products
    settings.value = settingsRes.data.settings || {}
    arcaConnected.value = !!settings.value.arca_connected
    if (window.innerWidth > 768) {
      setTimeout(() => scannerInputRef.value?.focus(), 500)
    }
  } catch {
    error.value = 'No se pudieron cargar los datos. Recargá la página para intentar de nuevo.'
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

const fiscalPreview = computed(() => {
  const totals = { net: 0, vat: 0, exempt: 0, untaxed: 0, gross: 0 }
  const registered = settings.value.tax_condition === 'Responsable Inscripto'
  for (const item of form.value.items) {
    const product = getProduct(item.product_id)
    if (!product) continue
    const gross = Math.round(Number(product.cost_usd) * Number(item.quantity) * 100)
    if (!Number.isFinite(gross)) continue
    totals.gross += gross
    const ivaId = Number(product.iva_id)
    if (!registered) totals.net += gross
    else if (ivaId === 1) totals.untaxed += gross
    else if (ivaId === 2) totals.exempt += gross
    else if (ivaRates[ivaId] !== undefined) {
      const net = Math.round(gross / (1 + ivaRates[ivaId] / 100))
      totals.net += net
      totals.vat += gross - net
    } else totals.net += gross
  }
  return Object.fromEntries(Object.entries(totals).map(([key, cents]) => [key, cents / 100])) as typeof totals
})
const tributosTotal = computed(() => tributos.value.reduce((sum, tribute) => sum + (validAmount(tribute.importe) ? Math.round(Number(tribute.importe) * 100) : 0), 0) / 100)
const totalARS = computed(() => Math.round((fiscalPreview.value.gross + tributosTotal.value) * 100) / 100)

const hasExternalPayment = computed(() => form.value.is_multiple_payment
  ? form.value.payments.some(p => ['card', 'mp'].includes(p.payment_method))
  : ['card', 'mp'].includes(form.value.payment_method))
watch(totalARS, value => { manualAmount.value = value })
watch([form, manualReceipt, manualAmount, tributos], () => {
  manualConfirmed.value = false
}, { deep: true })

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

async function submit() {
  if (loading.value || success.value) return
  error.value = ''
  if (!arcaConnected.value) { error.value = 'Debés vincular tu cuenta con ARCA antes de emitir facturas. Andá a Configuración → AFIP / ARCA.'; return }
  if (form.value.items.some(i => !i.product_id)) { error.value = 'Completá todos los productos'; return }
  if (tributos.value.some(t => !tributeTypes.value.some(type => type.id === Number(t.id)) || !t.desc.trim() || !t.jurisdiction.trim() || !validAmount(t.importe) || !validAmount(t.base_imp, true) || !validAmount(t.alicuota, true) || Number(t.alicuota) > 999.99)) {
    error.value = 'Revisá los tributos: seleccioná un tipo, completá la descripción, la jurisdicción y un importe numérico no negativo con hasta dos decimales. La base y la alícuota son opcionales.'
    return
  }
  if (!form.value.recipient_id && totalARS.value >= ANONYMOUS_CONSUMER_FINAL_LIMIT) {
    error.value = `Por disposición de ARCA, las ventas superiores a $${ANONYMOUS_CONSUMER_FINAL_LIMIT.toLocaleString('es-AR')} a Consumidor Final deben identificar obligatoriamente al cliente con DNI o CUIT.`
    return
  }
  const payments = form.value.is_multiple_payment
    ? form.value.payments.map(p => ({ payment_method: p.payment_method, amount: Number(p.amount), details: p.details, reference: ['card', 'mp'].includes(p.payment_method) ? p.details.trim() : undefined }))
    : [{ payment_method: form.value.payment_method, amount: hasExternalPayment.value ? Number(manualAmount.value) : totalARS.value, details: transferDetails.value, reference: hasExternalPayment.value ? manualReceipt.value.trim() : undefined }]
  if (Math.round(payments.reduce((sum, payment) => sum + payment.amount, 0) * 100) !== Math.round(totalARS.value * 100)) {
    error.value = 'El importe cobrado debe coincidir con el total de la factura.'; return
  }
  if (hasExternalPayment.value && (!manualConfirmed.value || payments.some(p => ['card', 'mp'].includes(p.payment_method) && !p.reference))) {
    error.value = 'Ingresá el comprobante de cada cobro y confirmá que fue aprobado.'; return
  }

  loading.value = true
  try {
    const payload = {
      recipient_id: form.value.recipient_id ? Number(form.value.recipient_id) : null,
      notify_afip: true,
      submission_key: submissionKey.value,
      manual_payment_confirmed: hasExternalPayment.value && manualConfirmed.value,
      payment_method: form.value.is_multiple_payment ? form.value.payments[0].payment_method : form.value.payment_method,
      items: form.value.items.map(i => ({ 
        product_id: Number(i.product_id), 
        quantity: Number(i.quantity),
        variant_index: i.variant_index !== null ? Number(i.variant_index) : null
      })),
      payments,
      tributos: tributos.value.map(t => ({
        id: Number(t.id),
        desc: t.desc.trim(),
        jurisdiction: t.jurisdiction.trim() || null,
        padron_reference: t.padron_reference.trim() || null,
        base_imp: t.base_imp === '' ? null : Number(t.base_imp),
        alicuota: t.alicuota === '' ? null : Number(t.alicuota),
        importe: Number(t.importe),
      })),
    }
    if (pendingPayload.value && !isRetryable.value && JSON.stringify({ ...pendingPayload.value, submission_key: '' }) !== JSON.stringify({ ...payload, submission_key: '' })) {
      submissionKey.value = crypto.randomUUID()
      payload.submission_key = submissionKey.value
    }
    if (!isRetryable.value) pendingPayload.value = structuredClone(payload)
    const res = await invoicesApi.create(pendingPayload.value)
    success.value = true
    setTimeout(() => router.push(`/facturas/${res.data.invoice.id}`), 1200)
  } catch (e: any) {
    const data = e?.response?.data
    error.value = data?.error || Object.values(data?.errors || {}).flat().join(' ') || 'Error al crear la factura'
    isRetryable.value = !!data?.retryable || !e?.response || [408, 429].includes(e.response?.status) || e.response?.status >= 500
    if (data?.submission_key) {
      submissionKey.value = data.submission_key
      if (pendingPayload.value) pendingPayload.value.submission_key = data.submission_key
    }
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
      <fieldset class="invoice-fields flex flex-col gap-4" :disabled="loading || isRetryable || success">
        <!-- Customer -->
        <div class="card">
          <h2 class="section-title" style="margin-bottom:1rem;">Cliente (Opcional)</h2>
          <div class="form-group">
            <label class="form-label">Seleccioná un cliente</label>
            <select v-model="form.recipient_id" class="form-select">
              <option value="">— Consumidor Final (Sin Cliente) —</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">
                {{ c.name }} &mdash; Documento {{ c.document_id }}
              </option>
            </select>
            <div v-if="!form.recipient_id && totalARS >= ANONYMOUS_CONSUMER_FINAL_LIMIT" style="margin-top:0.75rem;padding:0.75rem 1rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:8px;color:#ef4444;font-size:0.85rem;line-height:1.4;">
              ⚠️ <strong>Identificación obligatoria por ARCA:</strong> Esta venta supera los ${{ ANONYMOUS_CONSUMER_FINAL_LIMIT.toLocaleString('es-AR') }}. Debés seleccionar o registrar un cliente con DNI o CUIT.
            </div>
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

        <div class="card">
          <div class="flex items-center justify-between" style="gap:1rem;margin-bottom:0.75rem;">
            <h2 class="section-title">Percepciones y tributos <small class="text-muted">(opcional)</small></h2>
            <button type="button" class="btn btn-ghost btn-sm" :disabled="loadingTributeTypes || tributos.length >= 50" @click="addTribute">{{ loadingTributeTypes ? 'Cargando…' : 'Agregar tributo' }}</button>
          </div>
          <p class="text-muted tribute-help">Cargá los tributos que correspondan a esta operación según tu contador. El importe se ingresa manualmente y se suma al total; la alícuota sugerida no calcula ni aplica percepciones automáticamente.</p>
          <p v-if="settings.default_iibb_alicuota !== null && settings.default_iibb_alicuota !== undefined && settings.default_iibb_alicuota !== ''" class="text-muted tribute-help">Alícuota de referencia del negocio: {{ settings.default_iibb_alicuota }}%<template v-if="settings.fiscal_jurisdiction"> · {{ settings.fiscal_jurisdiction }}</template>.</p>
          <p v-if="tributeTypesError" class="form-error" role="alert">{{ tributeTypesError }}</p>
          <div v-for="(tribute, index) in tributos" :key="index" class="tribute-editor">
            <div class="flex items-center justify-between"><strong>Tributo {{ index + 1 }}</strong><button type="button" class="btn btn-danger btn-sm" @click="tributos.splice(index, 1)">Quitar</button></div>
            <div class="tribute-fields">
              <div class="form-group"><label class="form-label" :for="`tribute-type-${index}`">Tipo de tributo</label><select :id="`tribute-type-${index}`" v-model.number="tribute.id" class="form-select" required><option v-for="type in tributeTypes" :key="type.id" :value="type.id">{{ type.description }}</option></select></div>
              <div class="form-group"><label class="form-label" :for="`tribute-jurisdiction-${index}`">Jurisdicción</label><input :id="`tribute-jurisdiction-${index}`" v-model="tribute.jurisdiction" class="form-input" maxlength="100" required placeholder="Provincia, municipio o nacional" /></div>
              <div class="form-group"><label class="form-label" :for="`tribute-description-${index}`">Descripción</label><input :id="`tribute-description-${index}`" v-model="tribute.desc" class="form-input" maxlength="80" required /></div>
              <div class="form-group"><label class="form-label" :for="`tribute-padron-${index}`">Padrón o referencia (opcional)</label><input :id="`tribute-padron-${index}`" v-model="tribute.padron_reference" class="form-input" maxlength="150" /></div>
              <div class="form-group"><label class="form-label" :for="`tribute-base-${index}`">Base imponible (opcional)</label><input :id="`tribute-base-${index}`" v-model="tribute.base_imp" class="form-input" type="number" min="0" step="0.01" /></div>
              <div class="form-group"><label class="form-label" :for="`tribute-rate-${index}`">Alícuota % (opcional)</label><input :id="`tribute-rate-${index}`" v-model="tribute.alicuota" class="form-input" type="number" min="0" max="999.99" step="0.01" /></div>
              <div class="form-group"><label class="form-label" :for="`tribute-amount-${index}`">Importe a sumar</label><input :id="`tribute-amount-${index}`" v-model="tribute.importe" class="form-input" type="number" min="0" step="0.01" required placeholder="Ingresá el importe" /></div>
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
              Tarjeta / Posnet
            </label>
            <label class="p-method" :class="{ active: form.payment_method === 'transfer' }">
              <input type="radio" v-model="form.payment_method" value="transfer" />
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
              Transferencia
            </label>
          </div>

          <p v-if="hasExternalPayment" class="pos-integration-box mt-3" role="note">
            Cobrá con tu posnet y cargá aquí el comprobante aprobado. FactuFlash registra el pago y emite la factura; no envía un nuevo cobro al dispositivo.
          </p>
          <div v-if="hasExternalPayment && !form.is_multiple_payment" class="manual-payment-fields mt-3">
            <label class="form-label" for="manual-amount">Importe cobrado en el posnet</label>
            <input id="manual-amount" v-model.number="manualAmount" type="number" min="0.01" step="0.01" class="form-input" required />
            <label class="form-label" for="manual-receipt">Comprobante / operación</label>
            <input id="manual-receipt" v-model="manualReceipt" class="form-input" maxlength="150" placeholder="Ej: Payway caja 1 · 20/10 · lote 5 · operación 32" required />
            <small>Incluí proveedor, fecha y lote si el número de operación se repite.</small>
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
                <input v-model.number="p.amount" type="number" min="0.01" step="0.01" required class="form-input" :aria-label="`Importe del pago ${pIdx + 1}`" placeholder="Monto" />
              </div>
              <input v-model="p.details" class="form-input flex-1" :maxlength="['card', 'mp'].includes(p.payment_method) ? 150 : 200" :required="['card', 'mp'].includes(p.payment_method)" :aria-label="`Referencia del pago ${pIdx + 1}`" :placeholder="['card', 'mp'].includes(p.payment_method) ? 'Comprobante: proveedor, fecha, lote y operación' : 'Detalles (opcional)'" />
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

          <label v-if="hasExternalPayment" class="manual-confirmation mt-3">
            <input v-model="manualConfirmed" type="checkbox" required />
            Verifiqué que los cobros del posnet están aprobados y que sus importes coinciden.
          </label>

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
      </fieldset>

      <!-- Right: summary -->
      <div class="flex flex-col gap-4">
        <div class="card summary-card">
          <h2 class="section-title" style="margin-bottom:1.25rem;">Vista previa del total</h2>
          <div class="summary-line"><span class="text-muted">Neto</span><span>{{ formatARS(fiscalPreview.net) }}</span></div>
          <div v-if="fiscalPreview.exempt" class="summary-line"><span class="text-muted">Exento</span><span>{{ formatARS(fiscalPreview.exempt) }}</span></div>
          <div v-if="fiscalPreview.untaxed" class="summary-line"><span class="text-muted">No gravado</span><span>{{ formatARS(fiscalPreview.untaxed) }}</span></div>
          <div class="summary-line"><span class="text-muted">IVA por producto</span><span>{{ formatARS(fiscalPreview.vat) }}</span></div>
          <div class="summary-line"><span class="text-muted">Tributos</span><span>{{ formatARS(tributosTotal) }}</span></div>
          <div v-for="(tribute, index) in tributos" :key="index" class="tribute-preview">
            <strong>{{ tribute.desc || 'Tributo sin descripción' }}</strong>
            <div>{{ tributeTypes.find(type => type.id === Number(tribute.id))?.description }}<template v-if="tribute.jurisdiction"> · {{ tribute.jurisdiction }}</template></div>
            <div v-if="tribute.alicuota !== ''">Alícuota: {{ tribute.alicuota }}%</div>
            <div v-if="tribute.base_imp !== ''">Base: {{ formatARS(Number(tribute.base_imp)) }}</div>
            <div v-if="tribute.padron_reference">Padrón: {{ tribute.padron_reference }}</div>
            <div>{{ validAmount(tribute.importe) ? formatARS(Number(tribute.importe)) : 'Importe pendiente' }}</div>
          </div>
          <div class="summary-divider"></div>

          <div class="summary-line">
            <span class="text-muted">Total</span>
            <span class="font-semibold" style="font-size:1.25rem;color:var(--primary);">
              {{ new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(totalARS) }}
            </span>
          </div>

          <div v-if="arcaConnected" class="arca-status-ok">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            ARCA vinculado — Se notificará automáticamente
          </div>
          <div v-else class="arca-status-warn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
              <strong>ARCA no vinculado</strong>
              <p style="margin:0.25rem 0 0;font-size:0.75rem;opacity:0.85;">No podés emitir facturas hasta vincular tu cuenta con ARCA. Andá a <router-link to="/configuracion" style="color:inherit;text-decoration:underline;font-weight:700;">Configuración → AFIP / ARCA</router-link>.</p>
            </div>
          </div>

          <Transition name="fade">
            <div v-if="error" class="form-error">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <div style="flex:1;">
                <div>{{ error }}</div>
                <div v-if="isRetryable" style="margin-top:0.35rem;font-size:0.75rem;opacity:0.95;color:var(--text-primary);line-height:1.3;">
                  Ocurrió un error temporal. Reintentá con los mismos datos para consultar o completar esta emisión de forma segura, sin duplicarla. Los campos permanecen bloqueados hasta resolverla.
                </div>
              </div>
            </div>
          </Transition>

          <button type="submit" class="btn btn-primary w-full" :disabled="loading || loadingTributeTypes || !arcaConnected || success" style="justify-content:center;margin-top:1rem;">
            <span v-if="loading" class="spinner"></span>
            <span v-else-if="isRetryable">🔄 Reintentar Emisión Segura</span>
            <span v-else>Emitir Factura</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<style scoped>
.invoice-fields { border: 0; margin: 0; padding: 0; min-width: 0; }
.tribute-help { font-size: 0.8rem; line-height: 1.5; margin-bottom: 0.75rem; }
.tribute-editor { border: 1px solid var(--border); border-radius: 10px; padding: 1rem; margin-top: 1rem; }
.tribute-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; margin-top: 0.75rem; }
.tribute-preview { font-size: 0.78rem; line-height: 1.5; border-top: 1px solid var(--border); padding: 0.6rem 0; overflow-wrap: anywhere; }
@media (max-width: 600px) { .tribute-fields { grid-template-columns: 1fr; } }
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
.manual-payment-fields { display: grid; gap: .5rem; }
.manual-payment-fields small { color: var(--text-muted); }
.manual-confirmation { display: flex; align-items: flex-start; gap: .6rem; font-size: .9rem; line-height: 1.5; }
@media (max-width: 600px) { .payment-row { flex-wrap: wrap; } .payment-row > .form-input { flex-basis: 100%; } }
.pos-status-msg {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ARCA Status */
.arca-status-ok {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-top: 1.25rem;
  padding: 0.65rem 0.9rem;
  background: rgba(52, 211, 153, 0.08);
  border: 1px solid rgba(52, 211, 153, 0.2);
  border-radius: var(--radius-sm);
  color: var(--success);
  font-size: 0.82rem;
  font-weight: 600;
}
.arca-status-warn {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  margin-top: 1.25rem;
  padding: 0.75rem 0.9rem;
  background: rgba(251, 191, 36, 0.08);
  border: 1px solid rgba(251, 191, 36, 0.25);
  border-radius: var(--radius-sm);
  color: #fbbf24;
  font-size: 0.82rem;
  font-weight: 600;
}
.arca-status-warn svg {
  flex-shrink: 0;
  margin-top: 1px;
}
</style>
