<script setup lang="ts">
import { ref, onMounted, computed, reactive } from 'vue'
import { productsApi } from '@/api/services'

const products = ref<any[]>([])
const loading = ref(true)
const search = ref('')
const toast = ref<{ msg: string; type: string } | null>(null)

// Modal state
const showModal = ref(false)
const editing = ref<any | null>(null)
const saving = ref(false)
const deleting = ref<number | null>(null)
const confirmDelete = ref<any | null>(null)

const form = reactive({ name: '', description: '', cost_usd: '', stock: '', min_stock_alert: '' })
const imageFile = ref<File | null>(null)
const imagePreview = ref('')

onMounted(load)

async function load() {
  loading.value = true
  try {
    const res = await productsApi.list()
    products.value = res.data.products
  } finally {
    loading.value = false
  }
}

const filtered = computed(() =>
  products.value.filter(p =>
    !search.value || p.name.toLowerCase().includes(search.value.toLowerCase())
  )
)

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', description: '', cost_usd: '', stock: '', min_stock_alert: '' })
  imageFile.value = null
  imagePreview.value = ''
  showModal.value = true
}
function openEdit(p: any) {
  editing.value = p
  Object.assign(form, { name: p.name, description: p.description ?? '', cost_usd: p.cost_usd, stock: p.stock, min_stock_alert: p.min_stock_alert })
  imageFile.value = null
  imagePreview.value = p.image_url ? '/sistemafacturacion/api/public/storage/' + p.image_url : ''
  showModal.value = true
}

function handleImage(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.[0]) {
    imageFile.value = input.files[0]
    imagePreview.value = URL.createObjectURL(imageFile.value)
  }
}

async function save() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('name', form.name)
    fd.append('description', form.description)
    fd.append('cost_usd', String(form.cost_usd))
    fd.append('stock', String(form.stock))
    fd.append('min_stock_alert', String(form.min_stock_alert))
    if (imageFile.value) {
      fd.append('image', imageFile.value)
    }

    if (editing.value) {
      await productsApi.update(editing.value.id, fd)
      showToast('Producto actualizado', 'success')
    } else {
      await productsApi.create(fd)
      showToast('Producto creado', 'success')
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
    await productsApi.delete(id)
    products.value = products.value.filter(p => p.id !== id)
    confirmDelete.value = null
    showToast('Producto eliminado', 'success')
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

function stockStatus(p: any) {
  if (p.stock <= 0) return 'badge-danger'
  if (p.stock <= p.min_stock_alert) return 'badge-warning'
  return 'badge-success'
}
function stockLabel(p: any) {
  if (p.stock <= 0) return 'Sin stock'
  if (p.stock <= p.min_stock_alert) return 'Stock bajo'
  return 'En stock'
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Productos</h1>
        <p class="page-subtitle">Administrá tu catálogo de productos</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nuevo Producto
      </button>
    </div>

    <div class="search-wrap" style="max-width:320px;margin-bottom:1.25rem;">
      <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input v-model="search" class="form-input" placeholder="Buscar producto…" />
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:3rem;">
      <span class="spinner" style="width:28px;height:28px;color:var(--primary);"></span>
    </div>

    <div v-else-if="filtered.length === 0" class="empty-state">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
      </svg>
      <p>No hay productos todavía</p>
      <button class="btn btn-primary btn-sm" @click="openCreate">Agregar primer producto</button>
    </div>

    <div v-else class="products-grid">
      <div v-for="p in filtered" :key="p.id" class="product-card card">
        <div class="product-image-box" v-if="p.image_url">
           <img :src="'/sistemafacturacion/api/public/storage/' + p.image_url" class="product-list-img" />
        </div>
        <div class="product-image-placeholder" v-else>
           <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
        </div>
        <div class="product-head">
          <div class="flex-1 min-w-0">
            <div class="product-name truncate">{{ p.name }}</div>
            <div class="product-desc text-muted text-sm truncate">{{ p.description || '—' }}</div>
          </div>
          <div class="flex gap-1">
            <button class="btn btn-ghost btn-sm btn-icon" @click="openEdit(p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn btn-danger btn-sm btn-icon" @click="confirmDelete = p">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </div>
        <div class="product-stats">
          <div class="pstat">
            <div class="pstat-label">Precio USD</div>
            <div class="pstat-val">U$D {{ Number(p.cost_usd).toFixed(2) }}</div>
          </div>
          <div class="pstat">
            <div class="pstat-label">Stock</div>
            <div class="pstat-val">{{ p.stock }} <span class="text-muted text-xs">/ alerta {{ p.min_stock_alert }}</span></div>
          </div>
        </div>
        <div style="margin-top:0.75rem;">
          <span class="badge" :class="stockStatus(p)">{{ stockLabel(p) }}</span>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Transition name="fade">
      <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
        <div class="modal-box">
          <div class="modal-header">
            <h3 class="modal-title">{{ editing ? 'Editar Producto' : 'Nuevo Producto' }}</h3>
            <button class="btn btn-ghost btn-icon" @click="showModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <form @submit.prevent="save" class="flex flex-col gap-4">
            <div class="form-group">
              <label class="form-label">Nombre *</label>
              <input v-model="form.name" class="form-input" placeholder="Nombre del producto" required />
            </div>
            <div class="form-group">
              <label class="form-label">Descripción</label>
              <input v-model="form.description" class="form-input" placeholder="Descripción opcional" />
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Precio USD *</label>
                <input v-model="form.cost_usd" class="form-input" type="number" step="0.01" min="0" placeholder="0.00" required />
              </div>
              <div class="form-group">
                <label class="form-label">Stock *</label>
                <input v-model="form.stock" class="form-input" type="number" min="0" placeholder="0" required />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Alerta stock mínimo</label>
              <input v-model="form.min_stock_alert" class="form-input" type="number" min="0" placeholder="5" />
            </div>
            <div class="form-group">
              <label class="form-label">Imagen del producto (opcional)</label>
              <div class="image-upload-zone">
                 <div v-if="imagePreview" class="image-preview-mini">
                    <img :src="imagePreview" />
                 </div>
                 <input type="file" @change="handleImage" accept="image/*" class="form-input text-xs" />
              </div>
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
            <h3 class="modal-title">Eliminar Producto</h3>
            <button class="btn btn-ghost btn-icon" @click="confirmDelete = null">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:1.5rem;">
            ¿Eliminás <strong style="color:var(--text-primary);">{{ confirmDelete.name }}</strong>? Esta acción no se puede deshacer.
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
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.25rem;
}
.product-card { transition: transform 0.2s, box-shadow 0.2s; }
.product-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.product-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.product-name { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.2rem; }
.product-stats { display: flex; gap: 1.5rem; }
.pstat-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.2rem; }
.pstat-val { font-size: 0.9rem; font-weight: 600; }

.product-image-box { width: 100%; height: 140px; border-radius: var(--radius-md); overflow: hidden; margin-bottom: 1rem; border: 1px solid var(--border); background: #f8fafc; }
.product-list-img { width: 100%; height: 100%; object-fit: contain; }
.product-image-placeholder { width: 100%; height: 140px; border-radius: var(--radius-md); background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: var(--text-faint); margin-bottom: 1rem; border: 1px dashed var(--border); }

.image-upload-zone { display: flex; align-items: center; gap: 1rem; }
.image-preview-mini { width: 42px; height: 42px; border-radius: 6px; overflow: hidden; border: 1px solid var(--border); flex-shrink: 0; }
.image-preview-mini img { width: 100%; height: 100%; object-fit: cover; }
</style>
