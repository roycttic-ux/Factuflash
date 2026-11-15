<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { settingsApi, arcaApi } from '@/api/services'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const step = ref(1)
const TOTAL_STEPS = 4
const saving = ref(false)
const error = ref('')
const toast = ref('')

// ── Step 1: Empresa ──
const empresa = ref({
  company_name: auth.user?.company_name ?? '',
  company_cuit: '',
  company_address: '',
  company_phone: '',
  company_email: auth.user?.email ?? '',
  tax_condition: 'Responsable Inscripto',
})

// ── Step 2: Punto de Venta ──
const arca = ref({
  arca_pto_vta: 1,
  invoice_prefix: 'F',
})

// ── Step 3: Certificado ARCA ──
const cert = ref({
  cuit: '',
  loading: false,
  csr: '',
  certFile: null as File | null,
  certText: '',
  connected: false,
  skipped: false,
})

const progress = computed(() => Math.round(((step.value - 1) / TOTAL_STEPS) * 100))

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

async function saveStep1() {
  if (!empresa.value.company_name.trim()) {
    error.value = 'El nombre de empresa es obligatorio.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    await settingsApi.update({
      company_name: empresa.value.company_name,
      company_cuit: empresa.value.company_cuit,
      company_address: empresa.value.company_address,
      company_phone: empresa.value.company_phone,
      company_email: empresa.value.company_email,
      tax_condition: empresa.value.tax_condition,
    })
    step.value = 2
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Error al guardar. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}

async function saveStep2() {
  if (!arca.value.arca_pto_vta || arca.value.arca_pto_vta < 1) {
    error.value = 'El número de punto de venta debe ser al menos 1.'
    return
  }
  saving.value = true
  error.value = ''
  try {
    await settingsApi.update({
      arca_pto_vta: arca.value.arca_pto_vta,
      invoice_prefix: arca.value.invoice_prefix,
    })
    step.value = 3
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'Error al guardar. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}

async function generateCsr() {
  if (!cert.value.cuit) {
    error.value = 'Ingresá el CUIT para generar el CSR.'
    return
  }
  cert.value.loading = true
  error.value = ''
  try {
    const res = await arcaApi.generateCsr(cert.value.cuit)
    cert.value.csr = res.data.csr
    showToast('CSR generado. Descargalo y subilo a AFIP para obtener el certificado.')
  } catch (e: any) {
    error.value = e?.response?.data?.error ?? 'Error al generar CSR.'
  } finally {
    cert.value.loading = false
  }
}

function downloadCsr() {
  const blob = new Blob([cert.value.csr], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `factuflash_${cert.value.cuit}.csr`
  a.click()
  URL.revokeObjectURL(url)
}

function onCertFileChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) cert.value.certFile = file
}

async function uploadCert() {
  if (!cert.value.certFile) {
    error.value = 'Seleccioná el archivo .crt descargado desde AFIP.'
    return
  }
  cert.value.loading = true
  error.value = ''
  try {
    const fd = new FormData()
    fd.append('cert', cert.value.certFile)
    fd.append('cuit', cert.value.cuit)
    await arcaApi.uploadCert(fd)
    cert.value.connected = true
    showToast('¡Certificado cargado! ARCA conectada correctamente.')
  } catch (e: any) {
    error.value = e?.response?.data?.error ?? 'Error al subir el certificado.'
  } finally {
    cert.value.loading = false
  }
}

function skipCert() {
  cert.value.skipped = true
  step.value = 4
}

function goToStep4() {
  step.value = 4
}

async function finishOnboarding() {
  saving.value = true
  error.value = ''
  try {
    await settingsApi.completeOnboarding()
    await auth.fetchMe()
    router.push({ name: 'dashboard' })
  } catch (e: any) {
    error.value = 'Error al guardar. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="onb-wrapper">
    <!-- BG orbs -->
    <div class="onb-bg">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="grid-pattern"></div>
    </div>

    <!-- Toast -->
    <Transition name="fade">
      <div v-if="toast" class="onb-toast">✓ {{ toast }}</div>
    </Transition>

    <div class="onb-card">
      <!-- Header -->
      <div class="onb-header">
        <div class="onb-logo">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:white">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
          </svg>
        </div>
        <div>
          <h1 class="onb-title">Configuración inicial</h1>
          <p class="onb-sub">Completá estos pasos para empezar a facturar</p>
        </div>
      </div>

      <!-- Progress bar -->
      <div class="onb-progress-wrap">
        <div class="onb-progress-bar" :style="{ width: progress + '%' }"></div>
      </div>
      <div class="onb-steps-label">
        <span>Paso {{ step }} de {{ TOTAL_STEPS }}</span>
        <span>{{ progress }}%</span>
      </div>

      <!-- Step dots -->
      <div class="onb-dots">
        <div v-for="s in TOTAL_STEPS" :key="s" class="onb-dot" :class="{ active: s === step, done: s < step }">
          <span v-if="s < step">✓</span>
          <span v-else>{{ s }}</span>
        </div>
      </div>

      <!-- ══ STEP 1: Empresa ══ -->
      <Transition name="slide-fade" mode="out-in">
        <div v-if="step === 1" key="step1" class="onb-body">
          <div class="step-icon">🏢</div>
          <h2 class="step-title">Datos de tu empresa</h2>
          <p class="step-desc">Esta información aparecerá en tus facturas y en el catálogo público.</p>

          <div class="form-grid">
            <div class="form-group span-2">
              <label class="form-label">Nombre de empresa o comercio <span class="required">*</span></label>
              <input v-model="empresa.company_name" class="form-input" placeholder="Ej: Ferretería El Tornillo" id="onb-company-name" />
            </div>
            <div class="form-group">
              <label class="form-label">CUIT</label>
              <input v-model="empresa.company_cuit" class="form-input" placeholder="20-12345678-9" id="onb-cuit" />
            </div>
            <div class="form-group">
              <label class="form-label">Condición IVA</label>
              <select v-model="empresa.tax_condition" class="form-input">
                <option>Responsable Inscripto</option>
                <option>Monotributista</option>
                <option>Exento</option>
                <option>Consumidor Final</option>
              </select>
            </div>
            <div class="form-group span-2">
              <label class="form-label">Dirección fiscal</label>
              <input v-model="empresa.company_address" class="form-input" placeholder="Ej: Av. Corrientes 1234, CABA" id="onb-address" />
            </div>
            <div class="form-group">
              <label class="form-label">Teléfono</label>
              <input v-model="empresa.company_phone" class="form-input" placeholder="Ej: 011-4567-8901" />
            </div>
            <div class="form-group">
              <label class="form-label">Email de contacto</label>
              <input v-model="empresa.company_email" type="email" class="form-input" placeholder="ventas@tuempresa.com" />
            </div>
          </div>

          <p v-if="error" class="onb-error">{{ error }}</p>
          <button class="btn btn-primary onb-btn" @click="saveStep1" :disabled="saving" id="onb-next-1">
            <span v-if="saving" class="spinner"></span>
            <span v-else>Guardar y continuar →</span>
          </button>
        </div>
      </Transition>

      <!-- ══ STEP 2: Punto de Venta ══ -->
      <Transition name="slide-fade" mode="out-in">
        <div v-if="step === 2" key="step2" class="onb-body">
          <div class="step-icon">🔢</div>
          <h2 class="step-title">Punto de Venta ARCA</h2>
          <p class="step-desc">
            El Punto de Venta es el número que ARCA asigna a cada caja o sistema. Si tenés dudas, usá <strong>1</strong> para empezar.
          </p>

          <div class="info-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Para ver tus puntos de venta disponibles, ingresá a <strong>ARCA → Servicios habilitados → Mis puntos de venta</strong>.
          </div>

          <div class="form-grid" style="max-width:340px;">
            <div class="form-group">
              <label class="form-label">Número de Punto de Venta <span class="required">*</span></label>
              <input v-model.number="arca.arca_pto_vta" type="number" min="1" class="form-input" id="onb-pto-vta" />
            </div>
            <div class="form-group">
              <label class="form-label">Prefijo de factura</label>
              <select v-model="arca.invoice_prefix" class="form-input">
                <option value="F">F (Facturas)</option>
                <option value="V">V (Ventas)</option>
                <option value="A">A</option>
              </select>
            </div>
          </div>

          <p v-if="error" class="onb-error">{{ error }}</p>
          <div class="onb-btn-row">
            <button class="btn btn-ghost" @click="step = 1">← Volver</button>
            <button class="btn btn-primary onb-btn" @click="saveStep2" :disabled="saving" id="onb-next-2">
              <span v-if="saving" class="spinner"></span>
              <span v-else>Guardar y continuar →</span>
            </button>
          </div>
        </div>
      </Transition>

      <!-- ══ STEP 3: Certificado ARCA ══ -->
      <Transition name="slide-fade" mode="out-in">
        <div v-if="step === 3" key="step3" class="onb-body">
          <div class="step-icon">🔐</div>
          <h2 class="step-title">Certificado Digital ARCA</h2>
          <p class="step-desc">
            Para emitir facturas con CAE necesitás un certificado digital vinculado a tu CUIT en ARCA. Si no lo tenés listo, podés saltear este paso y configurarlo después.
          </p>

          <div v-if="!cert.connected" class="cert-steps">
            <!-- Sub-paso 1: Generar CSR -->
            <div class="cert-step-item" :class="{ done: cert.csr }">
              <div class="cert-step-num">{{ cert.csr ? '✓' : '1' }}</div>
              <div class="cert-step-body">
                <div class="cert-step-title">Generar solicitud de certificado (CSR)</div>
                <div class="cert-step-sub">Ingresá tu CUIT y generamos el archivo .csr para subir a ARCA.</div>
                <div class="flex gap-2 mt-2" v-if="!cert.csr">
                  <input v-model="cert.cuit" class="form-input" placeholder="20-12345678-9" style="max-width:180px;" id="onb-cert-cuit" />
                  <button class="btn btn-primary btn-sm" @click="generateCsr" :disabled="cert.loading">
                    <span v-if="cert.loading" class="spinner"></span>
                    <span v-else>Generar CSR</span>
                  </button>
                </div>
                <button v-if="cert.csr" class="btn btn-ghost btn-sm mt-2" @click="downloadCsr">
                  ⬇️ Descargar CSR
                </button>
              </div>
            </div>

            <!-- Sub-paso 2: Subir .crt -->
            <div class="cert-step-item" :class="{ done: cert.connected, locked: !cert.csr }">
              <div class="cert-step-num">2</div>
              <div class="cert-step-body">
                <div class="cert-step-title">Subir certificado desde ARCA</div>
                <div class="cert-step-sub">Una vez que ARCA te apruebe el .crt, subilo acá.</div>
                <div v-if="cert.csr" class="flex gap-2 mt-2">
                  <label class="btn btn-ghost btn-sm" for="onb-cert-file" style="cursor:pointer;">
                    📁 Elegir archivo .crt
                    <input type="file" accept=".crt,.pem,.cer" id="onb-cert-file" style="display:none;" @change="onCertFileChange" />
                  </label>
                  <span v-if="cert.certFile" style="font-size:0.8rem;color:var(--text-muted);align-self:center;">{{ cert.certFile.name }}</span>
                  <button v-if="cert.certFile" class="btn btn-primary btn-sm" @click="uploadCert" :disabled="cert.loading">
                    <span v-if="cert.loading" class="spinner"></span>
                    <span v-else>Subir certificado</span>
                  </button>
                </div>
                <p v-if="!cert.csr" style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem;">Completá el paso 1 primero.</p>
              </div>
            </div>
          </div>

          <!-- Conectado ✓ -->
          <div v-if="cert.connected" class="cert-success">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            ¡Certificado cargado! Tu sistema ya puede emitir facturas con CAE.
          </div>

          <p v-if="error" class="onb-error">{{ error }}</p>

          <div class="onb-btn-row mt-4">
            <button class="btn btn-ghost" @click="step = 2">← Volver</button>
            <button v-if="!cert.connected" class="btn btn-ghost" @click="skipCert">Saltar por ahora →</button>
            <button v-if="cert.connected" class="btn btn-primary" @click="goToStep4" id="onb-next-3">Continuar →</button>
          </div>
        </div>
      </Transition>

      <!-- ══ STEP 4: Listo 🎉 ══ -->
      <Transition name="slide-fade" mode="out-in">
        <div v-if="step === 4" key="step4" class="onb-body onb-finish">
          <div class="finish-emoji">🎉</div>
          <h2 class="step-title">¡Todo listo!</h2>
          <p class="step-desc">Tu sistema está configurado. Ya podés empezar a facturar.</p>

          <div class="finish-checklist">
            <div class="finish-item">
              <span class="check">✓</span>
              <div><strong>Datos de empresa</strong> guardados</div>
            </div>
            <div class="finish-item">
              <span class="check">✓</span>
              <div><strong>Punto de Venta ARCA</strong> configurado</div>
            </div>
            <div class="finish-item" :class="cert.connected ? '' : 'pending'">
              <span class="check">{{ cert.connected ? '✓' : '⏳' }}</span>
              <div>
                <strong>Certificado digital</strong>
                <span v-if="!cert.connected" style="font-size:0.8rem;color:var(--text-muted);display:block;">Pendiente — configuralo en Ajustes → ARCA</span>
              </div>
            </div>
          </div>

          <div class="finish-tips">
            <div class="tip-title">📌 Próximos pasos recomendados</div>
            <ul>
              <li>Agregá tus <strong>productos o servicios</strong> en el Catálogo</li>
              <li>Cargá tus <strong>clientes frecuentes</strong> en la sección Clientes</li>
              <li v-if="!cert.connected">Terminá de configurar tu <strong>certificado ARCA</strong> en Ajustes</li>
              <li>Emitís tu <strong>primera factura electrónica</strong> desde Facturas → Nueva</li>
            </ul>
          </div>

          <p v-if="error" class="onb-error">{{ error }}</p>
          <button class="btn btn-primary onb-btn" @click="finishOnboarding" :disabled="saving" id="onb-finish">
            <span v-if="saving" class="spinner"></span>
            <span v-else>🚀 Ir al Dashboard</span>
          </button>
        </div>
      </Transition>
    </div>
  </div>
</template>

<style scoped>
.onb-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  padding: 2rem 1rem;
  overflow: hidden;
}

.onb-bg {
  position: fixed;
  inset: 0;
  z-index: 0;
}
.orb { position: absolute; border-radius: 50%; filter: blur(120px); opacity: 0.25; }
.orb-1 { width: 700px; height: 700px; background: var(--primary, #6366f1); top: -200px; right: -150px; }
.orb-2 { width: 600px; height: 600px; background: var(--accent, #8b5cf6); bottom: -200px; left: -100px; }
.grid-pattern {
  position: absolute; inset: 0;
  background-image: linear-gradient(rgba(99,102,241,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.04) 1px, transparent 1px);
  background-size: 50px 50px;
}

.onb-card {
  position: relative; z-index: 1;
  width: 100%; max-width: 600px;
  background: var(--surface, rgba(255,255,255,0.04));
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 24px;
  padding: 2.5rem;
  box-shadow: 0 30px 80px rgba(0,0,0,0.4);
}

.onb-header {
  display: flex; align-items: center; gap: 1rem;
  margin-bottom: 1.75rem;
}
.onb-logo {
  width: 48px; height: 48px; border-radius: 14px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.onb-title { font-size: 1.35rem; font-weight: 800; margin: 0 0 0.15rem; }
.onb-sub { font-size: 0.85rem; color: var(--text-muted); margin: 0; }

/* Progress */
.onb-progress-wrap {
  height: 5px; background: rgba(255,255,255,0.08); border-radius: 99px; margin-bottom: 0.5rem; overflow: hidden;
}
.onb-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #a78bfa);
  border-radius: 99px;
  transition: width 0.5s ease;
}
.onb-steps-label {
  display: flex; justify-content: space-between;
  font-size: 0.75rem; color: var(--text-muted);
  margin-bottom: 1.25rem;
}

.onb-dots {
  display: flex; gap: 0.5rem; margin-bottom: 2rem; justify-content: center;
}
.onb-dot {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 700;
  background: rgba(255,255,255,0.06);
  border: 2px solid rgba(255,255,255,0.1);
  color: var(--text-muted);
  transition: all 0.3s;
}
.onb-dot.active { border-color: var(--primary, #6366f1); color: var(--primary, #6366f1); background: rgba(99,102,241,0.1); }
.onb-dot.done { background: var(--primary, #6366f1); border-color: var(--primary, #6366f1); color: white; }

/* Body */
.onb-body { display: flex; flex-direction: column; align-items: stretch; }
.step-icon { font-size: 2.5rem; text-align: center; margin-bottom: 0.75rem; }
.step-title { font-size: 1.4rem; font-weight: 800; text-align: center; margin-bottom: 0.5rem; }
.step-desc { font-size: 0.875rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem; line-height: 1.6; }
.required { color: #f87171; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1.25rem;
}
.form-group { margin: 0; }
.span-2 { grid-column: span 2; }

.onb-btn { width: 100%; justify-content: center; padding: 0.85rem; font-size: 1rem; margin-top: 0.5rem; }
.onb-btn-row { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 0.5rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }

.onb-error {
  background: rgba(248,113,113,0.1);
  border: 1px solid rgba(248,113,113,0.2);
  color: #f87171;
  border-radius: 10px;
  padding: 0.65rem 1rem;
  font-size: 0.85rem;
  margin-bottom: 0.75rem;
}

.info-box {
  display: flex; align-items: flex-start; gap: 0.6rem;
  background: rgba(99,102,241,0.08);
  border: 1px solid rgba(99,102,241,0.2);
  border-radius: 10px;
  padding: 0.75rem 1rem;
  font-size: 0.82rem;
  color: var(--text-muted);
  margin-bottom: 1.25rem;
  line-height: 1.5;
}
.info-box svg { flex-shrink: 0; color: var(--primary); margin-top: 1px; }

/* Cert steps */
.cert-steps { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem; }
.cert-step-item {
  display: flex; gap: 0.85rem; align-items: flex-start;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(255,255,255,0.08);
  border-radius: 12px;
  padding: 0.9rem 1rem;
  transition: all 0.2s;
}
.cert-step-item.done { border-color: rgba(99,102,241,0.3); background: rgba(99,102,241,0.06); }
.cert-step-item.locked { opacity: 0.5; }
.cert-step-num {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(99,102,241,0.15);
  color: var(--primary, #6366f1);
  font-size: 0.8rem; font-weight: 700;
}
.cert-step-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.2rem; }
.cert-step-sub { font-size: 0.78rem; color: var(--text-muted); line-height: 1.4; }

.cert-success {
  display: flex; align-items: center; gap: 0.75rem;
  background: rgba(34,197,94,0.1);
  border: 1px solid rgba(34,197,94,0.25);
  border-radius: 12px;
  padding: 1rem;
  color: #4ade80;
  font-weight: 600;
  margin-bottom: 0.75rem;
}

/* Finish */
.onb-finish { align-items: center; }
.finish-emoji { font-size: 4rem; margin-bottom: 0.5rem; animation: bounce 0.6s ease; }
@keyframes bounce { 0%,100%{transform:translateY(0)} 40%{transform:translateY(-16px)} }

.finish-checklist {
  width: 100%;
  display: flex; flex-direction: column; gap: 0.6rem;
  margin: 1.5rem 0;
}
.finish-item {
  display: flex; align-items: center; gap: 0.75rem;
  background: rgba(255,255,255,0.03);
  border-radius: 10px; padding: 0.7rem 1rem;
  font-size: 0.9rem;
}
.finish-item.pending { opacity: 0.7; }
.check {
  width: 26px; height: 26px; border-radius: 50%;
  background: rgba(34,197,94,0.15); color: #4ade80;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 700; flex-shrink: 0;
}

.finish-tips {
  width: 100%;
  background: rgba(99,102,241,0.07);
  border: 1px solid rgba(99,102,241,0.15);
  border-radius: 12px;
  padding: 1rem 1.25rem;
  margin-bottom: 1.5rem;
}
.tip-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 0.6rem; color: var(--primary, #6366f1); }
.finish-tips ul { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.4rem; }
.finish-tips li { font-size: 0.82rem; color: var(--text-muted); padding-left: 1rem; position: relative; }
.finish-tips li::before { content: '→'; position: absolute; left: 0; color: var(--primary, #6366f1); }

/* Toast */
.onb-toast {
  position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%);
  background: rgba(34,197,94,0.9); color: white;
  padding: 0.7rem 1.5rem; border-radius: 99px;
  font-weight: 600; font-size: 0.9rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  z-index: 1000;
}

/* Slide transition */
.slide-fade-enter-active { transition: all 0.3s ease-out; }
.slide-fade-leave-active { transition: all 0.2s ease-in; }
.slide-fade-enter-from { opacity: 0; transform: translateX(20px); }
.slide-fade-leave-to { opacity: 0; transform: translateX(-20px); }

.flex { display: flex; }
.gap-2 { gap: 0.5rem; }
.items-center { align-items: center; }

@media (max-width: 600px) {
  .onb-card { padding: 1.5rem; }
  .form-grid { grid-template-columns: 1fr; }
  .span-2 { grid-column: span 1; }
}
</style>
