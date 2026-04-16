<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { settingsApi, arcaApi, mpApi, subscriptionApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'

const auth = useAuthStore()
const route = useRoute()
const loading = ref(true)
const saving = ref(false)
const paying = ref('')
const toast = ref<{ msg: string; type: string } | null>(null)
const openSection = ref<string | null>('empresa')

function toggleSection(section: string) {
  openSection.value = openSection.value === section ? null : section
}

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
  if (route.query.payment === 'success') {
    showToast('¡Pago procesado con éxito! Tu plan se actualizará pronto.', 'success')
  } else if (route.query.payment === 'failure') {
    showToast('El pago no pudo completarse. Reintentá nuevamente.', 'error')
  }

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
      mp_public_key:      s.mp_public_key      ?? '',
      catalog_theme:      s.catalog_theme      ?? 'white',
    })
    arca.cuit = s.arca_cuit ?? ''
    arca.connected = !!s.arca_connected
    if (s?.company_logo) {
      logo.preview = '/sistemafacturacion/api/public/storage/' + s.company_logo
    }
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
    logo.file = input.files[0]
    logo.preview = URL.createObjectURL(input.files[0])
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
  } catch {
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
  if (arca.file) fd.append('cert', arca.file)
  if (arca.certText) fd.append('cert_text', arca.certText)
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

async function cancelSubscription() {
  if (!confirm('¿Estás seguro de cancelar tu suscripción?')) return
  saving.value = true
  try {
    await subscriptionApi.cancel()
    await auth.fetchMe()
    showToast('Suscripción cancelada con éxito', 'success')
  } catch {
    showToast('Error al cancelar la suscripción', 'error')
  } finally {
    saving.value = false
  }
}

async function pay(plan: string) {
  paying.value = plan
  try {
    const res = await mpApi.createSubscription(plan)
    if (res.data.init_point) window.location.href = res.data.init_point
  } catch {
    showToast('Error al conectar con Mercado Pago', 'error')
  } finally {
    paying.value = ''
  }
}

function copyCatalogLink() {
  const link = window.location.origin + window.location.pathname + '#/catalogo/' + (auth.user?.owner_id || auth.user?.id)
  navigator.clipboard.writeText(link)
  showToast('Link copiado al portapapeles', 'success')
}

const window = (globalThis as any).window
</script>

<template>
  <div class="settings-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Configuración</h1>
        <p class="page-subtitle">Personalizá tu empresa e integraciones desde aquí</p>
      </div>
    </div>

    <div v-if="loading" style="display:flex;justify-content:center;padding:4rem;">
      <span class="spinner" style="width:32px;height:32px;color:var(--primary);"></span>
    </div>

    <form v-else @submit.prevent="save" class="accordion-wrap">

      <!-- ── EMPRESA ─────────────────────────────────── -->
      <div class="acc-item" :class="{ open: openSection === 'empresa' }">
        <button type="button" class="acc-head" @click="toggleSection('empresa')">
          <span class="acc-icon" style="background:rgba(59,130,246,.12);color:#3b82f6;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </span>
          <div class="acc-meta">
            <strong>Empresa</strong>
            <small>Razón social, CUIT, logo y dirección fiscal</small>
          </div>
          <svg class="acc-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-inner">
            <!-- Logo -->
            <div class="logo-row">
              <div class="logo-box">
                <img v-if="logo.preview" :src="logo.preview" />
                <span v-else>Logo</span>
              </div>
              <div>
                <p class="label-bold">Logo de la empresa</p>
                <p class="text-muted text-xs mb-2">Aparecerá en tus facturas impresas. PNG o JPG.</p>
                <div class="flex gap-2">
                  <input type="file" @change="handleLogoChange" accept="image/*" class="form-input text-xs" style="max-width:180px;" />
                  <button type="button" class="btn btn-sm btn-ghost" :disabled="!logo.file || logo.uploading" @click="uploadLogo">
                    {{ logo.uploading ? '…' : 'Subir' }}
                  </button>
                </div>
              </div>
            </div>
            <!-- Campos -->
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
              <input v-model="form.company_address" class="form-input" placeholder="Av. Corrientes 1234, CABA" />
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Email de contacto</label>
                <input v-model="form.company_email" class="form-input" type="email" placeholder="contacto@empresa.com" />
              </div>
              <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input v-model="form.company_phone" class="form-input" placeholder="+54 11 1234-5678" />
              </div>
            </div>
            <div class="form-group" style="max-width:280px;">
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
      </div>

      <!-- ── CATÁLOGO ────────────────────────────────── -->
      <div class="acc-item" :class="{ open: openSection === 'catalogo' }">
        <button type="button" class="acc-head" @click="toggleSection('catalogo')">
          <span class="acc-icon" style="background:rgba(16,185,129,.12);color:#10b981;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          </span>
          <div class="acc-meta">
            <strong>Catálogo y Ventas</strong>
            <small>Tienda online, WhatsApp y cotización del dólar</small>
          </div>
          <svg class="acc-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-inner">
            <!-- Link catálogo -->
            <div class="info-pill">
              <div class="flex-1 overflow-hidden">
                <p class="label-bold mb-1">Link de tu catálogo público</p>
                <code class="catalog-code">{{ window.location.origin + window.location.pathname + '#/catalogo/' + (auth.user?.owner_id || auth.user?.id) }}</code>
              </div>
              <button type="button" class="btn btn-sm btn-primary" @click="copyCatalogLink">Copiar</button>
            </div>

            <div class="grid-2" style="margin-top:1.25rem;">
              <div class="form-group">
                <label class="form-label">WhatsApp (con código de país)</label>
                <input v-model="form.whatsapp_number" class="form-input" placeholder="5491112345678" />
              </div>
              <div class="form-group">
                <label class="form-label">Tema del catálogo</label>
                <div class="theme-pick">
                  <button type="button" class="tdot white" :class="{sel:form.catalog_theme==='white'}" @click="form.catalog_theme='white'" title="Blanco"></button>
                  <button type="button" class="tdot black" :class="{sel:form.catalog_theme==='black'}" @click="form.catalog_theme='black'" title="Negro"></button>
                  <button type="button" class="tdot blue"  :class="{sel:form.catalog_theme==='blue'}"  @click="form.catalog_theme='blue'"  title="Azul"></button>
                  <button type="button" class="tdot pink"  :class="{sel:form.catalog_theme==='pink'}"  @click="form.catalog_theme='pink'"  title="Rosa"></button>
                  <button type="button" class="tdot purple":class="{sel:form.catalog_theme==='purple'}" @click="form.catalog_theme='purple'" title="Violeta"></button>
                </div>
              </div>
            </div>

            <label class="toggle-row">
              <input type="checkbox" v-model="form.show_whatsapp_button" />
              <span>Mostrar botón de WhatsApp en catálogo</span>
            </label>

            <div class="divider"></div>

            <div class="form-group" style="max-width:280px;">
              <label class="form-label">Cotización del dólar</label>
              <select v-model="form.dollar_rate_mode" class="form-select">
                <option value="manual">Manual (fijo)</option>
                <option value="bcra">Automático – Oficial BCRA</option>
                <option value="blue">Automático – Blue / Informal</option>
              </select>
            </div>
            <div v-if="form.dollar_rate_mode === 'manual'" class="form-group" style="max-width:180px;">
              <label class="form-label">ARS por USD</label>
              <input v-model="form.dollar_rate_manual" class="form-input" type="number" step="0.01" min="0" />
            </div>
          </div>
        </div>
      </div>

      <!-- ── AFIP / ARCA ─────────────────────────────── -->
      <div class="acc-item" :class="{ open: openSection === 'arca' }">
        <button type="button" class="acc-head" @click="toggleSection('arca')">
          <span class="acc-icon" style="background:rgba(99,102,241,.12);color:#6366f1;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
          <div class="acc-meta">
            <strong>AFIP / ARCA</strong>
            <small>Certificados para facturación electrónica</small>
          </div>
          <span v-if="arca.connected" class="status-pill ok">Activo</span>
          <span v-else class="status-pill off">Sin vincular</span>
          <svg class="acc-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-inner">
            <!-- Conectado -->
            <div v-if="arca.connected" class="connected-box">
              <div class="check-circle">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
              </div>
              <div class="flex-1">
                <p class="font-bold text-sm">Facturación Electrónica Activa</p>
                <p class="text-xs text-muted">Tus facturas se comunican con AFIP en tiempo real.</p>
              </div>
              <button type="button" class="btn btn-sm btn-ghost" style="color:#f87171;" @click="resetArca" :disabled="arca.loading">Desconectar</button>
            </div>

            <!-- Sin conectar -->
            <div v-else>
              <div class="step-row">
                <div class="step-bubble">1</div>
                <div class="flex-1">
                  <p class="label-bold mb-1">Generá el pedido CSR</p>
                  <p class="text-xs text-muted mb-3">Ingresá tu CUIT sin guiones.</p>
                  <div class="flex gap-2">
                    <input v-model="arca.cuit" class="form-input" placeholder="20123456789" style="max-width:180px;" />
                    <button type="button" class="btn btn-sm btn-primary" :disabled="arca.loading" @click="generateCsr">Generar</button>
                  </div>
                </div>
              </div>
              <div v-if="arca.csr" class="csr-box">
                <p class="text-xs font-bold mb-2 text-muted">CSR generado — Copialo completo en AFIP:</p>
                <pre class="csr-pre">{{ arca.csr }}</pre>
                <button type="button" class="btn btn-xs btn-ghost w-full mt-2" @click="copyCsr">Copiar CSR</button>
              </div>
              <div class="step-row mt-4">
                <div class="step-bubble">2</div>
                <div class="flex-1">
                  <p class="label-bold mb-1">Cargá el certificado (.crt)</p>
                  <p class="text-xs text-muted mb-3">Pegá el contenido o subí el archivo que te entregó AFIP.</p>
                  <textarea v-model="arca.certText" class="form-input text-xs mb-3" rows="4" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
                  <div class="flex gap-2">
                    <input type="file" @change="handleFile" accept=".crt,.pem" class="form-input text-xs" />
                    <button type="button" class="btn btn-sm btn-primary" :disabled="(!arca.file && !arca.certText) || arca.loading" @click="uploadCert">Vincular</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── MERCADO PAGO ────────────────────────────── -->
      <div class="acc-item" :class="{ open: openSection === 'mp' }">
        <button type="button" class="acc-head" @click="toggleSection('mp')">
          <span class="acc-icon" style="background:rgba(0,158,227,.12);color:#009ee3;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
          </span>
          <div class="acc-meta">
            <strong>Mercado Pago</strong>
            <small>Cobros automáticos con Point o QR</small>
          </div>
          <svg class="acc-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-inner">
            <div class="info-pill" style="border-left:3px solid #009ee3; border-radius:8px; margin-bottom:1.25rem;">
              <p class="text-xs" style="color:var(--text-muted);">Vinculá tu cuenta para que cuando cobres con Point o QR, el sistema registre el pago automáticamente.</p>
            </div>
            <div class="grid-2">
              <div class="form-group">
                <label class="form-label">Access Token (Producción)</label>
                <input v-model="form.mp_access_token" type="password" class="form-input" placeholder="APP_USR-…" />
              </div>
              <div class="form-group">
                <label class="form-label">Public Key</label>
                <input v-model="form.mp_public_key" class="form-input" placeholder="APP_USR-…" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── PLAN ───────────────────────────────────── -->
      <div class="acc-item" :class="{ open: openSection === 'plan' }">
        <button type="button" class="acc-head" @click="toggleSection('plan')">
          <span class="acc-icon" style="background:rgba(234,179,8,.12);color:#eab308;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
          </span>
          <div class="acc-meta">
            <strong>Plan y Cuenta</strong>
            <small>Suscripción, facturación y datos de acceso</small>
          </div>
          <svg class="acc-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-inner">
            <!-- Plan actual banner -->
            <div class="current-plan-banner">
              <span class="cp-label">Plan actual</span>
              <span class="cp-name">{{ auth.user?.plan_status === 'trial' ? 'Prueba Gratis' : auth.user?.plan_status === 'monthly' ? 'Mensual Premium' : 'Anual Gold' }}</span>
            </div>

            <!-- Opciones -->
            <div class="plans-row">
              <div class="plan-card" :class="{ active: auth.user?.plan_status === 'monthly' }">
                <div class="plan-name">Mensual</div>
                <div class="plan-price">$35.000<small>/mes</small></div>
                <p class="plan-desc">Facturas y productos ilimitados.</p>
                <button type="button" class="btn btn-xs btn-ghost w-full" :disabled="paying === 'monthly'" @click="pay('monthly')">
                  <span v-if="paying === 'monthly'" class="spinner"></span>
                  <span v-else>{{ auth.user?.plan_status === 'monthly' ? 'Renovar' : 'Elegir' }}</span>
                </button>
              </div>
              <div class="plan-card featured" :class="{ active: auth.user?.plan_status === 'yearly' }">
                <div class="plan-badge">Mejor oferta</div>
                <div class="plan-name">Anual</div>
                <div class="plan-price">$310.000<small>/año</small></div>
                <p class="plan-desc">2 meses gratis + soporte prioritario.</p>
                <button type="button" class="btn btn-xs btn-primary w-full" :disabled="paying === 'yearly'" @click="pay('yearly')">
                  <span v-if="paying === 'yearly'" class="spinner"></span>
                  <span v-else>{{ auth.user?.plan_status === 'yearly' ? 'Renovar' : 'Elegir' }}</span>
                </button>
              </div>
            </div>

            <!-- Cancelar -->
            <div v-if="auth.user?.plan_status !== 'trial'" class="cancel-row">
              <button type="button" class="btn btn-xs btn-ghost" style="color:#f87171;" @click="cancelSubscription">Cancelar renovación automática</button>
            </div>

            <div class="divider"></div>

            <!-- Cuenta -->
            <p class="label-bold mb-3">Datos de la cuenta</p>
            <div class="account-row">
              <span class="text-muted text-sm">Nombre</span>
              <span class="font-bold text-sm">{{ auth.user?.name }}</span>
            </div>
            <div class="account-row">
              <span class="text-muted text-sm">Email</span>
              <span class="font-bold text-sm">{{ auth.user?.email }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── SAVE BAR ────────────────────────────────── -->
      <div class="save-bar">
        <span class="text-xs text-muted hide-on-mobile">Los cambios guardan toda la configuración.</span>
        <button type="submit" class="btn btn-primary save-btn" :disabled="saving">
          <span v-if="saving" class="spinner"></span>
          <template v-else>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Guardar Configuración
          </template>
        </button>
      </div>

    </form>

    <!-- Toast -->
    <Transition name="t">
      <div v-if="toast" class="toast-float" :class="toast.type">{{ toast.msg }}</div>
    </Transition>
  </div>
</template>

<style scoped>
.settings-page { max-width: 760px; padding-bottom: 6rem; }

/* ── ACORDEÓN ─────────────────────────────────────── */
.accordion-wrap { display: flex; flex-direction: column; gap: 0.6rem; }

.acc-item {
  border: 1px solid var(--border);
  border-radius: 14px;
  background: var(--bg-surface);
  overflow: hidden;
  transition: border-color .25s, box-shadow .25s;
}
.acc-item.open {
  border-color: rgba(99,102,241,.4);
  box-shadow: 0 4px 24px rgba(0,0,0,.12);
}

/* Header ─────────────────────────────────────────── */
.acc-head {
  width: 100%; display: flex; align-items: center; gap: 1rem;
  padding: 1.1rem 1.25rem;
  background: none; border: none; cursor: pointer; text-align: left;
}
.acc-head:hover .acc-meta strong { color: var(--primary); }

.acc-icon {
  width: 40px; height: 40px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.acc-meta { flex: 1; min-width: 0; }
.acc-meta strong { display: block; font-size: 0.92rem; font-weight: 700; color: var(--text-primary); transition: color .2s; }
.acc-meta small  { display: block; font-size: 0.76rem; color: var(--text-muted); margin-top: 1px; }

.acc-chevron { color: var(--text-faint); transition: transform .3s; flex-shrink: 0; }
.open .acc-chevron { transform: rotate(180deg); color: var(--primary); }

/* Status Pills */
.status-pill { font-size: 0.65rem; font-weight: 800; padding: 3px 9px; border-radius: 20px; flex-shrink: 0; }
.status-pill.ok  { background: rgba(16,185,129,.15); color: #10b981; }
.status-pill.off { background: rgba(99,102,241,.1);  color: var(--text-muted); }

/* Body ───────────────────────────────────────────── */
.acc-body {
  display: grid; grid-template-rows: 0fr;
  transition: grid-template-rows .3s cubic-bezier(.4,0,.2,1), opacity .2s;
  opacity: 0;
}
.open .acc-body { grid-template-rows: 1fr; opacity: 1; }
.acc-body > * { overflow: hidden; }

.acc-inner {
  padding: 0 1.25rem 1.5rem;
  border-top: 1px solid rgba(255,255,255,.04);
  padding-top: 1.25rem;
}

/* ── FIELDS ───────────────────────────────────────── */
.label-bold { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); }

.logo-row { display: flex; align-items: center; gap: 1.5rem; padding: 1rem; background: rgba(255,255,255,.02); border-radius: 12px; margin-bottom: 1.25rem; }
.logo-box { width: 68px; height: 68px; border-radius: 10px; background: var(--bg-card); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; font-size: 0.65rem; font-weight: 700; color: var(--text-faint); }
.logo-box img { width: 100%; height: 100%; object-fit: contain; }

.info-pill {
  display: flex; align-items: center; gap: 1rem;
  background: rgba(99,102,241,.06); border: 1px solid rgba(99,102,241,.12);
  border-radius: 10px; padding: 0.85rem 1rem;
}
.catalog-code { display: block; font-family: monospace; font-size: 0.7rem; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* Theme dots */
.theme-pick { display: flex; gap: 0.6rem; margin-top: 0.5rem; }
.tdot { width: 26px; height: 26px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; transition: .2s; }
.tdot.sel   { transform: scale(1.2); outline: 2px solid var(--primary); outline-offset: 2px; }
.tdot.white  { background:#fff; border-color:#ccc; }
.tdot.black  { background:#000; }
.tdot.blue   { background:#2563eb; }
.tdot.pink   { background:#db2777; }
.tdot.purple { background:#7c3aed; }

.toggle-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; cursor: pointer; margin-top: 0.75rem; }

.divider { height: 1px; background: var(--border); margin: 1.25rem 0; }

/* Steps AFIP */
.step-row { display: flex; gap: 1rem; }
.step-bubble { width: 26px; height: 26px; border-radius: 50%; background: var(--primary); color: #fff; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.csr-box { background: #0d1117; border: 1px solid var(--border); border-radius: 10px; padding: 1rem; margin-top: 1rem; }
.csr-pre { font-family: monospace; font-size: 10px; color: #58a6ff; white-space: pre-wrap; word-break: break-all; max-height: 100px; overflow-y: auto; margin: 0; }

.connected-box { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: rgba(16,185,129,.06); border: 1px solid rgba(16,185,129,.15); border-radius: 12px; }
.check-circle { width: 34px; height: 34px; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

/* Plans */
.current-plan-banner { background: linear-gradient(135deg, #1e293b, #0f172a); border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
.cp-label { display: block; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-faint); }
.cp-name  { display: block; font-size: 1.2rem; font-weight: 900; color: #fff; margin-top: 2px; }

.plans-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.plan-card { padding: 1.1rem; border: 1px solid var(--border); border-radius: 12px; position: relative; display: flex; flex-direction: column; gap: 0.5rem; }
.plan-card.featured { border-color: var(--primary); }
.plan-card.active   { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16,185,129,.15); }
.plan-badge { position: absolute; top: -9px; right: 10px; background: var(--primary); color: #fff; font-size: 0.6rem; font-weight: 800; padding: 2px 8px; border-radius: 20px; }
.plan-name  { font-size: 0.85rem; font-weight: 700; }
.plan-price { font-size: 1.2rem; font-weight: 900; color: var(--primary); }
.plan-price small { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
.plan-desc  { font-size: 0.78rem; color: var(--text-muted); flex: 1; }

.cancel-row { text-align: center; margin-top: 0.75rem; }
.account-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,.04); }

/* ── SAVE BAR ─────────────────────────────────────── */
.save-bar {
  position: fixed; bottom: 0; left: 240px; right: 0;
  display: flex; align-items: center; justify-content: flex-end; gap: 1.5rem;
  padding: 0.9rem 2rem;
  background: rgba(15,23,42,.85); backdrop-filter: blur(12px);
  border-top: 1px solid rgba(255,255,255,.06);
  z-index: 100;
}
.save-btn {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.65rem 2rem; border-radius: 100px; font-weight: 700;
  box-shadow: 0 0 20px rgba(59,130,246,.25); transition: all .2s;
}
.save-btn:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 0 30px rgba(59,130,246,.45); }

/* ── TOAST ────────────────────────────────────────── */
.toast-float {
  position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999;
  padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 600; color: #fff;
  box-shadow: 0 8px 24px rgba(0,0,0,.25);
}
.toast-float.success { background: #10b981; }
.toast-float.error   { background: #ef4444; }

.t-enter-active, .t-leave-active { transition: all .35s; }
.t-enter-from { opacity: 0; transform: translateX(40px); }
.t-leave-to   { opacity: 0; transform: translateX(40px); }

/* ── RESPONSIVE ───────────────────────────────────── */
@media (max-width: 900px) {
  .save-bar { left: 0; justify-content: center; }
  .hide-on-mobile { display: none; }
  .plans-row { grid-template-columns: 1fr; }
}
</style>
