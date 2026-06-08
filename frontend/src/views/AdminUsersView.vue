<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi } from '@/api/services'

const users = ref<any[]>([])
const loading = ref(true)
const processing = ref<Record<number, boolean>>({}) // ID del usuario -> estado de procesamiento
const toast = ref<{ msg: string; type: string } | null>(null)

const showToast = (msg: string, type: string = 'success') => {
  toast.value = { msg, type }
  setTimeout(() => (toast.value = null), 3000)
}

const fetchUsers = async () => {
  try {
    loading.value = true
    const response = await adminApi.users()
    users.value = response.data
  } catch (err: any) {
    showToast('Error al cargar usuarios', 'error')
  } finally {
    loading.value = false
  }
}

const deleteUser = async (id: number, name: string) => {
  if (!confirm(`¿Estás seguro de eliminar a ${name}?`)) return
  try {
    processing.value[id] = true
    await adminApi.deleteUser(id)
    showToast('Usuario eliminado correctamente')
    await fetchUsers()
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Error al eliminar', 'error')
  } finally {
    delete processing.value[id]
  }
}

const activateSubscription = async (id: number, plan: 'monthly' | 'annual') => {
  const planLabel = plan === 'annual' ? 'Anual' : 'Mensual'
  try {
    processing.value[id] = true
    await adminApi.activateSubscription(id, plan)
    showToast(`¡Plan ${planLabel} activado con éxito!`)
    await fetchUsers()
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Error al activar', 'error')
  } finally {
    delete processing.value[id]
  }
}

const formatDate = (dateStr: string) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('es-AR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const getInitials = (name: string) => {
  return name ? name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : '??'
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <div class="admin-view">
    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toast" class="admin-toast" :class="toast.type">
        {{ toast.msg }}
      </div>
    </Transition>

    <div class="page-header">
      <div>
        <h1 class="page-title">Panel de Control Admin</h1>
        <p class="page-subtitle">Gestión global de la plataforma FactuFlash</p>
      </div>
      <button class="btn btn-ghost btn-icon" @click="fetchUsers" :disabled="loading">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :class="{ 'spin': loading }">
          <path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
        </svg>
      </button>
    </div>

    <!-- Stats Grid -->
    <div class="admin-stats-grid">
      <div class="admin-stat-card">
        <div class="stat-inner">
          <div class="stat-icon-box blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="stat-info">
            <span class="stat-label">Usuarios Totales</span>
            <span class="stat-value">{{ users.length }}</span>
          </div>
        </div>
      </div>

      <div class="admin-stat-card">
        <div class="stat-inner">
          <div class="stat-icon-box green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <div class="stat-info">
            <span class="stat-label">Planes Activos</span>
            <span class="stat-value">{{ users.filter(u => u.plan_status === 'active').length }}</span>
          </div>
        </div>
      </div>

      <div class="admin-stat-card">
        <div class="stat-inner">
          <div class="stat-icon-box amber">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          </div>
          <div class="stat-info">
            <span class="stat-label">En Prueba</span>
            <span class="stat-value">{{ users.filter(u => u.plan_status === 'trial').length }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Table -->
    <div class="admin-card table-card">
      <div v-if="loading && users.length === 0" class="loading-overlay">
        <div class="spinner"></div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>USUARIO / EMPRESA</th>
              <th>ROL</th>
              <th>SUSCRIPCIÓN</th>
              <th>REGISTRO</th>
              <th class="text-right">MANUAL: MES / AÑO</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" :class="{ 'row-processing': processing[user.id] }">
              <td>
                <div class="user-cell">
                  <div class="user-avatar-box">
                    {{ getInitials(user.name) }}
                  </div>
                  <div class="user-meta">
                    <span class="u-name">{{ user.name }}</span>
                    <span class="u-email">{{ user.email }}</span>
                    <span v-if="user.role === 'cashier' && user.owner_name" class="u-owner">
                      Cajero de: <strong>{{ user.owner_name }}</strong>
                    </span>
                    <span v-else-if="user.company_name" class="u-company">{{ user.company_name }}</span>
                  </div>
                </div>
              </td>
              <td>
                <span class="role-badge" :class="user.role">
                  {{ user.role }}
                </span>
              </td>
              <td>
                <div class="plan-info">
                  <span class="plan-badge" :class="user.plan_status">{{ user.plan_status }}</span>
                  <span v-if="user.trial_expires_at" class="vence-text">
                    Vence: {{ formatDate(user.trial_expires_at) }}
                  </span>
                </div>
              </td>
              <td>
                <span class="date-text">{{ formatDate(user.created_at) }}</span>
              </td>
              <td class="text-right">
                <div class="actions-group">
                  <!-- Activar MES -->
                  <button 
                    class="btn btn-sm btn-manual month" 
                    @click="activateSubscription(user.id, 'monthly')" 
                    :disabled="processing[user.id]"
                  >
                    <svg v-if="!processing[user.id]" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span v-if="processing[user.id]" class="spinner-xs"></span>
                    <span v-else>+30 días</span>
                  </button>

                  <!-- Activar AÑO -->
                  <button 
                    class="btn btn-sm btn-manual annual" 
                    @click="activateSubscription(user.id, 'annual')" 
                    :disabled="processing[user.id]"
                  >
                    <svg v-if="!processing[user.id]" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span v-if="processing[user.id]" class="spinner-xs"></span>
                    <span v-else>+1 Año</span>
                  </button>

                  <!-- Eliminar -->
                  <button 
                    class="btn btn-ghost btn-sm btn-icon text-rose-500" 
                    @click="deleteUser(user.id, user.name)" 
                    :disabled="processing[user.id]"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-view { animation: fadeIn 0.5s ease; position: relative; }

.admin-toast {
  position: fixed; top: 2rem; right: 2rem; padding: 1rem 2rem;
  border-radius: 12px; color: white; font-weight: 600; z-index: 9999;
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}
.admin-toast.success { background: #10b981; }
.admin-toast.error { background: #ef4444; }

.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(-20px); }

.btn-manual {
  display: flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.8rem;
  font-size: 0.7rem; font-weight: 700; border-radius: 8px; transition: all 0.2s;
}
.btn-manual.month { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
.btn-manual.annual { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }

.btn-manual:hover:not(:disabled) { transform: scale(1.05); }
.btn-manual:disabled { opacity: 0.5; cursor: not-allowed; }

.row-processing { opacity: 0.6; pointer-events: none; }

.spinner-xs {
  width: 12px; height: 12px; border: 2px solid currentColor;
  border-right-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite;
}

/* Reutilizo tus estilos existentes */
.admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.admin-stat-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.5rem; }
.stat-inner { display: flex; align-items: center; gap: 1.25rem; }
.stat-icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
.stat-icon-box.blue { background: rgba(99,102,241,0.1); color: #818cf8; }
.stat-icon-box.green { background: rgba(16,185,129,0.1); color: #34d399; }
.stat-icon-box.amber { background: rgba(245,158,11,0.1); color: #fbbf24; }
.stat-info { display: flex; flex-direction: column; }
.stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }
.stat-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
.admin-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-lg); overflow: hidden; }
.user-cell { display: flex; align-items: center; gap: 1rem; }
.user-avatar-box { width: 40px; height: 40px; border-radius: 10px; background: var(--bg-elevated); display: flex; align-items: center; justify-content: center; font-weight: 700; border: 1px solid var(--border); }
.role-badge { padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.role-badge.admin { background: rgba(139,92,246,0.1); color: #a78bfa; }
.role-badge.owner { background: rgba(99,102,241,0.1); color: #818cf8; }
.plan-badge { padding: 0.15rem 0.5rem; border-radius: 99px; font-size: 0.7rem; font-weight: 700; }
.plan-badge.trial { background: #f59e0b20; color: #fbbf24; }
.plan-badge.active { background: #10b98120; color: #34d399; }
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

