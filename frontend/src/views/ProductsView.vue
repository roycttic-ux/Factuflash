<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, reactive } from 'vue'
import { productsApi } from '@/api/services'
import { QuillEditor } from '@vueup/vue-quill'
import '@vueup/vue-quill/dist/vue-quill.snow.css'

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
const expandedId = ref<number | null>(null)

const form = reactive({ 
  sku: '', name: '', description: '', cost_usd: '', stock: '', 
  unlimited_stock: false, min_stock_alert: '5', category: '',
  tags: '', 
  variants: [] as { name: string, value: string, stock: number, image_url?: string }[] 
})
const imageFile = ref<File | null>(null)
const imagePreview = ref('')
const extraPreviews = ref<{ file?: File; url: string; existingPath?: string }[]>([])
const existingTags = ref<string[]>([])
const newTagInput = ref('')
const showTagDropdown = ref(false)

const showBulkModal = ref(false)
const bulkForm = reactive({ percentage: '', category: 'Todas' })
const applyingBulk = ref(false)

const indecDate = ref('Cargando...')
const indecRate = ref<number | null>(null)
const indecError = ref(false)

onMounted(() => {
  load()
  loadTags()
  window.addEventListener('keydown', handleGlobalScanner)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalScanner)
})

let barcodeBuffer = ''
let barcodeTimeout: number | undefined

function handleGlobalScanner(e: KeyboardEvent) {
  // Ignorar si el usuario está tipeando en un input o dentro de un modal
  if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) {
    if (e.target.placeholder !== 'Buscar producto…') return
  }

  // Si presiona Enter y hay algo en el buffer, es un escaneo completado
  if (e.key === 'Enter') {
    if (barcodeBuffer.length > 2) {
      search.value = barcodeBuffer // Aplicar el código al buscador
      barcodeBuffer = ''
      e.preventDefault()
    }
    return
  }

  // Solo atrapar caracteres normales (ni Shift, ni Ctrl, etc)
  if (e.key.length === 1) {
    barcodeBuffer += e.key
    clearTimeout(barcodeTimeout)
    // Los escáneres escriben muy rápido. 150ms es suficiente para limpiar tipeos humanos lentos.
    barcodeTimeout = window.setTimeout(() => { barcodeBuffer = '' }, 150)
  }
}

async function load() {
  loading.value = true
  try {
    const pRes = await productsApi.list()
    products.value = pRes.data.products
  } finally {
    loading.value = false
  }
}

async function loadTags() {
  try {
    const res = await productsApi.getTags()
    existingTags.value = res.data.tags
  } catch {}
}



const filtered = computed(() => {
  if (!search.value) return products.value;
  const q = search.value.toLowerCase();
  return products.value.filter(p => 
    p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q))
  );
})

const categories = computed(() => {
  const allTags = products.value
    .flatMap(p => p.tags ? p.tags.split(',').map((t: string) => t.trim()) : [])
    .filter(t => !!t)
  return ['Todas', ...new Set(allTags)]
})

async function fetchIndecData() {
  try {
    const res = await fetch('https://api.argentinadatos.com/v1/finanzas/indices/inflacion')
    const data = await res.json()
    if (data && data.length > 0) {
      const ultimo = data[data.length - 1]
      indecRate.value = ultimo.valor
      
      // Parsear la fecha con cuidado del timezone
      const [y, m, d] = ultimo.fecha.split('-')
      const dateObj = new Date(y, m - 1, d)
      const mes = dateObj.toLocaleString('es-AR', { month: 'long' })
      const anio = dateObj.getFullYear()
      indecDate.value = `${mes.charAt(0).toUpperCase() + mes.slice(1)} ${anio}`

      if (!bulkForm.percentage) bulkForm.percentage = String(ultimo.valor)
    }
  } catch(e) {
    indecError.value = true
    indecDate.value = 'Sin conexión'
    if (!bulkForm.percentage) bulkForm.percentage = '5.1'
  }
}

async function openBulkAdjust() {
  bulkForm.percentage = ''
  bulkForm.category = 'Todas'
  showBulkModal.value = true
  
  if (indecRate.value === null && !indecError.value) {
    await fetchIndecData()
  } else if (indecRate.value && !bulkForm.percentage) {
    bulkForm.percentage = String(indecRate.value)
  }
}

async function applyBulk() {
  if (!bulkForm.percentage || Number(bulkForm.percentage) <= 0) return
  if (!confirm(`¿Estás seguro que querés aumentar los precios de [${bulkForm.category}] un ${bulkForm.percentage}%?`)) return
  
  applyingBulk.value = true
  try {
    await productsApi.applyInflation(Number(bulkForm.percentage), bulkForm.category)
    showToast('Precios actualizados con éxito', 'success')
    showBulkModal.value = false
    await load()
  } catch(e: any) {
    showToast(e?.response?.data?.message ?? 'Error al ajustar precios', 'error')
  } finally {
    applyingBulk.value = false
  }
}

function toggleTag(tag: string) {
  const currentTags = form.tags.split(',').map(t => t.trim()).filter(t => !!t)
  const index = currentTags.indexOf(tag)
  if (index === -1) {
    currentTags.push(tag)
  } else {
    currentTags.splice(index, 1)
  }
  form.tags = currentTags.join(', ')
}

function addNewTag() {
  if (!newTagInput.value.trim()) return
  const tag = newTagInput.value.trim()
  const currentTags = form.tags.split(',').map(t => t.trim()).filter(t => !!t)
  if (!currentTags.includes(tag)) {
    currentTags.push(tag)
    form.tags = currentTags.join(', ')
    if (!existingTags.value.includes(tag)) {
      existingTags.value.push(tag)
    }
  }
  newTagInput.value = ''
}

function openCreate() {
  editing.value = null
  Object.assign(form, { 
    sku: '', name: '', description: '', cost_usd: '', stock: '', 
    unlimited_stock: false, min_stock_alert: '5', category: '',
    tags: '', variants: []
  })
  newTagInput.value = ''
  showTagDropdown.value = false
  imageFile.value = null
  imagePreview.value = ''
  extraPreviews.value = []
  showModal.value = true
}
function openEdit(p: any) {
  editing.value = p
  Object.assign(form, {
    sku: p.sku || '',
    name: p.name,
    description: p.description ?? '',
    cost_usd: p.cost_usd,
    stock: p.stock,
    unlimited_stock: !!p.unlimited_stock,
    min_stock_alert: p.min_stock_alert,
    category: p.category || '',
    tags: p.tags || '',
    variants: Array.isArray(p.variants) ? JSON.parse(JSON.stringify(p.variants)) : []
  })
  showTagDropdown.value = false
  imageFile.value = null
  imagePreview.value = p.image_url ? '/api/storage/' + p.image_url : ''
  extraPreviews.value = p.images ? p.images.map((img: string) => ({ url: '/api/storage/' + img, existingPath: img })) : []
  showModal.value = true
}

function addVariant() {
  form.variants.push({ name: '', value: '', stock: 0 })
}

function removeVariant(index: number) {
  form.variants.splice(index, 1)
}


function handleImage(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.[0]) {
    const file = input.files[0]
    if (file.size > 2 * 1024 * 1024) return showToast('La imagen es muy pesada (max 2MB)', 'error')
    imageFile.value = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

function handleExtraImages(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files) {
    const files = Array.from(input.files)
    if (files.length + extraPreviews.value.length > 10) {
      return showToast('Máximo 10 fotos en total', 'error')
    }
    
    for (const file of files) {
      if (file.size > 2 * 1024 * 1024) {
        showToast(`La foto ${file.name} es muy pesada`, 'error')
        continue
      }
      extraPreviews.value.push({
        file: file,
        url: URL.createObjectURL(file)
      })
    }
  }
}

function removeExtra(index: number) {
  extraPreviews.value.splice(index, 1)
}


async function save() {
  saving.value = true
  try {
    const fd = new FormData()
    fd.append('sku', form.sku)
    fd.append('name', form.name)
    fd.append('description', form.description)
    fd.append('cost_usd', String(form.cost_usd))
    fd.append('stock', String(form.stock))
    fd.append('unlimited_stock', form.unlimited_stock ? '1' : '0')
    fd.append('min_stock_alert', String(form.min_stock_alert))
    fd.append('category', form.category)
    fd.append('tags', form.tags)
    fd.append('variants', JSON.stringify(form.variants))

    // Manejo de imágenes existentes a mantener
    const existingToKeep = extraPreviews.value
      .filter(p => p.existingPath)
      .map(p => p.existingPath)
    fd.append('existing_images', JSON.stringify(existingToKeep))

    if (imageFile.value) {
      fd.append('image', imageFile.value)
    }

    // Nuevas imágenes a subir
    extraPreviews.value.forEach(p => {
      if (p.file) {
        fd.append('extra_images[]', p.file)
      }
    })

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

</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Productos</h1>
        <p class="page-subtitle">Administrá tu catálogo de productos</p>
      </div>
      <div style="display:flex; gap:0.5rem;">
        <button class="btn btn-white" style="border:1px solid var(--border);background:#fff;" @click="openBulkAdjust" title="Aumento masivo por inflación o categoría">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
          Ajuste Masivo
        </button>
        <button class="btn btn-primary" @click="openCreate">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Nuevo
        </button>
      </div>
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

    <div v-else class="products-list" style="display:flex; flex-direction:column; gap:1rem;">
      <div v-for="p in filtered" :key="p.id" class="product-card card" style="cursor: pointer;" @click="expandedId = expandedId === p.id ? null : p.id">
        <div class="product-head-compact" style="display:flex; justify-content:space-between; align-items:center;">
          <div class="product-name" style="margin:0;">{{ p.name }}</div>
          <div style="display:flex; gap:1rem; align-items:center;">
            <span style="font-weight:700; color:var(--primary);">${{ new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 }).format(p.cost_usd) }}</span>
            <span class="badge" :class="stockStatus(p)">{{ p.unlimited_stock ? '∞' : p.stock }}</span>
          </div>
        </div>

        <div v-if="expandedId === p.id || (search && filtered.length === 1)" class="product-details" style="margin-top:1rem; border-top:1px solid var(--border); padding-top:1rem; display:flex; gap:1.25rem;" @click.stop>
          <div v-if="p.image_url" style="width:100px; height:100px; border-radius:var(--radius-sm); border:1px solid var(--border); overflow:hidden; flex-shrink:0; background:#fff;">
             <img :src="'/api/storage/' + p.image_url" style="width:100%; height:100%; object-fit:contain;" />
          </div>
          <div style="flex:1;">
            <div class="p-category" v-if="p.tags">
              <span v-for="tag in p.tags.split(',')" :key="tag" class="cat-pill" style="margin-right:0.25rem;">{{ tag.trim() }}</span>
            </div>
            <div class="text-sm text-muted" style="margin-bottom:0.5rem; line-height:1.5; white-space: pre-wrap;" v-html="p.description || 'Sin descripción disponible.'"></div>
            <p class="text-xs text-muted" style="margin-bottom:1rem;">SKU: {{ p.sku || 'N/A' }}</p>
            
            <div class="flex gap-2">
              <button class="btn btn-ghost btn-sm" @click="openEdit(p)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar
              </button>
              <button class="btn btn-danger btn-sm" @click="confirmDelete = p">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                Eliminar
              </button>
            </div>
          </div>
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
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Código (SKU/Barcode)</label>
                <input v-model="form.sku" class="form-input" placeholder="Opcional" />
              </div>
              <div class="form-group">
                <label class="form-label">Nombre *</label>
                <input v-model="form.name" class="form-input" placeholder="Nombre del producto" required />
              </div>
            </div>
              <div class="form-group">
                <label class="form-label">Descripción</label>
                <div class="quill-dark-wrapper">
                  <QuillEditor v-model:content="form.description" contentType="html" toolbar="minimal" theme="snow" placeholder="Descripción con formato opcional" />
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Etiquetas</label>
                <div class="tag-selector-container">
                  <div class="tag-selector-trigger" @click="showTagDropdown = !showTagDropdown">
                    <span v-if="!form.tags" class="text-muted">Seleccionar etiquetas...</span>
                    <div v-else class="flex flex-wrap gap-1">
                      <span v-for="t in form.tags.split(',')" :key="t" class="tag-badge-mini">{{ t.trim() }}</span>
                    </div>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" :style="{ transform: showTagDropdown ? 'rotate(180deg)' : '' }"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>

                  <div v-if="showTagDropdown" class="tag-dropdown-menu">
                    <div class="tag-list-scroll">
                      <div v-for="tag in existingTags" :key="tag" class="tag-option" @click="toggleTag(tag)">
                        <input type="checkbox" :checked="form.tags.includes(tag)" @click.stop="toggleTag(tag)" />
                        <span>{{ tag }}</span>
                      </div>
                      <div v-if="!existingTags.length" class="tag-option text-muted">No hay etiquetas</div>
                    </div>
                    <div class="tag-dropdown-footer">
                      <div class="flex gap-2">
                        <input v-model="newTagInput" class="form-input text-xs" placeholder="Nueva etiqueta..." @keydown.enter.prevent="addNewTag" @click.stop />
                        <button type="button" class="btn btn-xs btn-primary" @click.stop="addNewTag">Crear</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Precio ARS *</label>
                <input v-model="form.cost_usd" class="form-input" type="number" step="1" min="0" placeholder="0" required />
              </div>
              <div class="form-group">
                <label class="form-label">Stock *</label>
                <div class="flex items-center gap-3">
                  <input v-model.number="form.stock" class="form-input" type="number" min="0" :disabled="form.unlimited_stock" required />
                  <label class="flex items-center gap-1 text-xs cursor-pointer">
                    <input type="checkbox" v-model="form.unlimited_stock" /> Servicio (Stock Ilimitado)
                  </label>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Alerta stock mínimo</label>
              <input v-model="form.min_stock_alert" class="form-input" type="number" min="0" placeholder="5" />
            </div>
            <!-- Variantes -->
            <div class="form-group">
               <div class="flex justify-between items-center mb-2">
                 <label class="form-label mb-0">Variantes / Atributos (Talla, Color...)</label>
                 <button type="button" class="btn btn-xs btn-ghost" @click="addVariant">+ Agregar</button>
               </div>
                <div v-for="(variant, index) in form.variants" :key="index" class="variant-edit-row card p-3 mb-3">
                   <div class="grid-3 gap-2 items-end">
                      <div>
                        <label class="text-xs font-bold mb-1 block">Atributo</label>
                        <div v-if="variant.name === 'Color' || variant.name === 'Talle' || !variant.name">
                          <select :value="variant.name || ''" class="form-select text-xs" @change="(e) => { 
                            const val = (e.target as HTMLSelectElement).value;
                            if(val === 'Otro') variant.name = ' ';
                            else variant.name = val;
                          }">
                            <option value="">— Elegir —</option>
                            <option value="Color">🎨 Color</option>
                            <option value="Talle">📏 Talle</option>
                            <option value="Otro">✍️ Otro...</option>
                          </select>
                        </div>
                        <div v-else class="relative flex items-center gap-1">
                          <input v-model="variant.name" class="form-input text-xs" placeholder="Ej: Material..." @focus="() => { if(variant.name === ' ') variant.name = '' }" />
                          <button type="button" class="btn btn-xs btn-ghost" @click="variant.name = ''">↺</button>
                        </div>
                      </div>

                      <div>
                        <label class="text-xs font-bold mb-1 block">Valor</label>
                        <div v-if="variant.name.toLowerCase().includes('color')" class="flex items-center gap-2">
                          <input type="color" v-model="variant.value" class="color-picker-large" title="Elegir color" />
                          <input type="text" v-model="variant.value" class="form-input text-xs" placeholder="Nombre o Hex (#FF0000)" style="flex: 1;" />
                        </div>
                        <div v-else class="relative flex items-center">
                          <input v-model="variant.value" class="form-input text-xs" placeholder="Ej: XL, 42, Algodón..." />
                        </div>
                      </div>

                      <div>
                        <label class="text-xs font-bold mb-1 block">Stock</label>
                        <input v-model.number="variant.stock" type="number" class="form-input text-xs" placeholder="0" />
                      </div>
                   </div>

                   <div class="mt-3 flex items-center justify-between border-t pt-3 border-dashed">
                      <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-muted">Vincular Foto:</span>
                        <div class="flex gap-2">
                          <!-- Opción Principal -->
                          <div class="var-img-opt" :class="{ active: variant.image_url === (editing?.image_url || '') }" @click="variant.image_url = (editing?.image_url || '')" title="Foto principal">
                            <img v-if="imagePreview" :src="imagePreview" class="w-8 h-8 rounded object-cover" />
                            <div v-else class="w-8 h-8 bg-muted rounded"></div>
                          </div>
                          <!-- Opciones Extras -->
                          <div v-for="(img, idx) in extraPreviews" :key="idx" 
                               class="var-img-opt" 
                               :class="{ active: variant.image_url === img.existingPath }" 
                               @click="variant.image_url = img.existingPath"
                               title="Foto secundaria">
                            <img :src="img.url" class="w-8 h-8 rounded object-cover" />
                          </div>
                        </div>
                        <button v-if="variant.image_url" type="button" class="btn btn-xs btn-ghost text-danger" @click="variant.image_url = undefined">Quitar foto</button>
                      </div>
                      <button type="button" class="btn btn-xs btn-danger btn-icon" @click="removeVariant(index)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                      </button>
                   </div>
                </div>
            </div>

            <!-- Imágenes -->
            <div class="form-group">
              <label class="form-label">Imágenes (Principal + hasta 4 extras)</label>
              <div class="image-upload-grid">
                <!-- Principal -->
                <div class="img-box-wrap">
                  <div class="image-preview-box" :class="{ empty: !imagePreview }">
                     <img v-if="imagePreview" :src="imagePreview" />
                     <span v-else>Principal</span>
                     <input type="file" @change="handleImage" accept="image/*" class="abs-input" />
                  </div>
                </div>
                <!-- Extras -->
                <div v-for="(prev, i) in extraPreviews" :key="i" class="img-box-wrap">
                  <div class="image-preview-box">
                    <img :src="prev.url" />
                    <button type="button" class="img-remove" @click="removeExtra(i)">×</button>
                  </div>
                </div>
                <!-- Add More -->
                <div v-if="extraPreviews.length < 10" class="img-box-wrap">
                   <div class="image-preview-box add-more">
                      <span>+</span>
                      <input type="file" @change="handleExtraImages" accept="image/*" multiple class="abs-input" />
                   </div>
                </div>
              </div>
              <p class="text-xs text-muted mt-2">Máximo 2MB por imagen.</p>
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

    <!-- Bulk Adjust Modal -->
    <Transition name="fade">
      <div v-if="showBulkModal" class="modal-overlay" @click.self="showBulkModal = false">
        <div class="modal-box" style="max-width:400px;">
          <div class="modal-header">
            <h3 class="modal-title">Ajuste Masivo de Precios</h3>
            <button class="btn btn-ghost btn-icon" @click="showBulkModal = false">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          
          <div style="padding:1.5rem 1.5rem 0; color:var(--text-muted); font-size:0.9rem; line-height:1.6;">
            Subí los precios de tu catálogo de forma instantánea. Podés aplicarlo a toda tu cuenta o solo a una categoría específica.
          </div>

          <!-- Sugerencia de INDEC -->
          <div style="margin:1.5rem 1.5rem 0; padding:1.25rem; background:linear-gradient(135deg, rgba(56,189,248,0.1), rgba(99,102,241,0.05)); border:1px solid rgba(56,189,248,0.2); border-radius:14px; position:relative; overflow:hidden;">
             <div style="position:absolute; top:-20px; right:-20px; width:80px; height:80px; background:rgba(56,189,248,0.1); border-radius:50%; filter:blur(20px);"></div>
             
             <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; color:#38bdf8; font-weight:800; font-size:0.9rem; text-shadow:0 2px 10px rgba(56,189,248,0.2);">
                   <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                   Inflación INDEC 
                   <span v-if="indecRate === null && !indecError" class="spinner" style="width:12px; height:12px; border-width:2px;"></span>
                </div>
                <div style="background:rgba(56,189,248,0.15); color:#7dd3fc; border:1px solid rgba(56,189,248,0.2); padding:0.2rem 0.6rem; border-radius:20px; font-size:0.65rem; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">
                   {{ indecDate }}
                </div>
             </div>
             
             <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:1rem; line-height:1.5;">
               Este es el índice oficial de inflación (IPC) obtenido en tiempo real desde la API nacional para el último mes reportado.
             </p>
             
             <div style="display:grid; grid-template-columns:1fr; gap:0.75rem;">
               <div style="background:var(--bg-elevated); border:1px solid rgba(255,255,255,0.05); padding:0.75rem; border-radius:10px; text-align:center; box-shadow:0 4px 6px rgba(0,0,0,0.2); display:flex; align-items:center; justify-content:center; gap:1rem;">
                 <span style="color:var(--text-faint); font-weight:800; text-transform:uppercase; font-size:0.7rem;">Índice General (IPC)</span>
                 <strong v-if="indecRate !== null" style="color:#f87171; font-size:1.3rem;">+ {{ indecRate }}%</strong>
                 <strong v-else-if="indecError" style="color:var(--text-muted); font-size:0.9rem;">No disponible</strong>
                 <strong v-else style="color:var(--text-muted); font-size:0.9rem;">Obteniendo...</strong>
               </div>
             </div>
             <p style="font-size:0.65rem; color:var(--text-muted); margin-top:1rem; opacity:0.7;">
               <em>* Fuente oficial: BCRA / INDEC vía API de Mercado.</em>
             </p>
          </div>

          <form @submit.prevent="applyBulk" class="flex flex-col gap-4" style="margin-top:1rem; padding:0 1.5rem 1.5rem;">
            <div class="form-group">
              <label class="form-label" style="font-size:0.8rem;">Seleccioná la categoría a aumentar</label>
              <select v-model="bulkForm.category" class="form-select" style="font-weight:600;">
                <option v-for="cat in categories" :key="cat" :value="cat">
                   {{ cat === 'Todas' ? '📦 Aplicar a TODO el catálogo' : `📂 Solo categoría: ${cat}` }}
                </option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Porcentaje de Aumento (%) *</label>
              <div style="display:flex;align-items:center;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;overflow:hidden; box-shadow:inset 0 2px 4px rgba(0,0,0,0.02);">
                <span style="padding:0 0.85rem;color:var(--text-muted);font-weight:700; font-size:1.1rem;">%</span>
                <input v-model="bulkForm.percentage" type="number" step="0.1" min="0.1" class="form-input" style="border:none;flex:1;font-size:1.1rem;font-weight:700;padding:0.75rem 0.5rem;" placeholder="Ej: 5.1" required />
              </div>
            </div>
            
            <div class="flex gap-3" style="justify-content:flex-end;margin-top:0.5rem;">
              <button type="button" class="btn btn-ghost" @click="showBulkModal = false">Cancelar</button>
              <button type="submit" class="btn btn-primary" style="background:#ef4444;border-color:#ef4444;font-weight:800;" :disabled="applyingBulk">
                <span v-if="applyingBulk" class="spinner"></span>
                <span v-else>Aplicar Aumento</span>
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
        <div v-if="toast" class="toast-item" :class="toast?.type">{{ toast?.msg }}</div>
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

.cat-pill { font-size: 0.65rem; font-weight: 800; background: rgba(99,102,241,0.1); color: var(--primary); padding: 2px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

.image-upload-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 0.75rem; }
.img-box-wrap { position: relative; width: 100%; aspect-ratio: 1; }
.image-preview-box { 
  width: 100%; height: 100%; border-radius: 8px; border: 2px dashed var(--border); 
  background: var(--bg-card); display: flex; align-items: center; justify-content: center;
  overflow: hidden; cursor: pointer; position: relative; font-size: 0.65rem; color: var(--text-faint); font-weight: 700;
}
.image-preview-box img { width: 100%; height: 100%; object-fit: cover; }
.image-preview-box.empty { border-color: var(--primary); color: var(--primary); background: rgba(99,102,241,0.05); }
.abs-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.img-remove { 
  position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; 
  background: #ef4444; color: #fff; border: none; border-radius: 50%; 
  display: flex; align-items: center; justify-content: center; font-size: 12px; cursor: pointer;
  box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.add-more { border-style: dotted; font-size: 1.5rem; }

.tag-selector-container { position: relative; }
.tag-selector-trigger { 
  background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; 
  padding: 0.6rem 1rem; display: flex; justify-content: space-between; align-items: center;
  cursor: pointer; min-height: 40px;
}
.tag-badge-mini { background: var(--primary); color: #fff; padding: 1px 6px; border-radius: 4px; font-size: 11px; }
.tag-dropdown-menu { 
  position: absolute; top: 100%; left: 0; right: 0; z-index: 50;
  background: var(--bg-surface); border: 1px solid var(--border); border-radius: 8px;
  margin-top: 4px; box-shadow: var(--shadow-lg); overflow: hidden;
}
.tag-list-scroll { max-height: 180px; overflow-y: auto; padding: 0.5rem 0; }
.tag-option { 
  display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 1rem; 
  cursor: pointer; transition: background 0.2s; font-size: 0.85rem;
}
.tag-option:hover { background: var(--bg-muted); }
.tag-dropdown-footer { padding: 0.75rem; border-top: 1px solid var(--border); background: var(--bg-card); }

.variant-color-preview {
  position: absolute; right: 8px; width: 22px; height: 22px; 
  border-radius: 50%; border: 2px solid #fff;
  box-shadow: 0 0 4px rgba(0,0,0,0.3);
  cursor: pointer; z-index: 2;
}
.variant-color-preview.clickable:hover { transform: scale(1.1); }
.hidden-color-picker { position: absolute; visibility: hidden; width: 0; height: 0; }

.variant-edit-row { background: var(--bg-surface); border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
.var-img-opt { width: 32px; height: 32px; border-radius: 4px; border: 2px solid transparent; cursor: pointer; opacity: 0.6; transition: all 0.2s; overflow: hidden; background: #fff; }
.var-img-opt:hover { opacity: 1; transform: scale(1.1); }
.var-img-opt.active { opacity: 1; border-color: var(--primary); box-shadow: 0 0 8px var(--primary-low); }

.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); }

.tag-pill-btn {
  font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; 
  background: var(--bg-muted); border: 1px solid var(--border);
  color: var(--text-muted); cursor: pointer; transition: all 0.2s;
}
.tag-pill-btn:hover { background: var(--bg-elevated); color: var(--text-primary); }
.tag-pill-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

/* Quill Editor Dark Theme overrides */
.quill-dark-wrapper {
  background: var(--bg-card);
  border-radius: 8px;
  border: 1px solid var(--border);
  overflow: hidden;
}
.color-picker-large {
  width: 36px;
  height: 36px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  background: var(--bg-surface);
  box-shadow: var(--shadow-sm);
}
.color-picker-large::-webkit-color-swatch-wrapper {
  padding: 2px;
}
.color-picker-large::-webkit-color-swatch {
  border: none;
  border-radius: 6px;
}
:deep(.ql-toolbar.ql-snow) {
  border: none;
  border-bottom: 1px solid var(--border);
  background: var(--bg-surface);
}
:deep(.ql-container.ql-snow) {
  border: none;
  min-height: 100px;
  font-family: inherit;
  font-size: 0.9rem;
  color: var(--text-primary);
}
:deep(.ql-editor) {
  padding: 1rem;
}
:deep(.ql-editor.ql-blank::before) {
  color: var(--text-muted);
  font-style: normal;
}
:deep(.ql-snow .ql-stroke) { stroke: var(--text-primary); }
:deep(.ql-snow .ql-fill) { fill: var(--text-primary); }
:deep(.ql-snow .ql-picker) { color: var(--text-primary); }

@media (max-width: 600px) {
  .page-header { flex-direction: column; align-items: stretch; text-align: center; }
  .page-header div[style*="display:flex"] { flex-direction: column; width: 100%; }
  .page-header .btn { width: 100%; }
  .search-wrap { max-width: 100% !important; }
}
</style>
