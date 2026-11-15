<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { authApi } from '@/api/services'
import AppLogo from '@/components/AppLogo.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const requestedPlan = route.query.plan === 'yearly' ? 'annual' : route.query.plan
const plan = ['monthly', 'annual'].includes(String(requestedPlan)) ? String(requestedPlan) : 'trial'
const auth = useAuthStore()
const router = useRouter()
const form = ref({
  name: '',
  email: '',
  password: '',
  company_name: '',
})
const error = ref('')
const loading = ref(false)
const captchaToken = ref('')
const monthlyInvoices = ref<number | null>(null)
const simultaneousRegisters = ref<number | null>(null)
const needsPersonalizedPlan = computed(() => Number(monthlyInvoices.value) > 1000 || Number(simultaneousRegisters.value) > 3)
const contactUrl = computed(() => 'https://wa.me/5492954326436?text=' + encodeURIComponent(`Hola, quiero una solución personalizada de FactuFlash. Emito aproximadamente ${monthlyInvoices.value ?? 'sin definir'} facturas por mes y tengo ${simultaneousRegisters.value ?? 'sin definir'} cajas simultáneas.`))

const turnstileKey = import.meta.env.DEV ? '1x00000000000000000000AA' : '0x4AAAAAADAO6Zi2lqnvj_gb'
const homeUrl = import.meta.env.DEV ? '/landing/' : 'https://factuflash.com.ar'

onMounted(() => {
  // @ts-ignore
  window.onTurnstileCallback = (token: string) => {
    captchaToken.value = token
  }

  if (!document.getElementById('cloudflare-turnstile-script')) {
    const script = document.createElement('script')
    script.id = 'cloudflare-turnstile-script'
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js'
    script.async = true
    script.defer = true
    document.head.appendChild(script)
  }
})

async function handleRegister() {
  if (!Number.isInteger(monthlyInvoices.value) || Number(monthlyInvoices.value) < 0 || !Number.isInteger(simultaneousRegisters.value) || Number(simultaneousRegisters.value) < 1) {
    error.value = 'Indicá las facturas mensuales y al menos una caja, con números enteros.'
    return
  }
  if (needsPersonalizedPlan.value) return
  if (!form.value.name || !form.value.email || !form.value.password) {
    error.value = 'Completá los campos obligatorios'
    return
  }
  if (!captchaToken.value && !import.meta.env.DEV) {
    error.value = 'Por favor, completá la validación de seguridad (Captcha).'
    return
  }
  error.value = ''
  loading.value = true
  try {
    const res = await authApi.register({
      ...form.value,
      expected_monthly_invoices: String(monthlyInvoices.value),
      simultaneous_registers: String(simultaneousRegisters.value),
      captcha_token: captchaToken.value
    })
    localStorage.setItem('token', res.data.token)
    auth.token = res.data.token
    auth.user = res.data.user
    await router.push(plan === 'trial' ? '/dashboard' : { name: 'suscripcion', query: { plan } })
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Error al registrarse.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-wrapper-full">
    <!-- Navbar similar to landing -->
    <nav class="auth-nav">
      <div class="auth-nav-content">
        <AppLogo :width="160" />
        <a :href="homeUrl" class="back-link">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
          Inicio
        </a>
      </div>
    </nav>

    <!-- Content Area (Centered) -->
    <main class="auth-main">
      <div class="login-bg">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="grid-pattern"></div>
      </div>

      <div class="login-box card-glass">
        <h2 class="login-title text-center">
          {{ plan === 'monthly' ? 'Plan Mensual' : plan === 'annual' ? 'Plan Anual' : 'Probá 7 días gratis' }}
        </h2>
        <p class="login-sub text-center">
          {{ plan === 'trial' ? 'Empezá a facturar hoy mismo sin compromiso.' : 'Completá tus datos para activar tu suscripción.' }}
        </p>

        <div class="form-group" style="margin-bottom:1rem">
          <label class="form-label" for="monthly-invoices">¿Cuántas facturas emitís por mes aproximadamente?</label>
          <input id="monthly-invoices" v-model.number="monthlyInvoices" type="number" min="0" step="1" class="form-input" placeholder="Por ejemplo, 200" />
        </div>
        <div class="form-group" style="margin-bottom:1rem">
          <label class="form-label" for="register-count">¿Cuántas cajas facturan al mismo tiempo?</label>
          <input id="register-count" v-model.number="simultaneousRegisters" type="number" min="1" step="1" class="form-input" placeholder="Por ejemplo, 2" />
        </div>
        <div v-if="needsPersonalizedPlan" class="login-form">
          <h3>Una solución para tu volumen de ventas</h3>
          <p>Si emitís más de 1.000 facturas por mes o usás más de 3 cajas simultáneas, hablemos para evaluar tu operación y ofrecerte una solución personalizada.</p>
          <a :href="contactUrl" target="_blank" rel="noopener noreferrer" class="btn btn-primary">Consultar por WhatsApp</a>
          <p>Contacto: 2954 326436</p>
        </div>
        <form v-show="!needsPersonalizedPlan" @submit.prevent="handleRegister" class="login-form">
          <div class="form-group">
            <label class="form-label">Tu nombre completo</label>
            <input v-model="form.name" type="text" class="form-input" placeholder="Ej: Juan Pérez" autocomplete="name" />
          </div>

          <div class="form-group">
            <label class="form-label">Email</label>
            <input v-model="form.email" type="email" class="form-input" placeholder="tu@email.com" autocomplete="email" />
          </div>

          <div class="form-group">
            <label class="form-label">Contraseña</label>
            <input v-model="form.password" type="password" class="form-input" placeholder="Mínimo 6 caracteres" autocomplete="new-password" />
          </div>

          <div class="captcha-container">
            <div class="cf-turnstile" :data-sitekey="turnstileKey" data-callback="onTurnstileCallback"></div>
          </div>

          <Transition name="fade">
            <div v-if="error" class="login-error">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              {{ error }}
            </div>
          </Transition>

          <button type="submit" class="btn btn-primary w-full" :disabled="loading" style="justify-content:center;margin-top:0.5rem;">
            <span v-if="loading" class="spinner"></span>
            <span v-else>{{ plan === 'trial' ? 'Empezar mi prueba ahora' : 'Crear cuenta y pagar' }}</span>
          </button>

          <p class="text-center text-xs mt-4">
            <span class="text-muted">¿Ya tenés cuenta?</span>
            <router-link to="/login" class="ml-1 font-bold color-primary decoration-none">Iniciá sesión</router-link>
          </p>
        </form>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Reuso exactamente los mismos estilos del login para asegurar centrado perfecto */
.login-wrapper-full { min-height: 100vh; display: flex; flex-direction: column; }
.auth-nav {
  height: 80px; width: 100%; display: flex; align-items: center; padding: 0 2rem;
  background: rgba(19, 18, 75, 0.4); backdrop-filter: blur(15px);
  border-bottom: 1px solid rgba(255,255,255,0.05); z-index: 100;
}
.auth-nav-content { width: 100%; max-width: 1280px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
.back-link { display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-size: 0.9rem; font-weight: 500; transition: all 0.2s; }
.back-link:hover { color: white; transform: translateX(-2px); }

.auth-main { flex: 1; display: flex; align-items: center; justify-content: center; position: relative; padding: 2rem; overflow: hidden; }
.login-bg { position: absolute; inset: 0; z-index: 0; }
.orb { position: absolute; border-radius: 50%; filter: blur(100px); opacity: 0.3; }
.orb-1 { width: 600px; height: 600px; background: var(--primary); top: -200px; right: -100px; }
.orb-2 { width: 500px; height: 500px; background: var(--accent); bottom: -150px; left: -100px; }
.grid-pattern {
  position: absolute; inset: 0;
  background-image: linear-gradient(rgba(99,102,241,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.05) 1px, transparent 1px);
  background-size: 50px 50px;
}
.login-box { position: relative; z-index: 1; width: 100%; max-width: 420px; padding: 3rem; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
.login-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.2; }
.login-sub { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 2rem; }
.text-center { text-align: center; }
.login-form { display: flex; flex-direction: column; gap: 1.1rem; }
.login-error { display: flex; align-items: center; gap: 0.5rem; background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: var(--danger); border-radius: 12px; padding: 0.75rem 1rem; font-size: 0.85rem; }
.captcha-container { display: flex; justify-content: center; margin: 0.5rem 0; }
</style>
