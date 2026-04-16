<script setup lang="ts">
import { ref, onMounted, computed, reactive } from 'vue'
import { customersApi } from '@/api/services'

const customers = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const showModal = ref(false)
const editing = ref<any | null>(null)
const saving = ref(false)
const deleting = ref<number | null>(null)
const confirmDelete = ref<any | null>(null)
const toast = ref<{ msg: string; type: string } | null>(null)

const form = reactive({ name: '', cuit: '', email: '', phone: '', address: '' })

onMounted(load)

async function load() {
  loading.value = true
  try {
    const res = await customersApi.list()
    customers.value = res.data.customers
  } finally {
    loading.value = false
  }
}

const filtered = computed(() =>
  customers.value.filter(c => {
    const q = search.value.toLowerCase()
    return !q || c.name.toLowerCase().includes(q) || c.cuit?.includes(q) || c.email?.toLowerCase().includes(q)
  })
)

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', cuit: '', email: '', phone: '', address: '' })
  showModal.value = true
}
function openEdit(c: any) {
  editing.value = c
  Object.assign(form, { name: c.name, cuit: c.cuit ?? '', email: c.email ?? '', phone: c.phone ?? '', address: c.address ?? '' })
  showModal.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = { ...form }
    if (editing.value) {
      await customersApi.update(editing.value.id, payload)
      showToast('Cliente actualizado', 'success')
    } else {
      await customersApi.create(payload)
      showToast('Cliente creado', 'success')
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Error al guardar', 'error')
  } finally {
    saving.value = false
  }
}

async function doDelete(id: number) {
  deleting.value = id
  try {
    await customersApi.delete(id)
    customers.value = customers.value.filter(c => c.id !== id)
    confirmDelete.value = null
    showToast('Cliente eliminado', 'success')
  } catch {
    showToast('Error al eliminar', 'error')
  } finally {
    deleting.value = null
  }
}

function showToast(msg: string, type: string) {
  toast.value = { msg, type }
  setTimeout(() => (toast.value = null), 3000)
}

function initials(name: string) {
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Clientes</h1>
        <p class="page-subtitle">Gestioná tu cartera de clientes</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo Cliente
      </button>
    </div>

    <div class="search-wrap" style="max-width:340px;margin-bottom:1.25rem;">
      <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input v-model="search" class="form-input" placeholder="Buscar por nombre, CUIT o email…" />
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:3rem;">
      <span class="spinner" style="width:28px;height:28px;color:var(--primary);"></span>
    </div>

    <div v-else-if="filtered.length === 0" class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
      <p>No hay clientes todavía</p>
      <button class="btn btn-primary btn-sm" @click="openCreate">Agregar primer cliente</button>
    </div>

    <div v-else class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>CUIT</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Dirección</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in filtered" :key="c.id">
            <td>
              <div class="customer-name-cell">
                <div class="customer-avatar">{{ initials(c.name) }}</div>
                <span class="font-semibold">{{ c.name }}</span>
              </div>
            </td>
            <td class="mono text-sm">{{ c.cuit || '—' }}</td>
            <td class="text-sm text-muted">{{ c.email || '—' }}</td>
            <td class="text-sm text-muted">{{ c.phone || '—' }}</td>
            <td class="text-sm text-muted truncate" style="max-width:180px;">{{ c.address || '—' }}</td>
            <td>
              <div class="flex gap-2">
                <button class="btn btn-ghost btn-sm btn-icon" @click="openEdit(c)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="btn btn-danger btn-sm btn-icon" @click="confirmDelete = c">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <Transition name="fade">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <h3 class="modal-title">{{ editing ? 'Editar Cliente' : 'Nuevo Cliente' }}</h3>
            <button class="btn btn-ghost btn-icon" @click="showModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form @submit.prevent="save" class="flex flex-col gap-4">
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Nombre / Razón Social *</label>
                <input v-model="form.name" class="form-input" placeholder="Empresa S.A." required />
              </div>
              <div class="form-group">
                <label class="form-label">CUIT *</label>
                <input v-model="form.cuit" class="form-input" placeholder="20-12345678-9" required />
              </div>
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Email</label>
                <input v-model="form.email" class="form-input" type="email" placeholder="contacto@empresa.com" />
              </div>
              <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input v-model="form.phone" class="form-input" placeholder="+54 11 1234-5678" />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Dirección</label>
              <input v-model="form.address" class="form-input" placeholder="Av. Corrientes 1234, CABA" />
            </div>
            <div class="flex gap-3" style="justify-content:flex-end;margin-top:0.5rem;">
              <button type="button" class="btn btn-ghost" @click="showModal = false">Cancelar</button>
              <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner"></span>
                <span v-else>{{ editing ? 'Actualizar' : 'Crear' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- Confirm Delete Modal -->
    <Transition name="fade">
      <div v-if="confirmDelete" class="modal-overlay" @click.self="confirmDelete = null">
        <div class="modal-box" style="max-width:400px;">
          <div class="modal-header">
            <h3 class="modal-title">Eliminar Cliente</h3>
            <button class="btn btn-ghost btn-icon" @click="confirmDelete = null">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:1.5rem;">
            ¿Eliminás a <strong style="color:var(--text-primary);">{{ confirmDelete.name }}</strong>? Esta acción no se puede deshacer.
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

    <!-- Toast -->
    <div class="toast">
      <Transition name="fade">
        <div v-if="toast" class="toast-item" :class="toast.type">{{ toast.msg }}</div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.mono { font-family: 'Courier New', monospace; }
.customer-name-cell { display: flex; align-items: center; gap: 0.75rem; }
.customer-avatar {
  width: 34px; height: 34px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--primary), var(--accent));
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; font-weight: 700; color: #fff;
}
</style>
