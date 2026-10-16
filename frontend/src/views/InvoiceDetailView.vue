<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiDelete, apiGet, apiRequestMethod } from '@/api/client'
import CopyButton from '@/components/CopyButton.vue'
import DocumentLinks from '@/components/DocumentLinks.vue'
import QrCode from '@/components/QrCode.vue'
import QrDialog from '@/components/QrDialog.vue'
import { useDevice } from '@/composables/useDevice'
import { formatDate, formatEuro, formatNumber, invoiceStatusClass, invoiceStatusLabel, itemTypeLabels } from '@/labels'
import { pageTitle } from '@/pageTitle'

// One invoice: details, rows by request, VAT by rate, the PDF and the status actions.
const route = useRoute()
const router = useRouter()

const invoice = ref(null)
const loading = ref(true)
const notFound = ref(false)
const error = ref('')
const working = ref(false)

const groups = computed(() => {
  const byRequest = new Map()
  for (const line of invoice.value?.lines ?? []) {
    const key = `${line.service_request_id}|${line.request_title}`
    if (!byRequest.has(key)) byRequest.set(key, { title: line.request_title, requestId: line.service_request_id, deliveredOn: line.delivered_on, lines: [] })
    byRequest.get(key).lines.push(line)
  }
  return [...byRequest.values()]
})

const billingAddress = computed(() =>
  invoice.value
    ? [invoice.value.billing_street_address, [invoice.value.billing_postal_code, invoice.value.billing_city].filter(Boolean).join(' ')].filter(Boolean).join(', ')
    : '',
)

// Paying the invoice: the bank details with copy buttons for a banking app, and
// the payment QR code (EPC QR) that the backend builds. On a computer the code
// is on the page; on a phone or tablet it opens on the whole screen, so the
// customer can scan it with their own phone.
const { handheld } = useDevice()
const qrOpen = ref(false)
const payment = computed(() => {
  if (!invoice.value) return null
  const amount = invoice.value.totals.total
  return {
    ...invoice.value.payment,
    // Banking apps take the amount with a decimal comma and no spaces: "1234,50".
    amount: amount.toFixed(2).replace('.', ','),
    details: [
      ['Summa', formatEuro(amount)],
      ['Viitenumero', invoice.value.reference_number],
      ['Saaja', invoice.value.payment.recipient],
    ],
  }
})

onMounted(async () => {
  try {
    invoice.value = (await apiGet(`/invoices/${route.params.id}`)).data
    document.title = pageTitle(`Lasku ${invoice.value.number}`)
  } catch (e) {
    e.status === 404 ? (notFound.value = true) : (error.value = 'Laskun lataaminen epäonnistui.')
  } finally {
    loading.value = false
  }
})

async function setStatus(status, question) {
  if (question && !window.confirm(question)) return
  working.value = true
  error.value = ''
  try {
    invoice.value = (await apiRequestMethod('PATCH', `/invoices/${invoice.value.id}/status`, { status })).data
  } catch (e) {
    error.value = e.data?.message ?? 'Tilan vaihtaminen epäonnistui.'
  } finally {
    working.value = false
  }
}

async function remove() {
  if (!window.confirm(`Poistetaanko laskuluonnos ${invoice.value.number}? Sen pyynnöt voi laskuttaa uudelleen.`)) return
  try {
    await apiDelete(`/invoices/${invoice.value.id}`)
    router.push('/invoices')
  } catch (e) {
    error.value = e.data?.message ?? 'Laskun poistaminen epäonnistui.'
  }
}
</script>

<template>
  <section class="max-w-4xl">
    <p v-if="error" class="alert-error" role="alert">{{ error }}</p>
    <div v-if="loading" class="card card-body text-slate-500 dark:text-slate-400">Ladataan laskua…</div>
    <div v-else-if="notFound" class="card card-body">
      <p class="mb-4">Laskua ei löytynyt.</p>
      <RouterLink to="/invoices" class="btn btn-secondary">Takaisin laskuihin</RouterLink>
    </div>

    <template v-else-if="invoice">
      <header class="page-header">
        <div>
          <h1 class="page-title">Lasku {{ invoice.number }}</h1>
          <p class="mt-2"><span class="badge" :class="invoiceStatusClass(invoice)">{{ invoiceStatusLabel(invoice) }}</span></p>
        </div>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto max-sm:*:flex-1">
          <button v-if="invoice.status === 'draft'" type="button" class="btn btn-primary" :disabled="working" @click="setStatus('sent', 'Merkitäänkö lasku lähetetyksi? Lähetettyä laskua ei voi enää poistaa.')">Merkitse lähetetyksi</button>
          <button v-if="invoice.status === 'sent'" type="button" class="btn btn-primary" :disabled="working" @click="setStatus('paid')">Merkitse maksetuksi</button>
          <button v-if="invoice.status === 'paid'" type="button" class="btn btn-secondary" :disabled="working" @click="setStatus('sent', 'Palautetaanko lasku maksamattomaksi?')">Palauta maksamattomaksi</button>
          <button v-if="invoice.status === 'draft'" type="button" class="btn btn-danger" @click="remove">Poista luonnos</button>
        </div>
      </header>

      <DocumentLinks :base="`/api/invoices/${invoice.id}/export`" class="mb-4" />

      <div class="grid gap-4">
        <div class="card card-body grid gap-5 sm:grid-cols-2">
          <div>
            <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400">Laskutettava</h2>
            <p class="mt-1 font-semibold">{{ invoice.customer.name }}</p>
            <p v-if="billingAddress">{{ billingAddress }}</p>
            <p v-if="invoice.customer_business_id" class="text-sm text-slate-500 dark:text-slate-400">Y-tunnus {{ invoice.customer_business_id }}</p>
          </div>
          <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
            <dt class="text-slate-500 dark:text-slate-400">Laskun päivä</dt><dd class="tabular-nums">{{ formatDate(invoice.issue_date) }}</dd>
            <dt class="text-slate-500 dark:text-slate-400">Eräpäivä</dt><dd class="font-semibold tabular-nums">{{ formatDate(invoice.due_date) }}</dd>
            <dt class="text-slate-500 dark:text-slate-400">Viitenumero</dt><dd class="tabular-nums">{{ invoice.reference_number }}</dd>
            <template v-if="invoice.paid_at"><dt class="text-slate-500 dark:text-slate-400">Maksettu</dt><dd class="tabular-nums">{{ new Date(invoice.paid_at).toLocaleDateString('fi-FI') }}</dd></template>
          </dl>
        </div>

        <div class="card card-body">
          <h2 class="text-lg font-bold">Rivit</h2>
          <div v-for="group in groups" :key="`${group.requestId}-${group.title}`" class="mt-4">
            <h3 class="font-semibold">
              <RouterLink v-if="group.requestId" :to="`/requests/${group.requestId}`" class="hover:underline">{{ group.title }}</RouterLink>
              <template v-else>{{ group.title }}</template>
              <span v-if="group.deliveredOn" class="text-sm font-normal text-slate-500 dark:text-slate-400"> · toimitettu {{ formatDate(group.deliveredOn) }}</span>
            </h3>
            <ul class="mt-1 divide-y divide-slate-200 text-sm dark:divide-slate-800">
              <li v-for="line in group.lines" :key="line.id" class="flex flex-wrap justify-between gap-x-4 py-2">
                <span>{{ line.description }} <span class="text-xs text-slate-500 dark:text-slate-400">{{ itemTypeLabels[line.type] }}</span></span>
                <span class="tabular-nums">
                  {{ formatNumber(line.quantity) }} {{ line.unit }} × {{ formatEuro(line.unit_price) }} + {{ formatNumber(line.vat_rate) }} % =
                  <strong>{{ formatEuro(line.total) }}</strong>
                </span>
              </li>
            </ul>
          </div>

          <table class="mt-5 ml-auto text-sm tabular-nums" aria-label="ALV-erittely">
            <thead class="text-slate-500 dark:text-slate-400">
              <tr><th class="pr-6 text-right font-semibold">ALV</th><th class="pr-6 text-right font-semibold">Veroton</th><th class="pr-6 text-right font-semibold">Vero</th><th class="text-right font-semibold">Yhteensä</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in invoice.vat_breakdown" :key="row.rate">
                <td class="pr-6 text-right">{{ formatNumber(row.rate) }} %</td>
                <td class="pr-6 text-right">{{ formatEuro(row.net) }}</td>
                <td class="pr-6 text-right">{{ formatEuro(row.vat) }}</td>
                <td class="text-right">{{ formatEuro(row.net + row.vat) }}</td>
              </tr>
            </tbody>
          </table>
          <p class="mt-3 flex justify-end gap-6 border-t border-slate-300 pt-3 text-lg font-bold dark:border-slate-700">
            <span>Maksettava yhteensä</span><span class="tabular-nums">{{ formatEuro(invoice.totals.total) }}</span>
          </p>
          <p v-if="invoice.notes" class="mt-3 text-sm"><span class="text-slate-500 dark:text-slate-400">Lisätiedot:</span> {{ invoice.notes }}</p>
        </div>

        <div class="card card-body" data-test="payment">
          <h2 class="text-lg font-bold">Maksutiedot</h2>
          <p v-if="invoice.status === 'paid'" class="mt-1 text-slate-600 dark:text-slate-300">Lasku on maksettu.</p>
          <div class="mt-3 grid items-start gap-5 sm:grid-cols-[1fr_auto]">
            <dl class="grid gap-3">
              <div>
                <dt class="text-sm text-slate-500 dark:text-slate-400">Saaja</dt>
                <dd class="font-semibold">{{ payment.recipient }}</dd>
              </div>
              <div>
                <dt class="text-sm text-slate-500 dark:text-slate-400">Tilinumero (IBAN)<template v-if="payment.bic"> / BIC</template></dt>
                <dd v-if="payment.iban" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <span class="font-semibold tabular-nums">{{ payment.iban }}<template v-if="payment.bic"> / {{ payment.bic }}</template></span>
                  <CopyButton :value="payment.iban.replace(/\s/g, '')" label="Kopioi tilinumero" class="lg:min-h-8" />
                </dd>
                <dd v-else class="text-slate-500 dark:text-slate-400">Ei asetettu</dd>
              </div>
              <div>
                <dt class="text-sm text-slate-500 dark:text-slate-400">Viitenumero</dt>
                <dd class="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <span class="font-semibold tabular-nums">{{ invoice.reference_number }}</span>
                  <CopyButton :value="invoice.reference_number.replace(/\s/g, '')" label="Kopioi viitenumero" class="lg:min-h-8" />
                </dd>
              </div>
              <div>
                <dt class="text-sm text-slate-500 dark:text-slate-400">Summa · eräpäivä {{ formatDate(invoice.due_date) }}</dt>
                <dd class="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <span class="font-semibold tabular-nums">{{ formatEuro(invoice.totals.total) }}</span>
                  <CopyButton :value="payment.amount" label="Kopioi summa" class="lg:min-h-8" />
                </dd>
              </div>
            </dl>

            <div v-if="payment.qr" class="flex flex-col items-center gap-2 sm:w-44">
              <QrCode v-if="!handheld" :value="payment.qr" alt="Maksun QR-koodi" class="size-40 border border-slate-200 dark:border-transparent" />
              <p v-if="!handheld" class="text-center text-sm text-slate-500 dark:text-slate-400">Maksa skannaamalla koodi pankkisovelluksella.</p>
              <button type="button" class="btn btn-primary w-full" @click="qrOpen = true">{{ handheld ? 'Näytä maksukoodi' : 'Näytä suurena' }}</button>
            </div>
          </div>
          <QrDialog v-if="payment.qr" v-model:open="qrOpen" :value="payment.qr" title="Maksa pankkisovelluksella" alt="Maksun QR-koodi" :details="payment.details" />
        </div>
      </div>
    </template>
  </section>
</template>
