<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { catalogApi } from '@/api/services'

const route = useRoute()
const company = ref<any>({})
const products = ref<any[]>([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    const res = await catalogApi.get(Number(route.params.userId))
    company.value = res.data.company
    products.value = res.data.products
  } catch {
    error.value = 'El catálogo no está disponible'
  } finally {
    loading.value = false
  }
})

const themeClass = computed(() => {
  return `theme-${company.value.settings?.catalog_theme || 'white'}`
})

function formatARS(n: number) {
  // Asegurarnos de que el precio no sea null y el rate sea válido
  const price = parseFloat(String(n)) || 0
  const rate = parseFloat(String(company.value.settings?.dollar_rate)) || 1000
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(price * rate)
}

function whatsappLink() {
  const num = (company.value.settings?.whatsapp_number || company.value.whatsapp)?.replace(/[^0-9]/g, '')
  return `https://wa.me/${num}?text=Hola! Vengo de ver tu catálogo FactuFlash y me interesan algunos productos.`
}

function whatsappProductLink(product: any) {
  const num = (company.value.settings?.whatsapp_number || company.value.whatsapp)?.replace(/[^0-9]/g, '')
  const text = encodeURIComponent(`¡Hola! 👋 Me interesa el producto: *${product.name}* que vi en tu catálogo de FactuFlash.`)
  return `https://wa.me/${num}?text=${text}`
}
</script>

<template>
  <div class="catalog-page" :class="themeClass">
    <div v-if="loading" class="full-loading">
       <span class="spinner"></span>
    </div>
    
    <div v-else-if="error" class="error-view">
       <div class="error-card">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <p>{{ error }}</p>
          <a href="/" class="btn-back">Volver al inicio</a>
       </div>
    </div>

    <div v-else class="catalog-container">
       <header class="catalog-header">
          <div class="company-brand">
             <div v-if="company.logo" class="company-logo">
                <img :src="'/sistemafacturacion/api/public/storage/' + company.logo" />
             </div>
             <div v-else class="company-logo-placeholder">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
             </div>
             <div class="company-info">
                <h1>{{ company.name }}</h1>
                <p class="tagline">Catálogo Oficial</p>
             </div>
          </div>
          <div v-if="company.settings?.show_whatsapp_button" class="header-action">
             <a :href="whatsappLink()" target="_blank" class="btn-whatsapp">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.483 8.412-.003 6.557-5.338 11.892-11.893 11.892-1.997-.001-3.951-.5-5.688-1.448l-6.308 1.656zm6.757-4.242c1.472.873 3.123 1.334 4.808 1.335h.005c5.452 0 9.889-4.437 9.892-9.895.002-2.646-1.03-5.131-2.903-7.003-1.874-1.872-4.359-2.903-7.007-2.905-5.454 0-9.892 4.438-9.895 9.897-.001 1.75.465 3.457 1.348 4.96l-1.071 3.913 4.022-1.056zm10.511-6.605c-.285-.142-1.684-.832-1.945-.927-.261-.095-.451-.142-.641.142-.19.285-.451.641-.547.737-.095.094-.19.119-.475-.024-.285-.143-1.202-.442-2.29-1.411-.847-.756-1.419-1.688-1.586-1.972-.166-.285-.018-.439.124-.581.128-.127.285-.332.427-.499.143-.166.19-.285.285-.475.095-.19.047-.356-.024-.499-.071-.142-.641-1.543-.878-2.113-.231-.555-.465-.48-.641-.489-.166-.007-.356-.01-.546-.01-.19 0-.499.071-.76.356-.261.285-1.018.995-1.018 2.424 0 1.429 1.042 2.809 1.187 3.003.143.19 2.051 3.132 4.968 4.389.694.3 1.236.479 1.658.613.698.221 1.334.19 1.837.114.561-.085 1.684-.688 1.921-1.353.238-.665.238-1.235.166-1.353-.072-.119-.261-.19-.546-.332z"/></svg>
                Hacer un pedido
             </a>
          </div>
       </header>

       <div v-if="products.length === 0" class="empty-catalog text-center p-20">
          <p>Esta tienda aún no tiene productos disponibles.</p>
       </div>

       <div v-else class="products-grid">
          <div v-for="p in products" :key="p.id" class="p-card">
             <div class="p-image-wrap">
                <img v-if="p.image_url" :src="'/sistemafacturacion/api/public/storage/' + p.image_url" class="p-img" />
                <div v-else class="p-no-image">
                   <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                </div>
                <div class="p-badge" v-if="p.stock > 0">Disponible</div>
             </div>
             <div class="p-content">
                <h3 class="p-name">{{ p.name }}</h3>
                <p class="p-desc">{{ p.description }}</p>
                <div class="p-footer">
                   <div class="p-price">{{ formatARS(p.cost_usd) }}</div>
                   <a :href="whatsappProductLink(p)" target="_blank" class="btn-product-buy" title="Comprar por WhatsApp">
                      <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.483 8.412-.003 6.557-5.338 11.892-11.893 11.892-1.997-.001-3.951-.5-5.688-1.448l-6.308 1.656zm6.757-4.242c1.472.873 3.123 1.334 4.808 1.335h.005c5.452 0 9.889-4.437 9.892-9.895.002-2.646-1.03-5.131-2.903-7.003-1.874-1.872-4.359-2.903-7.007-2.905-5.454 0-9.892 4.438-9.895 9.897-.001 1.75.465 3.457 1.348 4.96l-1.071 3.913 4.022-1.056zm10.511-6.605c-.285-.142-1.684-.832-1.945-.927-.261-.095-.451-.142-.641.142-.19.285-.451.641-.547.737-.095.094-.19.119-.475-.024-.285-.143-1.202-.442-2.29-1.411-.847-.756-1.419-1.688-1.586-1.972-.166-.285-.018-.439.124-.581.128-.127.285-.332.427-.499.143-.166.19-.285.285-.475.095-.19.047-.356-.024-.499-.071-.142-.641-1.543-.878-2.113-.231-.555-.465-.48-.641-.489-.166-.007-.356-.01-.546-.01-.19 0-.499.071-.76.356-.261.285-1.018.995-1.018 2.424 0 1.429 1.042 2.809 1.187 3.003.143.19 2.051 3.132 4.968 4.389.694.3 1.236.479 1.658.613.698.221 1.334.19 1.837.114.561-.085 1.684-.688 1.921-1.353.238-.665.238-1.235.166-1.353-.072-.119-.261-.19-.546-.332z"/></svg>
                      Pedir
                   </a>
                </div>
             </div>
          </div>
       </div>

       <footer class="catalog-footer">
          <div class="footer-logo">FacturaYa</div>
          <p>Ventas seguras y facturación automática integrada.</p>
       </footer>
    </div>
  </div>
</template>

<style scoped>
/* Transiciones de Tema */
.catalog-page { min-height: 100vh; transition: all 0.5s ease; font-family: 'Outfit', 'Inter', sans-serif; }

/* --- TEMAS --- */

/* Blanco Clásico (Default) */
.theme-white { background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%); --primary: #2563eb; --text: #1e293b; --card: #ffffff; --muted: #64748b; }
.theme-white .company-info h1 { color: #0f172a; }
.theme-white .tagline { color: #64748b; font-weight: 800; }
.theme-white .p-name { color: #1e293b; }
.theme-white .p-desc { color: #64748b; }

/* Negro Premium */
.theme-black { background: #0f172a; --primary: #38bdf8; --text: #f1f5f9; --card: #1e293b; --muted: #94a3b8; color: white; }
.theme-black .p-card { border-color: rgba(255,255,255,0.05); }
.theme-black .company-info h1, .theme-black .p-name { color: white; }
.theme-black .p-badge { background: rgba(0,0,0,0.5); color: #38bdf8; border: 1px solid #38bdf8; }

/* Azul Profesional */
.theme-blue { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); --primary: #fbbf24; --text: #f1f5f9; --card: rgba(255,255,255,0.1); --muted: #cbd5e1; color: white; }
.theme-blue .p-card { backdrop-filter: blur(10px); border-color: rgba(255,255,255,0.1); }
.theme-blue .tagline, .theme-blue .p-desc { color: #bfdbfe; }
.theme-blue .p-price { color: #fbbf24; }
.theme-blue .company-info h1, .theme-blue .p-name { color: white; }

/* Rosa Moderno */
.theme-pink { background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); --primary: #db2777; --text: #831843; --card: #ffffff; --muted: #be185d; }
.theme-pink .company-info h1 { color: #831843; } /* Color fuerte para que se lea */
.theme-pink .tagline { color: #be185d; font-weight: 800; }
.theme-pink .p-price { color: #db2777; }

/* Violeta Creativo */
.theme-purple { background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%); --primary: #a78bfa; --text: #f5f3ff; --card: rgba(0,0,0,0.3); --muted: #ddd6fe; color: white; }
.theme-purple .p-card { border-color: rgba(255,255,255,0.1); }
.theme-purple .p-price { color: #c4b5fd; }
.theme-purple .company-info h1, .theme-purple .p-name { color: white; }


/* --- COMPONENTES --- */

.catalog-container { max-width: 1100px; margin: 0 auto; padding: 3rem 1.5rem; }

.catalog-header { 
  display: flex; justify-content: space-between; align-items: center; 
  margin-bottom: 4rem; padding-bottom: 2rem;
  border-bottom: 1px solid rgba(128,128,128,0.1);
}

.company-brand { display: flex; align-items: center; gap: 1.5rem; }
.company-logo { width: 70px; height: 70px; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 8px 24px rgba(0,0,0,0.1); padding: 4px; }
.company-logo img { width: 100%; height: 100%; object-fit: contain; }
.company-logo-placeholder { width: 70px; height: 70px; border-radius: 18px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; }

.company-info h1 { font-size: 1.75rem; font-weight: 900; margin-bottom: 0.15rem; letter-spacing: -0.02em; }
.tagline { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; opacity: 0.8; }

.btn-whatsapp { 
  display: flex; align-items: center; gap: 0.6rem; background: #25D366; color: white; 
  padding: 0.75rem 1.4rem; border-radius: 12px; font-weight: 700; text-decoration: none; 
  font-size: 0.9rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 6px 16px rgba(37,211,102,0.25);
}
.btn-whatsapp:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,211,102,0.35); filter: brightness(1.05); }

/* Grid de Productos */
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; }
.p-card { 
  background: var(--card); border-radius: 20px; border: 1px solid rgba(0,0,0,0.03); 
  overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.p-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }

.p-image-wrap { width: 100%; height: 220px; background: #f8fafc; position: relative; overflow: hidden; }
.p-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s; }
.p-card:hover .p-img { transform: scale(1.1); }
.p-no-image { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #cbd5e1; }

.p-badge { 
  position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(255,255,255,0.9); 
  backdrop-filter: blur(8px); padding: 0.35rem 0.7rem; border-radius: 50px; 
  font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: #059669;
}

.p-content { padding: 1.5rem; }
.p-name { font-size: 1.15rem; font-weight: 800; margin-bottom: 0.4rem; color: #0f172a; }
.p-desc { font-size: 0.85rem; color: var(--muted); margin-bottom: 1.25rem; line-height: 1.5; height: 40px; overflow: hidden; }

.p-footer { display: flex; justify-content: space-between; align-items: center; }
.p-price { font-size: 1.35rem; font-weight: 900; color: #2563eb; }

/* Rediseño Botón Pedir (Fino y Moderno) */
.btn-product-buy { 
  display: flex; align-items: center; gap: 0.5rem; background: #25D366; color: white; 
  padding: 0.6rem 1.1rem; border-radius: 10px; font-weight: 700; text-decoration: none; 
  font-size: 0.85rem; transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(37,211,102,0.2);
}
.btn-product-buy:hover { background: #20bd5a; transform: scale(1.05); box-shadow: 0 6px 16px rgba(37,211,102,0.3); }
.btn-product-buy svg { opacity: 0.9; }

.catalog-footer { text-align: center; margin-top: 6rem; padding: 4rem 1rem; opacity: 0.6; }
.footer-logo { font-size: 1.25rem; font-weight: 900; margin-bottom: 0.5rem; }
.catalog-footer p { font-size: 0.75rem; }

.full-loading { min-height: 100vh; display: flex; align-items: center; justify-content: center; }

@media (max-width: 640px) {
  .catalog-header { flex-direction: column; gap: 1.5rem; text-align: center; }
  .company-brand { flex-direction: column; gap: 1rem; }
  .btn-whatsapp { width: 100%; justify-content: center; }
}

/* Errores */
.error-view { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.error-card { text-align: center; }
.btn-back { display: inline-block; margin-top: 1rem; color: var(--primary); font-weight: 700; text-decoration: none; border-bottom: 2px solid; }
</style>
