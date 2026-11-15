<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { subscriptionApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'
const router = useRouter()
const auth = useAuthStore()
const status = ref<any>(null)
const checking = ref(false)
const error = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
let attempts = 0
let disposed = false
const confirmed = computed(() => status.value?.has_subscription === true && status.value?.status === 'active' && !status.value?.verification_pending)
async function verify() {
  clearTimeout(timer)
  checking.value = true
  error.value = ''
  try {
    const response = await subscriptionApi.status()
    status.value = response.data
    if (confirmed.value) await auth.fetchMe()
    else if (++attempts < 12 && !disposed) timer = setTimeout(verify, 5000)
  } catch {
    error.value = 'No pudimos consultar el pago. Podés volver a verificarlo.'
  } finally { checking.value = false }
}
onMounted(verify)
onUnmounted(() => { disposed = true; clearTimeout(timer) })
</script>

<template>
  <div class="success-page"><div class="success-card">
    <div class="success-icon">{{ confirmed ? '✓' : '…' }}</div>
    <h1>{{ confirmed ? '¡Pago confirmado!' : 'Verificando tu pago' }}</h1>
    <p class="success-sub">{{ confirmed ? 'Tu suscripción está activa. Ya tenés acceso al sistema durante el período abonado.' : 'Esperamos la confirmación de Mercado Pago. El acceso se activa cuando el pago está aprobado.' }}</p>
    <div v-if="confirmed" class="plan-summary">
      <div class="plan-row"><span>Plan contratado</span><strong>{{ status.plan === 'annual' ? 'Anual' : 'Mensual' }}</strong></div>
      <div class="plan-row"><span>Importe del plan</span><strong>{{ Number(status.amount).toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }) }}</strong></div>
      <div class="plan-row"><span>Acceso hasta</span><strong>{{ new Date(status.current_period_end).toLocaleDateString('es-AR') }}</strong></div>
    </div>
    <p v-if="error" role="alert">{{ error }}</p>
    <button v-if="confirmed" class="btn-dashboard" @click="router.push('/dashboard')">Ir al panel →</button>
    <button v-else class="btn-dashboard" :disabled="checking" @click="verify">{{ checking ? 'Consultando…' : 'Verificar nuevamente' }}</button>
    <RouterLink to="/suscripcion" style="display:block;margin-top:1rem">Volver a Suscripción</RouterLink>
  </div></div>
</template>

<style scoped>
.success-page {
  min-height: 80vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.success-card {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 3rem;
  text-align: center;
  max-width: 480px;
  width: 100%;
}

.success-icon {
  font-size: 3.5rem;
  margin-bottom: 1rem;
}

h1 {
  font-size: 1.8rem;
  font-weight: 800;
  margin-bottom: 0.8rem;
}

.success-sub {
  color: var(--text-muted);
  line-height: 1.6;
  margin-bottom: 2rem;
}

.plan-summary {
  background: var(--bg-elevated);
  border-radius: 10px;
  padding: 1rem 1.2rem;
  margin-bottom: 2rem;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.plan-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.9rem;
  color: var(--text-secondary);
}

.plan-row strong {
  color: var(--text-primary);
}

.btn-dashboard {
  width: 100%;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 0.9rem;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-dashboard:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(59,130,246,0.4);
}
</style>
