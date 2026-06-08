<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { settingsApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const toast = ref<{ msg: string; type: string } | null>(null)

const form = reactive({
  slug: '',
  whatsapp_number: '',
  show_whatsapp_button: true,
  catalog_theme: 'black',
  catalog_title: '',
  catalog_description: '',
  catalog_instagram: '',
  catalog_facebook: '',
  catalog_tiktok: '',
  catalog_show_stock: true,
  catalog_show_price: true,
  catalog_grid_columns: 1,
  catalog_video_url: '',
})

const selectedTheme = ref('black')
const selectedFont = ref('Outfit')

onMounted(async () => {
  try {
    const res = await settingsApi.get()
    const s = res.data.settings
    if (s) {
      const rawTheme = s.catalog_theme === 'white' ? 'black' : (s.catalog_theme ?? 'black')
      const parts = rawTheme.split('|')
      selectedTheme.value = parts[0] || 'black'
      selectedFont.value = parts[1] || 'Outfit'
      
      Object.assign(form, {
        slug: s.slug ?? '',
        whatsapp_number: s.whatsapp_number ?? '',
        show_whatsapp_button: s.show_whatsapp_button !== undefined ? !!s.show_whatsapp_button : true,
        catalog_theme: rawTheme,
        catalog_title: s.catalog_title ?? '',
        catalog_description: s.catalog_description ?? '',
        catalog_instagram: s.catalog_instagram ?? '',
        catalog_facebook: s.catalog_facebook ?? '',
        catalog_tiktok: s.catalog_tiktok ?? '',
        catalog_show_stock: s.catalog_show_stock !== undefined ? !!s.catalog_show_stock : true,
        catalog_show_price: s.catalog_show_price !== undefined ? !!s.catalog_show_price : true,
        catalog_grid_columns: s.catalog_grid_columns ?? 1,
        catalog_video_url: s.catalog_video_url ?? '',
      })
    }
  } finally {
    loading.value = false
  }
})

async function save() {
  if (form.show_whatsapp_button && !form.whatsapp_number) {
    showToast('Debes ingresar un número de WhatsApp si activas el botón', 'error')
    return
  }

  saving.value = true
  try {
    form.catalog_theme = `${selectedThemeTheme()}|${selectedFont.value}`
    await settingsApi.update({ ...form })
    showToast('Configuración del catálogo guardada', 'success')
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Error al guardar', 'error')
  } finally {
    saving.value = false
  }
}

function selectedThemeTheme() {
  return selectedTheme.value
}

function showToast(msg: string, type: string) {
  toast.value = { msg, type }
  setTimeout(() => (toast.value = null), 3000)
}

function copyCatalogLink() {
  const idOrSlug = form.slug || (auth.user?.owner_id || auth.user?.id)
  const link = window.location.origin + '/' + idOrSlug
  navigator.clipboard.writeText(link)
  showToast('Link copiado al portapapeles', 'success')
}

const window = (globalThis as any).window
</script>

<template>
  <div class="catalog-settings">
    <div class="page-header">
      <div class="flex flex-col gap-1">
        <RouterLink to="/configuracion" class="btn btn-ghost btn-xs mb-2" style="width:fit-content; padding: 0;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          Volver a Configuración
        </RouterLink>
        <h1 class="page-title">Catálogo Web</h1>
        <p class="page-subtitle">Personalizá tu tienda online y cómo te ven tus clientes</p>
      </div>
      <div class="flex gap-3">
        <a :href="'#/catalogo/' + (form.slug || (auth.user?.owner_id || auth.user?.id))" target="_blank" class="btn btn-white" style="border:1px solid var(--border)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Ver Catálogo
        </a>
        <button class="btn btn-primary" @click="save" :disabled="saving">
          <span v-if="saving" class="spinner"></span>
          <span v-else>Guardar Cambios</span>
        </button>
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:4rem;">
      <span class="spinner" style="width:32px;height:32px;color:var(--primary);"></span>
    </div>

    <div v-else class="catalog-grid">
      <!-- Left: General & Personalization -->
      <div class="flex flex-col gap-6">
        <!-- Link & Slug -->
        <div class="card p-6">
          <h2 class="section-title mb-4">Enlace del Catálogo</h2>
          <div class="info-pill mb-4">
            <div class="flex-1 overflow-hidden">
              <p class="text-xs font-bold text-muted mb-1">Tu URL pública:</p>
              <code class="catalog-code">{{ window.location.origin + '/' + (form.slug || (auth.user?.owner_id || auth.user?.id)) }}</code>
            </div>
            <button type="button" class="btn btn-sm btn-white" style="border:1px solid var(--border)" @click="copyCatalogLink">Copiar</button>
          </div>

          <div class="form-group">
            <label class="form-label">Identificador personalizado (Slug)</label>
            <div class="slug-input-wrap">
              <span class="slug-prefix">factuflash.com.ar/</span>
              <input v-model="form.slug" class="form-input" placeholder="mi-tienda" />
            </div>
            <p class="text-xs text-muted mt-2">Este nombre aparecerá en la URL. Usá solo letras, números y guiones.</p>
          </div>
        </div>

        <!-- Content Personalization -->
        <div class="card p-6">
          <h2 class="section-title mb-4">Personalización de Contenido</h2>
          <div class="form-group mb-4">
            <label class="form-label">Título del Catálogo</label>
            <input v-model="form.catalog_title" class="form-input" placeholder="Ej: Catálogo de Temporada 2024" />
          </div>
          <div class="form-group mb-4">
            <label class="form-label">Descripción o Bienvenida</label>
            <textarea v-model="form.catalog_description" class="form-input" rows="3" placeholder="Contale a tus clientes algo sobre tu tienda..."></textarea>
          </div>
          <div class="form-group mb-4">
            <label class="form-label">Video de Presentación (URL)</label>
            <input v-model="form.catalog_video_url" class="form-input" placeholder="Ej: https://www.youtube.com/watch?v=..." />
            <p class="text-xs text-muted mt-1">Podés incluir un link de YouTube o Vimeo para mostrar en tu catálogo.</p>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Instagram (Usuario)</label>
              <div class="input-with-icon">
                <span class="input-icon">@</span>
                <input v-model="form.catalog_instagram" class="form-input" placeholder="tu.tienda" />
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Facebook (Link)</label>
              <input v-model="form.catalog_facebook" class="form-input" placeholder="facebook.com/tienda" />
            </div>
            <div class="form-group">
              <label class="form-label">TikTok (Usuario)</label>
              <div class="input-with-icon">
                <span class="input-icon">@</span>
                <input v-model="form.catalog_tiktok" class="form-input" placeholder="tienda.oficial" />
              </div>
            </div>
          </div>
        </div>

        <!-- Visuals -->
        <div class="card p-6">
          <h2 class="section-title mb-4">Apariencia Visual</h2>
          
          <div class="form-group mb-6">
            <label class="form-label mb-3">Tema de colores</label>
            <div class="theme-grid">
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'black'}" @click="selectedTheme = 'black'">
                <div class="theme-preview black"></div>
                <span>Oscuro Corp.</span>
              </button>
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'white'}" @click="selectedTheme = 'white'">
                <div class="theme-preview white"></div>
                <span>Claro Clásico</span>
              </button>
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'blue'}" @click="selectedTheme = 'blue'">
                <div class="theme-preview blue"></div>
                <span>Zafiro Real</span>
              </button>
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'emerald'}" @click="selectedTheme = 'emerald'">
                <div class="theme-preview emerald"></div>
                <span>Esmeralda</span>
              </button>
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'pink'}" @click="selectedTheme = 'pink'">
                <div class="theme-preview pink"></div>
                <span>Rosa Pastel</span>
              </button>
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'purple'}" @click="selectedTheme = 'purple'">
                <div class="theme-preview purple"></div>
                <span>Amatista</span>
              </button>
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'sunset'}" @click="selectedTheme = 'sunset'">
                <div class="theme-preview sunset"></div>
                <span>Fuego Cálido</span>
              </button>
              <button type="button" class="theme-card" :class="{active: selectedTheme === 'minimal'}" @click="selectedTheme = 'minimal'">
                <div class="theme-preview minimal"></div>
                <span>Gris Minimal</span>
              </button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label mb-3">Tipografía (Letra)</label>
            <div class="font-grid">
              <button 
                v-for="f in ['Outfit', 'Poppins', 'Inter', 'Montserrat', 'Playfair Display']" 
                :key="f"
                type="button" 
                class="font-card" 
                :class="{ active: selectedFont === f }"
                @click="selectedFont = f"
                :style="{ fontFamily: f === 'Playfair Display' ? `'Playfair Display', serif` : `'${f}', sans-serif` }"
              >
                <span class="font-name">{{ f }}</span>
                <span class="font-preview">Abc 123</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Visibility & Sales -->
      <div class="flex flex-col gap-6">
        <!-- Sales & Contact -->
        <div class="card p-6 border-primary-glow">
          <h2 class="section-title mb-4">Ventas y Contacto</h2>
          <div class="form-group mb-4">
            <label class="form-label">WhatsApp de Ventas <span v-if="form.show_whatsapp_button" class="text-error">*</span></label>
            <input v-model="form.whatsapp_number" class="form-input" :class="{'border-error': form.show_whatsapp_button && !form.whatsapp_number}" placeholder="5491112345678" />
            <p class="text-xs text-muted mt-1">Incluí el código de país (ej: 549 para Argentina).</p>
          </div>
          <div class="flex flex-col gap-3">
            <label class="toggle-control">
              <input type="checkbox" v-model="form.show_whatsapp_button" />
              <div class="toggle-switch"></div>
              <span class="toggle-label">Mostrar botón de WhatsApp flotante</span>
            </label>
          </div>
        </div>

        <!-- Visibility settings -->
        <div class="card p-6 border-highlight shadow-xl">
          <div class="flex items-center gap-3 mb-6">
            <div class="icon-circle"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
            <h2 class="section-title">Diseño del Catálogo</h2>
          </div>

          <div class="flex flex-col gap-4 mb-8">
            <label class="toggle-control">
              <input type="checkbox" v-model="form.catalog_show_price" />
              <div class="toggle-switch"></div>
              <span class="toggle-label">Mostrar precios al público</span>
            </label>
            <label class="toggle-control">
              <input type="checkbox" v-model="form.catalog_show_stock" />
              <div class="toggle-switch"></div>
              <span class="toggle-label">Mostrar stock disponible</span>
            </label>
          </div>

        </div>

        <!-- NEW BUBBLE: Grid Layout -->
        <div class="card p-6 border-highlight shadow-xl">
          <div class="flex items-center gap-3 mb-6">
            <div class="icon-circle" style="background: rgba(245,158,11,0.15); color: #f59e0b;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </div>
            <h2 class="section-title">Vista en Celulares</h2>
          </div>

          <div class="form-group">
            <label class="form-label mb-4 text-sm opacity-80">Seleccioná cuántos productos se verán por fila:</label>
            <div class="columns-selector">
              <button 
                v-for="n in [1, 2, 3, 4]" 
                :key="n"
                type="button" 
                class="col-btn" 
                :class="{ active: form.catalog_grid_columns === n }"
                @click="form.catalog_grid_columns = n"
              >
                {{ n }}xP
              </button>
            </div>
            <div class="columns-legend mt-4">
              <span><strong>1xP:</strong> Gigante</span>
              <span><strong>2xP:</strong> Estándar</span>
              <span><strong>3xP:</strong> Galería</span>
              <span><strong>4xP:</strong> Compacto</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Transition name="fade">
      <div v-if="toast" class="toast-float" :class="toast.type">{{ toast.msg }}</div>
    </Transition>
  </div>
</template>

<style scoped>
.catalog-settings {
  max-width: 1100px;
  margin: 0 auto;
}

.catalog-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 1000px) {
  .catalog-grid { grid-template-columns: 1fr; }
}

.section-title { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0; }
.icon-circle { width: 32px; height: 32px; border-radius: 10px; background: rgba(19,18,75,0.08); color: #13124b; display: flex; align-items: center; justify-content: center; }
.border-highlight { border: 1px solid rgba(19,18,75,0.15) !important; background: rgba(19,18,75,0.02); }
.border-dashed { border-top: 1px dashed var(--border); }

.info-pill {
  display: flex; align-items: center; gap: 1rem;
  background: rgba(19, 18, 75, 0.05); border: 1px solid rgba(19, 18, 75, 0.12);
  border-radius: 12px; padding: 1.25rem;
}
.catalog-code { font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: #13124b; font-weight: 700; word-break: break-all; }

.slug-input-wrap { display: flex; align-items: center; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; }
.slug-prefix { padding: 0 0.75rem; background: var(--bg-surface); color: var(--text-muted); font-size: 0.8rem; border-right: 1px solid var(--border); height: 42px; display: flex; align-items: center; }
.slug-input-wrap .form-input { border: none; border-radius: 0; flex: 1; height: 42px; }

.input-with-icon { position: relative; }
.input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-weight: 700; }
.input-with-icon .form-input { padding-left: 28px; }

/* Themes */
.theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; }
.theme-card {
  background: var(--bg-elevated); border: 2px solid var(--border); border-radius: 12px;
  padding: 0.75rem; cursor: pointer; transition: all 0.2s;
  display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
}
.theme-card span { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); transition: color 0.2s; }
.theme-card:hover span { color: var(--text-primary); }
.theme-card.active { border-color: #13124b; background: rgba(19,18,75,0.06); }
.theme-card.active span { color: #13124b; }

.theme-preview { width: 100%; height: 40px; border-radius: 6px; border: 1px solid rgba(0,0,0,0.1); }
.theme-preview.white { background: #fff; }
.theme-preview.black { background: #13124b; }
.theme-preview.blue  { background: #2563eb; }
.theme-preview.pink  { background: #db2777; }
.theme-preview.purple { background: #7c3aed; }

/* Toggle */
.toggle-control { display: flex; align-items: center; gap: 0.75rem; cursor: pointer; user-select: none; }
.toggle-control input { display: none; }
.toggle-switch {
  width: 44px; height: 24px; background: var(--border); border-radius: 12px;
  position: relative; transition: all 0.3s;
}
.toggle-switch::after {
  content: ''; position: absolute; left: 4px; top: 4px;
  width: 16px; height: 16px; background: #fff; border-radius: 50%;
  transition: all 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.toggle-control input:checked + .toggle-switch { background: #13124b; }
.toggle-control input:checked + .toggle-switch::after { left: 24px; }
.toggle-label { font-size: 1rem; font-weight: 600; color: var(--text-primary); }

.info-box-mini {
  display: flex; align-items: center; gap: 0.6rem;
  background: rgba(19, 18, 75, 0.04); color: var(--text-muted);
  font-size: 0.85rem; padding: 1rem; border-radius: 12px;
  border: 1px solid rgba(19, 18, 75, 0.1);
}

.border-primary-glow { border: 1px solid rgba(19, 18, 75, 0.1); box-shadow: 0 0 15px rgba(19, 18, 75, 0.02); }

/* Toast */
.toast-float {
  position: fixed; bottom: 2rem; right: 2rem; z-index: 9999;
  padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 600; color: #fff;
  box-shadow: 0 8px 24px rgba(0,0,0,.25);
}
.toast-float.success { background: #10b981; }
.toast-float.error   { background: #ef4444; }

.text-error { color: #ef4444; margin-left: 0.2rem; }
.border-error { border-color: #ef4444 !important; box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.2) !important; }

/* Selector de columnas */
.columns-selector { display: flex; gap: 0.5rem; background: var(--bg-elevated); padding: 0.4rem; border-radius: 12px; border: 1px solid var(--border); }
.col-btn { 
  flex: 1; padding: 0.6rem; border-radius: 8px; border: none; 
  background: none; color: var(--text-muted); font-weight: 700; 
  cursor: pointer; transition: all 0.2s; font-size: 0.9rem;
}
.col-btn:hover { background: rgba(19, 18, 75, 0.05); color: #13124b; }
.col-btn.active { background: #13124b; color: #fff; box-shadow: 0 4px 12px rgba(19,18,75,0.25); }

.theme-preview.emerald { background: #064e3b; }
.theme-preview.sunset  { background: #f97316; }
.theme-preview.minimal { background: #e5e7eb; }

/* Selector de fuentes */
.font-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.75rem; }
.font-card {
  background: var(--bg-elevated); border: 2px solid var(--border); border-radius: 12px;
  padding: 0.85rem; cursor: pointer; transition: all 0.2s;
  display: flex; flex-direction: column; align-items: flex-start; gap: 0.25rem;
  text-align: left; width: 100%;
}
.font-card .font-name { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); }
.font-card .font-preview { font-size: 0.75rem; color: var(--text-faint); }
.font-card:hover .font-name { color: var(--text-primary); }
.font-card.active { border-color: #13124b; background: rgba(19,18,75,0.06); }
.font-card.active .font-name { color: #13124b; }
.font-card.active .font-preview { color: #13124b; opacity: 0.7; }

.columns-legend { display: flex; justify-content: space-between; gap: 0.5rem; }
.columns-legend span { font-size: 0.7rem; color: var(--text-muted); background: rgba(255,255,255,0.03); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border); }
.columns-legend strong { color: #13124b; }

</style>
