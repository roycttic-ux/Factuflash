<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { authApi } from '@/api/services'

const router = useRouter()
const form = ref({
  name: '',
  email: '',
  password: '',
  company_name: '',
})
const error = ref('')
const loading = ref(false)

async function handleRegister() {
  if (!form.value.name || !form.value.email || !form.value.password) {
    error.value = 'Completá los campos obligatorios'
    return
  }
  error.value = ''
  loading.value = true
  try {
    const res = await authApi.register(form.value)
    localStorage.setItem('token', res.data.token)
    router.push('/dashboard')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Error al registrarse. Verificá los datos.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-bg">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="grid-pattern"></div>
    </div>

    <div class="login-box card-glass">
      <div class="login-brand">
        <div class="login-logo">
           <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
        </div>
        <h1 class="brand-name">FacturaYa</h1>
      </div>

      <h2 class="login-title">Probá 7 días gratis 🚀</h2>
      <p class="login-sub">Empezá a facturar hoy mismo sin compromiso.</p>

      <form @submit.prevent="handleRegister" class="login-form">
        <div class="form-group">
          <label class="form-label">Tu nombre completo</label>
          <input v-model="form.name" type="text" class="form-input" placeholder="Ej: Juan Pérez" autocomplete="name" />
        </div>

        <div class="form-group">
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" class="form-input" placeholder="tu@email.com" autocomplete="email" />
        </div>

        <div class="form-group">
          <label class="form-label">Nombre de tu empresa (Opcional)</label>
          <input v-model="form.company_name" type="text" class="form-input" placeholder="Mi Negocio S.A." autocomplete="organization" />
        </div>

        <div class="form-group">
          <label class="form-label">Contraseña</label>
          <input v-model="form.password" type="password" class="form-input" placeholder="Mínimo 6 caracteres" autocomplete="new-password" />
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
          <span v-else>Empezar mi prueba ahora</span>
        </button>

        <p class="text-center text-xs mt-4">
          <span class="text-muted">¿Ya tenés cuenta?</span>
          <router-link to="/login" class="ml-1 font-bold color-primary decoration-none">Iniciá sesión</router-link>
        </p>
      </form>
    </div>
  </div>
</template>

<style scoped>
/* Reuso los mismos estilos del login para consistencia */
.login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; padding: 1rem; overflow: hidden; }
.login-bg { position: absolute; inset: 0; z-index: 0; }
.orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.35; }
.orb-1 { width: 500px; height: 500px; background: var(--primary); top: -150px; right: -100px; }
.orb-2 { width: 400px; height: 400px; background: var(--accent); bottom: -100px; left: -80px; }
.grid-pattern { position: absolute; inset: 0; background-image: linear-gradient(rgba(99,102,241,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.04) 1px, transparent 1px); background-size: 40px 40px; }
.login-box { position: relative; z-index: 1; width: 100%; max-width: 440px; padding: 2.5rem; border-radius: var(--radius-xl); animation: slideUp 0.35s ease; }
.login-brand { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; }
.login-logo { width: 46px; height: 46px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 14px; display: flex; align-items: center; justify-content: center; color: #fff; box-shadow: 0 4px 20px var(--primary-glow); }
.brand-name { font-size: 1.3rem; font-weight: 800; background: linear-gradient(135deg, #fff, var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.login-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 0.35rem; }
.login-sub { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 2rem; }
.login-form { display: flex; flex-direction: column; gap: 1.1rem; }
.login-error { display: flex; align-items: center; gap: 0.5rem; background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.2); color: var(--danger); border-radius: var(--radius-sm); padding: 0.65rem 0.9rem; font-size: 0.85rem; }
</style>
