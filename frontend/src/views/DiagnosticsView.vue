<template>
  <div class="p-8 max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6 text-white">Panel de Diagnóstico</h1>
    
    <div v-if="loading" class="text-gray-400">Cargando diagnóstico...</div>
    
    <div v-else-if="error" class="bg-red-900/50 border border-red-500 text-red-200 p-4 rounded mb-6">
      Error al conectar con la API: {{ error }}
    </div>

    <div v-if="data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Sistema Core -->
      <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-xl">
        <h2 class="text-xl font-semibold mb-4 text-blue-400">Core</h2>
        <div class="space-y-2 text-gray-300">
          <p><span class="font-bold">PHP:</span> {{ data.php_version }}</p>
          <p><span class="font-bold">Laravel:</span> {{ data.laravel_version }}</p>
          <p><span class="font-bold">Ambiente:</span> {{ data.environment }}</p>
          <p><span class="font-bold">Debug:</span> 
            <span :class="data.debug_mode ? 'text-yellow-400' : 'text-green-400'">
              {{ data.debug_mode ? 'Activado (Peligro)' : 'Desactivado (Ok)' }}
            </span>
          </p>
        </div>
      </div>

      <!-- Base de Datos -->
      <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-xl">
        <h2 class="text-xl font-semibold mb-4 text-blue-400">Base de Datos</h2>
        <div class="space-y-2">
          <p :class="data.database.status === 'OK' ? 'text-green-400' : 'text-red-400'">
            Estado: {{ data.database.status }}
          </p>
          <p v-if="data.database.status === 'OK'" class="text-gray-300">
            Nombre: {{ data.database.database }}
          </p>
          <p v-else class="text-red-300 text-sm">
            {{ data.database.message }}
          </p>
        </div>
      </div>

      <!-- Almacenamiento -->
      <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-xl">
        <h2 class="text-xl font-semibold mb-4 text-blue-400">Storage (Permisos)</h2>
        <div class="space-y-2">
          <p :class="data.storage.writable ? 'text-green-400' : 'text-red-400'">
            Escritura: {{ data.storage.writable ? 'OK' : 'FAIL' }}
          </p>
          <p class="text-gray-400 text-xs truncate">Ruta: {{ data.storage.path }}</p>
        </div>
      </div>

      <!-- OpenSSL / ARCA -->
      <div class="bg-slate-800 p-6 rounded-xl border border-slate-700 shadow-xl">
        <h2 class="text-xl font-semibold mb-4 text-blue-400">Servicios AFIP/ARCA</h2>
        <div class="space-y-2">
          <p :class="data.openssl.available ? 'text-green-400' : 'text-red-400'">
            OpenSSL: {{ data.openssl.available ? 'Disponible' : 'No disponible' }}
          </p>
          <p class="text-gray-400 text-sm">Necesario para la facturación electrónica.</p>
        </div>
      </div>
    </div>

    <div class="mt-8 text-center">
      <button @click="fetchData" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
        Re-intentar Diagnóstico
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import api from '@/api/client'

const data = ref<any>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const fetchData = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await api.get('/diagnostics')
    data.value = res.data
  } catch (e: any) {
    error.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>
