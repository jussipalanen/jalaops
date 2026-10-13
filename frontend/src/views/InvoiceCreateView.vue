<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiGet, apiPost } from '@/api/client'
import { useCustomerOptions } from '@/composables/useCustomerOptions'
import { formatDate, formatEuro } from '@/labels'

// A new invoice: choose the customer and which of their billable requests go
// on it. /invoices/new?customer=3&request=12 comes from a request's "Luo lasku".
const route = useRoute()
const router = useRouter()
const customers = useCustomerOptions()

const customerId = ref(/^\d+$/.test(route.query.customer ?? '') ? route.query.customer : '')
const requests = ref([])
const selected = ref(new Set())
const notes = ref('')
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const total = computed(() =>
  requests.value.filter((request) => selected.value.has(request.id)).reduce((sum, request) => sum + request.totals.total, 0),
)

async function loadBillable() {
  requests.value = []
  selected.value = new Set()
  error.value = ''
  if (!customerId.value) return

  loading.value = true
  try {
    requests.value = (await apiGet(`/customers/${customerId.value}/billable-requests`)).data
    // From a request's "Luo lasku" only that request is chosen; otherwise all.
    const only = Number(route.query.request)
    selected.value = new Set(requests.value.filter((request) => !only || request.id === only).map((request) => request.id))
  } catch {
    error.value = 'Laskutettavien pyyntöjen lataaminen epäonnistui.'
  } finally {
    loading.value = false
  }
}

function toggle(id) {
  const next = new Set(selected.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selected.value = next
}

async function create() {
  saving.value = true
  error.value = ''
  try {
    const invoice = (await apiPost('/invoices', { customer_id: Number(customerId.value), request_ids: [...selected.value], notes: notes.value.trim() || null })).data
    router.push(`/invoices/${invoice.id}`)
  } catch (e) {
    error.value = e.status === 422 ? Object.values(e.errors).flat()[0] : 'Laskun luominen epäonnistui.'
  } finally {
    saving.value = false
  }
}

watch(customerId, loadBillable, { immediate: true })
</script>

<template>
  <section class="max-w-3xl">
    <header class="page-header">
      <h1 class="page-title">Uusi lasku</h1>
    </header>

    <div class="card card-body grid gap-5">
      <div class="grid gap-1.5 sm:max-w-md">
        <label for="invoice-customer" class="text-[0.9375rem] font-semibold">Asiakas</label>
        <select id="invoice-customer" v-model="customerId" class="form-control">
          <option value="">Valitse asiakas</option>
          <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.name }}</option>
        </select>
      </div>

      <p v-if="error" class="alert-error mb-0" role="alert">{{ error }}</p>
      <p v-if="loading" class="text-slate-500 dark:text-slate-400">Ladataan laskutettavia pyyntöjä…</p>

      <template v-else-if="customerId">
        <div v-if="requests.length === 0" class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
          <p class="font-semibold">Asiakkaalla ei ole laskutettavia pyyntöjä.</p>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Laskulle voi ottaa valmiit pyynnöt, joilla on töitä tai tarvikkeita ja joita ei ole vielä laskutettu.
          </p>
        </div>

        <fieldset v-else class="grid gap-2">
          <legend class="mb-2 text-[0.9375rem] font-semibold">Laskutettavat pyynnöt</legend>
          <label
            v-for="request in requests"
            :key="request.id"
            class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
          >
            <input type="checkbox" class="mt-1 size-4 accent-primary-700" :checked="selected.has(request.id)" @change="toggle(request.id)" />
            <span class="min-w-0 flex-1">
              <span class="block font-semibold">{{ request.title }}</span>
              <span class="block text-sm text-slate-500 dark:text-slate-400">
                {{ request.items.length }} {{ request.items.length === 1 ? 'rivi' : 'riviä' }}<template v-if="request.due_date"> · määräpäivä {{ formatDate(request.due_date) }}</template>
              </span>
            </span>
            <span class="font-semibold tabular-nums">{{ formatEuro(request.totals.total) }}</span>
          </label>
        </fieldset>

        <template v-if="requests.length">
          <div class="grid gap-1.5">
            <label for="invoice-notes" class="text-[0.9375rem] font-semibold">Lisätiedot laskulle</label>
            <textarea id="invoice-notes" v-model="notes" class="form-control resize-y" rows="2" maxlength="2000"></textarea>
          </div>

          <p class="flex justify-between border-t border-slate-200 pt-3 text-lg font-bold dark:border-slate-800">
            <span>Yhteensä (sis. ALV)</span><span class="tabular-nums" aria-live="polite">{{ formatEuro(total) }}</span>
          </p>
        </template>
      </template>

      <div class="flex flex-col gap-3 sm:flex-row">
        <button type="button" class="btn btn-primary" :disabled="saving || selected.size === 0" @click="create">
          {{ saving ? 'Luodaan…' : 'Luo laskuluonnos' }}
        </button>
        <RouterLink to="/invoices" class="btn btn-secondary">Peruuta</RouterLink>
      </div>
    </div>
  </section>
</template>
