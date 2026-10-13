<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiGet } from '@/api/client'
import ExportMenu from '@/components/ExportMenu.vue'
import { formatDate, formatEuro, invoiceStatusClass, invoiceStatusLabel } from '@/labels'

// The invoices, newest first, with a status filter kept in the URL.
const route = useRoute()
const router = useRouter()

const statusOptions = { draft: 'Luonnokset', sent: 'Lähetetyt', overdue: 'Erääntyneet', paid: 'Maksetut' }
const status = computed(() => (route.query.status in statusOptions ? route.query.status : ''))

const invoices = ref([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    invoices.value = (await apiGet(status.value ? `/invoices?status=${status.value}` : '/invoices')).data
  } catch {
    error.value = 'Laskujen lataaminen epäonnistui.'
  } finally {
    loading.value = false
  }
}

watch(status, load, { immediate: true })
</script>

<template>
  <section>
    <header class="page-header">
      <div>
        <h1 class="page-title">Laskut</h1>
        <p class="page-subtitle mt-1">Laskut tehdään valmiiden pyyntöjen töistä ja tarvikkeista.</p>
      </div>
      <RouterLink to="/invoices/new" class="btn btn-primary w-full sm:w-auto">+ Uusi lasku</RouterLink>
    </header>

    <div class="card mb-4 flex flex-wrap items-end justify-between gap-3 p-4">
      <div class="grid flex-[1_1_12rem] gap-1 sm:max-w-64">
        <label for="invoice-status" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Tila</label>
        <select
          id="invoice-status"
          class="form-control lg:min-h-9 lg:py-1.5"
          :value="status"
          @change="router.replace({ query: $event.target.value ? { status: $event.target.value } : {} })"
        >
          <option value="">Kaikki</option>
          <option v-for="(label, value) in statusOptions" :key="value" :value="value">{{ label }}</option>
        </select>
      </div>
      <ExportMenu
        :query="status ? `status=${status}` : ''"
        base="/api/invoices/export"
        label="Vie kirjanpitoon"
        class="max-sm:w-full"
      />
    </div>

    <p v-if="error" class="alert-error" role="alert">{{ error }}</p>
    <div v-if="loading" class="card card-body text-slate-500 dark:text-slate-400">Ladataan laskuja…</div>
    <div v-else-if="invoices.length === 0 && !error" class="card px-6 py-12 text-center">
      <p class="font-semibold">{{ status ? 'Ei valintaa vastaavia laskuja.' : 'Ei vielä laskuja.' }}</p>
      <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Luo lasku valmiista pyynnöistä, joilla on töitä tai tarvikkeita.</p>
    </div>

    <ul v-else class="grid gap-3">
      <li v-for="invoice in invoices" :key="invoice.id">
        <RouterLink
          :to="`/invoices/${invoice.id}`"
          class="card flex flex-wrap items-center gap-x-6 gap-y-2 p-4 transition-colors hover:border-primary-300 sm:p-5 dark:hover:border-primary-500/50"
        >
          <div class="min-w-0 flex-[1_1_14rem]">
            <p class="font-bold">Lasku {{ invoice.number }} <span class="badge ml-2 align-middle" :class="invoiceStatusClass(invoice)">{{ invoiceStatusLabel(invoice) }}</span></p>
            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ invoice.customer.name }}</p>
          </div>
          <dl class="grid grid-cols-2 gap-x-6 text-sm sm:flex">
            <div><dt class="text-slate-500 dark:text-slate-400">Laskun päivä</dt><dd class="tabular-nums">{{ formatDate(invoice.issue_date) }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Eräpäivä</dt><dd class="tabular-nums">{{ formatDate(invoice.due_date) }}</dd></div>
          </dl>
          <p class="text-lg font-bold tabular-nums sm:ml-auto">{{ formatEuro(invoice.totals.total) }}</p>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
