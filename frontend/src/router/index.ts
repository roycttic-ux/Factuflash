import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    { path: '/', redirect: '/dashboard' },
    {
      path: '/catalogo/:userId',
      name: 'catalogo',
      component: () => import('@/views/CatalogView.vue'),
    },
    {
      path: '/diagnostics',
      name: 'diagnostics',
      component: () => import('@/views/DiagnosticsView.vue'),
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: 'dashboard', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'facturas', name: 'facturas', component: () => import('@/views/InvoicesView.vue') },
        { path: 'facturas/nueva', name: 'nueva-factura', component: () => import('@/views/NewInvoiceView.vue') },
        { path: 'facturas/:id', name: 'factura-detalle', component: () => import('@/views/InvoiceDetailView.vue') },
        { path: 'productos', name: 'productos', component: () => import('@/views/ProductsView.vue') },
        { path: 'clientes', name: 'clientes', component: () => import('@/views/CustomersView.vue') },
        { path: 'cajas', name: 'cajas', component: () => import('@/views/CashiersView.vue') },
        { path: 'configuracion', name: 'configuracion', component: () => import('@/views/SettingsView.vue') },
        { path: 'suscripcion', name: 'suscripcion', component: () => import('@/views/SubscriptionView.vue') },
        { path: 'suscripcion/exito', name: 'subscription-success', component: () => import('@/views/SubscriptionSuccessView.vue') },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }
  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
