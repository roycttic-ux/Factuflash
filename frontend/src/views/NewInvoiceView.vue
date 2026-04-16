<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { invoicesApi, customersApi, productsApi } from '@/api/services'

const router = useRouter()
const loading = ref(false)
const customers = ref<any[]>([])
const products = ref<any[]>([])
const error = ref('')
const success = ref(false)

const form = ref({
  recipient_id: '',
  notify_afip: false,
  payment_method: 'cash',
  items: [{ product_id: '', quantity: 1 }],
})

onMounted(async () => {
  const [custRes, prodRes] = await Promise.all([customersApi.list(), productsApi.list()])
  customers.value = custRes.data.customers
  products.value = prodRes.data.products
})

function addItem() {
  form.value.items.push({ product_id: '', quantity: 1 })
}
function removeItem(i: number) {
  form.value.items.splice(i, 1)
}

function getProduct(id: string) {
  return products.value.find(p => p.id == id)
}

const subtotalUSD = computed(() =>
  form.value.items.reduce((acc, item) => {
    const p = getProduct(item.product_id)
    return acc + (p ? p.cost_usd * item.quantity : 0)
  }, 0)
)

async function submit() {
  error.value = ''
  if (form.value.items.some(i => !i.product_id)) { error.value = 'Completá todos los productos'; return }

  loading.value = true
  try {
    const payload = {
      recipient_id: form.value.recipient_id ? Number(form.value.recipient_id) : null,
      notify_afip: form.value.notify_afip,
      payment_method: form.value.payment_method,
      items: form.value.items.map(i => ({ product_id: Number(i.product_id), quantity: Number(i.quantity) })),
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
            <button type="button" class="btn btn-ghost btn-sm" @click="addItem">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Agregar
            </button>
          </div>

          <div class="items-list">
            <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
              <div class="form-group flex-1">
                <label class="form-label">Producto</label>
                <select v-model="item.product_id" class="form-select">
                  <option value="">— Elegir —</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">
                    {{ p.name }} (U$D {{ p.cost_usd }})
                  </option>
                </select>
              </div>
              <div class="form-group" style="width:90px;">
                <label class="form-label">Cant.</label>
                <input v-model.number="item.quantity" type="number" min="1" class="form-input" />
              </div>
              <div class="item-total">
                <span class="form-label">Subtotal</span>
                <span class="item-total-val">
                  U$D {{ getProduct(item.product_id) ? (getProduct(item.product_id)!.cost_usd * item.quantity).toFixed(2) : '—' }}
                </span>
              </div>
              <button
                v-if="form.items.length > 1"
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
          <div class="payment-methods">
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
        </div>
      </div>

      <!-- Right: summary -->
      <div class="flex flex-col gap-4">
        <div class="card summary-card">
          <h2 class="section-title" style="margin-bottom:1.25rem;">Resumen</h2>

          <div class="summary-line">
            <span class="text-muted">Subtotal USD</span>
            <span class="font-semibold">U$D {{ subtotalUSD.toFixed(2) }}</span>
          </div>
          <div class="summary-line">
            <span class="text-muted">Tipo de cambio</span>
            <span class="font-semibold">$1,240</span>
          </div>
          <div class="summary-divider"></div>
          <div class="summary-total">
            <span>Total ARS</span>
            <span>{{ new Intl.NumberFormat('es-AR', { style:'currency', currency:'ARS', maximumFractionDigits:0 }).format(subtotalUSD * 1240) }}</span>
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
</style>
