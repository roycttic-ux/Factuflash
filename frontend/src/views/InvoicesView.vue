<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { invoicesApi } from '@/api/services'

const router = useRouter()
const invoices = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const deleting = ref<number | null>(null)
const confirmDelete = ref<any | null>(null)

onMounted(async () => {
  await load()
})

async function load() {
  loading.value = true
  try {
    const res = await invoicesApi.list()
    invoices.value = res.data.invoices
  } finally {
    loading.value = false
  }
}

const filtered = computed(() =>
  invoices.value.filter(inv => {
    const q = search.value.toLowerCase()
    return !q ||
      inv.invoice_number?.toLowerCase().includes(q) ||
      inv.recipient?.name?.toLowerCase().includes(q)
  })
)

async function doDelete(id: number) {
  deleting.value = id
  try {
    await invoicesApi.delete(id)
    invoices.value = invoices.value.filter(i => i.id !== id)
  } finally {
    deleting.value = null
    confirmDelete.value = null
  }
}

function formatARS(n: number) {
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n)
}
function formatDate(d: string) {
  return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' })
}
function statusBadge(s: string) {
  return s === 'paid' ? 'badge-success' : s === 'pending' ? 'badge-warning' : 'badge-muted'
}
function statusLabel(s: string) {
  return s === 'paid' ? 'Pagada' : s === 'pending' ? 'Pendiente' : s
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Facturas</h1>
        <p class="page-subtitle">Gestioná tus facturas electrónicas</p>
      </div>
      <button class="btn btn-primary" @click="router.push('/facturas/nueva')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva Factura
      </button>
    </div>

    <!-- Search -->
    <div class="search-wrap" style="max-width:340px;margin-bottom:1.25rem;">
      <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input v-model="search" class="form-input" placeholder="Buscar por número o cliente…" />
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:3rem;color:var(--text-muted);">
      <span class="spinner" style="width:28px;height:28px;color:var(--primary);"></span>
    </div>

    <div v-else-if="filtered.length === 0" class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
      </svg>
      <p>No se encontraron facturas</p>
      <button class="btn btn-primary btn-sm" @click="router.push('/facturas/nueva')">Crear primera factura</button>
    </div>

    <div v-else class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>N° Factura</th>
            <th>Cliente</th>
            <th>Fecha</th>
            <th>USD</th>
            <th>ARS</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in filtered" :key="inv.id">
            <td><span class="mono">{{ inv.invoice_number }}</span></td>
            <td>{{ inv.recipient?.name ?? 'Consumidor Final' }}</td>
            <td class="text-muted text-sm">{{ formatDate(inv.created_at) }}</td>
            <td>U$D {{ Number(inv.total_usd).toFixed(2) }}</td>
            <td class="font-semibold">{{ formatARS(inv.total_local) }}</td>
            <td><span class="badge" :class="statusBadge(inv.status)">{{ statusLabel(inv.status) }}</span></td>
            <td>
              <div class="flex gap-2">
                <button class="btn btn-ghost btn-sm btn-icon" @click="router.push(`/facturas/${inv.id}`)" title="Ver detalle">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button class="btn btn-danger btn-sm btn-icon" @click="confirmDelete = inv" title="Eliminar">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Confirm delete modal -->
    <Transition name="fade">
      <div v-if="confirmDelete" class="modal-overlay" @click.self="confirmDelete = null">
        <div class="modal-box" style="max-width:400px;">
          <div class="modal-header">
            <h3 class="modal-title">Eliminar factura</h3>
            <button class="btn btn-ghost btn-icon" @click="confirmDelete = null">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:1.5rem;">
            ¿Estás seguro que querés eliminar la factura <strong style="color:var(--text-primary);">{{ confirmDelete.invoice_number }}</strong>? Esta acción no se puede deshacer.
          </p>
          <div class="flex gap-3" style="justify-content:flex-end;">
            <button class="btn btn-ghost" @click="confirmDelete = null">Cancelar</button>
            <button class="btn btn-danger" :disabled="!!deleting" @click="doDelete(confirmDelete.id)">
              <span v-if="deleting === confirmDelete.id" class="spinner"></span>
              <span v-else>Eliminar</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.mono { font-family: 'Courier New', monospace; font-size: 0.82rem; color: var(--text-muted); }
</style>
