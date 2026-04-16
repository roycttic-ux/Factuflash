<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute, RouterView, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const sidebarOpen = ref(true)

const navItems = [
  { to: '/dashboard',   label: 'Dashboard',   icon: 'grid' },
  { to: '/facturas',    label: 'Facturas',     icon: 'file-text' },
  { to: '/productos',   label: 'Productos',    icon: 'package' },
  { to: '/clientes',    label: 'Clientes',     icon: 'users' },
  { label: 'Catálogo Público', icon: 'external-link', isExternal: true },
  { to: '/configuracion', label: 'Configuración', icon: 'settings' },
]

function isActive(path: string) {
  return route.path.startsWith(path)
}

async function logout() {
  await auth.logout()
  router.push('/login')
}

function openCatalog() {
  const link = window.location.origin + window.location.pathname + '#/catalogo/' + auth.user?.id
  window.open(link, '_blank')
}
</script>

<template>
  <div class="app-shell" :class="{ collapsed: !sidebarOpen }">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-mark">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
            <polyline points="10 9 9 9 8 9"/>
          </svg>
        </div>
        <span class="logo-text">FacturaYa</span>
        <button class="sidebar-toggle btn btn-icon btn-ghost" @click="sidebarOpen = !sidebarOpen">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
      </div>

      <nav class="sidebar-nav">
        <template v-for="item in navItems" :key="item.label">
          <a
            v-if="item.isExternal"
            href="javascript:void(0)"
            class="nav-item"
            @click="openCatalog"
          >
             <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
             </svg>
             <span class="nav-label">{{ item.label }}</span>
          </a>
          <RouterLink
            v-else
            :to="item.to!"
            class="nav-item"
            :class="{ active: isActive(item.to!) }"
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
            <div class="user-plan">{{ auth.user?.plan_status }}</div>
          </div>
        </div>
        <button class="btn btn-ghost btn-icon" @click="logout" title="Cerrar sesión">
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
}
.logo-mark {
  width: 38px; height: 38px;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  color: #fff;
  box-shadow: 0 4px 12px var(--primary-glow);
}
.logo-text { font-weight: 800; font-size: 1rem; flex: 1; background: linear-gradient(135deg, #fff, var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.sidebar-toggle { margin-left: auto; flex-shrink: 0; }

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
}
.nav-item:hover { background: var(--bg-elevated); color: var(--text-primary); }
.nav-item.active {
  background: rgba(99,102,241,0.15);
  color: var(--primary);
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
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem; color: #fff;
}
.user-info { min-width: 0; }
.user-name { font-size: 0.82rem; font-weight: 600; }
.user-plan { font-size: 0.7rem; color: var(--accent); text-transform: capitalize; }

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

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); width: 240px; }
  .app-shell.collapsed .sidebar { transform: translateX(0); }
  .main-content { margin-left: 0 !important; }
}
</style>
