<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import { apiDelete, apiGet } from '@/api/client'
import { formatAddress, formatDate, hasLocation, invoiceStatusLabels, isNew, priorityLabels, statusLabels, streetViewUrl } from '@/labels'
import CopyButton from '@/components/CopyButton.vue'
import DocumentLinks from '@/components/DocumentLinks.vue'
import QrCode from '@/components/QrCode.vue'
import QrDialog from '@/components/QrDialog.vue'
import RequestItems from '@/components/RequestItems.vue'
import ShareLink from '@/components/ShareLink.vue'
import { useDevice } from '@/composables/useDevice'
import { createMap, requestPin, STREET_ZOOM } from '@/map'
import { pageTitle } from '@/pageTitle'

const route = useRoute()
const router = useRouter()

const request = ref(null)
const loading = ref(true)
const notFound = ref(false)
const error = ref('')

const address = computed(() => (request.value ? formatAddress(request.value) : ''))

// The request's own address, for opening it on another device: a QR code on a
// computer; on a phone or tablet the share menu, or the code on the whole
// screen for a colleague to scan.
const { handheld } = useDevice()
const appUrl = computed(() => (request.value ? `${window.location.origin}/requests/${request.value.id}` : ''))
const qrOpen = ref(false)

// A completed request of a customer, with rows and not invoiced yet, can be invoiced.
const billable = computed(
  () =>
    request.value?.status === 'completed' && request.value.customer && !request.value.invoice && (request.value.items?.length ?? 0) > 0,
)

const mapContainer = useTemplateRef('mapContainer')
let destroyMap = null

// A small map of the request's location, shown once the request has loaded.
watch(mapContainer, (element) => {
  if (!element || destroyMap) {
    return
  }

  const point = [request.value.latitude, request.value.longitude]
  const created = createMap(element, { scrollWheelZoom: false })
  created.map.setView(point, STREET_ZOOM)
  L.marker(point, { icon: requestPin(request.value), title: request.value.title, keyboard: false }).addTo(created.map)
  destroyMap = created.destroy
})

onMounted(async () => {
  try {
    const response = await apiGet(`/requests/${route.params.id}`)
    request.value = response.data
    document.title = pageTitle(request.value.title)
  } catch (e) {
    if (e.status === 404) {
      notFound.value = true
    } else {
      error.value = 'Pyynnön lataaminen epäonnistui.'
    }
  } finally {
    loading.value = false
  }
})

onBeforeUnmount(() => destroyMap?.())

async function deleteRequest() {
  if (!window.confirm(`Poistetaanko pyyntö "${request.value.title}"?`)) {
    return
  }

  try {
    await apiDelete(`/requests/${request.value.id}`)
    router.push('/requests')
  } catch {
    error.value = 'Pyynnön poistaminen epäonnistui.'
  }
}
</script>

<template>
  <section class="max-w-3xl">
    <p v-if="error" class="alert-error" role="alert">{{ error }}</p>

    <div v-if="loading" class="card card-body text-slate-500 dark:text-slate-400">Ladataan pyyntöä…</div>

    <div v-else-if="notFound" class="card card-body">
      <p class="mb-4">Pyyntöä ei löytynyt.</p>
      <RouterLink to="/requests" class="btn btn-secondary">Takaisin pyyntöihin</RouterLink>
    </div>

    <template v-else-if="request">
      <header class="page-header">
        <div class="min-w-0">
          <h1 class="page-title break-words">{{ request.title }}</h1>
          <p class="mt-2 flex flex-wrap gap-2">
            <span v-if="isNew(request)" class="badge badge-new">Uusi</span>
            <span class="badge" :class="`priority-${request.priority}`">{{ priorityLabels[request.priority] }}</span>
            <span class="badge" :class="`status-${request.status}`">{{ statusLabels[request.status] }}</span>
          </p>
        </div>
        <div class="flex w-full gap-2 sm:w-auto max-sm:*:flex-1">
          <RouterLink :to="`/requests/${request.id}/edit`" class="btn btn-primary">Muokkaa</RouterLink>
          <button type="button" class="btn btn-danger" @click="deleteRequest">Poista</button>
        </div>
      </header>

      <div class="mb-4 flex flex-wrap items-center gap-2">
        <DocumentLinks :base="`/api/requests/${request.id}/export`" />
        <RouterLink
          v-if="billable"
          :to="`/invoices/new?customer=${request.customer.id}&request=${request.id}`"
          class="btn btn-primary max-sm:w-full"
        >
          Luo lasku
        </RouterLink>
        <template v-if="handheld">
          <ShareLink :url="appUrl" :title="request.title" class="max-sm:flex-1" />
          <button type="button" class="btn btn-secondary max-sm:flex-1" @click="qrOpen = true">Näytä QR-koodi</button>
        </template>
        <RouterLink
          v-if="request.invoice"
          :to="`/invoices/${request.invoice.id}`"
          class="font-semibold text-primary-700 hover:underline max-sm:w-full dark:text-primary-400"
        >
          Laskulla {{ request.invoice.number }} ({{ invoiceStatusLabels[request.invoice.status] }})
        </RouterLink>
      </div>

      <div class="card card-body grid gap-5">
        <div>
          <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400">Kuvaus</h2>
          <p class="mt-1 whitespace-pre-line">{{ request.description || 'Ei kuvausta.' }}</p>
        </div>

        <dl class="grid gap-4 sm:grid-cols-2">
          <div v-if="request.customer">
            <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">Asiakas</dt>
            <dd class="mt-1">
              <RouterLink :to="`/requests?customer=${request.customer.id}`" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">
                {{ request.customer.name }}
              </RouterLink>
            </dd>
          </div>
          <div>
            <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">Määräpäivä</dt>
            <dd class="mt-1 tabular-nums">{{ formatDate(request.due_date) }}</dd>
          </div>
          <div>
            <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">Osoite</dt>
            <dd class="mt-1">{{ address || '–' }}</dd>
          </div>
        </dl>

        <div v-if="request.signed_by || request.has_signature">
          <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400">Kuittaus</h2>
          <img
            v-if="request.has_signature"
            :src="`/api/requests/${request.id}/signature?v=${encodeURIComponent(request.signed_at ?? '')}`"
            :alt="`Allekirjoitus: ${request.signed_by ?? 'kuittaaja'}`"
            class="mt-2 block aspect-[3/1] w-full max-w-md rounded-lg border border-slate-200 bg-white object-contain dark:border-slate-700"
          />
          <p class="mt-2">
            Kuitannut <strong>{{ request.signed_by ?? '–' }}</strong><template v-if="request.signed_at">, {{ new Date(request.signed_at).toLocaleString('fi-FI') }}</template>
          </p>
        </div>

        <div v-if="hasLocation(request)">
          <div
            ref="mapContainer"
            class="h-64 overflow-hidden rounded-lg border border-slate-200 sm:h-72 dark:border-slate-700"
            role="region"
            :aria-label="`Kartta: ${address || request.title}`"
          ></div>
          <p class="mt-2 flex flex-wrap gap-x-5 gap-y-1">
            <RouterLink
              :to="`/map?request=${request.id}`"
              class="inline-flex min-h-11 items-center font-semibold text-primary-700 hover:underline lg:min-h-0 dark:text-primary-400"
            >
              Näytä suurella kartalla →
            </RouterLink>
            <a
              :href="streetViewUrl(request)"
              class="inline-flex min-h-11 items-center font-semibold text-primary-700 hover:underline lg:min-h-0 dark:text-primary-400"
              target="_blank"
              rel="noopener"
            >
              Avaa Street View ↗<span class="sr-only"> (avautuu uuteen välilehteen)</span>
            </a>
          </p>
        </div>
      </div>

      <RequestItems :request-id="request.id" class="mt-5" />

      <div v-if="!handheld" class="card card-body mt-5 flex items-center gap-5" data-test="open-on-phone">
        <QrCode :value="appUrl" alt="QR-koodi: avaa pyyntö puhelimella" class="size-28 shrink-0" />
        <div>
          <h2 class="text-lg font-bold">Avaa puhelimella</h2>
          <p class="mt-1 text-slate-600 dark:text-slate-300">Skannaa koodi puhelimen kameralla, niin pyyntö aukeaa siellä.</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <CopyButton :value="appUrl" label="Kopioi linkki" text="Kopioi linkki" copied-text="Linkki kopioitu" />
            <button type="button" class="btn btn-secondary" @click="qrOpen = true">Näytä suurena</button>
          </div>
        </div>
      </div>

      <QrDialog v-model:open="qrOpen" :value="appUrl" :title="request.title" alt="QR-koodi: avaa pyyntö puhelimella" :details="[['Pyyntö', `#${request.id}`]]" />

      <RouterLink to="/requests" class="mt-5 inline-block font-semibold text-primary-700 hover:underline dark:text-primary-400">
        ← Takaisin pyyntöihin
      </RouterLink>
    </template>
  </section>
</template>
