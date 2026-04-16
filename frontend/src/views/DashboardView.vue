<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { dashboardApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const router = useRouter()
const loading = ref(true)
const stats = ref({ monthly_total: 0, invoice_count: 0, product_count: 0, low_stock_count: 0 })
const recentInvoices = ref<any[]>([])
const topProducts = ref<any[]>([])
const salesByCashier = ref<any[]>([])

onMounted(async () => {
  try {
    const res = await dashboardApi.stats()
    stats.value = res.data.stats
    recentInvoices.value = res.data.recent_invoices
    topProducts.value = res.data.top_products
    salesByCashier.value = res.data.sales_by_cashier
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

    <!-- BANNER DE ESTADO DE CUENTA -->
    <div 
      v-if="auth.user && auth.user.role === 'owner'"
      class="account-status-banner" 
      :class="[auth.user.plan_status === 'trial' ? 'status-trial' : 'status-paid']"
    >
      <div class="status-content">
        <div class="status-icon">
          <svg v-if="auth.user.plan_status === 'trial'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div class="status-text">
          <p class="status-main">
            {{ auth.user.plan_status === 'trial' ? 'Estás en el período de PRUEBA GRATUITA' : 'Tu suscripción está ACTIVA' }}
          </p>
          <p class="status-sub">
            {{ auth.user.days_left > 0 
                ? `Te quedan ${auth.user.days_left} días de sistema. ¡Aprovechalos!` 
                : 'Tu período ha vencido. ¡Actualizá tu cuenta para seguir operando!' 
            }}
          </p>
        </div>
      </div>
      <button 
        v-if="auth.user.plan_status === 'trial' || auth.user.days_left <= 0"
        class="btn btn-white btn-sm"
        @click="router.push('/configuracion')"
      >
        Actualizar mi Plan ⭐
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

      <!-- Ventas por Caja (Solo vista para Jefe y con cajas activas) -->
      <div v-if="salesByCashier && salesByCashier.length > 0" class="card" style="margin-top:1.5rem;">
         <div class="card-head">
            <h2 class="section-title">Desglose por Cajero / Sucursal</h2>
         </div>
         <div class="table-wrap" style="margin-top:1rem;">
            <table>
               <thead>
                  <tr>
                     <th>Identificador del Cajero</th>
                     <th>Caja / Sucursal</th>
                     <th class="text-right">Volumen de Facturas</th>
                     <th class="text-right">Total Recaudado</th>
                  </tr>
               </thead>
               <tbody>
                  <tr v-for="sc in salesByCashier" :key="sc.cashier_id || 0">
                     <td>
                        <div class="font-bold">{{ sc.cashier ? sc.cashier.name : 'Ventas del Jefe (Dueño)' }}</div>
                     </td>
                     <td><span class="badge badge-primary">{{ sc.cashier ? sc.cashier.cash_register_name : 'Principal' }}</span></td>
                     <td class="text-right font-medium">{{ sc.total_invoices }} comprobantes</td>
                     <td class="text-right font-semibold color-primary">{{ formatARS(sc.total_sales) }}</td>
                  </tr>
               </tbody>
            </table>
         </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* BANNER ESTADO */
.account-status-banner {
  margin: 1.5rem 0;
  padding: 1.25rem 1.5rem;
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  color: white;
  animation: slideDown 0.4s ease;
}
.status-trial { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2); }
.status-paid { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }

.status-content { display: flex; align-items: center; gap: 1rem; }
.status-icon { width: 44px; height: 44px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.status-main { font-weight: 700; font-size: 0.9rem; margin: 0; }
.status-sub { font-size: 0.75rem; opacity: 0.9; margin: 0.15rem 0 0; }

.btn-white { background: white; color: #d97706; border: none; font-weight: 700; }
.status-paid .btn-white { color: #059669; }

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 640px) {
  .account-status-banner { flex-direction: column; text-align: center; }
  .status-content { flex-direction: column; }
}

</style>
