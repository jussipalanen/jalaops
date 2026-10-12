<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import L from 'leaflet'
import { apiGet } from '@/api/client'
import RequestSearch from '@/components/RequestSearch.vue'
import { useRequestFilters } from '@/composables/useRequestFilters'
import { formatAddress, hasLocation, isNew, NEW_REQUEST_DAYS, priorityLabels, statusLabels } from '@/labels'
import { createMap, FINLAND_BOUNDS, requestPin, requestPopup, STREET_ZOOM } from '@/map'

const route = useRoute()
const router = useRouter()
const { filters, hasFilters, apiPath, setFilter, setSearch, clearFilters } = useRequestFilters()

const requests = ref([])
const loading = ref(true)
const error = ref('')
// On phones the list starts closed so the map is visible; on wider screens it's always shown.
const panelOpen = ref(false)

const located = computed(() => requests.value.filter(hasLocation))
const newCount = computed(() => located.value.filter((request) => isNew(request)).length)
const withoutLocation = computed(() => requests.value.length - located.value.length)

const container = useTemplateRef('container')
let map = null
let destroy = null
let pins = null
const markers = new Map()

function isWide() {
  return window.matchMedia?.('(min-width: 40rem)').matches ?? true
}

// Keeps pins clear of the request panel, which covers the left side on wide screens.
function fitPadding() {
  return isWide()
    ? { paddingTopLeft: [352, 32], paddingBottomRight: [32, 32] }
    : { paddingTopLeft: [24, 24], paddingBottomRight: [24, 96] }
}

function showPins() {
  pins.clearLayers()
  markers.clear()

  for (const request of located.value) {
    const marker = L.marker([request.latitude, request.longitude], {
      icon: requestPin(request),
      title: request.title,
      alt: request.title,
      riseOnHover: true,
    })
      .bindPopup(() => requestPopup(request, (selected) => router.push(`/requests/${selected.id}`)))
      .addTo(pins)

    markers.set(request.id, marker)
  }

  const focused = markers.get(Number(route.query.request))

  if (focused) {
    map.setView(focused.getLatLng(), STREET_ZOOM)
    focused.openPopup()
  } else if (markers.size > 0) {
    map.fitBounds(L.latLngBounds([...markers.values()].map((marker) => marker.getLatLng())), {
      ...fitPadding(),
      maxZoom: STREET_ZOOM,
    })
  } else {
    map.fitBounds(FINLAND_BOUNDS, fitPadding())
  }
}

// Clicking a request in the list flies to its pin and opens its popup.
function focusRequest(request) {
  const marker = markers.get(request.id)

  if (!marker) {
    return
  }

  if (!isWide()) {
    panelOpen.value = false
  }

  map.flyTo(marker.getLatLng(), Math.max(map.getZoom(), STREET_ZOOM), { duration: 0.8 })
  map.once('moveend', () => marker.openPopup())
}

async function loadRequests() {
  loading.value = true
  error.value = ''

  try {
    const response = await apiGet(apiPath.value)
    requests.value = response.data
  } catch {
    error.value = 'Pyyntöjen lataaminen epäonnistui.'
    requests.value = []
  } finally {
    loading.value = false
  }

  showPins()
}

onMounted(() => {
  ;({ map, destroy } = createMap(container.value, { zoomPosition: 'topright' }))
  pins = L.layerGroup().addTo(map)
  map.fitBounds(FINLAND_BOUNDS, fitPadding())

  watch(filters, loadRequests, { immediate: true, deep: true })
})

onBeforeUnmount(() => destroy?.())
</script>

<template>
  <section class="absolute inset-0">
    <h1 class="sr-only">Kartta</h1>

    <div ref="container" class="absolute inset-0" role="region" aria-label="Pyyntöjen kartta"></div>

    <aside
      class="absolute inset-x-2 bottom-2 z-[1000] flex max-h-[60%] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-lg backdrop-blur-md sm:inset-x-auto sm:top-4 sm:bottom-4 sm:left-4 sm:max-h-none sm:w-80 dark:border-slate-800 dark:bg-slate-900/95"
      aria-label="Kartan pyynnöt"
    >
      <div class="flex items-center justify-between gap-3 px-4 py-3">
        <div>
          <p class="font-bold">Pyynnöt kartalla</p>
          <p class="text-sm text-slate-500 dark:text-slate-400" aria-live="polite">
            <template v-if="loading">Ladataan…</template>
            <template v-else>
              {{ located.length }} {{ located.length === 1 ? 'pyyntö' : 'pyyntöä' }}<template v-if="newCount > 0">, {{ newCount }} {{ newCount === 1 ? 'uusi' : 'uutta' }}</template>
            </template>
          </p>
        </div>
        <button
          type="button"
          class="btn btn-secondary sm:hidden"
          :aria-expanded="panelOpen"
          aria-controls="map-panel-content"
          @click="panelOpen = !panelOpen"
        >
          {{ panelOpen ? 'Piilota lista' : 'Näytä lista' }}
        </button>
      </div>

      <div
        id="map-panel-content"
        class="min-h-0 flex-1 flex-col border-t border-slate-200 sm:flex dark:border-slate-800"
        :class="panelOpen ? 'flex' : 'hidden'"
      >
        <div class="grid grid-cols-2 gap-2 p-3" role="search" aria-label="Suodata kartan pyyntöjä">
          <RequestSearch id="map-filter-search" class="col-span-2" :model-value="filters.search" @search="setSearch" />
          <div class="grid gap-1">
            <label for="map-filter-status" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Tila</label>
            <select
              id="map-filter-status"
              class="form-control lg:min-h-9 lg:py-1.5"
              :value="filters.status"
              @change="setFilter('status', $event.target.value)"
            >
              <option value="">Kaikki</option>
              <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="grid gap-1">
            <label for="map-filter-priority" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Prioriteetti</label>
            <select
              id="map-filter-priority"
              class="form-control lg:min-h-9 lg:py-1.5"
              :value="filters.priority"
              @change="setFilter('priority', $event.target.value)"
            >
              <option value="">Kaikki</option>
              <option v-for="(label, value) in priorityLabels" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <button v-if="hasFilters" type="button" class="btn btn-secondary col-span-2" @click="clearFilters">
            Tyhjennä suodattimet
          </button>
        </div>

        <p v-if="error" class="alert-error mx-3" role="alert">{{ error }}</p>

        <p
          v-else-if="!loading && located.length === 0"
          class="px-4 pb-4 text-sm text-slate-500 dark:text-slate-400"
        >
          {{ hasFilters ? 'Ei suodattimia vastaavia pyyntöjä kartalla.' : 'Yhdelläkään pyynnöllä ei ole vielä sijaintia.' }}
        </p>

        <ul class="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
          <li v-for="request in located" :key="request.id">
            <button
              type="button"
              class="grid w-full cursor-pointer gap-1 rounded-xl px-3 py-2.5 text-left hover:bg-slate-100 dark:hover:bg-slate-800"
              @click="focusRequest(request)"
            >
              <span class="flex items-start gap-2 font-semibold">
                <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :class="`map-dot-${request.priority}`" aria-hidden="true"></span>
                {{ request.title }}
              </span>
              <span class="pl-4.5 text-sm text-slate-500 dark:text-slate-400">{{ formatAddress(request) }}</span>
              <span class="flex flex-wrap gap-1.5 pl-4.5">
                <span v-if="isNew(request)" class="badge badge-new">Uusi</span>
                <span class="badge" :class="`status-${request.status}`">{{ statusLabels[request.status] }}</span>
              </span>
            </button>
          </li>
        </ul>

        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-200 px-4 py-2.5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400" aria-label="Merkkien selitys">
          <span class="inline-flex items-center gap-1.5"><span class="map-dot-high size-2.5 rounded-full" aria-hidden="true"></span>Korkea</span>
          <span class="inline-flex items-center gap-1.5"><span class="map-dot-normal size-2.5 rounded-full" aria-hidden="true"></span>Normaali</span>
          <span class="inline-flex items-center gap-1.5"><span class="map-dot-low size-2.5 rounded-full" aria-hidden="true"></span>Matala</span>
          <span class="inline-flex items-center gap-1.5"><span class="map-legend-new" aria-hidden="true"></span>Uusi ({{ NEW_REQUEST_DAYS }} pv)</span>
        </p>

        <p
          v-if="!loading && withoutLocation > 0"
          class="border-t border-slate-200 px-4 py-3 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400"
        >
          {{ withoutLocation }} {{ withoutLocation === 1 ? 'pyyntö' : 'pyyntöä' }} ilman sijaintia.
          <RouterLink to="/requests" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">
            Näytä listana
          </RouterLink>
        </p>
      </div>
    </aside>
  </section>
</template>
