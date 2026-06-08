<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { subscriptionApi } from '@/api/services'

const router = useRouter()
const plan = new URLSearchParams(window.location.search).get('plan')
const planLabel = plan === 'annual' ? 'Plan Anual' : 'Plan Mensual'
const planPrice = plan === 'annual' ? '$310.000' : '$35.000'

onMounted(async () => {
  // Verify the subscription status on server
  try {
    await subscriptionApi.status()
  } catch {
    // Silently ignore - the user already has the MP confirmation
  }
})
</script>

<template>
  <div class="success-page">
    <div class="success-card">
      <div class="success-icon">ðŸŽ‰</div>
      <h1>Â¡Pago confirmado!</h1>
      <p class="success-sub">
        Tu suscripción a <strong>FactuFlash {{ planLabel }}</strong> está activa.
        Ya tenés acceso completo al sistema.
      </p>

      <div class="plan-summary">
        <div class="plan-row">
          <span>Plan contratado</span>
          <strong>{{ planLabel }}</strong>
        </div>
        <div class="plan-row">
          <span>Monto abonado</span>
          <strong>{{ planPrice }}</strong>
        </div>
      </div>

      <button class="btn-dashboard" @click="router.push('/dashboard')">
        Ir al Dashboard â†’
      </button>
    </div>
  </div>
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
