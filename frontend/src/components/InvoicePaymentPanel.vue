<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { invoicesApi, mpApi, posDevicesApi } from '@/api/services'

const props = defineProps<{ invoice: any }>()
const emit = defineEmits<{ updated: [invoice: any] }>()
const devices = ref<any[]>([])
const selected = ref('')
const receipt = ref('')
const method = ref(props.invoice.payment_method === 'mp' ? 'mp' : 'card')
const amount = ref<number>(Math.round((Number(props.invoice.total_local) - (props.invoice.payments || []).reduce((sum: number, p: any) => sum + Number(p.amount), 0)) * 100) / 100)
const confirmed = ref(false)
const busy = ref(false)
const message = ref('')
const polling = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined
let disposed = false
const device = computed(() => devices.value.find(d => String(d.id) === selected.value))
const balance = computed(() => Math.round((Number(props.invoice.total_local) - (props.invoice.payments || []).reduce((sum: number, p: any) => sum + Number(p.amount), 0)) * 100) / 100)
const money = (value: number) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(value)
onMounted(async () => {
  try {
    devices.value = (await posDevicesApi.list()).data.devices.filter((d: any) => d.is_active)
    selected.value = props.invoice.pos_device_id ? String(props.invoice.pos_device_id) : ''
  } catch { message.value = 'No se pudieron cargar los dispositivos. Podés registrar el comprobante manualmente.' }
})
onUnmounted(() => { disposed = true; stop() })
function stop() {
  polling.value = false
  if (timer) clearTimeout(timer)
}
function failure(e: any) {
  message.value = e.response?.data?.error || Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'No se pudo verificar el pago. La factura sigue pendiente.'
}
async function check() {
  if (busy.value) return
  busy.value = true
  try {
    const { data } = await mpApi.paymentStatus(props.invoice.id)
    if (disposed) return
    emit('updated', { ...props.invoice, ...data.invoice })
    if (data.invoice.status === 'paid') { stop(); message.value = 'Pago verificado en Mercado Pago.' }
    else {
      const statuses: Record<string, string> = {
        failed: 'El pago fue rechazado o falló. Revisá el detalle en Mercado Pago.',
        canceled: 'El cobro fue cancelado en Mercado Pago.',
        expired: 'La orden de cobro venció sin aprobación.',
        action_required: 'Revisá la terminal: Mercado Pago requiere una confirmación adicional.',
        refunded: 'El pago figura reembolsado en Mercado Pago.',
      }
      message.value = statuses[data.provider_status] || 'Todavía no hay un pago aprobado para esta factura.'
      if (statuses[data.provider_status]) stop()
    }
  } catch (e) { failure(e); stop() }
  finally { busy.value = false }
}
async function poll(until: number) {
  if (disposed || !polling.value) return
  await check()
  if (!polling.value || disposed) return
  if (Date.now() >= until) {
    stop()
    message.value = 'Terminó la espera. Podés volver a consultar el estado; no envíes otro cobro sin revisar el posnet.'
    return
  }
  timer = setTimeout(() => poll(until), 5000)
}
async function charge() {
  if (!device.value || busy.value) return
  busy.value = true
  message.value = ''
  try {
    const { data } = await mpApi.createPointIntent({ invoice_id: props.invoice.id, device_id: device.value.external_id, amount: balance.value })
    if (disposed) return
    emit('updated', { ...props.invoice, pos_device_id: device.value.id, point_request_key: 'sent', point_order_id: data.id })
    message.value = 'Cobro enviado. Completá el pago en el Point.'
    polling.value = true
    timer = setTimeout(() => poll(Date.now() + 120000), 5000)
  } catch (e) {
    failure(e)
    try {
      const { data } = await invoicesApi.get(props.invoice.id)
      if (!disposed) emit('updated', data.invoice)
    } catch { /* Keep the original payment error visible. */ }
  }
  finally { busy.value = false }
}
async function record() {
  if (busy.value || !confirmed.value || !receipt.value.trim()) return
  if (Math.round(Number(amount.value) * 100) !== Math.round(balance.value * 100)) {
    message.value = 'El importe cobrado debe coincidir con el saldo pendiente.'
    return
  }
  busy.value = true
  try {
    const { data } = await posDevicesApi.confirm({ invoice_id: props.invoice.id, external_id: device.value?.external_id, amount: Number(amount.value), payment_method: method.value, transaction_id: receipt.value.trim(), confirmed: true })
    emit('updated', { ...props.invoice, ...data.invoice })
    message.value = 'Cobro registrado con confirmación manual.'
  } catch (e) { failure(e) }
  finally { busy.value = false }
}
</script>

<template>
  <section class="card payment-panel" aria-label="Cobro de la factura">
    <div class="payment-heading"><strong>{{ invoice.status === 'paid' ? 'Factura cobrada' : invoice.status === 'pending' ? 'Pendiente de cobro' : 'Factura anulada' }}</strong><span v-if="invoice.status === 'pending'">Saldo: {{ money(balance) }}</span></div>
    <template v-if="invoice.status === 'pending'">
      <p>Registrá el cobro que ya efectuaste en tu posnet o billetera. No se genera un nuevo cargo.</p>
      <div v-if="invoice.point_request_key || invoice.point_order_id" class="payment-actions">
        <p>Esta factura ya tiene una orden enviada a Mercado Pago. Consultá su estado para evitar registrar un cobro duplicado.</p>
        <button v-if="!invoice.point_order_id && device" class="btn btn-primary" :disabled="busy || polling" @click="charge">Recuperar envío al Point</button>
        <button class="btn btn-ghost" :disabled="busy || polling" @click="check">Consultar pago en Mercado Pago</button>
        <button v-if="polling" class="btn btn-ghost" @click="stop">Dejar de consultar</button>
        <p v-if="polling">Esperando aprobación. Dejar de consultar no cancela el cobro en el dispositivo.</p>
      </div>
      <form v-else @submit.prevent="record" class="manual-payment">
        <p>Revisá que el comprobante muestre <strong>APROBADO</strong> por {{ money(balance) }}.</p>
        <label for="payment-method">Medio de pago</label>
        <select id="payment-method" v-model="method" class="form-select" :disabled="busy">
          <option value="card">Tarjeta / Posnet</option>
          <option value="mp">Mercado Pago / QR</option>
          <option value="transfer">Transferencia</option>
          <option value="cash">Efectivo</option>
        </select>
        <label for="payment-amount">Importe cobrado</label>
        <input id="payment-amount" v-model.number="amount" type="number" min="0.01" step="0.01" required class="form-input" :disabled="busy" />
        <template v-if="devices.length">
          <label for="payment-device">Dispositivo (opcional)</label>
          <select id="payment-device" v-model="selected" class="form-select" :disabled="busy">
            <option value="">Sin dispositivo registrado</option>
            <option v-for="d in devices" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
          </select>
        </template>
        <label for="payment-receipt">Número de operación o comprobante (incluí lote o fecha si se repite)</label>
        <input id="payment-receipt" v-model="receipt" class="form-input" required maxlength="150" :disabled="busy" />
        <label class="confirmation"><input v-model="confirmed" type="checkbox" required :disabled="busy" /> Verifiqué el importe y la aprobación en el comprobante.</label>
        <button class="btn btn-primary" :disabled="busy || !confirmed || !receipt.trim()">Registrar cobro confirmado</button>
      </form>
    </template>
    <p v-if="invoice.status === 'paid' && invoice.payments?.some((p: any) => p.details?.includes('Confirmación manual'))">Pago registrado manualmente según el comprobante verificado por el operador.</p>
    <p v-if="message" role="status" aria-live="polite">{{ message }}</p>
  </section>
</template>

<style scoped>
.payment-panel { margin-bottom: 1.5rem; display: grid; gap: .8rem; }
.payment-heading, .payment-actions { display:flex; flex-wrap:wrap; align-items:center; gap:1rem; justify-content:space-between; }
.payment-panel p { font-size:.9rem; line-height:1.6; margin:0; }
.manual-payment { display:grid; gap:.8rem; }
.confirmation { display:flex; align-items:flex-start; gap:.6rem; }
@media print { .payment-panel { display:none; } }
</style>
