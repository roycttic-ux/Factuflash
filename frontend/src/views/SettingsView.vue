<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { settingsApi, arcaApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const toast = ref<{ msg: string; type: string } | null>(null)

const form = reactive({
  company_name: '',
  company_cuit: '',
  company_address: '',
  company_phone: '',
  company_email: '',
  invoice_prefix: 'F',
  tax_condition: 'Responsable Inscripto',
  dollar_rate_mode: 'manual',
  dollar_rate_manual: '1240',
  whatsapp_number: '',
  show_whatsapp_button: true,
  mp_access_token: '',
  mp_public_key: '',
  catalog_theme: 'white',
})

const logo = reactive({
  file: null as File | null,
  preview: '',
  uploading: false,
})

const arca = reactive({
  cuit: '',
  connected: false,
  csr: '',
  loading: false,
  file: null as File | null,
  certText: '', 
})

onMounted(async () => {
  try {
    const res = await settingsApi.get()
    const s = res.data.settings
    if (s) Object.assign(form, {
      company_name:       s.company_name       ?? auth.user?.company_name ?? '',
      company_cuit:       s.company_cuit       ?? '',
      company_address:    s.company_address    ?? '',
      company_phone:      s.company_phone      ?? '',
      company_email:      s.company_email      ?? auth.user?.email ?? '',
      invoice_prefix:     s.invoice_prefix     ?? 'F',
      tax_condition:      s.tax_condition      ?? 'Responsable Inscripto',
      dollar_rate_mode:   s.dollar_rate_mode   ?? 'manual',
      dollar_rate_manual: s.dollar_rate_manual ?? '1240',
      whatsapp_number:    s.whatsapp_number    ?? '',
      show_whatsapp_button: s.show_whatsapp_button !== undefined ? !!s.show_whatsapp_button : true,
      mp_access_token:    s.mp_access_token    ?? '',
      mp_public_key:     s.mp_public_key     ?? '',
      catalog_theme:      s.catalog_theme      ?? 'white',
    })
    arca.cuit = s.arca_cuit ?? ''
    arca.connected = !!s.arca_connected
    if (s.company_logo) {
      logo.preview = '/sistemafacturacion/api/public/storage/' + s.company_logo
    }
  } catch {
    // defaults already set
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  try {
    await settingsApi.update({ ...form })
    showToast('Configuración guardada', 'success')
  } catch (e: any) {
    showToast(e?.response?.data?.message ?? 'Error al guardar', 'error')
  } finally {
    saving.value = false
  }
}

function showToast(msg: string, type: string) {
  toast.value = { msg, type }
  setTimeout(() => (toast.value = null), 3000)
}

function handleLogoChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files && input.files[0]) {
    const file = input.files[0]
    logo.file = file
    logo.preview = URL.createObjectURL(file)
  }
}

async function uploadLogo() {
  if (!logo.file) return
  logo.uploading = true
  const fd = new FormData()
  fd.append('logo', logo.file)
  try {
    const res = await settingsApi.uploadLogo(fd)
    logo.preview = res.data.logo_url
    showToast('Logo actualizado', 'success')
  } catch (e: any) {
    showToast('Error al subir logo', 'error')
  } finally {
    logo.uploading = false
  }
}

async function generateCsr() {
  if (!arca.cuit) return showToast('Ingresá el CUIT primero', 'error')
  arca.loading = true
  try {
    const res = await arcaApi.generateCsr(arca.cuit)
    arca.csr = res.data.csr
    showToast('¡CSR generado! Copialo y pegalo en AFIP', 'success')
  } catch (e: any) {
    showToast(e.response?.data?.error ?? 'Error al generar CSR', 'error')
  } finally {
    arca.loading = false
  }
}

function handleFile(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files) arca.file = input.files[0]
}

async function uploadCert() {
  if (!arca.file && !arca.certText) return
  arca.loading = true
  
  const fd = new FormData()
  if (arca.file) {
    fd.append('cert', arca.file)
  }
  if (arca.certText) {
    fd.append('cert_text', arca.certText)
  }

  try {
    await arcaApi.uploadCert(fd)
    arca.connected = true
    arca.file = null
    arca.certText = ''
    showToast('¡Certificado vinculado!', 'success')
  } catch (e: any) {
    showToast(e.response?.data?.error ?? 'Error al subir certificado', 'error')
  } finally {
    arca.loading = false
  }
}

async function resetArca() {
  if (!confirm('¿Restablecer la conexión con AFIP? Se borrarán las claves actuales.')) return
  arca.loading = true
  try {
    await arcaApi.reset()
    arca.connected = false
    arca.csr = ''
    showToast('Configuración restablecida', 'success')
  } catch {
    showToast('Error al restablecer', 'error')
  } finally {
    arca.loading = false
  }
}

function copyCsr() {
  navigator.clipboard.writeText(arca.csr)
  showToast('Copiado al portapapeles', 'success')
}

function copyCatalogLink() {
  const link = window.location.origin + window.location.pathname + '#/catalogo/' + auth.user?.id
  navigator.clipboard.writeText(link)
  showToast('Link copiado al portapapeles', 'success')
}

const window = (globalThis as any).window
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Configuración</h1>
        <p class="page-subtitle">Datos de tu empresa y preferencias del sistema</p>
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:3rem;">
      <span class="spinner" style="width:28px;height:28px;color:var(--primary);"></span>
    </div>

    <form v-else @submit.prevent="save" class="settings-layout">
      <!-- Empresa -->
      <div class="card settings-section">
        <div class="settings-section-header">
          <div class="settings-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
          <div>
            <h2 class="settings-title">Datos de la Empresa</h2>
            <p class="settings-sub">Información que aparece en las facturas</p>
          </div>
        </div>
        <div class="settings-fields">
          <div class="logo-upload-wrap">
             <div class="logo-preview-box">
                <img v-if="logo.preview" :src="logo.preview" class="logo-preview-img" />
                <div v-else class="logo-placeholder">Sin Logo</div>
             </div>
             <div class="logo-actions">
                <p class="text-xs font-bold mb-1">Logo de la empresa</p>
                <p class="text-xs text-muted mb-2">Se mostrará en tus facturas impresas.</p>
                <div class="flex gap-2">
                  <input type="file" @change="handleLogoChange" accept="image/*" class="form-input text-xs" style="max-width:200px;" />
                  <button type="button" class="btn btn-sm btn-ghost" :disabled="!logo.file || logo.uploading" @click="uploadLogo">
                    {{ logo.uploading ? '...' : 'Subir' }}
                  </button>
                </div>
             </div>
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Razón Social</label>
              <input v-model="form.company_name" class="form-input" placeholder="Mi Empresa S.A." />
            </div>
            <div class="form-group">
              <label class="form-label">CUIT</label>
              <input v-model="form.company_cuit" class="form-input" placeholder="20-12345678-9" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Dirección fiscal</label>
            <input v-model="form.company_address" class="form-input" placeholder="Av. Corrientes 1234, Buenos Aires" />
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Email</label>
              <input v-model="form.company_email" class="form-input" type="email" placeholder="facturacion@empresa.com" />
            </div>
            <div class="form-group">
              <label class="form-label">Teléfono</label>
              <input v-model="form.company_phone" class="form-input" placeholder="+54 11 1234-5678" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Condición IVA</label>
            <select v-model="form.tax_condition" class="form-select">
              <option>Responsable Inscripto</option>
              <option>Monotributista</option>
              <option>Exento</option>
              <option>Consumidor Final</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Catálogo y WhatsApp -->
      <div class="card settings-section">
        <div class="settings-section-header">
          <div class="settings-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
               <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
            </svg>
          </div>
          <div>
            <h2 class="settings-title">Catálogo y WhatsApp</h2>
            <p class="settings-sub">Gestioná tu catálogo público para clientes</p>
          </div>
        </div>
        <div class="settings-fields">
           <div class="info-box">
             <div class="flex-1">
                <p class="font-bold text-sm mb-1">Tu link de catálogo:</p>
                <code class="text-xs color-primary" style="word-break:break-all;">{{ window.location.origin + window.location.pathname + '#/catalogo/' + auth.user?.id }}</code>
             </div>
             <button type="button" class="btn btn-sm btn-ghost" @click="copyCatalogLink">Copiar Link</button>
           </div>
           
           <div class="grid-2" style="margin-top:1rem;">
             <div class="form-group">
                <label class="form-label">Número de WhatsApp (con código de país)</label>
                <input v-model="form.whatsapp_number" class="form-input" placeholder="5491112345678" />
             </div>
             <div class="form-group">
                <label class="form-label">Tema de color del catálogo</label>
                <select v-model="form.catalog_theme" class="form-select">
                   <option value="white">Blanco Clásico</option>
                   <option value="black">Negro Premium</option>
                   <option value="blue">Azul Profesional</option>
                   <option value="pink">Rosa Moderno</option>
                   <option value="purple">Violeta Creativo</option>
                </select>
             </div>
           </div>
           
           <div class="form-group" style="margin-top:0.5rem;">
              <label class="flex items-center gap-2 cursor-pointer">
                 <input type="checkbox" v-model="form.show_whatsapp_button" />
                 <span class="text-sm font-medium">Mostrar botón de WhatsApp en catálogo</span>
              </label>
           </div>
        </div>
      </div>

      <!-- Facturación -->
      <div class="card settings-section">
        <div class="settings-section-header">
          <div class="settings-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
          </div>
          <div>
            <h2 class="settings-title">Facturación</h2>
            <p class="settings-sub">Configuración de numeración y tipo de cambio</p>
          </div>
        </div>
        <div class="settings-fields">
          <div class="form-group">
            <label class="form-label">Modo de cotización (Dólar)</label>
            <select v-model="form.dollar_rate_mode" class="form-select" style="max-width:280px;">
              <option value="manual">Manual (fijo)</option>
              <option value="bcra">Automático (BCRA oficial)</option>
              <option value="blue">Automático (Blue/Informal)</option>
            </select>
          </div>
          <div v-if="form.dollar_rate_mode === 'manual'" class="form-group">
            <label class="form-label">Tipo de cambio manual (ARS por USD)</label>
            <input v-model="form.dollar_rate_manual" class="form-input" type="number" step="0.01" min="0" placeholder="1240" style="max-width:200px;" />
          </div>
          <div v-else class="info-box">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Se usará el valor del dólar en tiempo real al momento de facturar.
          </div>
          
          <div class="info-box" style="margin-top:1rem; border-color:var(--accent);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <div>
               <p class="font-bold text-xs" style="color:var(--accent);">¿Conectar Postnet?</p>
               <p class="text-xs">Para registrar pagos con tarjeta, ahora puedes elegir "Tarjeta" al crear una factura. El sistema llevará el control de esos cobros.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ARCA / AFIP -->
      <div class="card settings-section">
        <div class="settings-section-header">
          <div class="settings-icon" :style="arca.connected ? 'background:rgba(52,211,153,0.15);color:var(--success);' : ''">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <h2 class="settings-title">Vinculación ARCA (AFIP)</h2>
              <span v-if="arca.connected" class="badge badge-success">Conectado</span>
              <span v-else class="badge badge-warning">Sin vincular</span>
            </div>
            <p class="settings-sub">Configurá tus certificados para facturación electrónica</p>
          </div>
          <button v-if="arca.connected" type="button" class="btn btn-ghost btn-danger btn-sm" @click="resetArca">Restablecer</button>
        </div>

        <div class="settings-fields">
          <div v-if="!arca.connected">
            <div class="step-box">
              <div class="step-num">1</div>
              <div class="flex-1">
                <p class="font-bold text-sm mb-1">Generar claves y pedido CSR</p>
                <p class="text-xs text-muted mb-3">Ingresá tu CUIT y generá las claves necesarias para AFIP.</p>
                <div class="flex gap-2">
                  <input v-model="arca.cuit" class="form-input" placeholder="20-12345678-9" style="max-width:180px;" />
                  <button type="button" class="btn btn-sm btn-primary" :disabled="arca.loading" @click="generateCsr">
                    Generar
                  </button>
                </div>
              </div>
            </div>

            <div v-if="arca.csr" class="csr-result">
              <p class="text-xs font-bold mb-2">Pedido CSR generado (Copialo completo):</p>
              <pre class="csr-code">{{ arca.csr }}</pre>
              <button type="button" class="btn btn-sm btn-ghost w-full" style="margin-top:0.5rem;" @click="copyCsr">
                 Copiar CSR
              </button>
            </div>

            <div class="step-box" style="margin-top:1rem;">
              <div class="step-num">2</div>
              <div class="flex-1">
                <p class="font-bold text-sm mb-1">Subir o Pegar Certificado (.crt)</p>
                <p class="text-xs text-muted mb-3">Una vez tramitado en AFIP, pegá el texto aquí o subí el archivo.</p>
                
                <textarea v-model="arca.certText" class="form-input text-xs mb-3" rows="5" placeholder="-----BEGIN CERTIFICATE----- ..."></textarea>
                
                <div class="flex gap-2">
                  <input type="file" @change="handleFile" accept=".crt,.pem" class="form-input text-xs" />
                  <button type="button" class="btn btn-sm btn-primary" :disabled="(!arca.file && !arca.certText) || arca.loading" @click="uploadCert">
                    Vincular
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mercado Pago / Integración Automática -->
      <div class="card settings-section">
        <div class="settings-section-header">
          <div class="settings-icon" style="background:rgba(0,158,227,0.1);color:#009ee3;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
               <rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
            </svg>
          </div>
          <div>
            <h2 class="settings-title">Integración Mercado Pago</h2>
            <p class="settings-sub">Cobrá automáticamente con Point o QR y recibilo en el sistema</p>
          </div>
        </div>
        <div class="settings-fields">
           <div class="info-box" style="border-left: 4px solid #009ee3;">
              <p class="text-xs">Usa esto para que cuando cobres con un <strong>Mercado Pago Point</strong> o <strong>QR</strong>, la factura se marque como pagada automáticamente sin que tengas que tocar nada.</p>
           </div>
           <div class="form-group">
            <label class="form-label">Access Token</label>
            <input v-model="form.mp_access_token" type="password" class="form-input" placeholder="APP_USR-..." />
          </div>
          <div class="form-group">
            <label class="form-label">Public Key</label>
            <input v-model="form.mp_public_key" class="form-input" placeholder="APP_USR-..." />
          </div>
        </div>
      </div>

      <!-- Account -->
      <div class="card settings-section">
        <div class="settings-section-header">
          <div class="settings-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
          <div>
            <h2 class="settings-title">Cuenta</h2>
            <p class="settings-sub">Información de tu cuenta de acceso</p>
          </div>
        </div>
        <div class="settings-fields">
          <div class="account-info-grid">
            <div class="account-info-item">
              <span class="form-label">Nombre</span>
              <span class="font-semibold">{{ auth.user?.name }}</span>
            </div>
            <div class="account-info-item">
              <span class="form-label">Email</span>
              <span class="font-semibold">{{ auth.user?.email }}</span>
            </div>
            <div class="account-info-item">
              <span class="form-label">Plan</span>
              <span class="badge badge-primary" style="text-transform:capitalize;">{{ auth.user?.plan_status }}</span>
            </div>
          </div>
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;margin-top:0.5rem;">
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="spinner"></span>
          <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Guardar Cambios
        </button>
      </div>
    </form>

    <div class="toast">
      <Transition name="fade">
        <div v-if="toast" class="toast-item" :class="toast.type">{{ toast.msg }}</div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.settings-layout { display: flex; flex-direction: column; gap: 1.5rem; max-width: 760px; }

.settings-section-header {
  display: flex; align-items: center; gap: 1rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}
.settings-icon {
  width: 40px; height: 40px;
  background: rgba(99,102,241,0.12);
  color: var(--primary);
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.settings-title { font-size: 0.95rem; font-weight: 700; }
.settings-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.1rem; }

.settings-fields { display: flex; flex-direction: column; gap: 1rem; }

.info-box {
  display: flex; align-items: center; gap: 0.6rem;
  background: rgba(99,102,241,0.08);
  border: 1px solid rgba(99,102,241,0.15);
  border-radius: var(--radius-sm);
  padding: 0.7rem 1rem;
  font-size: 0.85rem;
  color: var(--text-muted);
}

.account-info-grid { display: flex; flex-direction: column; gap: 0.85rem; }
.account-info-item { display: flex; align-items: center; justify-content: space-between; }

/* ARCA Steps */
.step-box { display: flex; gap: 1rem; padding: 1rem; background: var(--bg-surface); border: 1px dashed var(--border); border-radius: var(--radius-md); }
.step-num { width: 24px; height: 24px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; flex-shrink: 0; }
.csr-result { margin-top: 1rem; padding: 1rem; background: #1e1e1e; border-radius: var(--radius-sm); border: 1px solid var(--border); }
.csr-code { font-family: monospace; font-size: 10px; color: #a5d6ff; white-space: pre-wrap; word-break: break-all; max-height: 120px; overflow-y: auto; }
.info-box.success { background: rgba(52,211,153,0.1); border-color: rgba(52,211,153,0.2); color: var(--success); }

/* Logo Upload */
.logo-upload-wrap { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 0.5rem; padding: 1rem; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px solid var(--border); }
.logo-preview-box { width: 80px; height: 80px; border-radius: var(--radius-md); background: #f1f5f9; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.logo-preview-img { width: 100%; height: 100%; object-fit: contain; }
.logo-placeholder { font-size: 0.7rem; color: var(--text-faint); font-weight: 700; text-transform: uppercase; }
.color-selector { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.5rem; }
.color-option { 
  display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; border: 1px solid var(--border); 
  border-radius: 12px; cursor: pointer; transition: all 0.2s; background: white;
}
.color-option:hover { border-color: var(--primary); background: #f8faff; }
.color-option.active { border-color: var(--primary); background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
.hidden-radio { position: absolute; opacity: 0; width: 0; height: 0; }
.color-dot { width: 14px; height: 14px; border-radius: 4px; }
.dot-white { background: #fff; border: 1px solid #ddd; }
.dot-black { background: #0f172a; }
.dot-blue { background: #1e40af; }
.dot-pink { background: #db2777; }
.dot-purple { background: #6d28d9; }
.color-name { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }
</style>
