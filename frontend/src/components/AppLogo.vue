<template>
  <div class="logo-wrapper" :style="{ width: width + 'px' }">
    <svg v-if="collapsed" width="24" height="24" viewBox="0 0 24 24" fill="none" class="rayo-logo">
      <path d="M13 2L3 14H12L11 22L21 10H12L13 2Z" fill="url(#rayoGrad)" />
      <defs>
        <linearGradient id="rayoGrad" x1="3" y1="2" x2="21" y2="22" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#1fede8" />
          <stop offset="100%" stop-color="#a952e7" />
        </linearGradient>
      </defs>
    </svg>
    <img v-else src="@/assets/logo.svg" alt="FactuFlash Logo" class="factuflash-logo" />
  </div>
</template>

<script setup lang="ts">
defineProps({
  width: { type: [String, Number], default: 180 },
  collapsed: { type: Boolean, default: false }
})
</script>

<style scoped>
.logo-wrapper {
  display: flex;
  align-items: center;
  justify-content: center;
}
.factuflash-logo {
  width: 100%;
  height: auto;
  filter: drop-shadow(0 0 8px rgba(31, 237, 232, 0.3));
  transition: filter 0.3s ease;
}
.rayo-logo {
  filter: drop-shadow(0 0 6px rgba(31, 237, 232, 0.6));
}

/* 
   En la app, como el fondo suele ser oscuro, el logo es blanco por defecto.
   Si detectamos que el contenedor tiene una clase clara, podríamos invertirlo.
 */
:global(.light) .factuflash-logo {
  filter: brightness(0) drop-shadow(0 0 2px rgba(0,0,0,0.1));
}
</style>
