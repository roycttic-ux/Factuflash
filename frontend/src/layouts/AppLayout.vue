<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute, RouterView, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLogo from '@/components/AppLogo.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const sidebarOpen = ref(window.innerWidth > 768)
const isMobile = ref(window.innerWidth <= 768)

function handleResize() {
  const wasMobile = isMobile.value
  isMobile.value = window.innerWidth <= 768
  if (wasMobile && !isMobile.value) {
    sidebarOpen.value = true
  }
}

onMounted(() => {
  window.addEventListener('resize', handleResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
})

function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value
}

function closeSidebarOnMobile() {
  if (isMobile.value) {
    sidebarOpen.value = false
  }
}

const navItems = computed(() => {
  const items = [
    { to: '/dashboard',     label: 'Dashboard',     icon: 'grid' },
    { to: '/facturas',      label: 'Facturas',      icon: 'file-text' },
    { to: '/productos',     label: 'Productos',     icon: 'package' },
    { to: '/clientes',      label: 'Clientes',      icon: 'users' },
    { to: '/cajas',         label: 'Empleados',     icon: 'briefcase', hideForCashier: true },
    { to: '/suscripcion',   label: 'Suscripción',   icon: 'credit-card', hideForCashier: true },
    { to: '/configuracion', label: 'Configuración', icon: 'settings', hideForCashier: true, exact: true },
    { to: '/configuracion/catalogo', label: 'Catálogo Web', icon: 'monitor', hideForCashier: true },
    { to: '/soporte',       label: 'Soporte',       icon: 'help-circle' },
    { to: '/admin/usuarios', label: 'Panel Admin',  icon: 'shield', adminOnly: true },
  ]
  
  return items.filter(item => {
    if (auth.user?.role === 'cashier' && item.hideForCashier) return false
    if (item.adminOnly) {
      const isOwnerEmail = auth.user?.email === 'admin@factuflash.com'
      if (!isOwnerEmail) return false
    }
    return true
  })
})

function isActive(item: any) {
  if (item.exact) return route.path === item.to
  return route.path.startsWith(item.to)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}


</script>

<template>
  <div class="app-shell" :class="{ collapsed: !sidebarOpen, 'is-mobile': isMobile }">
    <!-- Mobile Top Bar -->
    <header v-if="isMobile" class="mobile-header">
      <button type="button" class="btn btn-icon btn-ghost" @click="toggleSidebar">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <div class="mobile-logo">
        <AppLogo :width="130" />
      </div>
      <div class="user-avatar-sm">{{ auth.user?.name?.charAt(0).toUpperCase() }}</div>
    </header>

    <!-- Overlay for mobile -->
    <div v-if="isMobile && sidebarOpen" class="sidebar-overlay" @click="sidebarOpen = false"></div>

    <!-- Sidebar -->
    <aside class="sidebar" :class="{ 'sidebar-visible': sidebarOpen }">
      <div class="sidebar-logo">
        <div class="logo-area" @click="sidebarOpen = true" :style="{ cursor: !sidebarOpen ? 'pointer' : 'default' }">
          <AppLogo :width="sidebarOpen ? 140 : 24" :collapsed="!sidebarOpen" style="color: white" />
        </div>
        <button type="button" v-if="!isMobile" class="sidebar-toggle btn btn-icon btn-ghost" @click="toggleSidebar" :title="sidebarOpen ? 'Colapsar menú' : 'Expandir menú'">
          <svg v-if="sidebarOpen" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="15 18 9 12 15 6"/>
          </svg>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="9 18 15 12 9 6"/>
          </svg>
        </button>
        <button type="button" v-else class="sidebar-toggle btn btn-icon btn-ghost" @click="sidebarOpen = false">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <nav class="sidebar-nav">
        <template v-for="item in navItems" :key="item.label">
          <RouterLink
            :to="item.to!"
            class="nav-item"
            :class="{ active: isActive(item) }"
            @click="closeSidebarOnMobile"
          >
            <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <template v-if="item.icon === 'grid'">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
              </template>
              <template v-else-if="item.icon === 'file-text'">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
              </template>
              <template v-else-if="item.icon === 'package'">
                <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/>
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
              </template>
              <template v-else-if="item.icon === 'users'">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </template>
              <template v-else-if="item.icon === 'settings'">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
              </template>
              <template v-else-if="item.icon === 'briefcase'">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
              </template>
              <template v-else-if="item.icon === 'credit-card'">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
              </template>
              <template v-else-if="item.icon === 'shield'">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </template>
              <template v-else-if="item.icon === 'help-circle'">
                <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
              </template>
              <template v-else-if="item.icon === 'monitor'">
                <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
              </template>
            </svg>
            <span class="nav-label">{{ item.label }}</span>
          </RouterLink>
        </template>
      </nav>

      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar">{{ auth.user?.name?.charAt(0).toUpperCase() }}</div>
          <div class="user-info">
            <div class="user-name truncate">{{ auth.user?.name }}</div>
            <div class="flex items-center gap-2">
              <div class="user-plan">{{ auth.user?.plan_status }}</div>
              <div v-if="auth.user?.plan_status === 'trial'" class="trial-days-badge">
                {{ auth.user?.days_left }} {{ auth.user?.days_left === 1 ? 'día' : 'días' }}
              </div>
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-icon" @click="logout" title="Cerrar sesión">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </button>
      </div>
    </aside>

    <!-- Main -->
    <main class="main-content">
      <RouterView v-slot="{ Component }">
        <Transition name="fade" mode="out-in">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>
  </div>
</template>

<style scoped>
.app-shell {
  display: flex;
  min-height: 100vh;
}

/* SIDEBAR */
.sidebar {
  width: 240px;
  background: var(--bg-surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
  transition: width 0.25s ease, transform 0.25s ease;
  overflow: hidden;
}

.app-shell.collapsed .sidebar { width: 64px; }
.app-shell.collapsed .logo-text,
.app-shell.collapsed .nav-label,
.app-shell.collapsed .user-info { display: none; }

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem 1rem;
  border-bottom: 1px solid var(--border);
  background: #13124b; /* Azul marino corporativo */
  color: #fff;
}
.logo-mark {
  width: 38px; height: 38px;
  background: var(--primary);
  border-radius: 0px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  color: #fff;
}
.logo-text { font-weight: 800; font-size: 1rem; flex: 1; color: #fff; }
.sidebar-logo .sidebar-toggle {
  color: #9ca3af;
  margin-left: auto;
  flex-shrink: 0;
}
.sidebar-logo .sidebar-toggle:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
}

/* NAV */
.sidebar-nav { flex: 1; padding: 1rem 0.5rem; display: flex; flex-direction: column; gap: 2px; overflow-y: auto; }

.nav-item {
  display: flex; align-items: center; gap: 0.75rem;
  padding: 0.65rem 0.85rem;
  border-radius: var(--radius-md);
  color: var(--text-muted);
  transition: all var(--transition);
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
  border: 1px solid transparent;
}
.nav-item:hover {
  background: var(--bg-elevated);
  color: var(--text-primary);
  border-color: var(--border);
}
.nav-item.active {
  background: var(--bg-elevated);
  color: var(--text-primary);
  border: 1px solid var(--border);
}
.nav-icon { flex-shrink: 0; }

/* FOOTER */
.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid var(--border);
  display: flex; align-items: center; gap: 0.5rem;
}
.user-chip { flex: 1; display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.user-avatar {
  width: 34px; height: 34px; flex-shrink: 0;
  background: var(--primary);
  border-radius: 0px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem; color: #fff;
}
.user-info { min-width: 0; }
.user-name { font-size: 0.82rem; font-weight: 600; }
.user-plan { font-size: 0.7rem; color: var(--accent); text-transform: capitalize; }
.trial-days-badge {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  font-size: 0.65rem;
  font-weight: 800;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  white-space: nowrap;
  border: 1px solid rgba(245, 158, 11, 0.2);
}
.flex { display: flex; }
.items-center { align-items: center; }
.gap-2 { gap: 0.5rem; }

/* MAIN */
.main-content {
  flex: 1;
  margin-left: 240px;
  transition: margin-left 0.25s ease;
  padding: 2rem;
  min-height: 100vh;
  max-width: 100%;
}
.app-shell.collapsed .main-content { margin-left: 64px; }

/* MOBILE HEADER */
.mobile-header {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 60px;
  background: #13124b; /* Azul marino corporativo */
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
  padding: 0 1rem;
  z-index: 90;
  gap: 1rem;
  color: #fff;
}
.mobile-header .btn-ghost {
  color: rgba(255, 255, 255, 0.85);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 6px;
  width: 32px;
  height: 32px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}
.mobile-header .btn-ghost:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.25);
}
.mobile-header .btn-ghost svg {
  width: 18px;
  height: 18px;
}
.mobile-logo {
  flex: 1;
  font-weight: 800;
  font-size: 1.1rem;
  background: linear-gradient(135deg, #fff, var(--accent));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}
.user-avatar-sm {
  width: 32px; height: 32px;
  background: var(--primary);
  border-radius: 0px;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.8rem; color: #fff;
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  z-index: 95;
  animation: fadeIn 0.2s ease;
}

@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    width: 280px !important;
    z-index: 101;
    box-shadow: 20px 0 50px rgba(0,0,0,0.5);
  }
  .sidebar.sidebar-visible {
    transform: translateX(0);
  }
  .main-content {
    margin-left: 0 !important;
    padding: 5rem 1rem 2rem;
  }
  .app-shell.collapsed .sidebar { transform: translateX(-100%); }
  .app-shell.collapsed .main-content { margin-left: 0; }
}

/* Colapsado: Redimensionado de Logo y Footer a rectángulos pequeños y limpios */
.app-shell.collapsed .sidebar-logo {
  padding: 0.5rem;
  height: 50px; /* Rectángulo súper bajo y estilizado */
  gap: 0.25rem;
  justify-content: center;
}
.app-shell.collapsed .sidebar-logo .logo-area {
  width: 24px;
}
.app-shell.collapsed .sidebar-logo .sidebar-toggle {
  width: 24px;
  height: 24px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: 0;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 4px;
  color: rgba(255, 255, 255, 0.8);
}
.app-shell.collapsed .sidebar-logo .sidebar-toggle svg {
  width: 12px;
  height: 12px;
}
.app-shell.collapsed .sidebar-footer {
  flex-direction: column;
  padding: 0.75rem 0.25rem;
  gap: 0.75rem;
  justify-content: center;
  align-items: center;
}
.app-shell.collapsed .sidebar-footer .user-chip {
  justify-content: center;
}
.app-shell.collapsed .sidebar-footer .btn {
  width: 32px;
  height: 32px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(255, 255, 255, 0.1);
}
</style>
