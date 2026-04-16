<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { cashierApi } from '@/api/services'

const loading = ref(true)
const saving = ref(false)
const cashiers = ref<any[]>([])

const newCashier = reactive({
  name: '',
  email: '',
  password: '',
  cash_register_name: '',
})

// Toast local
const toast = ref<{ msg: string; type: 'success' | 'error' } | null>(null)
function showToast(msg: string, type: 'success' | 'error' = 'success') {
   toast.value = { msg, type }
   setTimeout(() => { toast.value = null }, 3000)
}

onMounted(async () => {
  await loadCashiers()
})

async function loadCashiers() {
  try {
    const res = await cashierApi.list()
    cashiers.value = res.data.cashiers
  } catch {
    showToast('Error cargando cajeros', 'error')
  } finally {
    loading.value = false
  }
}

async function addCashier() {
  if (!newCashier.name || !newCashier.email || !newCashier.password || !newCashier.cash_register_name) {
    return showToast('Completá todos los campos del cajero', 'error')
  }
  saving.value = true
  try {
    await cashierApi.create(newCashier)
    showToast('Cajero creado exitosamente', 'success')
    newCashier.name = ''
    newCashier.email = ''
    newCashier.password = ''
    newCashier.cash_register_name = ''
    await loadCashiers()
  } catch (e: any) {
    showToast(e.response?.data?.error || 'Error al crear cajero', 'error')
  } finally {
    saving.value = false
  }
}

async function deleteCashier(id: number) {
  if (!confirm('¿Estás seguro? Sus facturas seguirán existiendo, pero ya no podrá operar.')) return
  try {
    await cashierApi.delete(id)
    showToast('Cuenta de cajero eliminada', 'success')
    await loadCashiers()
  } catch {
    showToast('Error al eliminar', 'error')
  }
}

function formatARS(n: number) {
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n || 0)
}

function relativeTime(dateStr: string) {
  if (!dateStr) return 'Nunca'
  const date = new Date(dateStr)
  const diffInMinutes = Math.floor((new Date().getTime() - date.getTime()) / 60000)
  if (diffInMinutes < 1) return 'Hace instantes'
  if (diffInMinutes < 60) return `Hace ${diffInMinutes} min`
  const diffInHours = Math.floor(diffInMinutes / 60)
  if (diffInHours < 24) return `Hace ${diffInHours} horas`
  return date.toLocaleDateString()
}
</script>

<template>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h1 class="page-title">Empleados y Cajas</h1>
        <p class="page-subtitle">Gestioná accesos para tu equipo y supervisá su rendimiento.</p>
      </div>
    </div>

    <!-- Creador de Nuevo Cajero -->
    <div class="card" style="margin-bottom: 2rem;">
       <div class="card-head" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom:1rem;">
          <h2 class="section-title text-primary" style="display:flex; align-items:center; gap:0.5rem;">
             <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
             Dar de Alta Nuevo Cajero
          </h2>
       </div>
       <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Nombre y Apellido</label>
            <input v-model="newCashier.name" class="form-input" placeholder="Ej: Juan Pérez" />
          </div>
          <div class="form-group">
            <label class="form-label">Identificador de Caja/Sucursal</label>
            <input v-model="newCashier.cash_register_name" class="form-input" placeholder="Ej: Mostrador 1" />
          </div>
          <div class="form-group">
            <label class="form-label">Email (Usuario)</label>
            <input v-model="newCashier.email" type="email" class="form-input" placeholder="empleado@comercio.com" />
          </div>
          <div class="form-group">
            <label class="form-label">Contraseña Clave</label>
            <input v-model="newCashier.password" type="password" class="form-input" placeholder="Min. 6 caracteres" />
          </div>
       </div>
       <div style="display:flex; justify-content:flex-end; margin-top:1rem;">
          <button class="btn btn-primary" :disabled="saving" @click="addCashier">
             <span v-if="saving" class="spinner"></span>
             Habilitar Cajero
          </button>
       </div>
    </div>

    <!-- Lista de Empleados -->
    <div class="card">
       <div class="card-head">
          <h2 class="section-title">Tu Equipo de Ventas</h2>
       </div>

       <div v-if="loading" class="text-center text-muted py-8">Cargando métricas...</div>
       <div v-else-if="cashiers.length === 0" class="text-center py-8">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto text-muted mb-4"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <p class="text-muted">No tienes empleados registrados en el sistema.</p>
       </div>

       <div v-else class="table-wrap" style="margin-top:1rem;">
          <table>
             <thead>
                <tr>
                   <th>Estado</th>
                   <th>Nombre del Empleado</th>
                   <th>Sucursal Designada</th>
                   <th class="text-right">Comprobantes Hist.</th>
                   <th class="text-right">Rendimiento Histórico</th>
                   <th class="text-right">Acción</th>
                </tr>
             </thead>
             <tbody>
                <tr v-for="c in cashiers" :key="c.id">
                   <td style="width:120px;">
                      <span class="badge" :class="c.is_online ? 'badge-success' : 'badge-muted'" style="display:inline-flex; align-items:center; gap:0.25rem;">
                         <span class="pulse-dot" :class="{ 'pulse-active': c.is_online }"></span>
                         {{ c.is_online ? 'En Línea' : 'Desconectado' }}
                      </span>
                      <div class="text-[0.65rem] text-muted mt-1 ml-1" v-if="!c.is_online">Visto: {{ relativeTime(c.last_seen) }}</div>
                   </td>
                   <td>
                      <div class="font-bold">{{ c.name }}</div>
                      <div class="text-xs text-muted">{{ c.email }}</div>
                   </td>
                   <td><span class="badge" style="background:#2d3748; color:#e2e8f0;">{{ c.cash_register_name }}</span></td>
                   <td class="text-right font-medium">{{ c.total_comprobantes || 0 }}</td>
                   <td class="text-right font-bold color-primary">{{ formatARS(c.total_recaudacion) }}</td>
                   <td class="text-right">
                      <button class="btn btn-ghost btn-sm btn-icon text-red-400 hover:bg-red-500/10" @click="deleteCashier(c.id)" title="Despedir / Eliminar">
                         <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                   </td>
                </tr>
             </tbody>
          </table>
       </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-container" v-if="toast">
      <div class="toast" :class="'toast-' + toast.type">
        {{ toast.msg }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.pulse-dot {
   width: 6px;
   height: 6px;
   background: currentColor;
   border-radius: 50%;
   display: block;
}
.pulse-active {
   animation: pulser 1.5s infinite;
}
@keyframes pulser {
   0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
   70% { box-shadow: 0 0 0 4px rgba(16, 185, 129, 0); }
   100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}
.page-container {
   max-width: 1000px;
   margin: 0 auto;
}

/* Toast Styles */
.toast-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
}
.toast {
  padding: 12px 24px;
  border-radius: 8px;
  color: white;
  font-weight: 500;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  animation: slideUp 0.3s ease;
}
.toast-success { background: #10b981; }
.toast-error { background: #ef4444; }
@keyframes slideUp {
  from { transform: translateY(100%); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
</style>
