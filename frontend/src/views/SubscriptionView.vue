<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { subscriptionApi } from '@/api/services'

const route = useRoute()

const plans = ref<Record<string, any>>({})
const loading = ref(true)
const processing = ref(false)
const error = ref('')
const mpNotConfigured = ref(false)

const selectedPlan = ref(route.query.plan as string || '')

onMounted(async () => {
  try {
    const res = await subscriptionApi.plans()
    plans.value = res.data
    if (!selectedPlan.value) {
      selectedPlan.value = 'monthly'
    }
  } catch {
    error.value = 'No se pudieron cargar los planes.'
  } finally {
    loading.value = false
  }
})

async function checkout() {
  if (!selectedPlan.value) return
  processing.value = true
  error.value = ''
  mpNotConfigured.value = false

  try {
    const res = await subscriptionApi.checkout(selectedPlan.value)
    if (res.data.checkout_url) {
      window.location.href = res.data.checkout_url
    }
  } catch (err: any) {
    const msg = err?.response?.data?.error ?? ''
    if (msg.includes('Mercado Pago no está configurado')) {
      mpNotConfigured.value = true
      error.value = ''
    } else {
      error.value = 'No se pudo iniciar el pago. Intentá de nuevo.'
    }
  } finally {
    processing.value = false
  }
}
</script>

<template>
  <div class="sub-page">
    <div class="page-header">
      <h1 class="page-title">Elegí tu plan</h1>
      <p class="page-subtitle">Accedé a todas las funciones de FacturaYa sin límites.</p>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando planes...</p>
    </div>

    <div v-else class="plans-grid">
      <!-- Plan Mensual -->
      <div
        class="plan-card"
        :class="{ selected: selectedPlan === 'monthly', featured: true }"
        @click="selectedPlan = 'monthly'"
      >
        <div class="plan-badge-pop">Más elegido</div>
        <div class="plan-header">
          <div class="plan-name">Plan Mensual</div>
        </div>
        <div class="plan-price">
          <span class="currency">$</span>
          <span class="amount">35.000</span>
          <span class="period">/ mes</span>
        </div>
        <ul class="plan-features">
          <li><span class="check">✔</span> Facturas ilimitadas</li>
          <li><span class="check">✔</span> Productos ilimitados</li>
          <li><span class="check">✔</span> Clientes ilimitados</li>
          <li><span class="check">✔</span> Integración AFIP + CAE</li>
          <li><span class="check">✔</span> Integración Mercado Pago</li>
          <li><span class="check">✔</span> Soporte por WhatsApp</li>
          <li><span class="check">✔</span> Dashboard completo</li>
        </ul>
        <div class="plan-selector">
          <div class="selector-dot" :class="{ active: selectedPlan === 'monthly' }"></div>
        </div>
      </div>

      <!-- Plan Anual -->
      <div
        class="plan-card plan-annual"
        :class="{ selected: selectedPlan === 'annual' }"
        @click="selectedPlan = 'annual'"
      >
        <div class="plan-save-tag">Ahorrás $110.000</div>
        <div class="plan-header">
          <div class="plan-name">Plan Anual</div>
          <div class="plan-free">2 meses gratis</div>
        </div>
        <div class="plan-price">
          <span class="currency">$</span>
          <span class="amount gold">310.000</span>
          <span class="period">/ año</span>
        </div>
        <ul class="plan-features">
          <li><span class="check">✔</span> Todo lo del plan mensual</li>
          <li><span class="check">✔</span> Soporte prioritario WhatsApp</li>
          <li><span class="check">✔</span> Dashboard con exportación</li>
          <li><span class="check">✔</span> Precio fijo por 12 meses</li>
        </ul>
        <div class="plan-selector">
          <div class="selector-dot" :class="{ active: selectedPlan === 'annual' }"></div>
        </div>
      </div>
    </div>

    <!-- MP not configured warning -->
    <div v-if="mpNotConfigured" class="alert alert-warning">
      <strong>⚠️ Mercado Pago no está configurado.</strong>
      <p>Para poder abonar, primero necesitas configurar tu token de Mercado Pago en
        <RouterLink to="/configuracion" class="alert-link">Configuración → Integraciones</RouterLink>.
      </p>
    </div>

    <!-- Error -->
    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <!-- CTA -->
    <div class="checkout-bar" v-if="!loading">
      <div class="checkout-summary">
        <span class="checkout-plan">
          {{ selectedPlan === 'annual' ? 'Plan Anual' : 'Plan Mensual' }}
        </span>
        <span class="checkout-price">
          {{ selectedPlan === 'annual' ? '$310.000' : '$35.000' }}
          <small>/ {{ selectedPlan === 'annual' ? 'año' : 'mes' }}</small>
        </span>
      </div>
      <button
        class="btn btn-checkout"
        :disabled="processing"
        @click="checkout"
      >
        <span v-if="processing" class="spinner-sm"></span>
        <span v-else>💳 Pagar con Mercado Pago</span>
      </button>
    </div>

    <p class="secure-note">🔒 Pago 100% seguro a través de Mercado Pago</p>
  </div>
</template>

<style scoped>
.sub-page {
  max-width: 860px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 2rem;
}

.page-title {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--text-primary);
}

.page-subtitle {
  color: var(--text-muted);
  margin-top: 0.4rem;
}

.loading-state {
  text-align: center;
  padding: 4rem;
  color: var(--text-muted);
}

.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  margin: 0 auto 1rem;
}

.spinner-sm {
  display: inline-block;
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255,255,255,0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Plans grid */
.plans-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 2rem;
}

@media (max-width: 580px) {
  .plans-grid { grid-template-columns: 1fr; }
}

.plan-card {
  background: var(--bg-surface);
  border: 2px solid var(--border);
  border-radius: 16px;
  padding: 1.8rem;
  cursor: pointer;
  position: relative;
  transition: all 0.2s ease;
}

.plan-card:hover {
  border-color: var(--primary);
  transform: translateY(-2px);
}

.plan-card.selected {
  border-color: var(--primary);
  background: rgba(99,102,241,0.06);
  box-shadow: 0 0 30px rgba(99,102,241,0.12);
}

.plan-card.plan-annual {
  border-color: var(--gold);
}

.plan-card.plan-annual.selected {
  border-color: var(--gold);
  background: rgba(245,158,11,0.06);
  box-shadow: 0 0 30px rgba(245,158,11,0.12);
}

.plan-badge-pop {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, var(--primary), var(--accent));
  color: white;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.25rem 0.9rem;
  border-radius: 99px;
  white-space: nowrap;
}

.plan-save-tag {
  position: absolute;
  top: -12px;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, var(--gold), #d97706);
  color: white;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.25rem 0.9rem;
  border-radius: 99px;
  white-space: nowrap;
}

.plan-header {
  margin-bottom: 1rem;
}

.plan-name {
  font-size: 1.2rem;
  font-weight: 800;
}

.plan-free {
  font-size: 0.8rem;
  color: var(--gold);
  font-weight: 600;
  margin-top: 0.2rem;
}

.plan-price {
  display: flex;
  align-items: baseline;
  gap: 0.2rem;
  margin-bottom: 1.2rem;
}

.currency {
  font-size: 1.2rem;
  font-weight: 800;
  color: var(--text-muted);
}

.amount {
  font-size: 2.4rem;
  font-weight: 900;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.amount.gold {
  background: linear-gradient(135deg, var(--gold), #d97706);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.period {
  font-size: 0.9rem;
  color: var(--text-muted);
  font-weight: 400;
}

.plan-features {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.plan-features li {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.88rem;
  color: var(--text-secondary);
}

.check {
  color: var(--accent3);
  font-weight: 700;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.plan-selector {
  display: flex;
  justify-content: center;
}

.selector-dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid var(--border);
  transition: all 0.2s;
}

.selector-dot.active {
  border-color: var(--primary);
  background: var(--primary);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.25);
}

/* Alert */
.alert {
  padding: 1rem 1.2rem;
  border-radius: 10px;
  margin-bottom: 1.5rem;
  font-size: 0.9rem;
  line-height: 1.6;
}

.alert-warning {
  background: rgba(245,158,11,0.1);
  border: 1px solid rgba(245,158,11,0.3);
  color: #fbbf24;
}

.alert-error {
  background: rgba(239,68,68,0.1);
  border: 1px solid rgba(239,68,68,0.3);
  color: #fca5a5;
}

.alert-link {
  color: var(--primary);
  font-weight: 600;
  text-decoration: none;
}

/* Checkout bar */
.checkout-bar {
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 1.2rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
}

.checkout-summary {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.checkout-plan {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.checkout-price {
  font-size: 1.5rem;
  font-weight: 900;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.checkout-price small {
  font-size: 0.8rem;
  font-weight: 400;
  color: var(--text-muted);
}

.btn-checkout {
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 0.8rem 1.8rem;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.2s;
  box-shadow: 0 0 20px rgba(59,130,246,0.3);
}

.btn-checkout:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 0 30px rgba(59,130,246,0.5);
}

.btn-checkout:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.secure-note {
  text-align: center;
  font-size: 0.82rem;
  color: var(--text-muted);
  margin-top: 1rem;
}
</style>