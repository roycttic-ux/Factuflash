<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { subscriptionApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const plans = ref<Record<string, any>>({})
const loading = ref(true)
const processing = ref(false)
const error = ref('')
const mpNotConfigured = ref(false)

const selectedPlan = ref(route.query.plan === 'annual' || route.query.plan === 'yearly' ? 'annual' : 'monthly')
const subscriptionStatus = ref<any>(null)
function price(plan: string) {
  const amount = plans.value[plan]?.price
  return typeof amount === 'number' ? amount.toLocaleString('es-AR') : '—'
}

function formatDate(date: string) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('es-AR', { day: 'numeric', month: 'long', year: 'numeric' })
}

async function loadStatus() {
  try {
    const res = await subscriptionApi.status()
    subscriptionStatus.value = res.data
    await auth.fetchMe()
  } catch (err) {
    console.error('Error al cargar estado', err)
  }
}

async function cancelSubscription() {
  if (!confirm('¿Estás seguro de que querés cancelar la renovación automática? No se te cobrará más el próximo mes.')) return
  
  processing.value = true
  try {
    await subscriptionApi.cancel()
    await loadStatus()
    alert('Tu renovación automática ha sido cancelada correctamente.')
  } catch (err: any) {
    error.value = err?.response?.data?.error ?? 'No se pudo cancelar la suscripción.'
  } finally {
    processing.value = false
  }
}

onMounted(async () => {
  await loadStatus()
  try {
    const res = await subscriptionApi.plans()
    plans.value = res.data
    if (!selectedPlan.value) {
      selectedPlan.value = 'monthly'
    }
    error.value = '' // Limpiar error si carga bien
  } catch (err) {
    error.value = 'No pudimos consultar los planes. Recargá la página para ver los precios e iniciar el pago.'
  } finally {
    loading.value = false
  }
})

async function checkout() {
  if (!selectedPlan.value || !plans.value[selectedPlan.value]) return
  processing.value = true
  error.value = ''
  mpNotConfigured.value = false

  try {
    const res = await subscriptionApi.checkout(selectedPlan.value)
    if (res.data.checkout_url) {
      window.location.href = res.data.checkout_url
    } else {
      error.value = 'No se pudo generar el enlace de pago. Intentá nuevamente.'
    }
  } catch (err: any) {
    const msg = err?.response?.data?.error ?? ''
    if (msg.includes('Mercado Pago no está configurado')) {
      mpNotConfigured.value = true
      error.value = ''
    } else {
      error.value = msg || 'No se pudo iniciar el pago. Intentá de nuevo.'
    }
  } finally {
    processing.value = false
  }
}
</script>

<template>
  <div class="sub-page">
    <!-- Fondo decorativo sutil -->
    <div class="sub-bg">
      <div class="sub-orb orb-1"></div>
      <div class="sub-orb orb-2"></div>
    </div>

    <div v-if="route.query.expired" class="paywall-banner">
      <div class="paywall-content">
        <div class="paywall-icon">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="paywall-text">
          <h2>Tu período de acceso ha finalizado</h2>
          <p>Para seguir gestionando tu negocio con FactuFlash, elegí un plan y activá tu suscripción.</p>
        </div>
      </div>
    </div>

    <div class="page-header text-center">
      <div class="badge-premium">SUSCRIPCIÓN</div>
      <h1 class="page-title">{{ subscriptionStatus?.has_subscription ? 'Tu Suscripción Activa' : 'Elegí el plan perfecto para tu negocio' }}</h1>
      <p class="page-subtitle">
        {{ subscriptionStatus?.has_subscription 
          ? 'Gestioná tu plan actual y detalles de facturación.' 
          : 'Desbloqueá el potencial total de tu empresa con FactuFlash.' 
        }}
      </p>
    </div>

    <!-- Current Subscription Details -->
    <div v-if="subscriptionStatus?.has_subscription" class="current-sub-card glass">
      <div class="sub-info">
        <div class="sub-main">
          <div class="sub-title">ESTADO ACTUAL: <span class="status-active">ACTIVA</span></div>
          <p class="sub-desc">
            <strong>{{ subscriptionStatus.plan === 'annual' ? 'Plan Anual' : 'Plan Mensual' }}</strong> activo. 
            Acceso abonado hasta: <strong>{{ formatDate(subscriptionStatus.current_period_end) }}</strong>
          </p>
        </div>
        <button 
          v-if="subscriptionStatus.can_cancel"
          @click="cancelSubscription" 
          class="btn-cancel-sub" 
          :disabled="processing"
        >
          {{ processing ? 'Procesando...' : 'Cancelar renovación automática' }}
        </button>
      </div>
      <div v-if="subscriptionStatus.can_cancel" class="sub-footer-note">
        * Al cancelar, dejarás de recibir cargos automáticos pero mantendrás el acceso hasta el final de tu periodo actual.
      </div>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Consultando cotizaciones actualizadas...</p>
    </div>

    <div v-else class="plans-container">
      <div class="plans-grid">
        <!-- Plan Mensual -->
        <div
          class="plan-card glass"
          :class="{ selected: selectedPlan === 'monthly' }"
          @click="selectedPlan = 'monthly'"
        >
          <div class="plan-badge-pop">MÁS ELEGIDO</div>
          <div class="plan-header">
            <div class="plan-icon-top">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/><path d="M17 12h.01"/><path d="M7 12h.01"/></svg>
            </div>
            <h3 class="plan-name">Plan Mensual</h3>
            <p class="plan-tagline">Ideal para empezar sin compromiso</p>
          </div>
          <div class="plan-price">
            <span class="currency">$</span>
            <span class="amount">{{ price('monthly') }}</span>
            <span class="period">/ mes</span>
          </div>
          <div class="plan-divider"></div>
          <ul class="plan-features">
            <li><div class="f-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> <strong>Facturas ilimitadas</strong></li>
            <li><div class="f-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Productos y Stock Pro</li>
            <li><div class="f-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Integración ARCA + CAE</li>
            <li><div class="f-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Sincronización INDEC</li>
            <li><div class="f-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Mercado Pago Checkouts</li>
            <li><div class="f-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Dashboard en tiempo real</li>
            <li><div class="f-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Reportes y Exportación</li>
          </ul>
          <div class="plan-select-indicator">
             <div class="indicator-inner"></div>
          </div>
        </div>

        <!-- Plan Anual -->
        <div
          class="plan-card glass plan-annual"
          :class="{ selected: selectedPlan === 'annual' }"
          @click="selectedPlan = 'annual'"
        >
          <div class="plan-save-tag">12 MESES DE ACCESO</div>
          <div class="plan-header">
            <div class="plan-icon-top">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <h3 class="plan-name">Plan Anual</h3>
            <p class="plan-tagline">Pagá un año completo por adelantado</p>
          </div>
          <div class="plan-price">
            <span class="currency">$</span>
            <span class="amount-gold-text">{{ price('annual') }}</span>
            <span class="period">/ año</span>
          </div>
          <div class="plan-divider gold"></div>
          <ul class="plan-features">
            <li><div class="f-check gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> <strong>Facturas ilimitadas</strong></li>
            <li><div class="f-check gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Productos y Stock Pro</li>
            <li><div class="f-check gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Integración ARCA + CAE</li>
            <li><div class="f-check gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Sincronización INDEC</li>
            <li><div class="f-check gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Mercado Pago Checkouts</li>
            <li><div class="f-check gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Dashboard en tiempo real</li>
            <li><div class="f-check gold"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div> Reportes y Exportación</li>
          </ul>
          <div class="plan-select-indicator gold">
             <div class="indicator-inner gold"></div>
          </div>
        </div>
      </div>

      <!-- MP not configured warning -->
      <Transition name="slide">
        <div v-if="mpNotConfigured" class="alert-premium warning">
          <div class="alert-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div class="alert-content">
            <strong>Configuración pendiente</strong>
            <p>El cobro de suscripciones no está disponible en este momento. Contactá al soporte de FactuFlash para que lo revise.</p>
          </div>
        </div>
      </Transition>

      <Transition name="slide">
        <div v-if="error" class="alert-premium error">
          <div class="alert-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          </div>
          <div class="alert-content">
            <strong>Error al iniciar pago</strong>
            <p>{{ error }}</p>
          </div>
        </div>
      </Transition>

      <!-- Checkout Button Section -->
      <div class="checkout-section glass" v-if="!loading">
        <div class="checkout-info">
          <div class="selected-label">PLAN SELECCIONADO</div>
          <div class="selected-value">
            {{ selectedPlan === 'annual' ? 'Plan Anual' : 'Plan Mensual' }} 
            <span class="final-price">${{ price(selectedPlan) }} / {{ selectedPlan === 'annual' ? 'año' : 'mes' }}</span>
          </div>
        </div>
        <button
          class="btn-pay-premium"
          :class="{ processing: processing }"
          :disabled="processing || !plans[selectedPlan]"
          @click="checkout"
        >
          <span v-if="processing" class="loader-white"></span>
          <template v-else>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
            </svg>
            Pagar suscripción segura
          </template>
        </button>
      </div>

      <p class="trust-footer">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        Encriptación de grado bancario · Mercado Pago oficial
      </p>
    </div>
  </div>
</template>

<style scoped>
.sub-page { max-width: 900px; margin: 0 auto; position: relative; padding-bottom: 4rem; }
.paywall-banner {
  background: linear-gradient(135deg, #ef4444, #991b1b);
  border-radius: 20px;
  padding: 1.5rem 2rem;
  margin-bottom: 3rem;
  box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.2);
  animation: slideDown 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.paywall-content { display: flex; align-items: center; gap: 1.5rem; color: white; }
.paywall-icon { font-size: 2.5rem; filter: drop-shadow(0 0 10px rgba(255,255,255,0.4)); }
.paywall-text h2 { font-size: 1.4rem; font-weight: 900; margin-bottom: 0.25rem; }
.paywall-text p { font-size: 0.95rem; opacity: 0.9; font-weight: 500; }

/* Decoración de fondo */
.sub-bg { position: absolute; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
.sub-orb { position: absolute; border-radius: 50%; filter: blur(140px); opacity: 0.12; mix-blend-mode: screen; }
.orb-1 { width: 600px; height: 600px; background: radial-gradient(circle, var(--primary), transparent 70%); top: -250px; right: -150px; }
.orb-2 { width: 500px; height: 500px; background: radial-gradient(circle, var(--accent), transparent 70%); bottom: -100px; left: -150px; }

.page-header { margin-bottom: 3.5rem; }
.badge-premium { display: inline-block; background: rgba(99,102,241,0.15); color: var(--primary); font-size: 0.7rem; font-weight: 800; padding: 0.3rem 0.8rem; border-radius: 6px; letter-spacing: 1px; margin-bottom: 1rem; border: 1px solid rgba(99,102,241,0.2); }
.page-title { font-size: 2.2rem; font-weight: 900; letter-spacing: -0.5px; }
.page-subtitle { color: var(--text-muted); font-size: 1.1rem; margin-top: 0.6rem; opacity: 0.8; }

.plans-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 3rem; }
@media (max-width: 650px) { .plans-grid { grid-template-columns: 1fr; } }

.glass { background: var(--bg-card); border: 1px solid var(--border); }
 
.current-sub-card { border-radius: var(--radius-lg); padding: 1.5rem 2rem; margin-bottom: 2.5rem; border: 1px solid rgba(5, 150, 105, 0.3); background: rgba(5, 150, 105, 0.03); }
.sub-info { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; }
@media (max-width: 600px) { .sub-info { flex-direction: column; text-align: center; } }
.sub-title { font-size: 0.75rem; font-weight: 800; color: var(--primary); letter-spacing: 1px; margin-bottom: 0.3rem; }
.status-active { color: var(--success); margin-left: 0.5rem; background: rgba(5, 150, 105, 0.1); padding: 0.1rem 0.6rem; border-radius: 0px; border: 1px solid currentColor; }
.sub-desc { font-size: 1.05rem; }
.sub-footer-note { font-size: 0.75rem; color: var(--text-muted); margin-top: 1rem; border-top: 1px solid var(--border); padding-top: 0.8rem; font-style: italic; }
.btn-cancel-sub { background: rgba(220, 38, 38, 0.05); color: var(--danger); border: 1px solid rgba(220, 38, 38, 0.2); padding: 0.6rem 1.2rem; border-radius: var(--radius-sm); font-weight: 700; cursor: pointer; transition: all var(--transition); font-size: 0.85rem; }
.btn-cancel-sub:hover:not(:disabled) { background: rgba(220, 38, 38, 0.1); }
 
.plan-card { border-radius: var(--radius-lg); padding: 2.5rem; cursor: pointer; position: relative; transition: all var(--transition); display: flex; flex-direction: column; min-height: 520px; }
.plan-card:hover { border-color: var(--primary); background: var(--bg-surface); }
.plan-card.selected { border-color: var(--primary); border-width: 2px; background: rgba(124, 58, 237, 0.04); }
 
.plan-badge-pop { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); background: var(--primary); color: white; font-size: 0.7rem; font-weight: 900; padding: 0.45rem 1.6rem; border-radius: var(--radius-sm); z-index: 10; letter-spacing: 0.05em; text-transform: uppercase; }
.plan-icon-top { font-size: 2.2rem; margin-bottom: 0.75rem; }
 
.plan-header { margin-bottom: 2rem; text-align: center; }
.plan-name { font-size: 1.6rem; font-weight: 900; margin-bottom: 0.3rem; letter-spacing: -0.5px; }
.plan-tagline { font-size: 0.9rem; color: var(--text-muted); font-weight: 500; opacity: 0.9; }
 
.plan-price { display: flex; align-items: baseline; justify-content: center; gap: 0.4rem; margin-bottom: 2rem; }
.currency { font-size: 1.5rem; font-weight: 700; color: var(--text-muted); }
.amount { font-size: 3.2rem; font-weight: 700; color: var(--text-primary); letter-spacing: -1px; font-family: 'JetBrains Mono', monospace; }
.amount-gold-text { font-size: 3.2rem; font-weight: 700; color: #d97706; letter-spacing: -1px; font-family: 'JetBrains Mono', monospace; }
.period { color: var(--text-muted); font-weight: 600; font-size: 1.1rem; }
 
.plan-divider { height: 1px; background: var(--border); margin-bottom: 2rem; }
.plan-divider.gold { background: var(--border); }
 
.plan-features { list-style: none; display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2.5rem; flex-grow: 1; }
.plan-features li { display: flex; align-items: center; gap: 0.9rem; font-size: 0.95rem; color: var(--text-muted); transition: transform 0.2s; }
.plan-features li:hover { transform: translateX(5px); color: var(--text-primary); }
.f-check { width: 22px; height: 22px; border-radius: 50%; background: rgba(124, 58, 237, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 5px; }
.f-check.gold { background: rgba(217, 119, 6, 0.1); color: #d97706; }
.f-check svg { width: 100%; height: 100%; }
 
.plan-select-indicator { height: 4px; background: var(--border); border-radius: 0px; overflow: hidden; margin-top: auto; }
.indicator-inner { height: 100%; width: 0%; background: var(--primary); transition: width 0.5s cubic-bezier(0.19, 1, 0.22, 1); }
.selected .indicator-inner { width: 100%; }
.indicator-inner.gold { background: #d97706; }
 
/* Plan Anual Especial */
.plan-annual { border-color: var(--border); background: var(--bg-card); }
.plan-annual:hover { border-color: #d97706; }
.plan-annual.selected { border-color: #d97706; background: rgba(217, 119, 6, 0.04); }
.plan-save-tag { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #d97706; color: #fff; font-size: 10px; font-weight: 900; padding: 4px 16px; border-radius: var(--radius-sm); z-index: 10; letter-spacing: 0.05em; text-transform: uppercase; }
 
/* Checkout Section */
.checkout-section { border-radius: var(--radius-lg); padding: 1.75rem 3rem; display: flex; align-items: center; justify-content: space-between; gap: 2rem; border: 1px solid var(--border); background: var(--bg-card); }
@media (max-width: 650px) { .checkout-section { flex-direction: column; text-align: center; padding: 2.5rem; gap: 1.5rem; } }
 
.selected-label { font-size: 0.75rem; font-weight: 800; color: var(--primary); letter-spacing: 1.5px; margin-bottom: 0.4rem; }
.selected-value { font-size: 1.6rem; font-weight: 900; letter-spacing: -0.5px; }
.final-price { font-size: 1.1rem; color: var(--text-muted); font-weight: 500; margin-left: 0.75rem; }
 
.btn-pay-premium { background: var(--primary); color: white; border: none; padding: 1.1rem 2.5rem; border-radius: var(--radius-sm); font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s ease; }
.btn-pay-premium:hover:not(:disabled) { background: var(--primary-hover); transform: translateY(-1px); }
.btn-pay-premium:active { transform: translateY(0); }
.btn-pay-premium:disabled { opacity: 0.6; cursor: not-allowed; }
 
.loader-white { width: 22px; height: 22px; border: 3px solid rgba(255,255,255,0.25); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s cubic-bezier(0.5, 0.1, 0.5, 0.9) infinite; }
 
/* Alerts */
.alert-premium { display: flex; gap: 1.25rem; padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2.5rem; align-items: center; border: 1px solid transparent; animation: slideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.alert-premium.warning { background: rgba(217, 119, 6, 0.08); border-color: rgba(217, 119, 6, 0.25); color: #d97706; }
.alert-premium.error { background: rgba(220, 38, 38, 0.08); border-color: rgba(220, 38, 38, 0.25); color: var(--danger); }
.alert-icon { font-size: 1.75rem; }
.alert-premium p { font-size: 1rem; line-height: 1.5; font-weight: 500; }
.alert-link { color: inherit; font-weight: 800; text-decoration: underline; text-underline-offset: 3px; }
 
.trust-footer { margin-top: 2.5rem; display: flex; align-items: center; justify-content: center; gap: 0.6rem; font-size: 0.9rem; color: var(--text-muted); opacity: 0.7; }
 
/* Animations */
.slide-enter-active, .slide-leave-active { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.slide-enter-from, .slide-leave-to { opacity: 0; transform: translateY(-20px); }
 
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
</style>
