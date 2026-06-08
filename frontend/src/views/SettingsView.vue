<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { settingsApi, arcaApi, subscriptionApi, posDevicesApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'
import { useRoute } from 'vue-router'

const auth = useAuthStore()
const route = useRoute()
const loading = ref(true)
const saving = ref(false)
const paying = ref('')
const toast = ref<{ msg: string; type: string } | null>(null)
const openSection = ref<string | null>('empresa')
const localIp = ref<string>('Detectando...')

function toggleSection(section: string) {
  openSection.value = openSection.value === section ? null : section
}

// Detecta la IP local vía WebRTC (sin servidor externo)
function detectLocalIp() {
  try {
    const pc = new RTCPeerConnection({ iceServers: [] })
    pc.createDataChannel('')
    pc.createOffer().then(o => pc.setLocalDescription(o))
    pc.onicecandidate = (e) => {
      if (!e || !e.candidate) return
      const match = e.candidate.candidate.match(
        /([0-9]{1,3}(\.[0-9]{1,3}){3})/
      )
      if (match) {
        const ip = match[1]
        // Filtrar IPs privadas de red local (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
        if (/^(192\.168|10\.|172\.(1[6-9]|2[0-9]|3[01]))\./.test(ip)) {
          localIp.value = ip
          pc.close()
        }
      }
    }
    setTimeout(() => {
      if (localIp.value === 'Detectando...') {
        localIp.value = 'No detectada — usá ipconfig'
      }
    }, 3000)
  } catch {
    localIp.value = 'No disponible — usá ipconfig'
  }
}

function copyIp() {
  if (localIp.value.includes('ipconfig')) return
  navigator.clipboard.writeText(localIp.value)
  showToast('IP copiada al portapapeles', 'success')
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
  catalog_theme: 'black',
  company_logo: '',
  slug: '',
  arca_pto_vta: 1,
  bank_name: '',
  bank_cbu: '',
  bank_alias: '',
  bank_holder: '',
  pos_provider: 'none',
  pos_clover_merchant_id: '',
  pos_clover_access_token: '',
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

const pos = reactive({
  devices: [] as any[],
  loading: false,
  newName: '',
  newType: 'mercadopago_point',
  newExternalId: '',
})

onMounted(async () => {
  detectLocalIp()
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
      catalog_theme:      s.catalog_theme === 'white' ? 'black' : (s.catalog_theme ?? 'black'),
      company_logo:       s.company_logo       ?? '',
      slug:               s.slug               ?? '',
      arca_pto_vta:       s.arca_pto_vta       ?? 1,
      bank_name:          s.bank_name          ?? '',
      bank_cbu:           s.bank_cbu           ?? '',
      bank_alias:         s.bank_alias         ?? '',
      bank_holder:        s.bank_holder        ?? '',
      pos_provider:       s.pos_provider       ?? 'none',
      pos_clover_merchant_id: s.pos_clover_merchant_id ?? '',
      pos_clover_access_token: s.pos_clover_access_token ?? '',
    })
    arca.cuit = s.arca_cuit ?? ''
    arca.connected = !!s.arca_connected
    if (s?.company_logo) {
      let path = s.company_logo
      // Limpiar la ruta para que no se duplique /storage o /api/storage
      path = path.replace('/storage/', '').replace('public/', '').replace('/api/storage/', '')
      logo.preview = '/api/storage/' + path
    }
    await fetchPosDevices()
  } finally {
    loading.value = false
  }
})

async function fetchPosDevices() {
  try {
    const res = await posDevicesApi.list()
    pos.devices = res.data.devices
  } catch (e) {
    console.error('Error fetching POS devices', e)
  }
}

async function addPosDevice() {
  if (!pos.newName || !pos.newExternalId) return
  pos.loading = true
  try {
    await posDevicesApi.create({
      name: pos.newName,
      type: pos.newType,
      external_id: pos.newExternalId
    })
    pos.newName = ''
    pos.newExternalId = ''
    await fetchPosDevices()
    showToast('Dispositivo agregado', 'success')
  } catch (e: any) {
    showToast(e.response?.data?.error ?? 'Error al agregar dispositivo', 'error')
  } finally {
    pos.loading = false
  }
}

async function deletePosDevice(id: number) {
  if (!confirm('¿Eliminar este dispositivo?')) return
  try {
    await posDevicesApi.delete(id)
    await fetchPosDevices()
    showToast('Dispositivo eliminado', 'success')
  } catch {
    showToast('Error al eliminar', 'error')
  }
}

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

async function handleLogoChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files && input.files[0]) {
    logo.file = input.files[0]
    logo.preview = URL.createObjectURL(input.files[0])
    
    // Subida automática al seleccionar
    logo.uploading = true
    const fd = new FormData()
    fd.append('logo', logo.file)
    try {
      const res = await settingsApi.uploadLogo(fd)
      const newPath = res.data.logo_url.replace('/api/storage/', '')
      form.company_logo = newPath
      logo.preview = '/api/storage/' + newPath
      showToast('Logo actualizado y guardado', 'success')
    } catch {
      showToast('Error al subir logo', 'error')
    } finally {
      logo.uploading = false
    }
  }
}

async function generateCsr() {
  if (!arca.cuit) return showToast('Ingresá el CUIT primero', 'error')
  arca.loading = true
  try {
    const res = await arcaApi.generateCsr(arca.cuit)
    arca.csr = res.data.csr
    downloadCsr()
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

function downloadCsr() {
  const blob = new Blob([arca.csr], { type: 'application/pkcs10' })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `pedido_factuflash_${arca.cuit || 'cuit'}.csr`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
  showToast('Descargando archivo CSR', 'success')
}

function copyToken() {
  const token = auth.user?.api_token ?? ''
  if (!token) { showToast('Token no disponible', 'error'); return }
  navigator.clipboard.writeText(token)
  showToast('Token copiado', 'success')
}

function downloadUrl(platform: string) {
  const token = auth.user?.api_token ?? ''
  const baseUrl = window.location.origin
  return `${baseUrl}/api/v1/pos-devices/download-agent/${platform}?token=${token}`
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
    const res = await subscriptionApi.checkout(plan)
    if (res.data.checkout_url) {
      window.location.href = res.data.checkout_url
    } else {
      showToast('No se pudo generar el link de pago', 'error')
    }
  } catch (e: any) {
    showToast(e.response?.data?.error ?? 'Error al conectar con Mercado Pago', 'error')
  } finally {
    paying.value = ''
  }
}


import { RouterLink } from 'vue-router'

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

      <!-- EMPRESA SECTION -->
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
                <div class="flex flex-col gap-2">
                  <div class="flex gap-2 items-center">
                    <label class="btn btn-sm btn-ghost" style="cursor:pointer;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                      Seleccionar imagen
                      <input type="file" @change="handleLogoChange" accept="image/*" style="display:none;" />
                    </label>
                    <span v-if="logo.uploading" class="spinner spinner-xs"></span>
                  </div>
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
            <!-- Datos de Transferencia Bancaria -->
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08);">
              <p class="label-bold mb-3" style="color: var(--primary);">Datos para Transferencias Bancarias</p>
              <div class="grid-2">
                <div class="form-group">
                  <label class="form-label">Banco</label>
                  <input v-model="form.bank_name" class="form-input" placeholder="Ej: Banco Galicia" />
                </div>
                <div class="form-group">
                  <label class="form-label">Titular de la Cuenta</label>
                  <input v-model="form.bank_holder" class="form-input" placeholder="Ej: Juan Pérez" />
                </div>
              </div>
              <div class="grid-2" style="margin-top: 1rem;">
                <div class="form-group">
                  <label class="form-label">CBU / CVU</label>
                  <input v-model="form.bank_cbu" class="form-input" placeholder="00701234..." />
                </div>
                <div class="form-group">
                  <label class="form-label">Alias</label>
                  <input v-model="form.bank_alias" class="form-input" placeholder="JUAN.GALICIA.ALIAS" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ——— ENLACE RÁPIDO CATÁLOGO ————————————————————————————————— -->
      <div class="acc-item">
        <div class="acc-head" style="cursor:default;">
          <span class="acc-icon" style="background:rgba(16,185,129,.12);color:#10b981;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          </span>
          <div class="acc-meta">
            <strong>Catálogo Web</strong>
            <small>Personalización avanzada y ventas online</small>
          </div>
          <RouterLink to="/configuracion/catalogo" class="btn btn-xs btn-primary">Configurar Catálogo</RouterLink>
        </div>
      </div>

      <!-- ——— AFIP / ARCA ————————————————————————————————————— -->
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
                <p class="text-xs font-bold mb-2 text-muted">CSR generado — Subí este archivo en AFIP:</p>
                <button type="button" class="btn btn-sm btn-primary w-full mt-2" @click="downloadCsr">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; display: inline-block; vertical-align: middle;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                  </svg>
                  Descargar archivo .csr
                </button>
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

            <!-- Configuración del Punto de Venta -->
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08);">
              <div class="form-group" style="max-width:280px;">
                <label class="form-label">Punto de Venta de Facturación Electrónica</label>
                <input v-model.number="form.arca_pto_vta" type="number" min="1" class="form-input" placeholder="Ej: 2" />
                <p class="text-[10px] text-muted mt-1">El Punto de Venta registrado en AFIP para Web Services (ej. 2). No uses el de facturación manual.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── POS DEVICES ── -->
      <div class="acc-item" :class="{ open: openSection === 'pos' }">
        <button type="button" class="acc-head" @click="toggleSection('pos')">
          <span class="acc-icon" style="background:rgba(16,185,129,.12);color:#10b981;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
          </span>
          <div class="acc-meta">
            <strong>Terminales de Pago (POS)</strong>
            <small>Gestioná tus Postnets integrados</small>
          </div>
          <svg class="acc-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-inner">
            <div class="pos-list mb-4">
              <div v-for="d in pos.devices" :key="d.id" class="pos-device-item">
                <div class="flex-1">
                  <strong>{{ d.name }}</strong>
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] uppercase font-bold text-muted">{{ d.type === 'mercadopago_point' ? 'MP Point' : 'Tradicional' }}</span>
                    <span class="text-[10px] text-faint">S/N: {{ d.external_id }}</span>
                  </div>
                </div>
                <button type="button" class="btn btn-xs btn-ghost" @click="deletePosDevice(d.id)" style="color:#f87171;">Eliminar</button>
              </div>
              <p v-if="!pos.devices.length" class="text-xs text-muted text-center py-2">No hay terminales configuradas</p>
            </div>

            <div class="add-pos-form">
              <p class="label-bold mb-2">Agregar Terminal</p>
              <div class="grid-2">
                <input v-model="pos.newName" class="form-input" placeholder="Nombre (ej: Caja 1)" />
                <select v-model="pos.newType" class="form-select">
                  <option value="mercadopago_point">Mercado Pago Point</option>
                  <option value="traditional_pos">Postnet Tradicional</option>
                </select>
              </div>
              <div class="flex gap-2 mt-2">
                <input v-model="pos.newExternalId" class="form-input" placeholder="N° de Serie / ID" />
                <button type="button" class="btn btn-primary" :disabled="pos.loading || !pos.newName || !pos.newExternalId" @click="addPosDevice">Agregar</button>
              </div>
              <p class="text-[10px] text-muted mt-2">Para MP Point: El N° de serie figura en la parte de atrás del equipo.</p>
            </div>

            <div class="divider"></div>

            <!-- AGENTE POS BRIDGE -->
            <div class="agent-section">
              <div class="agent-header">
                <div class="agent-icon-wrap">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                  </svg>
                </div>
                <div>
                  <p class="label-bold">Agente FactuFlash — Postnet Tradicional</p>
                  <p class="text-xs text-muted">Detecta cobros del posnet y crea facturas automáticamente</p>
                </div>
                <span class="agent-badge">Nuevo</span>
              </div>

              <!-- Aviso MercadoPago Point ARRIBA -->
              <div class="info-pill mb-4" style="border-left:3px solid #009ee3; border-radius:10px; background: rgba(0,158,227,0.07);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#009ee3" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <p class="text-xs"><strong style="color:#009ee3;">Punto MercadoPago:</strong> No necesita este agente. Cuando el cobro es aprobado, el sistema emite la factura automáticamente por la nube.</p>
              </div>

              <!-- Cómo funciona -->
              <div class="how-it-works">
                <div class="hiw-step">
                  <div class="hiw-dot">1</div>
                  <div class="hiw-text">
                    <strong>Descargás el agente</strong>
                    <span>Un programa pequeño que corre en la PC del negocio</span>
                  </div>
                </div>
                <div class="hiw-arrow">→</div>
                <div class="hiw-step">
                  <div class="hiw-dot">2</div>
                  <div class="hiw-text">
                    <strong>Configurás el postnet</strong>
                    <span>El postnet se conecta a tu PC <em>como si fuera una impresora de red</em>. Vos le decís la IP de tu PC y el puerto 9100. Desde ahí, cada cobro aprobado llega al Agente.</span>
                  </div>
                </div>
                <div class="hiw-arrow">→</div>
                <div class="hiw-step">
                  <div class="hiw-dot">3</div>
                  <div class="hiw-text">
                    <strong>¡Listo!</strong>
                    <span>Cada cobro aprobado crea la factura solo</span>
                  </div>
                </div>
              </div>

              <!-- Downloads -->
              <div class="download-card">
                <div class="download-left">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="1.5">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                  </svg>
                  <div>
                    <p class="font-bold text-sm">Agente FactuFlash</p>
                    <p class="text-xs text-muted">Seleccioná tu sistema operativo:</p>
                  </div>
                </div>
                <div class="flex gap-2">
                  <a :href="downloadUrl('windows')"
                     download="Agente_FactuFlash_Windows.zip"
                     class="btn btn-primary btn-sm" style="text-decoration:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M3 12L12 3v18l-9-9z"/></svg>
                    Windows
                  </a>
                  <a :href="downloadUrl('macos')"
                     download="Agente_FactuFlash_Mac.zip"
                     class="btn btn-primary btn-sm" style="text-decoration:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M9 7c-3 0-4 3-4 5s1 5 4 5c1.5 0 2.5-.5 3-1.5.5 1 1.5 1.5 3 1.5 3 0 4-3 4-5s-1-5-4-5c-1.5 0-2.5.5-3 1.5C11.5 7.5 10.5 7 9 7z"/></svg>
                    macOS
                  </a>
                </div>
              </div>

              <!-- Token -->
              <div class="token-section">
                <div class="flex items-center gap-2 mb-2">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1fede8" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                  <p class="label-bold">Llave de Vinculación</p>
                </div>
                <p class="text-xs text-muted mb-3">Este código es único y sirve para que el Agente sepa que debe enviar las facturas a tu cuenta.</p>
                <div class="token-box" style="background: rgba(31, 237, 232, 0.05); border: 1px dashed rgba(31, 237, 232, 0.3);">
                  <code class="token-value" style="color: #1fede8; font-weight: 600;">{{ auth.user?.api_token ?? 'Cargando llave...' }}</code>
                  <button type="button" class="btn btn-xs btn-primary" @click="copyToken" style="flex-shrink:0; background:#1fede8; color:#13124b;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;">
                      <rect x="9" y="9" width="13" height="13" rx="2"/>
                      <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                    Copiar Llave
                  </button>
                </div>
                <p class="text-[10px] opacity-40 mt-2">⚠️ No compartas esta llave con nadie ajeno a tu negocio.</p>
              </div>

              <!-- Pasos Simplificados -->
              <div class="install-steps">
                <p class="label-bold mb-3">Cómo instalar:</p>

                <div class="install-step">
                  <div class="is-num">1</div>
                  <div class="is-body">
                    <p class="is-title">Descargá y abrí el programa</p>
                    <p class="text-xs text-muted">Hacé doble clic en <code>Agente_FactuFlash.exe</code> para iniciar.</p>
                  </div>
                </div>

                <div class="install-step">
                  <div class="is-num">2</div>
                  <div class="is-body">
                    <p class="is-title">Vinculá tu cuenta</p>
                    <p class="text-xs text-muted">Pegá el Token de acceso que copiaste arriba en la ventana del programa.</p>
                  </div>
                </div>

                <div class="install-step">
                  <div class="is-num">3</div>
                  <div class="is-body">
                    <p class="is-title">¡Listo para facturar!</p>
                    <p class="text-xs text-muted">El agente se quedará trabajando en la barra de tareas (junto al reloj) automáticamente.</p>
                  </div>
                </div>
              </div>

                <div class="install-step">
                  <div class="is-num">4</div>
                  <div class="is-body">
                    <p class="is-title">Configurá la impresora de red en tu postnet</p>
                    <p class="text-xs text-muted mb-2">En el menú del postnet buscá <strong>Impresora de Red</strong> o <strong>Network Printer</strong>:</p>
                    <div class="network-config">
                      <div class="nc-row">
                        <span class="nc-label">IP de la PC:</span>
                        <span class="nc-val" style="display:flex; align-items:center; gap:8px;">
                          <code style="color:var(--primary); font-weight:700; font-size:0.9rem;">{{ localIp }}</code>
                          <button
                            v-if="!localIp.includes('ipconfig') && !localIp.includes('Detectando')"
                            type="button"
                            class="btn btn-xs btn-ghost"
                            style="padding:2px 8px; font-size:10px;"
                            @click="copyIp"
                            title="Copiar IP"
                          >
                            📋 Copiar
                          </button>
                          <span v-if="localIp === 'Detectando...'" class="spinner spinner-xs"></span>
                        </span>
                      </div>
                      <div class="nc-row">
                        <span class="nc-label">Puerto:</span>
                        <span class="nc-val" style="color:var(--primary);font-weight:700;">9100</span>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="install-step">
                  <div class="is-num">5</div>
                  <div class="is-body">
                    <p class="is-title">Opcional: Iniciar automáticamente con Windows</p>
                    <div class="code-block" style="font-size:10px;">Copiá el .py a: Inicio → Ejecutar → shell:startup</div>
                  </div>
                </div>
              </div>

              <!-- Nota final MP se eliminó de acá porque se movió arriba -->
            </div>
          </div>
        </div>

      <!-- POSNET INTEGRATION SECTION -->
      <div class="acc-item" :class="{ open: openSection === 'mp' }">
        <button type="button" class="acc-head" @click="toggleSection('mp')">
          <span class="acc-icon" style="background:rgba(0,158,227,.12);color:#009ee3;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
          </span>
          <div class="acc-meta">
            <strong>Integración de Pagos / Posnet</strong>
            <small>Cobros automáticos con Mercado Pago o Clover</small>
          </div>
          <svg class="acc-chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="acc-body">
          <div class="acc-inner">
            <div class="info-pill" style="border-left:3px solid #009ee3; border-radius:8px; margin-bottom:1.25rem;">
              <p class="text-xs" style="color:var(--text-muted);">Vinculá tu cuenta para que cuando cobres, el sistema registre el pago automáticamente.</p>
            </div>
            
            <div class="form-group mb-4">
              <label class="form-label">Proveedor de Posnet</label>
              <select v-model="form.pos_provider" class="form-select">
                <option value="none">Ninguno</option>
                <option value="mercadopago">Mercado Pago</option>
                <option value="clover">Clover</option>
              </select>
            </div>

            <div v-if="form.pos_provider === 'mercadopago'" class="grid-2">
              <div class="form-group">
                <label class="form-label">Access Token (Producción)</label>
                <input v-model="form.mp_access_token" type="password" autocomplete="off" class="form-input" placeholder="APP_USR-…" />
              </div>
              <div class="form-group">
                <label class="form-label">Public Key</label>
                <input v-model="form.mp_public_key" class="form-input" placeholder="APP_USR-…" />
              </div>
            </div>

            <div v-if="form.pos_provider === 'clover'" class="grid-2">
              <div class="form-group">
                <label class="form-label">Merchant ID</label>
                <input v-model="form.pos_clover_merchant_id" class="form-input" placeholder="Ej: A1B2C3D4E5F6G" />
              </div>
              <div class="form-group">
                <label class="form-label">API Access Token</label>
                <input v-model="form.pos_clover_access_token" type="password" autocomplete="off" class="form-input" placeholder="Ej: 01a2b3c4-d5e6-..." />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PLAN SECTION -->
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
            <!-- Billing Section -->
            <div class="billing-container">
              <div class="billing-header">
                <span class="billing-badge">SUSCRIPCIÓN</span>
                <h2 class="billing-title">Tu Suscripción Activa</h2>
                <p class="billing-subtitle">Gestioná tu plan actual y detalles de facturación.</p>
              </div>

              <!-- Active Status Banner -->
              <div class="active-plan-banner">
                <div class="banner-left">
                  <div class="status-label">ESTADO ACTUAL: <span class="status-active">ACTIVA</span></div>
                  <p class="plan-info"><strong>Plan {{ auth.user?.plan_status === 'monthly' ? 'Mensual' : 'Anual' }}</strong> activo. Próxima renovación: <strong>10 de junio de 2026</strong></p>
                </div>
                <button type="button" class="btn-cancel-subs" @click="cancelSubscription">Cancelar renovación automática</button>
              </div>
              <p class="cancel-disclaimer">* Al cancelar, dejarás de recibir cargos automáticos pero mantendrás el acceso hasta el final de tu período actual.</p>

              <!-- Plans Grid -->
              <div class="plans-grid-minimal">
                <!-- Monthly -->
                <div class="plan-card-minimal" :class="{ selected: auth.user?.plan_status === 'monthly' }">
                  <div class="tag popular">MÁS ELEGIDO</div>
                  <div class="plan-head">
                    <div class="plan-icon-svg">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M12 12h.01"/><path d="M17 12h.01"/><path d="M7 12h.01"/></svg>
                    </div>
                    <h3 class="name">Plan Mensual</h3>
                    <p class="desc">Ideal para empezar sin compromiso</p>
                  </div>
                  <div class="price">
                    <span class="sym">$</span>
                    <span class="num">35.000</span>
                    <span class="per">/ mes</span>
                  </div>
                  <ul class="features">
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Facturas ilimitadas</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Productos y Stock Pro</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Integración ARCA + CAE</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Mercado Pago Checkouts</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Soporte prioritario WhatsApp</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Dashboard en tiempo real</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Reportes y Exportación</li>
                  </ul>
                  <div class="selection-indicator"></div>
                </div>

                <!-- Yearly -->
                <div class="plan-card-minimal featured" :class="{ selected: auth.user?.plan_status === 'yearly' }">
                  <div class="tag save">AHORRÁS $100.000</div>
                  <div class="plan-head">
                    <div class="plan-icon-svg">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    </div>
                    <h3 class="name">Plan Anual</h3>
                    <p class="desc">Ahorrá y congelá precio por un año</p>
                  </div>
                  <div class="price">
                    <span class="sym">$</span>
                    <span class="num">320.000</span>
                    <span class="per">/ año</span>
                  </div>
                  <ul class="features">
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Facturas ilimitadas</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Productos y Stock Pro</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Integración ARCA + CAE</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Mercado Pago Checkouts</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Soporte prioritario WhatsApp</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Dashboard en tiempo real</li>
                    <li><svg class="check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Reportes y Exportación</li>
                  </ul>
                  <div class="selection-indicator gold"></div>
                </div>
              </div>

              <!-- Selection Bar -->
              <div class="plan-selection-bar">
                <div class="selection-info">
                  <span class="label">PLAN SELECCIONADO</span>
                  <div class="selected-plan-name">
                    Plan {{ auth.user?.plan_status === 'monthly' ? 'Mensual' : 'Anual' }}
                    <span class="selected-price">${{ auth.user?.plan_status === 'monthly' ? '35.000' : '320.000' }}/mes</span>
                  </div>
                </div>
                <button type="button" class="btn-pay-secure" @click="pay(auth.user?.plan_status || 'monthly')">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                  Pagar suscripción segura
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

      <!-- SAVE BAR SECTION -->
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

/* â”€â”€ ACORDEÓN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* Header â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* Body â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ FIELDS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

.slug-input-wrap { display: flex; align-items: center; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; }
.slug-prefix { padding: 0 0.75rem; background: var(--bg-muted); color: var(--text-muted); font-size: 0.8rem; border-right: 1px solid var(--border); height: 38px; display: flex; align-items: center; }
.slug-input-wrap .form-input { border: none; border-radius: 0; flex: 1; }

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

/* Billing Section Minimalist (Photo Style) */
.billing-container { padding: 1rem 0; }

.billing-header { margin-bottom: 2.5rem; }
.billing-badge { 
  background: rgba(99, 102, 241, 0.1); 
  color: #6366f1; 
  font-size: 10px; 
  font-weight: 800; 
  padding: 4px 12px; 
  border-radius: 6px; 
  letter-spacing: 1px;
}
.billing-title { font-size: 2.2rem; font-weight: 900; margin: 1.5rem 0 0.5rem; }
.billing-subtitle { color: var(--text-muted); font-size: 1rem; }

.active-plan-banner {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 20px;
  padding: 1.5rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}
.status-label { font-size: 11px; font-weight: 800; color: #6366f1; letter-spacing: 1px; margin-bottom: 4px; }
.status-active { color: #10b981; margin-left: 10px; background: rgba(16,185,129,0.1); padding: 2px 8px; border-radius: 4px; }
.plan-info { font-size: 0.95rem; color: #fff; }
.btn-cancel-subs {
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #ef4444;
  font-size: 12px;
  font-weight: 700;
  padding: 8px 16px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-cancel-subs:hover { background: rgba(239, 68, 68, 0.1); }
.cancel-disclaimer { font-size: 11px; font-style: italic; opacity: 0.4; margin-bottom: 3rem; padding-left: 1rem; }

.plans-grid-minimal {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  margin-bottom: 4rem;
}

.plan-card-minimal {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 24px;
  padding: 3rem 2rem;
  position: relative;
  display: flex;
  flex-direction: column;
  transition: all 0.3s;
}
.plan-card-minimal:hover { transform: translateY(-5px); border-color: rgba(99, 102, 241, 0.3); }
.plan-card-minimal.selected { border-color: #6366f1; background: rgba(99, 102, 241, 0.02); }
.plan-card-minimal.featured.selected { border-color: #f59e0b; background: rgba(245, 158, 11, 0.02); }

.tag {
  position: absolute;
  top: -10px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 10px;
  font-weight: 900;
  padding: 4px 16px;
  border-radius: 100px;
  white-space: nowrap;
}
.tag.popular { background: #6366f1; color: white; box-shadow: 0 0 15px rgba(99, 102, 241, 0.4); }
.tag.save { background: #f59e0b; color: white; box-shadow: 0 0 15px rgba(245, 158, 11, 0.4); }

.plan-head { margin-bottom: 2rem; text-align: center; }
.plan-icon-svg { color: var(--primary); margin-bottom: 1rem; opacity: 0.8; }
.plan-card-minimal.featured .plan-icon-svg { color: #f59e0b; }
.name { font-size: 1.5rem; font-weight: 900; margin-bottom: 0.25rem; }
.desc { font-size: 0.85rem; opacity: 0.5; }

.price { display: flex; align-items: baseline; gap: 4px; justify-content: center; margin-bottom: 2.5rem; }
.price .sym { font-size: 1.2rem; font-weight: 700; opacity: 0.4; }
.price .num { font-size: 2.8rem; font-weight: 950; color: #fff; }
.price .per { font-size: 0.9rem; opacity: 0.4; }
.plan-card-minimal.featured .num { color: #f59e0b; }

.features { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 1rem; }
.features li { display: flex; align-items: center; gap: 12px; font-size: 0.9rem; font-weight: 600; opacity: 0.7; }
.check { width: 14px; height: 14px; color: #f59e0b; flex-shrink: 0; }

.selection-indicator { height: 4px; width: 80%; background: #6366f1; position: absolute; bottom: 30px; left: 10%; border-radius: 100px; opacity: 0.2; }
.selection-indicator.gold { background: #f59e0b; }
.selected .selection-indicator { opacity: 1; }

.plan-selection-bar {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 20px;
  padding: 1.25rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  backdrop-filter: blur(10px);
}
.selection-info .label { font-size: 10px; font-weight: 800; color: #6366f1; letter-spacing: 1px; }
.selected-plan-name { font-size: 1.2rem; font-weight: 900; margin-top: 4px; }
.selected-price { font-size: 0.85rem; font-weight: 600; opacity: 0.5; margin-left: 10px; }
.btn-pay-secure {
  background: #7c3aed;
  color: white;
  font-weight: 800;
  padding: 12px 24px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
  transition: all 0.2s;
}
.btn-pay-secure:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4); }

@media (max-width: 640px) {
  .plans-grid-minimal { grid-template-columns: 1fr; }
  .active-plan-banner, .plan-selection-bar { flex-direction: column; gap: 1rem; text-align: center; }
}

.cancel-row { text-align: center; margin-top: 0.75rem; }
.account-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,.04); }

/* â”€â”€ SAVE BAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ TOAST â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
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

/* â”€â”€ RESPONSIVE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
@media (max-width: 900px) {
  .save-bar { left: 0; justify-content: center; }
  .hide-on-mobile { display: none; }
  .plans-row { grid-template-columns: 1fr; }
}

.pos-device-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  background: rgba(255,255,255,0.02);
  border: 1px solid var(--border);
  border-radius: 10px;
  margin-bottom: 0.5rem;
}
.pos-device-item strong { font-size: 0.85rem; }
.add-pos-form {
  background: var(--bg-elevated);
  padding: 1.25rem;
  border-radius: 12px;
  border: 1px dashed var(--border);
}

/* ── AGENT SECTION ────────────────────────────────────────────── */
.agent-section { margin-top: 0.5rem; }

.agent-header {
  display: flex; align-items: center; gap: 1rem;
  padding: 1rem; background: rgba(99,102,241,.06);
  border: 1px solid rgba(99,102,241,.15);
  border-radius: 12px; margin-bottom: 1.25rem;
}
.agent-icon-wrap {
  width: 40px; height: 40px; border-radius: 10px;
  background: rgba(99,102,241,.15); color: var(--primary);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.agent-badge {
  margin-left: auto; background: var(--primary); color: #fff;
  font-size: 0.6rem; font-weight: 800; padding: 3px 9px;
  border-radius: 20px; flex-shrink: 0;
}

/* How it works */
.how-it-works {
  display: flex; align-items: center; gap: 0.5rem;
  background: var(--bg-elevated); border: 1px solid var(--border);
  border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem;
  flex-wrap: wrap;
}
.hiw-step {
  display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 120px;
}
.hiw-dot {
  width: 28px; height: 28px; border-radius: 50%;
  background: var(--primary); color: #fff;
  font-size: 0.75rem; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.hiw-text { display: flex; flex-direction: column; }
.hiw-text strong { font-size: 0.8rem; font-weight: 700; }
.hiw-text span { font-size: 0.72rem; color: var(--text-muted); }
.hiw-arrow { color: var(--text-faint); font-size: 1.2rem; flex-shrink: 0; }

/* Download card */
.download-card {
  display: flex; align-items: center; justify-content: space-between;
  gap: 1rem; padding: 1rem 1.25rem;
  background: linear-gradient(135deg, rgba(99,102,241,.08), rgba(139,92,246,.05));
  border: 1px solid rgba(99,102,241,.2); border-radius: 12px;
  margin-bottom: 1.25rem;
}
.download-left { display: flex; align-items: center; gap: 1rem; }

/* Token */
.token-section {
  background: var(--bg-elevated); border: 1px solid var(--border);
  border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem;
}
.token-box {
  display: flex; align-items: center; gap: 0.5rem;
  background: #0d1117; border: 1px solid var(--border);
  border-radius: 8px; padding: 0.5rem 0.75rem;
}
.token-value {
  font-family: monospace; font-size: 0.72rem; color: #58a6ff;
  flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* Install steps */
.install-steps {
  background: var(--bg-elevated); border: 1px solid var(--border);
  border-radius: 12px; padding: 1.25rem; margin-bottom: 1.25rem;
}
.install-step {
  display: flex; gap: 1rem; margin-bottom: 1.25rem;
}
.install-step:last-child { margin-bottom: 0; }
.is-num {
  width: 26px; height: 26px; border-radius: 50%;
  background: rgba(99,102,241,.15); color: var(--primary);
  font-size: 0.75rem; font-weight: 800;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  margin-top: 2px;
}
.is-body { flex: 1; min-width: 0; }
.is-title { font-size: 0.83rem; font-weight: 700; margin-bottom: 0.4rem; }

.code-block {
  background: #0d1117; border: 1px solid rgba(255,255,255,.08);
  border-radius: 8px; padding: 0.6rem 0.9rem;
  font-family: monospace; font-size: 0.78rem; color: #79c0ff;
  word-break: break-all; white-space: pre-wrap;
}

/* Network config */
.network-config {
  background: rgba(99,102,241,.06); border: 1px solid rgba(99,102,241,.1);
  border-radius: 8px; padding: 0.75rem 1rem; margin-top: 0.5rem;
}
.nc-row {
  display: flex; gap: 1rem; align-items: center;
  padding: 0.3rem 0; font-size: 0.82rem;
}
.nc-label { color: var(--text-muted); font-weight: 600; min-width: 100px; }
.nc-val { font-weight: 600; }

/* --- AGENT SECTION --- */
.agent-section {
  margin-top: 1.5rem;
  padding: 1.5rem;
  background: rgba(255, 255, 255, 0.03);
  border-radius: 20px;
  border: 1px border-white/5;
}

.agent-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.agent-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.agent-badge {
  margin-left: auto;
  background: #6366f1;
  color: white;
  font-size: 10px;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 100px;
  text-transform: uppercase;
}

.how-it-works {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 2rem;
  padding: 1rem;
  background: rgba(0,0,0,0.2);
  border-radius: 16px;
}

.hiw-step {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.5rem;
}

.hiw-dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #6366f1;
  color: white;
  font-size: 12px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
}

.hiw-text strong {
  display: block;
  font-size: 11px;
  margin-bottom: 2px;
}

.hiw-text span {
  font-size: 10px;
  opacity: 0.5;
}

.hiw-arrow {
  opacity: 0.2;
  font-weight: bold;
}

.download-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.2rem;
  background: rgba(99, 102, 241, 0.05);
  border: 1px solid rgba(99, 102, 241, 0.2);
  border-radius: 16px;
  margin-bottom: 1.5rem;
}

.download-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.token-section {
  margin-bottom: 1.5rem;
}

.token-box {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  padding: 0.8rem 1rem;
  background: rgba(0,0,0,0.3);
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,0.05);
}

.token-value {
  flex: 1;
  font-family: monospace;
  font-size: 12px;
  color: #1fede8;
  word-break: break-all;
}

.install-steps {
  border-top: 1px solid rgba(255,255,255,0.05);
  padding-top: 1.5rem;
}

.install-step {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.2rem;
}

.is-num {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: bold;
  flex-shrink: 0;
  margin-top: 2px;
}

.is-title {
  font-size: 12px;
  font-weight: bold;
  margin-bottom: 0.5rem;
}

.code-block {
  background: #000;
  padding: 0.6rem 0.8rem;
  border-radius: 8px;
  font-family: monospace;
  font-size: 11px;
  color: #ddd;
  overflow-x: auto;
}

@media (max-width: 640px) {
  .how-it-works { flex-direction: column; gap: 1.5rem; }
  .hiw-arrow { display: none; }
  .download-card { flex-direction: column; gap: 1rem; text-align: center; }
  .download-left { flex-direction: column; }
}
</style>
