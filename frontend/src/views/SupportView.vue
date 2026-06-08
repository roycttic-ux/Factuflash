<script setup lang="ts">
import { ref } from 'vue'

const steps = ref([
  {
    title: 'Paso 0: Clave Fiscal Nivel 3',
    description: 'Es el requisito básico. Lo obtenés bajando la app "Mi AFIP" en tu celular y usando la opción "Reconocimiento Facial". No necesitás ir a una oficina.',
    icon: 'shield-check',
    details: [
      'Descargá la App "Mi AFIP".',
      'Entrá a "Solicitar o recuperar Clave Fiscal".',
      'Escaneá tu DNI y hacé la prueba de vida (mover la cara frente a la cámara).',
      'Al terminar, ya tenés Nivel 3 automáticamente.'
    ]
  },
  {
    title: 'Paso 1: Generar Pedido (CSR)',
    description: 'Es un archivo que le dice a AFIP quién sos. Se genera junto con una Clave Privada (.key) que debés guardar bajo llave.',
    icon: 'file-code',
    details: [
      'Si no sabés cómo generarlo, contactame por WhatsApp y yo te paso los archivos listos.',
      'La Clave Privada (.key) nunca se comparte con nadie fuera del sistema.'
    ]
  },
  {
    title: 'Paso 2: Obtener Certificado (.crt)',
    description: 'Con el archivo CSR, entrás a la web de AFIP para que ellos te den el Certificado Digital oficial.',
    icon: 'certificate',
    details: [
      'Entrá a AFIP con tu CUIT y Clave.',
      'Buscá el servicio "Administrador de Certificados Digitales".',
      'Hacé clic en "Agregar Alias", poné un nombre (ej: FactuFlash) y subí tu archivo .csr.',
      'Descargá el archivo .crt que te da la página.'
    ]
  },
  {
    title: 'Paso 3: Delegación de Servicio',
    description: 'El último paso legal. Tenés que autorizar al sistema para que facture por vos.',
    icon: 'user-check',
    details: [
      'En AFIP, entrá a "Administrador de Relaciones".',
      'Nueva Relación -> Buscar -> AFIP -> Servicios Interactivos.',
      'Buscá "Facturación Electrónica" y asocialo al Alias que creaste en el paso anterior.'
    ]
  },
  {
    title: 'Paso 4: Carga y Activación',
    description: 'Con el archivo .crt en mano, ya podés activar la facturación en el sistema.',
    icon: 'upload-cloud',
    details: [
      'Andá a la sección de "Configuración" en el menú lateral izquierdo.',
      'Buscá el panel que dice "AFIP / ARCA".',
      'Pegá el contenido del certificado (.crt) o subí el archivo que descargaste.',
      'Hacé clic en "Vincular" y ¡listo! El sistema ya está habilitado para facturar.'
    ]
  },
  {
    title: 'Anulaciones (Notas de Crédito)',
    description: 'Si te equivocas en una factura legal, no se puede borrar. Se debe anular con una Nota de Crédito.',
    icon: 'rotate-ccw',
    details: [
      'Al presionar el botón de "Eliminar" en una factura con CAE, el sistema generará automáticamente una Nota de Crédito legal ante AFIP.',
      'El stock de los productos se devuelve al inventario automáticamente.',
      'AFIP permite anular facturas de productos hasta 30 días después.',
      'Para servicios, el límite es hasta el día 15 del mes siguiente.'
    ]
  }
])

const faqs = ref([
  {
    question: '¿Qué es ARCA?',
    answer: 'Es el nuevo nombre de la AFIP. Todos los trámites y servicios siguen funcionando igual bajo esta nueva denominación.'
  },
  {
    question: '¿Cómo cargo un nuevo producto?',
    answer: 'Ve a la sección "Productos", haz clic en el botón "+" y completa los datos básicos como nombre, precio y stock. ¡No olvides asignar una categoría!'
  },
  {
    question: '¿Puedo usar lector de código de barras?',
    answer: '¡Sí! El sistema es totalmente compatible con cualquier lector de código de barras USB o Bluetooth. Solo conecta y escanea.'
  },
  {
    question: '¿Qué límites de tiempo hay para anular?',
    answer: 'AFIP permite anular facturas de productos (Concepto 1) hasta 30 días después. Para servicios (Concepto 2), tenés tiempo hasta el día 15 del mes siguiente.'
  }
])
</script>

<template>
  <div class="support-view">
    <header class="support-header">
      <div class="header-content">
        <div class="badge">Guía Definitiva</div>
        <h1>Centro de Soporte</h1>
        <p>Configura tu conexión con ARCA (AFIP) y domina el sistema desde cero.</p>
      </div>
      <div class="header-image">
        <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
    </header>

    <div class="support-grid">
      <!-- AFIP Connection Section -->
      <section class="support-card afip-detailed">
        <div class="card-header">
          <div class="icon-box primary">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </div>
          <div>
            <h2>Conexión con ARCA (AFIP) de 0 a 100</h2>
            <p class="subtitle">Seguí estos pasos para dejar todo funcionando</p>
          </div>
        </div>
        
        <div class="steps-detailed">
          <div v-for="(step, index) in steps" :key="index" class="detailed-step">
            <div class="step-sidebar">
              <div class="step-icon">
                <svg v-if="step.icon === 'shield-check'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 11 11 13 15 9"/>
                </svg>
                <svg v-else-if="step.icon === 'file-code'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="m10 13-2 2 2 2"/><path d="m14 17 2-2-2-2"/>
                </svg>
                <svg v-else-if="step.icon === 'certificate'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                </svg>
                <svg v-else-if="step.icon === 'user-check'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/>
                </svg>
                <svg v-else-if="step.icon === 'upload-cloud'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M16 16l-4-4-4 4M12 12v9"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/><polyline points="16 16 12 12 8 16"/>
                </svg>
                <svg v-else-if="step.icon === 'rotate-ccw'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                </svg>
              </div>
              <div v-if="index < steps.length - 1" class="step-line"></div>
            </div>
            <div class="step-body">
              <h3>{{ step.title }}</h3>
              <p class="main-desc">{{ step.description }}</p>
              <ul class="step-sublist">
                <li v-for="(detail, dIdx) in step.details" :key="dIdx">{{ detail }}</li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <div class="side-support">
        <!-- Contact Section -->
        <section class="support-card contact-card">
          <div class="card-header">
            <div class="icon-box success">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
              </svg>
            </div>
            <h2>Soporte Directo</h2>
          </div>
          <p>¿Te trabaste en algún paso? No reniegues, mandame un mensaje y lo resolvemos en 5 minutos.</p>
          
          <a href="https://wa.me/2954326436" target="_blank" class="whatsapp-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            Soporte por WhatsApp
          </a>
          <div class="phone-number">2954326436</div>
        </section>

        <!-- FAQ Section -->
        <section class="support-card usage-guide">
          <div class="card-header">
            <div class="icon-box warning">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
              </svg>
            </div>
            <h2>Dudas de Uso</h2>
          </div>
          <div class="faq-list">
            <div v-for="(faq, index) in faqs" :key="index" class="faq-item">
              <h4>{{ faq.question }}</h4>
              <p>{{ faq.answer }}</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.support-view {
  animation: fadeIn 0.5s ease;
  max-width: 1200px;
  margin: 0 auto;
}
 
.support-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 3rem;
  background: var(--bg-card);
  border-radius: var(--radius-xl);
  margin-bottom: 2rem;
  border: 1px solid var(--border);
  position: relative;
  overflow: hidden;
}
 
.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  background: rgba(124, 58, 237, 0.08);
  color: var(--primary);
  border-radius: 0px;
  border: 1px solid var(--primary);
  font-size: 0.75rem;
  font-weight: 700;
  margin-bottom: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
 
.header-content h1 {
  font-size: 2.5rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
  color: var(--text-primary);
}
 
.header-content p {
  color: var(--text-muted);
  font-size: 1.1rem;
}
 
.header-image {
  color: var(--primary);
  opacity: 0.8;
}
 
.support-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 2rem;
}
 
@media (max-width: 1024px) {
  .support-grid { grid-template-columns: 1fr; }
}
 
.support-card {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 2rem;
  height: fit-content;
}
 
.card-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 2rem;
}
 
.icon-box {
  width: 48px;
  height: 48px;
  border-radius: 0px;
  display: flex;
  align-items: center;
  justify-content: center;
}
 
.icon-box.primary { background: rgba(124, 58, 237, 0.08); color: var(--primary); border: 1px solid var(--primary); }
.icon-box.success { background: rgba(13, 148, 136, 0.08); color: var(--secondary); border: 1px solid var(--secondary); }
.icon-box.warning { background: rgba(217, 119, 6, 0.08); color: var(--warning); border: 1px solid var(--warning); }
 
.card-header h2 { font-size: 1.25rem; font-weight: 700; margin: 0; }
.subtitle { color: var(--text-muted); font-size: 0.9rem; margin: 0.25rem 0 0; }
 
/* DETAILED STEPS */
.steps-detailed {
  display: flex;
  flex-direction: column;
}
 
.detailed-step {
  display: flex;
  gap: 1.5rem;
}
 
.step-sidebar {
  display: flex;
  flex-direction: column;
  align-items: center;
}
 
.step-icon {
  width: 40px;
  height: 40px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 0px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-primary);
  flex-shrink: 0;
  z-index: 1;
}
 
.step-line {
  width: 2px;
  flex: 1;
  background: var(--border);
  margin: 0.5rem 0;
}
 
.step-body {
  padding-bottom: 2.5rem;
}
 
.step-body h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--primary); }
.main-desc { font-size: 0.95rem; color: var(--text-primary); margin-bottom: 1rem; line-height: 1.5; }
 
.step-sublist {
  list-style: none;
  padding: 1rem;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
 
.step-sublist li {
  font-size: 0.85rem;
  color: var(--text-muted);
  position: relative;
  padding-left: 1.25rem;
}
 
.step-sublist li::before {
  content: '→';
  position: absolute;
  left: 0;
  color: var(--primary);
}
 
/* SIDE SUPPORT */
.side-support {
  display: flex;
  flex-direction: column;
  gap: 2rem;
}
 
.contact-card p { color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.9rem; }
 
.whatsapp-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  background: #25D366;
  color: white;
  padding: 1rem;
  border-radius: var(--radius-sm);
  text-decoration: none;
  font-weight: 700;
  transition: all var(--transition);
}
 
.whatsapp-btn:hover { background: #128C7E; transform: translateY(-1px); }
 
.phone-number {
  text-align: center;
  margin-top: 1rem;
  font-family: monospace;
  font-size: 1.1rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  color: var(--text-muted);
}
 
.faq-list { display: flex; flex-direction: column; gap: 1.5rem; }
.faq-item h4 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary); }
.faq-item p { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; }
 
@media (max-width: 768px) {
  .support-header { padding: 2rem; flex-direction: column; text-align: center; gap: 1.5rem; }
  .header-content h1 { font-size: 1.8rem; }
}
</style>
