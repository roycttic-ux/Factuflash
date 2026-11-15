<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
const router = useRouter()
const auth = useAuthStore()
onMounted(() => {
  if (auth.isAuthenticated) router.replace('/dashboard')
  else window.location.replace(new URL('landing/index.html', document.baseURI).href)
})
</script>
<template><p role="status">Abriendo FactuFlash…</p></template>
