<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { dashboardApi } from '@/api/services'

const router = useRouter()
const loading = ref(true)
const stats = ref({ monthly_total: 0, invoice_count: 0, product_count: 0, low_stock_count: 0 })
const recentInvoices = ref<any[]>([])
const topProducts = ref<any[]>([])

onMounted(async () => {
  try {
    const res = await dashboardApi.stats()
    stats.value = res.data.stats
    recentInvoices.value = res.data.recent_invoices
    topProducts.value = res.data.top_products
  } finally {
    loading.value = false
  }
})

const statCards = computed(() => [
  {
    label: 'Facturado este mes',
    value: formatARS(stats.value.monthly_total),
    icon: 'dollar',
    color: '#6366f1',
    delta: 'ARS',
  },
  {
    label: 'Total facturas',
    value: stats.value.invoice_count,
    icon: 'file',
    color: '#22d3ee',
    delta: 'emitidas',
  },
  {
    label: 'Productos',
    value: stats.value.product_count,
    icon: 'box',
    color: '#a78bfa',
    delta: 'en catálogo',
  },
  {
    label: 'Stock bajo',
    value: stats.value.low_stock_count,
    icon: 'alert',
    color: '#fbbf24',
    delta: 'productos',
  },
])

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
  <div class="dashboard-view">
    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Resumen de tu actividad comercial</p>
      </div>
      <button class="btn btn-primary" @click="router.push('/facturas/nueva')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nueva Factura
      </button>
    </div>

    <!-- Loading skeleton -->
    <template v-if="loading">
      <div class="grid-stats">
        <div v-for="i in 4" :key="i" class="stat-card skeleton-card"></div>
      </div>
    </template>

    <template v-else>
      <!-- Stat cards -->
      <div class="grid-stats" style="margin-bottom:1.75rem;">
        <div
          v-for="card in statCards"
          :key="card.label"
          class="stat-card"
          :style="{ '--stat-color': card.color }"
        >
          <div class="stat-icon">
            <!-- dollar -->
            <svg v-if="card.icon==='dollar'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
            <!-- file -->
            <svg v-else-if="card.icon==='file'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            <!-- box -->
            <svg v-else-if="card.icon==='box'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            </svg>
            <!-- alert -->
            <svg v-else-if="card.icon==='alert'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
              <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </div>
          <div class="stat-label">{{ card.label }}</div>
          <div class="stat-value">{{ card.value }}</div>
          <div class="stat-delta">{{ card.delta }}</div>
        </div>
      </div>

      <!-- Stats Grid (Invoices + Top Products) -->
      <div class="dashboard-grid" style="margin-top:1.5rem; display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem;">
        <!-- Recent invoices -->
        <div class="card">
          <div class="card-head">
            <h2 class="section-title">Últimas Facturas</h2>
            <button class="btn btn-ghost btn-sm" @click="router.push('/facturas')">Ver todas →</button>
          </div>

          <div v-if="recentInvoices.length === 0" class="empty-state" style="padding: 2rem;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            <p>No hay facturas todavía</p>
          </div>

          <div v-else class="table-wrap" style="margin-top:1rem;">
            <table>
              <thead>
                <tr>
                  <th>N° Factura</th>
                  <th>Cliente</th>
                  <th>Fecha</th>
                  <th>Total ARS</th>
                  <th>Estado</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="inv in recentInvoices" :key="inv.id">
                  <td><span class="mono">{{ inv.invoice_number }}</span></td>
                  <td>{{ inv.recipient?.name ?? '—' }}</td>
                  <td>{{ formatDate(inv.created_at) }}</td>
                  <td class="font-semibold">{{ formatARS(inv.total_local) }}</td>
                  <td><span class="badge" :class="statusBadge(inv.status)">{{ statusLabel(inv.status) }}</span></td>
                  <td>
                    <button class="btn btn-ghost btn-sm btn-icon" @click="router.push(`/facturas/${inv.id}`)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top Selling Products -->
        <div class="card">
          <div class="card-head">
            <h2 class="section-title">Más Vendidos</h2>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--primary);"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          </div>
          <div class="top-list" style="margin-top:1.25rem;">
             <div v-for="(tp, idx) in topProducts" :key="tp.product_id" class="top-item">
                <div class="top-rank">{{ idx + 1 }}</div>
                <div class="top-info">
                   <div class="top-name">{{ tp.product?.name || 'Producto eliminado' }}</div>
                   <div class="top-qty">{{ tp.total_qty }} unidades vendidas</div>
                </div>
             </div>
             <div v-if="topProducts.length === 0" class="empty-mini">
                <p>Sin datos de venta</p>
             </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.card-head {
  display: flex; align-items: center; justify-content: space-between;
}
.section-title { font-size: 1rem; font-weight: 700; }
.mono { font-family: 'Courier New', monospace; font-size: 0.82rem; color: var(--text-muted); }

.top-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0; border-bottom: 1px solid var(--border); }
.top-item:last-child { border: none; }
.top-rank { width: 24px; height: 24px; border-radius: 6px; background: var(--bg-elevated); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: var(--primary); }
.top-name { font-size: 0.875rem; font-weight: 600; color: var(--text-primary); }
.top-qty { font-size: 0.75rem; color: var(--text-muted); }
.empty-mini { text-align: center; padding: 2rem 0; color: var(--text-faint); font-size: 0.8rem; }

@media (max-width: 900px) {
  .dashboard-grid { grid-template-columns: 1fr !important; }
}
</style>
