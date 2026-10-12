<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import { apiDelete, apiGet } from '@/api/client'
import { formatAddress, formatDate, hasLocation, isNew, priorityLabels, statusLabels, streetViewUrl } from '@/labels'
import RequestDownloads from '@/components/RequestDownloads.vue'
import { createMap, requestPin, STREET_ZOOM } from '@/map'
import { pageTitle } from '@/pageTitle'

const route = useRoute()
const router = useRouter()

const request = ref(null)
const loading = ref(true)
const notFound = ref(false)
const error = ref('')

const address = computed(() => (request.value ? formatAddress(request.value) : ''))

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

      <RequestDownloads :request-id="request.id" class="mb-4" />

      <div class="card card-body grid gap-5">
        <div>
          <h2 class="text-sm font-semibold text-slate-500 dark:text-slate-400">Kuvaus</h2>
          <p class="mt-1 whitespace-pre-line">{{ request.description || 'Ei kuvausta.' }}</p>
        </div>

        <dl class="grid gap-4 sm:grid-cols-2">
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
            role="img"
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

      <RouterLink to="/requests" class="mt-5 inline-block font-semibold text-primary-700 hover:underline dark:text-primary-400">
        ← Takaisin pyyntöihin
      </RouterLink>
    </template>
  </section>
</template>
