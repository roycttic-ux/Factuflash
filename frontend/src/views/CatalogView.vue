<script setup lang="ts">

import { ref, onMounted, computed } from 'vue'

import { useRoute } from 'vue-router'

import { catalogApi } from '@/api/services'



const route = useRoute()

const company = ref<any>({})

const hasWhatsapp = computed(() => {
  return !!(company.value.settings?.whatsapp_number || company.value.whatsapp)
})

const products = ref<any[]>([])

const loading = ref(true)

const error = ref('')

const selectedCategory = ref('Todas')

const searchQuery = ref('')

const selectedProduct = ref<any>(null)

const selectedQuantity = ref(1)

const currentImage = ref('')



// Reactive Shopping Cart State

const cart = ref<any[]>([])

const showCart = ref(false)



function addToCart(product: any, qty: number = 1) {

  const existing = cart.value.find(item => item.product.id === product.id)

  if (existing) {

    existing.quantity += qty

  } else {

    cart.value.push({ product, quantity: qty })

  }

}



function removeFromCart(productId: any) {

  cart.value = cart.value.filter(item => item.product.id !== productId)

}



function updateQuantity(productId: any, delta: number) {

  const item = cart.value.find(i => i.product.id === productId)

  if (item) {

    item.quantity += delta

    if (item.quantity <= 0) {

      removeFromCart(productId)

    }

  }

}



const cartCount = computed(() => {

  return cart.value.reduce((total, item) => total + item.quantity, 0)

})



const cartTotal = computed(() => {

  return cart.value.reduce((total, item) => {

    const price = parseFloat(item.product.cost_usd) || 0

    return total + price * item.quantity

  }, 0)

})



function whatsappCartLink() {

  let num = (company.value.settings?.whatsapp_number || company.value.whatsapp)?.replace(/[^0-9]/g, '')

  if (num && num.length === 10) num = '549' + num

  

  const itemsList = cart.value.map(item => `- *${item.quantity}x ${item.product.name}* (${formatARS(item.product.cost_usd * item.quantity)})`).join('\n')

  const message = `¡Hola! Quiero confirmar este pedido de mi catálogo web:\n\n${itemsList}\n\n*Total:* ${formatARS(cartTotal.value)}`

  return `https://api.whatsapp.com/send?phone=${num}&text=${encodeURIComponent(message)}`

}



onMounted(async () => {

  try {

    const res = await catalogApi.get(route.params.id as any)

    company.value = res.data.company

    products.value = res.data.products

  } catch {

    error.value = 'El catálogo no está disponible'

  } finally {

    loading.value = false

  }

})



const selectedTheme = computed(() => {

  const rawTheme = company.value.settings?.catalog_theme || 'black'

  const parts = rawTheme.split('|')

  const theme = parts[0] || 'black'

  return theme === 'white' ? 'white' : theme

})



const selectedFont = computed(() => {

  const rawTheme = company.value.settings?.catalog_theme || 'black'

  const parts = rawTheme.split('|')

  return parts[1] || 'Outfit'

})



const themeClass = computed(() => {

  return `theme-${selectedTheme.value}`

})



const categories = computed(() => {
  const allTags = products.value
    .flatMap(p => p.tags ? p.tags.split(',').map((t: string) => t.trim()) : [])
    .filter(t => !!t)
  return ['Todas', ...new Set(allTags)]
})

const filteredProducts = computed(() => {
  let result = products.value
  
  if (selectedCategory.value !== 'Todas') {
    result = result.filter(p => {
      if (!p.tags) return false
      const tagsList = p.tags.split(',').map((t: string) => t.trim())
      return tagsList.includes(selectedCategory.value)
    })
  }
  
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(p => 
      p.name.toLowerCase().includes(q) || 
      p.description?.toLowerCase().includes(q)
    )
  }
  
  return result
})



function openProduct(product: any) {

  selectedProduct.value = product

  currentImage.value = product.image_url ? '/api/storage/' + product.image_url : ''

  selectedQuantity.value = 1

  document.body.style.overflow = 'hidden'

}



function closeProduct() {

  selectedProduct.value = null

  document.body.style.overflow = ''

}



function formatARS(n: number) {

  const price = parseFloat(String(n)) || 0

  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(price)

}



function whatsappLink() {

  let num = (company.value.settings?.whatsapp_number || company.value.whatsapp)?.replace(/[^0-9]/g, '')

  if (num && num.length === 10) num = '549' + num // Auto-fix para Argentina si faltan dígitos

  return `https://api.whatsapp.com/send?phone=${num}&text=${encodeURIComponent('Hola! Vengo de ver tu catálogo FactuFlash.')}`

}



function whatsappProductLink(product: any) {

  let num = (company.value.settings?.whatsapp_number || company.value.whatsapp)?.replace(/[^0-9]/g, '')

  if (num && num.length === 10) num = '549' + num // Auto-fix para Argentina

  const qtyText = selectedQuantity.value > 1 ? ` ${selectedQuantity.value} unidades de ` : ' '

  const text = encodeURIComponent(`¡Hola! Me interesa comprar${qtyText}*${product.name}* que vi en tu catálogo.`)

  return `https://api.whatsapp.com/send?phone=${num}&text=${text}`

}



const catalogVideoEmbedUrl = computed(() => {

  const url = company.value.settings?.catalog_video_url

  if (!url) return null

  // Convert youtube links to embed links

  if (url.includes('youtube.com/watch?v=')) {

    const videoId = url.split('v=')[1]?.split('&')[0]

    return videoId ? `https://www.youtube.com/embed/${videoId}` : url

  } else if (url.includes('youtu.be/')) {

    const videoId = url.split('youtu.be/')[1]?.split('?')[0]

    return videoId ? `https://www.youtube.com/embed/${videoId}` : url

  } else if (url.includes('vimeo.com/')) {

    const videoId = url.split('vimeo.com/')[1]?.split('?')[0]

    return videoId ? `https://player.vimeo.com/video/${videoId}` : url

  }

  return url

})



const pageStyle = computed(() => {

  const cols = company.value.settings?.catalog_grid_columns || 1

  const font = selectedFont.value

  return {

    '--mobile-cols': cols,

    fontFamily: font === 'Playfair Display' ? `'Playfair Display', serif` : `'${font}', sans-serif`

  }

})



const colorNames = ['rojo', 'azul', 'verde', 'amarillo', 'negro', 'blanco', 'gris', 'naranja', 'violeta', 'rosa', 'celeste', 'marron', 'marrón', 'purpura', 'púrpura', 'beige']

const colorMap: Record<string, string> = {

  'rojo': '#FF0000', 'azul': '#0000FF', 'verde': '#008000', 'amarillo': '#FFFF00', 

  'negro': '#000000', 'blanco': '#FFFFFF', 'gris': '#808080', 'naranja': '#FFA500', 

  'violeta': '#EE82EE', 'rosa': '#FFC0CB', 'celeste': '#87CEEB', 'marron': '#8B4513', 

  'marrón': '#8B4513', 'purpura': '#800080', 'púrpura': '#800080', 'beige': '#F5F5DC'

}



function isColor(str: string) {

  if (!str) return false

  const s = str.toLowerCase().trim()

  return colorNames.includes(s) || s.includes('color') || /^#([0-9A-F]{3}){1,2}$/i.test(s)

}



function getColorValue(v: any) {

  const name = v.name.toLowerCase().trim()

  const val = v.value?.toLowerCase().trim()

  const key = colorNames.includes(name) ? name : (colorNames.includes(val) ? val : null)

  if (key && colorMap[key]) return colorMap[key]

  if (/^#([0-9A-F]{3}){1,2}$/i.test(val)) return val

  return val || name

}



const groupedVariants = computed(() => {

  if (!selectedProduct.value?.variants) return {}

  const groups: Record<string, any[]> = {}

  selectedProduct.value.variants.forEach((v: any) => {

    const name = v.name || 'Opción'

    if (!groups[name]) groups[name] = []

    groups[name].push(v)

  })

  return groups

})



function selectVariant(v: any) {

  if (v.image_url) {

    currentImage.value = '/api/storage/' + v.image_url

  }

}

</script>



<template>

  <div class="catalog-page" :class="themeClass" :style="pageStyle">

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

                <img :src="'/api/storage/' + company.logo" />

             </div>

             <div v-else class="company-logo-placeholder">

                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>

             </div>

             <div class="company-info">

                <h1>{{ company.settings?.catalog_title || company.name }}</h1>

                <p class="tagline">{{ company.settings?.catalog_description || 'Catálogo Oficial' }}</p>

                <div v-if="company.settings?.catalog_instagram || company.settings?.catalog_facebook || company.settings?.catalog_tiktok" class="social-links">

                   <a v-if="company.settings?.catalog_instagram" :href="'https://instagram.com/' + company.settings?.catalog_instagram" target="_blank" class="social-link instagram">

                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>

                      @{{ company.settings?.catalog_instagram }}

                   </a>

                   <a v-if="company.settings?.catalog_facebook" :href="company.settings?.catalog_facebook.startsWith('http') ? company.settings?.catalog_facebook : 'https://' + company.settings?.catalog_facebook" target="_blank" class="social-link facebook">

                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>

                      Facebook

                   </a>

                </div>

             </div>

          </div>

          <div class="header-actions-wrap" style="display: flex; align-items: center; gap: 0.75rem;">

             <!-- Cart Button -->

             <button v-if="hasWhatsapp" type="button" @click="showCart = true" class="btn-cart-toggle">

                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>

                <span class="cart-text">Carrito</span>

                <span class="cart-badge-num" v-if="cartCount > 0">{{ cartCount }}</span>

             </button>

             <div v-if="hasWhatsapp && company.settings?.show_whatsapp_button" class="header-action">

                <a :href="whatsappLink()" target="_blank" class="btn-whatsapp">

                   <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.483 8.412-.003 6.557-5.338 11.892-11.893 11.892-1.997-.001-3.951-.5-5.688-1.448l-6.308 1.656zm6.757-4.242c1.472.873 3.123 1.334 4.808 1.335h.005c5.452 0 9.889-4.437 9.892-9.895.002-2.646-1.03-5.131-2.903-7.003-1.874-1.872-4.359-2.903-7.007-2.905-5.454 0-9.892 4.438-9.895 9.897-.001 1.75.465 3.457 1.348 4.96l-1.071 3.913 4.022-1.056zm10.511-6.605c-.285-.142-1.684-.832-1.945-.927-.261-.095-.451-.142-.641.142-.19.285-.451.641-.547.737-.095.094-.19.119-.475-.024-.285-.143-1.202-.442-2.29-1.411-.847-.756-1.419-1.688-1.586-1.972-.166-.285-.018-.439.124-.581.128-.127.285-.332.427-.499.143-.166.19-.285.285-.475.095-.19.047-.356-.024-.499-.071-.142-.641-1.543-.878-2.113-.231-.555-.465-.48-.641-.489-.166-.007-.356-.01-.546-.01-.19 0-.499.071-.76.356-.261.285-1.018.995-1.018 2.424 0 1.429 1.042 2.809 1.187 3.003.143.19 2.051 3.132 4.968 4.389.694.3 1.236.479 1.658.613.698.221 1.334.19 1.837.114.561-.085 1.684-.688 1.921-1.353.238-.665.238-1.235.166-1.353-.072-.119-.261-.19-.546-.332z"/></svg>

                   Hacer un pedido

                </a>

             </div>

          </div>

       </header>



       <!-- Video de Presentación Opcional -->

       <div v-if="catalogVideoEmbedUrl" class="catalog-video-wrap" style="margin: 2rem auto; max-width: 800px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">

          <iframe 

            :src="catalogVideoEmbedUrl" 

            width="100%" 

            height="450" 

            frameborder="0" 

            allow="autoplay; fullscreen; picture-in-picture" 

            allowfullscreen

            style="display: block;"

          ></iframe>

       </div>



       <div v-if="products.length === 0" class="empty-catalog text-center p-20">

          <p>Esta tienda aún no tiene productos disponibles.</p>

       </div>



       <div v-else>

          <!-- Buscador -->

          <div class="search-wrap">

             <div class="search-input-group">

                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>

                <input 

                  v-model="searchQuery" 

                  type="text" 

                  placeholder="Buscar productos..." 

                  class="search-input"

                />

             </div>

          </div>



          <!-- Categorias Nav -->

          <nav v-if="categories.length > 1" class="categories-nav">

             <button 

                v-for="cat in categories" 

                :key="cat"

                class="cat-link"

                :class="{ active: selectedCategory === cat }"

                @click="selectedCategory = cat"

             >

                {{ cat }}

             </button>

          </nav>



          <div v-if="filteredProducts.length === 0" class="empty-catalog text-center p-20">

             <p>No se encontraron productos que coincidan con tu búsqueda.</p>

          </div>



          <div v-else class="products-grid">

             <div v-for="p in filteredProducts" :key="p.id" class="p-card" @click="openProduct(p)">

                <div class="p-image-wrap">

                   <img v-if="p.image_url" :src="'/api/storage/' + p.image_url" class="p-img" />

                   <div v-else class="p-no-image">

                      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>

                   </div>

                   <div class="p-badge" v-if="company.settings?.catalog_show_stock && (p.stock > 0 || p.unlimited_stock)">

                       {{ p.unlimited_stock ? 'Disponible' : p.stock + ' en stock' }}

                    </div>

                </div>

                <div class="p-content">

                   <div v-if="p.tags" class="p-cat">{{ p.tags.split(',')[0].trim() }}</div>

                   <div class="p-info-wrap">

                      <h3 class="p-name">{{ p.name }}</h3>

                      <div class="p-card-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.75rem; width: 100%;">
                         <div class="p-price" v-if="company.settings?.catalog_show_price">{{ formatARS(p.cost_usd) }}</div>
                         <div v-if="hasWhatsapp" style="display: flex; gap: 0.5rem; align-items: center;">
                            <a :href="whatsappProductLink(p)" target="_blank" @click.stop class="btn-pedir-card">
                               Pedir
                            </a>
                            <button type="button" @click.stop="addToCart(p)" class="btn-add-cart-card">
                               + Agregar
                            </button>
                         </div>
                      </div>

                   </div>

                </div>

             </div>

          </div>

        </div>



        <!-- Modal de Producto -->

        <Transition name="modal">

          <div v-if="selectedProduct" class="product-modal-overlay" @click.self="closeProduct">

             <div class="product-modal-content">

                <button class="modal-close" @click="closeProduct">×</button>

                <div class="modal-body">

                   <div class="modal-image-side">

                      <div class="main-modal-img">

                        <img :src="currentImage || (selectedProduct.image_url ? '/api/storage/' + selectedProduct.image_url : '')" />

                      </div>

                      <div v-if="selectedProduct.images && selectedProduct.images.length" class="modal-gallery">

                         <div class="gallery-item" @click="currentImage = '/api/storage/' + selectedProduct.image_url">

                            <img :src="'/api/storage/' + selectedProduct.image_url" />

                         </div>

                         <div v-for="(img, idx) in selectedProduct.images" :key="idx" class="gallery-item" @click="currentImage = '/api/storage/' + img">

                            <img :src="'/api/storage/' + img" />

                         </div>

                      </div>

                      <div v-if="!selectedProduct.image_url" class="modal-no-image">

                         <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>

                      </div>

                   </div>

                   <div class="modal-info-side">

                      <div v-if="selectedProduct.tags" class="p-cat">{{ selectedProduct.tags.split(',')[0].trim() }}</div>

                      <h2 class="modal-title">{{ selectedProduct.name }}</h2>

                       <div v-if="selectedProduct.sku" class="modal-sku" style="font-size: 0.8rem; color: var(--muted); margin-top: -0.25rem; margin-bottom: 0.5rem; font-family: monospace; font-weight: bold;">

                          SKU: {{ selectedProduct.sku }}

                       </div>

                       <div v-if="selectedProduct.tags" class="modal-tags" style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.5rem;">

                          <span v-for="tag in selectedProduct.tags.split(',')" :key="tag" class="tag-badge-mini">

                             #{{ tag.trim() }}

                          </span>

                       </div>

                      <div class="modal-price" v-if="company.settings?.catalog_show_price">{{ formatARS(selectedProduct.cost_usd) }}</div>

                      <div class="modal-description">

                         <h3>Descripción</h3>

                         <div class="product-desc-html" v-html="selectedProduct.description || 'Sin descripción disponible.'"></div>

                      </div>

                      <div v-if="Object.keys(groupedVariants).length" class="modal-variants">

                         <div v-for="(vars, name) in groupedVariants" :key="name" class="variant-group mb-4">

                            <h3 class="text-xs font-bold uppercase text-muted mb-2">{{ name }}</h3>

                            <div class="variants-flex">

                               <template v-for="(v, i) in vars" :key="i">

                                  <div v-if="isColor(name) || isColor(v.value)" 

                                       class="v-color-opt" 

                                       :title="v.value"

                                       @click="selectVariant(v)">

                                     <span class="v-color-circle-large" :style="{ backgroundColor: getColorValue(v) }"></span>

                                     <span class="v-color-name-tiny">{{ v.value }}</span>

                                  </div>

                                  <div v-else class="v-pill-opt" @click="selectVariant(v)">

                                     {{ v.value }}

                                  </div>

                               </template>

                            </div>

                         </div>

                      </div>

                       <div class="modal-quantity">

                           <span class="qty-label">Cantidad</span>

                           <div class="qty-controls">

                               <button type="button" @click="selectedQuantity > 1 && selectedQuantity--" class="btn-qty-modal">-</button>

                               <span class="qty-value">{{ selectedQuantity }}</span>

                               <button type="button" @click="selectedQuantity++" class="btn-qty-modal">+</button>

                           </div>

                       </div>





                      <div v-if="hasWhatsapp" class="modal-actions">

                          <button type="button" @click="addToCart(selectedProduct, selectedQuantity); closeProduct(); showCart = true" class="btn-add-cart-modal">
                             <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                             Agregar

                          </button>

                          <a :href="whatsappProductLink(selectedProduct)" target="_blank" class="btn-whatsapp-modal">
                             <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.483 8.412-.003 6.557-5.338 11.892-11.893 11.892-1.997-.001-3.951-.5-5.688-1.448l-6.308 1.656zm6.757-4.242c1.472.873 3.123 1.334 4.808 1.335h.005c5.452 0 9.889-4.437 9.892-9.895.002-2.646-1.03-5.131-2.903-7.003-1.874-1.872-4.359-2.903-7.007-2.905-5.454 0-9.892 4.438-9.895 9.897-.001 1.75.465 3.457 1.348 4.96l-1.071 3.913 4.022-1.056zm10.511-6.605c-.285-.142-1.684-.832-1.945-.927-.261-.095-.451-.142-.641.142-.19.285-.451.641-.547.737-.095.094-.19.119-.475-.024-.285-.143-1.202-.442-2.29-1.411-.847-.756-1.419-1.688-1.586-1.972-.166-.285-.018-.439.124-.581.128-.127.285-.332.427-.499.143-.166.19-.285.285-.475.095-.19.047-.356-.024-.499-.071-.142-.641-1.543-.878-2.113-.231-.555-.465-.48-.641-.489-.166-.007-.356-.01-.546-.01-.19 0-.499.071-.76.356-.261.285-1.018.995-1.018 2.424 0 1.429 1.042 2.809 1.187 3.003.143.19 2.051 3.132 4.968 4.389.694.3 1.236.479 1.658.613.698.221 1.334.19 1.837.114.561-.085 1.684-.688 1.921-1.353.238-.665.238-1.235.166-1.353-.072-.119-.261-.19-.546-.332z"/></svg>
                             Comprar Directo
                          </a>

                       </div>

                   </div>

                </div>

             </div>

          </div>

        </Transition>



        <!-- Carrito Drawer (Panel Flotante Lateral Premium) -->

        <Transition name="drawer">

          <div v-if="showCart" class="cart-drawer-overlay" @click.self="showCart = false">

             <div class="cart-drawer">

                <div class="cart-drawer-header">

                   <h2 class="cart-drawer-title">Mi Carrito ({{ cartCount }})</h2>

                   <button class="cart-drawer-close" @click="showCart = false">×</button>

                </div>

                

                <div v-if="cart.length === 0" class="cart-drawer-empty">

                   <div class="empty-cart-icon"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg></div>

                   <p>Tu carrito está vacío</p>

                   <button @click="showCart = false" class="btn-cart-empty-close">Seguir explorando</button>

                </div>

                

                <div v-else class="cart-drawer-body">

                   <div class="cart-items-list">

                      <div v-for="item in cart" :key="item.product.id" class="cart-item-row">

                         <div class="cart-item-img-wrap">

                            <img v-if="item.product.image_url" :src="'/api/storage/' + item.product.image_url" />

                            <div v-else class="cart-item-no-img"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg></div>

                         </div>

                         <div class="cart-item-info">

                            <h4 class="cart-item-name">{{ item.product.name }}</h4>

                            <div class="cart-item-price-row">

                               <div class="cart-item-price">{{ formatARS(item.product.cost_usd) }}</div>

                               <div class="cart-item-qty-selector">

                                  <button @click="updateQuantity(item.product.id, -1)" class="btn-qty-delta">-</button>

                                  <span class="qty-num">{{ item.quantity }}</span>

                                  <button @click="updateQuantity(item.product.id, 1)" class="btn-qty-delta">+</button>

                               </div>

                            </div>

                         </div>

                         <button @click="removeFromCart(item.product.id)" class="btn-cart-remove" title="Eliminar">×</button>

                      </div>

                   </div>

                   

                   <div class="cart-drawer-footer">

                      <div class="cart-total-row">

                         <span>Total Estimado:</span>

                         <span class="cart-total-val">{{ formatARS(cartTotal) }}</span>

                      </div>

                      <a :href="whatsappCartLink()" target="_blank" class="btn-cart-checkout">

                         💬 Enviar Pedido a WhatsApp

                      </a>

                   </div>

                </div>

             </div>

          </div>

        </Transition>



        <footer class="catalog-footer">

           <div class="footer-content">

              <div class="footer-brand">

                 <div class="footer-logo">FactuFlash</div>

                 <p>Ventas seguras y facturación automática integrada.</p>

              </div>

              <div class="footer-social" v-if="company.settings?.catalog_instagram || company.settings?.catalog_facebook || company.settings?.catalog_tiktok">

                 <h4 class="footer-title">Seguinos</h4>

                 <div class="social-icons">

                    <a v-if="company.settings?.catalog_instagram" :href="'https://instagram.com/' + company.settings?.catalog_instagram" target="_blank" class="social-icon-btn instagram">

                       <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>

                    </a>

                    <a v-if="company.settings?.catalog_facebook" :href="company.settings?.catalog_facebook.startsWith('http') ? company.settings?.catalog_facebook : 'https://' + company.settings?.catalog_facebook" target="_blank" class="social-icon-btn facebook">

                       <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>

                    </a>

                    <a v-if="company.settings?.catalog_tiktok" :href="'https://tiktok.com/@' + company.settings?.catalog_tiktok" target="_blank" class="social-icon-btn tiktok">

                       <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>

                    </a>

                 </div>

              </div>

              <div class="footer-contact">

                 <h4 class="footer-title">Contacto</h4>

                 <p v-if="company.email" class="text-xs">{{ company.email }}</p>

                 <p v-if="company.whatsapp" class="text-xs">WhatsApp: {{ company.whatsapp }}</p>

              </div>

           </div>

           <div class="footer-bottom">

              &copy; {{ new Date().getFullYear() }} {{ company.name }}. Todos los derechos reservados.

           </div>

        </footer>

     </div>

  </div>

</template>



<style scoped>

@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Montserrat:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap');



/* Transiciones de Tema */

.catalog-page { min-height: 100vh; transition: all 0.5s ease; }



/* --- TEMAS --- */



/* Blanco Clásico (Default) */

.theme-white { background: linear-gradient(135deg, #f8faff 0%, #ffffff 100%); --primary: #2563eb; --text: #1e293b; --card: #ffffff; --muted: #64748b; --primary-glow: rgba(37, 99, 235, 0.18); }

.theme-white .company-info h1 { color: #0f172a; }

.theme-white .tagline { color: #64748b; font-weight: 800; }

.theme-white .p-name { color: #1e293b; }

.theme-white .p-desc { color: #64748b; }



/* Negro Premium (FactuFlash Corporate Dark) */

.theme-black { background: #13124b; --primary: #1fede8; --text: #f0f4ff; --card: #1a1e3c; --muted: #7c8aaa; --primary-glow: rgba(31, 237, 232, 0.18); color: white; }

.theme-black .p-card { border-color: rgba(255,255,255,0.07); }

.theme-black .company-info h1, .theme-black .p-name { color: white; }

.theme-black .p-badge { background: rgba(19,18,75,0.6); color: #1fede8; border: 1px solid #1fede8; }



/* Azul Profesional */

.theme-blue { background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); --primary: #fbbf24; --text: #f1f5f9; --card: rgba(255,255,255,0.1); --muted: #cbd5e1; --primary-glow: rgba(251, 191, 36, 0.18); color: white; }

.theme-blue .p-card { backdrop-filter: blur(10px); border-color: rgba(255,255,255,0.1); }

.theme-blue .tagline, .theme-blue .p-desc { color: #bfdbfe; }

.theme-blue .p-price { color: #fbbf24; }

.theme-blue .company-info h1, .theme-blue .p-name { color: white; }



/* Rosa Moderno */

.theme-pink { background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%); --primary: #db2777; --text: #831843; --card: #ffffff; --muted: #be185d; --primary-glow: rgba(219, 39, 119, 0.18); }

.theme-pink .company-info h1 { color: #831843; } /* Color fuerte para que se lea */

.theme-pink .tagline { color: #be185d; font-weight: 800; }

.theme-pink .p-price { color: #db2777; }



/* Violeta Creativo */

.theme-purple { background: linear-gradient(135deg, #2e1065 0%, #4c1d95 100%); --primary: #a78bfa; --text: #f5f3ff; --card: rgba(0,0,0,0.3); --muted: #ddd6fe; --primary-glow: rgba(167, 139, 250, 0.18); color: white; }

.theme-purple .p-card { border-color: rgba(255,255,255,0.1); }

.theme-purple .p-price { color: #c4b5fd; }

.theme-purple .company-info h1, .theme-purple .p-name { color: white; }



/* Esmeralda Real */

.theme-emerald { background: linear-gradient(135deg, #064e3b 0%, #022c22 100%); --primary: #34d399; --text: #f0fdf4; --card: rgba(255,255,255,0.06); --muted: #a7f3d0; --primary-glow: rgba(52, 211, 153, 0.18); color: white; }

.theme-emerald .p-card { border-color: rgba(255,255,255,0.08); }

.theme-emerald .tagline, .theme-emerald .p-desc { color: #a7f3d0; }

.theme-emerald .company-info h1, .theme-emerald .p-name { color: white; }

.theme-emerald .p-price { color: #34d399; }



/* Fuego Cálido */

.theme-sunset { background: linear-gradient(135deg, #7c2d12 0%, #451a03 100%); --primary: #f97316; --text: #fff7ed; --card: rgba(255,255,255,0.06); --muted: #ffedd5; --primary-glow: rgba(249, 115, 22, 0.18); color: white; }

.theme-sunset .p-card { border-color: rgba(255,255,255,0.08); }

.theme-sunset .tagline, .theme-sunset .p-desc { color: #ffedd5; }

.theme-sunset .company-info h1, .theme-sunset .p-name { color: white; }

.theme-sunset .p-price { color: #f97316; }



/* Gris Minimal */

.theme-minimal { background: #f3f4f6; --primary: #111827; --text: #1f2937; --card: #ffffff; --muted: #6b7280; --primary-glow: rgba(17, 24, 39, 0.12); }

.theme-minimal .p-card { border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }

.theme-minimal .company-info h1 { color: #111827; }

.theme-minimal .tagline { color: #6b7280; font-weight: 700; }

.theme-minimal .p-name { color: #1f2937; }

.theme-minimal .p-desc { color: #6b7280; }

.theme-minimal .p-price { color: #111827; }





/* --- COMPONENTES --- */



.catalog-container { max-width: 1100px; margin: 0 auto; padding: 3rem 1.5rem; }



.catalog-header { 
  display: flex; justify-content: space-between; align-items: center; 
  margin-bottom: 4rem; padding-bottom: 2rem;
  border-bottom: 1px solid rgba(128,128,128,0.1);
  gap: 2rem;
}

.company-brand { display: flex; align-items: center; gap: 1.5rem; max-width: 70%; }
.company-logo { width: 70px; height: 70px; border-radius: 18px; overflow: hidden; background: #fff; box-shadow: 0 8px 24px rgba(0,0,0,0.1); padding: 4px; flex-shrink: 0; }
.company-logo img { width: 100%; height: 100%; object-fit: contain; }
.company-logo-placeholder { width: 70px; height: 70px; border-radius: 18px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.company-info h1 { font-size: 1.75rem; font-weight: 900; margin-bottom: 0.15rem; letter-spacing: -0.02em; }
.tagline { font-size: 0.85rem; font-weight: 500; opacity: 0.85; margin-bottom: 0.75rem; max-width: 600px; line-height: 1.5; }

.social-links { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.75rem; }
.social-link { 
  display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; font-weight: 700; 
  color: var(--text); background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08); 
  padding: 0.35rem 0.8rem; border-radius: 50px; text-decoration: none; 
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(5px); 
}
.social-link:hover { 
  transform: translateY(-1.5px); background: rgba(255, 255, 255, 0.12); 
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
}
.theme-white .social-link {
  background: rgba(0, 0, 0, 0.03); border-color: rgba(0, 0, 0, 0.06); color: #1e293b;
}
.theme-white .social-link:hover {
  background: rgba(0, 0, 0, 0.07);
}
.social-link.instagram:hover {
  border-color: #e1306c; color: #e1306c; box-shadow: 0 4px 12px rgba(225, 48, 108, 0.15);
}
.social-link.facebook:hover {
  border-color: #1877f2; color: #1877f2; box-shadow: 0 4px 12px rgba(24, 119, 242, 0.15);
}

.btn-whatsapp { 
  display: flex; align-items: center; gap: 0.6rem; background: #25D366; color: white; 
  padding: 0.75rem 1.4rem; border-radius: 12px; font-weight: 700; text-decoration: none; 
  font-size: 0.9rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 6px 16px rgba(37,211,102,0.25);
  white-space: nowrap; flex-shrink: 0;
  animation: pulse-glow 3s infinite;
}
.btn-whatsapp:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,211,102,0.45); filter: brightness(1.05); }

@keyframes pulse-glow {
  0% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.45); }
  70% { box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); }
  100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
}

/* Grid de Productos */
.products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; }

@media (min-width: 1024px) {
  .products-grid { grid-template-columns: repeat(3, 1fr); }
}
.p-card { 
  background: var(--card); border-radius: 20px; border: 1px solid rgba(255,255,255,0.04); 
  overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.p-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); border-color: rgba(255,255,255,0.1); }

.p-image-wrap { width: 100%; height: 220px; background: rgba(0,0,0,0.2); position: relative; overflow: hidden; }
.p-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.p-card:hover .p-img { transform: scale(1.06); filter: brightness(1.03); }
.p-no-image { 
  width: 100%; height: 100%; display: flex; flex-direction: column; gap: 0.75rem;
  align-items: center; justify-content: center; 
  background: linear-gradient(135deg, rgba(255,255,255,0.01) 0%, rgba(255,255,255,0.05) 100%);
  color: var(--muted); position: relative; overflow: hidden;
}
.p-no-image::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle at center, rgba(31, 237, 232, 0.08) 0%, transparent 70%);
  pointer-events: none;
}
.p-no-image svg {
  opacity: 0.45; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2)); transition: all 0.4s ease;
}
.p-card:hover .p-no-image svg {
  transform: scale(1.1) translateY(-2px); opacity: 0.7; color: var(--primary);
}
.theme-white .p-no-image {
  background: linear-gradient(135deg, rgba(0,0,0,0.01) 0%, rgba(0,0,0,0.04) 100%); color: #94a3b8;
}

.p-badge { 
  position: absolute; top: 0.75rem; right: 0.75rem; 
  background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.25);
  backdrop-filter: blur(8px); padding: 0.35rem 0.7rem; border-radius: 50px; 
  font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: #34d399;
  letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.3s ease;
}
.p-card:hover .p-badge {
  transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,0.15);
}
.theme-white .p-badge {
  background: rgba(5, 150, 105, 0.08); border-color: rgba(5, 150, 105, 0.15); color: #059669;
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

/* Categorias */
.categories-nav { 
  display: flex; gap: 0.75rem; overflow-x: auto; 
  padding: 0.5rem 0.25rem 1.5rem; scrollbar-width: none; 
  margin-bottom: 2rem; 
}
.categories-nav::-webkit-scrollbar { display: none; }
.cat-link { 
  white-space: nowrap; padding: 0.6rem 1.3rem; border-radius: 50px; 
  font-size: 0.85rem; font-weight: 700; border: 1px solid rgba(255, 255, 255, 0.08); 
  background: rgba(255, 255, 255, 0.03); color: var(--muted); cursor: pointer; 
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 4px rgba(0,0,0,0.01); backdrop-filter: blur(5px);
}
.theme-white .cat-link {
  background: rgba(0, 0, 0, 0.02); border-color: rgba(0, 0, 0, 0.05);
}
.cat-link:hover { 
  background: rgba(255, 255, 255, 0.08); border-color: var(--primary); color: var(--primary); 
  transform: translateY(-1.5px);
}
.theme-white .cat-link:hover {
  background: rgba(0, 0, 0, 0.05);
}
.cat-link.active { 
  background: var(--primary); color: #fff; border-color: var(--primary); 
  box-shadow: 0 8px 20px var(--primary-glow); 
  transform: translateY(-2px); filter: brightness(1.05);
}



.p-cat { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); margin-bottom: 0.5rem; opacity: 0.8; }



.catalog-footer { text-align: center; margin-top: 6rem; padding: 4rem 1rem; opacity: 0.6; }

.footer-logo { font-size: 1.25rem; font-weight: 900; margin-bottom: 0.5rem; }

.catalog-footer p { font-size: 0.75rem; }



/* BUSCADOR */

.search-wrap { margin-bottom: 1.5rem; }

.search-input-group { position: relative; max-width: 100%; margin: 0 auto; }

.search-icon { position: absolute; left: 1.35rem; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; opacity: 0.75; transition: color 0.3s; }

.search-input { 
  width: 100%; padding: 0.95rem 1rem 0.95rem 3.4rem; border-radius: 50px; 
  border: 1px solid rgba(255, 255, 255, 0.08); 
  background: rgba(255, 255, 255, 0.04); 
  backdrop-filter: blur(10px);
  color: var(--text); font-size: 0.95rem; outline: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.05), 0 4px 12px rgba(0,0,0,0.02);
}
.theme-white .search-input {
  background: rgba(0, 0, 0, 0.02); border-color: rgba(0, 0, 0, 0.06); color: #1e293b;
}
.search-input::placeholder {
  color: var(--muted); opacity: 0.6;
}
.search-input:focus { 
  border-color: var(--primary); 
  background: rgba(255, 255, 255, 0.08);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05), 0 0 0 3px var(--primary-glow); 
  transform: translateY(-1px);
}
.search-input:focus + .search-icon {
  color: var(--primary);
}
.theme-white .search-input:focus {
  background: #ffffff;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03), 0 0 0 3px rgba(37, 99, 235, 0.15);
}



/* MODAL PRODUCTO */
.product-modal-overlay { 
  position: fixed; inset: 0; background: rgba(10, 10, 15, 0.7); backdrop-filter: blur(20px) saturate(1.2); 
  z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1rem; animation: fadeIn 0.3s ease; 
}
.product-modal-content { 
  background: var(--card); width: 100%; max-width: 900px; max-height: 90vh; border-radius: 24px; 
  position: relative; overflow: hidden; display: flex; flex-direction: column; 
  border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 30px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.04);
}
.theme-white .product-modal-content {
  border-color: rgba(0, 0, 0, 0.06); box-shadow: 0 30px 70px rgba(30, 41, 59, 0.12);
}
.modal-close { 
  position: absolute; top: 1rem; right: 1rem; width: 40px; height: 40px; border-radius: 50%; 
  background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08); 
  font-size: 1.5rem; color: var(--text); cursor: pointer; z-index: 10; 
  display: flex; align-items: center; justify-content: center; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
}
.theme-white .modal-close {
  background: rgba(0, 0, 0, 0.03); border-color: rgba(0, 0, 0, 0.05);
}
.modal-close:hover { 
  background: var(--primary); color: #fff; border-color: var(--primary); transform: rotate(90deg) scale(1.05); 
}

.modal-body { display: flex; flex-direction: row; height: 100%; overflow-y: auto; }
.modal-image-side { 
  flex: 1; background: rgba(0,0,0,0.15); display: flex; flex-direction: column; 
  align-items: center; justify-content: center; max-height: 600px; padding: 1.5rem; 
  border-right: 1px solid rgba(255,255,255,0.05);
}
.theme-white .modal-image-side {
  background: rgba(0,0,0,0.01); border-right-color: rgba(0,0,0,0.05);
}
.main-modal-img { 
  width: 100%; height: 380px; display: flex; align-items: center; justify-content: center; 
  position: relative;
}
.main-modal-img img { max-width: 100%; max-height: 100%; object-fit: contain; filter: drop-shadow(0 10px 25px rgba(0,0,0,0.2)); }

.modal-gallery { display: flex; gap: 0.6rem; margin-top: 1rem; flex-wrap: wrap; justify-content: center; }
.gallery-item { 
  width: 50px; height: 50px; border-radius: 8px; overflow: hidden; flex-shrink: 0;
  border: 2px solid rgba(255,255,255,0.08); background: rgba(0,0,0,0.1); cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
}
.theme-white .gallery-item {
  border-color: rgba(0,0,0,0.08); background: rgba(0,0,0,0.02);
}
.gallery-item:hover { transform: translateY(-2px) scale(1.05); border-color: var(--primary); }
.gallery-item img { width: 100%; height: 100%; object-fit: cover; }

.modal-info-side { flex: 1; padding: 2.5rem; display: flex; flex-direction: column; gap: 1.5rem; }

.modal-title { font-size: 2rem; font-weight: 900; line-height: 1.15; color: var(--text); margin-bottom: 0.25rem; letter-spacing: -0.01em; }
.modal-price { font-size: 2.5rem; font-weight: 900; color: var(--primary); }
.modal-description h3 { font-size: 0.9rem; font-weight: 800; text-transform: uppercase; color: var(--muted); margin-bottom: 0.5rem; letter-spacing: 0.5px; }
.modal-description p { line-height: 1.6; color: var(--text); opacity: 0.85; font-size: 0.95rem; }

/* Quantity controls in modal */
.modal-quantity {
  display: flex; align-items: center; gap: 1rem; margin-top: 1.5rem;
  background: rgba(255, 255, 255, 0.04); padding: 0.5rem 0.75rem;
  border-radius: 12px; width: fit-content;
  border: 1px solid rgba(255, 255, 255, 0.06);
}
.theme-white .modal-quantity {
  background: rgba(0, 0, 0, 0.02); border-color: rgba(0, 0, 0, 0.06);
}
.qty-label {
  font-size: 0.85rem; font-weight: 600; color: var(--muted); padding-left: 0.25rem;
}
.qty-controls {
  display: flex; align-items: center; gap: 0.5rem;
  background: rgba(255, 255, 255, 0.04); border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.theme-white .qty-controls {
  background: rgba(0, 0, 0, 0.02); border-color: rgba(0, 0, 0, 0.08);
}
.btn-qty-modal {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  border: none; background: none; color: var(--text); cursor: pointer; font-weight: bold;
  font-size: 1.1rem; transition: color 0.2s;
}
.btn-qty-modal:hover { color: var(--primary); }
.qty-value {
  width: 24px; text-align: center; font-weight: 700; font-size: 1rem; color: var(--text);
}

.modal-variants { margin-bottom: 1.5rem; }
.modal-variants h3 { font-size: 0.9rem; font-weight: 800; text-transform: uppercase; color: var(--muted); margin-bottom: 0.75rem; }
.variants-grid { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.variant-item { 
  background: var(--bg-muted); padding: 0.5rem 0.85rem; border-radius: 10px; 
  border: 1px solid var(--border); font-size: 0.9rem; 
}
.v-name { color: var(--muted); margin-right: 0.4rem; font-weight: 500; }
.v-val { font-weight: 700; color: var(--primary); }

.v-color-circle { 
  display: inline-block; width: 18px; height: 18px; border-radius: 50%; 
  border: 2px solid #fff; box-shadow: 0 0 0 1px var(--border);
  vertical-align: middle;
}
.variant-item.is-color { display: flex; align-items: center; }
.tag-badge-mini {
  background: rgba(99, 102, 241, 0.15);
  color: var(--primary);
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: inline-block;
}

.variant-item-color { display: flex; flex-direction: column; gap: 0.5rem; width: 100%; }
.color-stack { display: flex; flex-direction: column; align-items: center; width: fit-content; gap: 0.25rem; }
.v-color-circle-large { 
  width: 36px; height: 36px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.9); 
  box-shadow: 0 4px 12px rgba(0,0,0,0.18), inset 0 1px 2px rgba(255,255,255,0.3); 
  transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.v-color-opt { display: flex; flex-direction: column; align-items: center; gap: 0.4rem; cursor: pointer; transition: all 0.3s ease; }
.v-color-opt:hover .v-color-circle-large { transform: scale(1.2) translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,0.28), 0 0 0 3px var(--primary-glow); }
.v-color-name-tiny { font-size: 0.6rem; font-weight: 700; color: var(--text); text-transform: capitalize; opacity: 0.8; transition: opacity 0.3s; }
.v-color-opt:hover .v-color-name-tiny { opacity: 1; }

.variants-flex { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.v-pill-opt { 
  background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); padding: 0.5rem 1.1rem; 
  border-radius: 50px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 6px rgba(0,0,0,0.05); backdrop-filter: blur(5px); color: var(--text);
}
.theme-white .v-pill-opt { background: rgba(0,0,0,0.02); border-color: rgba(0,0,0,0.08); }
.v-pill-opt:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.08); }



.catalog-footer { margin-top: 6rem; padding: 4rem 1.5rem 2rem; background: rgba(0,0,0,0.02); border-top: 1px solid rgba(128,128,128,0.1); }

.footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 3rem; max-width: 1100px; margin: 0 auto; text-align: left; }

.footer-title { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 1.25rem; }

.social-icons { display: flex; gap: 0.75rem; }

.social-icon-btn { 

  width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center;

  background: var(--card); border: 1px solid var(--border); color: var(--text); transition: all 0.3s;

}

.social-icon-btn:hover { transform: translateY(-3px); border-color: var(--primary); color: var(--primary); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }

.footer-bottom { margin-top: 4rem; padding-top: 2rem; border-top: 1px solid rgba(128,128,128,0.1); text-align: center; font-size: 0.7rem; color: var(--muted); }



.modal-actions {
  display: flex; gap: 0.75rem; width: 100%; align-items: center; margin-top: 1rem;
}

.btn-add-cart-modal {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  background: var(--primary); color: var(--text); padding: 0.9rem; border-radius: 12px;
  font-weight: 800; border: none; cursor: pointer; height: 48px; font-size: 0.95rem;
  filter: brightness(0.95); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-add-cart-modal:hover { filter: brightness(1.05); transform: translateY(-2px); }

.btn-whatsapp-modal {
  flex: 1.2; display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  background: #25D366; color: white; padding: 0.9rem; border-radius: 12px;
  font-weight: 800; text-decoration: none; font-size: 0.95rem; height: 48px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 6px 20px rgba(37,211,102,0.25);
}

.btn-whatsapp-modal:hover { filter: brightness(1.05); transform: translateY(-2px); box-shadow: 0 10px 28px rgba(37,211,102,0.4); }



.btn-view-more { background: rgba(128,128,128,0.08); border: none; padding: 0.5rem 0.8rem; border-radius: 8px; font-size: 0.75rem; font-weight: 700; color: var(--text); cursor: pointer; transition: all 0.2s; }

.btn-view-more:hover { background: rgba(128,128,128,0.15); }



/* Transitions */

.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }

.modal-enter-from, .modal-leave-to { opacity: 0; }

.modal-enter-active .product-modal-content, .modal-leave-active .product-modal-content { transition: transform 0.3s ease; }

.modal-enter-from .product-modal-content, .modal-leave-to .product-modal-content { transform: scale(0.9); }



@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }



@media (max-width: 900px) {

  .modal-body { flex-direction: column; }

  .modal-image-side { 
    max-height: none; height: auto; min-height: 0; flex: none;
    padding: 1rem; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.05);
  }

  .main-modal-img { height: 260px; }

  .modal-gallery { margin-top: 0.75rem; gap: 0.5rem; }
  .gallery-item { width: 44px; height: 44px; }

  .modal-info-side { padding: 1.25rem; gap: 0.75rem; }

  .modal-title { font-size: 1.4rem; }

  .modal-price { font-size: 1.6rem; }

  .modal-description h3 { font-size: 0.8rem; margin-bottom: 0.35rem; }

  .modal-quantity { margin-top: 0.75rem; }

  .product-modal-content { max-height: 95vh; border-radius: 20px; }

  .product-modal-overlay { padding: 0.5rem; align-items: flex-end; }

}



.full-loading { min-height: 100vh; display: flex; align-items: center; justify-content: center; }



@media (max-width: 640px) {

  .catalog-container { padding: 1.5rem 1rem; }

  .catalog-header { flex-direction: column; gap: 1.5rem; text-align: center; margin-bottom: 2.5rem; padding-bottom: 1.5rem; }

  .company-brand { flex-direction: column; gap: 1rem; max-width: 100%; }

  .btn-whatsapp { width: 100%; justify-content: center; padding: 1rem; }

  .products-grid { 

    grid-template-columns: repeat(var(--mobile-cols, 1), 1fr) !important; 

    gap: 0.5rem !important; 

  }

  

  /* Adjustments for multi-column mobile */

  .p-card { border-radius: 12px; height: 100%; display: flex; flex-direction: column; }

  .p-image-wrap { height: 130px !important; } /* Aún más chico para que entren más productos en pantalla */

  .p-content { padding: 0.6rem; flex: 1; display: flex; flex-direction: column; }

  .p-info-wrap { display: flex; flex-direction: column; justify-content: space-between; flex: 1; }

  

  .p-name { font-size: 0.75rem; line-height: 1.2; margin-bottom: 0.5rem; height: 1.8rem; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

  .p-price { font-size: 0.9rem !important; margin-top: auto; white-space: nowrap; }

  

  /* When it's 1xP, keep image bigger */

  .catalog-page[style*="--mobile-cols: 1"] .p-image-wrap { height: 220px !important; }

  .catalog-page[style*="--mobile-cols: 1"] .p-name { font-size: 1.1rem; height: auto; }

  .catalog-page[style*="--mobile-cols: 1"] .p-price { font-size: 1.3rem !important; }



  .btn-view-more { display: none; }

  .p-footer { display: none; }

  .p-badge { font-size: 0.45rem; padding: 0.2rem 0.4rem; top: 0.4rem; right: 0.4rem; }

  /* MODAL MOBILE FIXES */
  .product-modal-overlay { padding: 0; align-items: flex-end; }
  .product-modal-content { 
    max-height: 100vh; max-height: 100dvh; border-radius: 20px 20px 0 0;
    height: 95vh; height: 95dvh;
  }
  .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; }
  .modal-close { 
    top: 0.75rem; right: 0.75rem; width: 34px; height: 34px; 
    background: rgba(0,0,0,0.4); border-color: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px); z-index: 20;
  }
  .modal-image-side { 
    min-height: 0; flex: none; padding: 1rem 1rem 0.75rem;
    max-height: none; height: auto;
    border-right: none; border-bottom: 1px solid rgba(255,255,255,0.06);
  }
  .main-modal-img { height: 220px; }
  .main-modal-img img { border-radius: 12px; }
  .modal-gallery { margin-top: 0.6rem; gap: 0.4rem; }
  .gallery-item { width: 40px; height: 40px; border-radius: 6px; }
  .modal-info-side { padding: 1rem 1.25rem 1.5rem; gap: 0.5rem; }
  .modal-title { font-size: 1.25rem; margin-bottom: 0; }
  .modal-price { font-size: 1.5rem; }
  .modal-description { margin-top: 0; }
  .modal-description h3 { font-size: 0.75rem; margin-bottom: 0.25rem; }
  .product-desc-html { font-size: 0.85rem; }
  .modal-quantity { margin-top: 0.5rem; padding: 0.4rem 0.6rem; }
  .qty-label { font-size: 0.8rem; }
  .modal-variants { margin-bottom: 0.5rem; }
  .modal-actions-wrap { padding-bottom: env(safe-area-inset-bottom, 0); }
  .btn-add-cart-modal, .btn-whatsapp-modal { font-size: 0.85rem !important; padding: 0.75rem !important; height: 44px !important; border-radius: 10px !important; }

}



/* Errores */

.error-view { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }

.error-card { text-align: center; }

.btn-back { display: inline-block; margin-top: 1rem; color: var(--primary); font-weight: 700; text-decoration: none; border-bottom: 2px solid; }



.product-desc-html { line-height: 1.6; color: var(--text); opacity: 0.8; font-size: 1rem; }

.product-desc-html :deep(ul), .product-desc-html :deep(ol) { margin-left: 1.5rem; margin-bottom: 1rem; }

.product-desc-html :deep(li) { margin-bottom: 0.25rem; }

.product-desc-html :deep(p) { margin-bottom: 0.75rem; }

.product-desc-html :deep(strong) { font-weight: 800; color: var(--primary); }



/* BOTON CARRITO EN EL HEADER */

.btn-cart-toggle {
  display: flex; align-items: center; gap: 0.5rem; 
  background: var(--card); border: 1px solid rgba(255, 255, 255, 0.08); 
  color: var(--text); padding: 0.7rem 1.2rem; border-radius: 12px; 
  font-weight: 800; font-size: 0.88rem; cursor: pointer; 
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03); position: relative;
  white-space: nowrap; flex-shrink: 0; backdrop-filter: blur(10px);
}
.theme-white .btn-cart-toggle {
  border-color: rgba(0, 0, 0, 0.06);
}
.btn-cart-toggle:hover {
  border-color: var(--primary); transform: translateY(-1px);
  box-shadow: 0 6px 16px var(--primary-glow);
}

.cart-badge-num {
  background: var(--primary); color: #fff; font-size: 0.65rem; 
  font-weight: 900; width: 20px; height: 20px; border-radius: 50%; 
  display: flex; align-items: center; justify-content: center; 
  line-height: 1; border: 2px solid var(--card);
  box-shadow: 0 2px 5px rgba(0,0,0,0.15);
}



/* BOTON AGREGAR EN TARJETA */
.btn-add-cart-card {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 0.45rem 1rem;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 2px 6px var(--primary-glow);
}
.btn-add-cart-card:hover {
  transform: translateY(-1.5px);
  filter: brightness(1.08);
  box-shadow: 0 6px 15px var(--primary-glow);
}
.btn-add-cart-card:active {
  transform: translateY(0);
}

/* BOTON PEDIR EN TARJETA */
.btn-pedir-card {
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  background: rgba(37, 211, 102, 0.08);
  color: #25D366;
  font-size: 0.75rem;
  font-weight: 800;
  padding: 0.45rem 0.85rem;
  border-radius: 8px;
  border: 1px solid rgba(37, 211, 102, 0.18);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.btn-pedir-card:hover {
  background: #25D366;
  color: #fff;
  border-color: #25D366;
  transform: translateY(-1.5px);
  box-shadow: 0 6px 15px rgba(37, 211, 102, 0.2);
}
.btn-pedir-card:active {
  transform: translateY(0);
}



/* DRAWER DEL CARRITO */
.cart-drawer-overlay {
  position: fixed; inset: 0; background: rgba(10, 10, 15, 0.5); backdrop-filter: blur(12px); 
  z-index: 1100; display: flex; justify-content: flex-end; animation: fadeIn 0.3s ease; 
}
.cart-drawer {
  background: var(--card); width: 100%; max-width: 420px; height: 100vh; 
  box-shadow: -15px 0 45px rgba(0, 0, 0, 0.3); display: flex; flex-direction: column; 
  color: var(--text); border-left: 1px solid rgba(255, 255, 255, 0.06);
}
.theme-white .cart-drawer {
  border-left-color: rgba(0, 0, 0, 0.05); box-shadow: -15px 0 45px rgba(30, 41, 59, 0.08);
}

.cart-drawer-header {
  padding: 1.5rem; border-bottom: 1px solid rgba(128, 128, 128, 0.1); 
  display: flex; justify-content: space-between; align-items: center; 
}
.cart-drawer-title { font-size: 1.25rem; font-weight: 900; }
.cart-drawer-close {
  background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08); 
  width: 34px; height: 34px; border-radius: 50%; font-size: 1.2rem; 
  display: flex; align-items: center; justify-content: center; cursor: pointer; 
  color: var(--text); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
}
.theme-white .cart-drawer-close {
  background: rgba(0, 0, 0, 0.03); border-color: rgba(0, 0, 0, 0.05);
}
.cart-drawer-close:hover {
  background: rgba(255, 255, 255, 0.12); transform: rotate(90deg) scale(1.05);
}
.theme-white .cart-drawer-close:hover {
  background: rgba(0, 0, 0, 0.07);
}

.cart-drawer-empty {
  flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; 
  gap: 1rem; padding: 2rem; color: var(--muted); 
}
.empty-cart-icon { font-size: 3rem; opacity: 0.5; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.15)); }
.btn-cart-empty-close {
  background: var(--primary); color: #fff; border: none; padding: 0.65rem 1.4rem; 
  border-radius: 10px; font-weight: 800; font-size: 0.85rem; cursor: pointer; 
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 12px var(--primary-glow);
}
.btn-cart-empty-close:hover {
  transform: translateY(-1.5px); box-shadow: 0 6px 16px var(--primary-glow);
}

.cart-drawer-body { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.cart-items-list { flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.cart-item-row {
  display: flex; align-items: center; gap: 1rem; background: rgba(255, 255, 255, 0.02); 
  padding: 0.85rem; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.05); position: relative;
}
.theme-white .cart-item-row {
  background: rgba(0, 0, 0, 0.01); border-color: rgba(0, 0, 0, 0.04);
}
.cart-item-img-wrap {
  width: 50px; height: 50px; border-radius: 8px; overflow: hidden; 
  background: rgba(0,0,0,0.1); flex-shrink: 0; border: 1px solid rgba(255,255,255,0.04);
}
.theme-white .cart-item-img-wrap {
  background: #f1f5f9; border-color: rgba(0,0,0,0.06);
}
.cart-item-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.cart-item-no-img { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.cart-item-info { flex: 1; min-width: 0; }
.cart-item-name {
  font-size: 0.85rem; font-weight: 800; margin-bottom: 0.25rem; 
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text);
}
.cart-item-price-row { display: flex; justify-content: space-between; align-items: center; }
.cart-item-price { font-size: 0.9rem; font-weight: 850; color: var(--primary); }

.cart-item-qty-selector {
  display: flex; align-items: center; gap: 0.35rem; 
  background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); 
  padding: 0.2rem; border-radius: 50px;
}
.theme-white .cart-item-qty-selector {
  background: rgba(0, 0, 0, 0.02); border-color: rgba(0, 0, 0, 0.06);
}
.btn-qty-delta {
  background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08); 
  width: 26px; height: 26px; border-radius: 50%; 
  font-size: 0.85rem; font-weight: 900; 
  cursor: pointer; color: var(--text); 
  display: flex; align-items: center; justify-content: center;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); line-height: 1;
}
.theme-white .btn-qty-delta {
  background: rgba(0,0,0,0.03); border-color: rgba(0,0,0,0.06);
}
.btn-qty-delta:hover {
  transform: scale(1.15);
  color: var(--primary); background: var(--primary-glow); border-color: var(--primary);
}
.btn-qty-delta:active { transform: scale(0.95); }
.qty-num { font-size: 0.8rem; font-weight: 900; min-width: 18px; text-align: center; }

.btn-cart-remove {
  background: none; border: none; font-size: 1.2rem; color: var(--muted); 
  cursor: pointer; transition: all 0.2s; padding: 0.2rem; line-height: 1; 
}
.btn-cart-remove:hover { color: #ef4444; transform: scale(1.1); }

.cart-drawer-footer { padding: 1.5rem; border-top: 1px solid rgba(128, 128, 128, 0.1); background: rgba(0,0,0,0.02); }
.cart-total-row { display: flex; justify-content: space-between; align-items: center; font-size: 1rem; font-weight: 800; margin-bottom: 1.25rem; }
.cart-total-val { font-size: 1.35rem; font-weight: 900; color: var(--primary); }

.btn-cart-checkout {
  display: flex; align-items: center; justify-content: center; gap: 0.6rem; 
  background: #25D366; color: #fff; padding: 1rem; border-radius: 14px; 
  font-weight: 800; font-size: 0.95rem; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 6px 20px rgba(37, 211, 102, 0.25);
}
.btn-cart-checkout:hover {
  filter: brightness(1.05); transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(37, 211, 102, 0.4);
}
.btn-cart-checkout:active {
  transform: translateY(0);
}

/* DRAWER ANIMATIONS */
.drawer-enter-active, .drawer-leave-active { transition: opacity 0.35s ease; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-active .cart-drawer { transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.drawer-leave-active .cart-drawer { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.drawer-enter-from .cart-drawer, .drawer-leave-to .cart-drawer { transform: translateX(100%); }

/* RESPONSIVE */
@media (max-width: 640px) {
  .btn-cart-toggle { padding: 0.5rem 0.75rem; }
  .btn-cart-toggle .cart-text { display: none; }
  .cart-drawer { max-width: 100%; }
}
</style>

