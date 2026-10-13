<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiDelete, apiGet } from '@/api/client'
import ExportMenu from '@/components/ExportMenu.vue'
import RequestDownloads from '@/components/RequestDownloads.vue'
import RequestSearch from '@/components/RequestSearch.vue'
import { useCustomerOptions } from '@/composables/useCustomerOptions'
import { useRequestFilters } from '@/composables/useRequestFilters'
import { duePeriodLabels, formatAddress, formatDate, hasLocation, isNew, priorityLabels, statusLabels } from '@/labels'

const route = useRoute()
const router = useRouter()
const { filters, hasFilters, apiPath, setFilter, setSearch, clearFilters } = useRequestFilters()
const customers = useCustomerOptions()

const requests = ref([])
// Pagination details from the API: current_page, last_page, from, to, total.
const meta = ref(null)
const loading = ref(true)
const error = ref('')

// Sort order, page and page size live in the URL like the filters
// (?sort=-priority&page=2&per_page=25); defaults are left out of it.
const PAGE_SIZES = [10, 25, 50, 100]
const DEFAULT_PAGE_SIZE = 10
const DEFAULT_SORT = 'due_date'

const sortOptions = {
  due_date: 'Määräpäivä, aikaisin ensin',
  '-due_date': 'Määräpäivä, myöhäisin ensin',
  '-priority': 'Prioriteetti, korkein ensin',
  priority: 'Prioriteetti, matalin ensin',
  status: 'Tila, avoimet ensin',
  '-status': 'Tila, valmiit ensin',
  title: 'Otsikko, A–Ö',
  '-title': 'Otsikko, Ö–A',
  '-created_at': 'Uusimmat ensin',
  created_at: 'Vanhimmat ensin',
}

// The first click on a column sorts the useful way round: high priority and
// the newest first, the rest ascending. Another click reverses it.
const FIRST_DIRECTION = { priority: '-priority', created_at: '-created_at' }

const sort = computed(() => (route.query.sort in sortOptions ? route.query.sort : DEFAULT_SORT))
const perPage = computed(() =>
  PAGE_SIZES.includes(Number(route.query.per_page)) ? Number(route.query.per_page) : DEFAULT_PAGE_SIZE,
)
const page = computed(() => Math.max(1, Number.parseInt(route.query.page, 10) || 1))
const total = computed(() => meta.value?.total ?? requests.value.length)

const listPath = computed(() => {
  const options = new URLSearchParams({
    ...(sort.value !== DEFAULT_SORT && { sort: sort.value }),
    per_page: perPage.value,
    ...(page.value > 1 && { page: page.value }),
  })

  return `${apiPath.value}${apiPath.value.includes('?') ? '&' : '?'}${options}`
})

function updateQuery(changes) {
  const query = { ...route.query, ...changes }

  if (query.sort === DEFAULT_SORT) delete query.sort
  if (Number(query.per_page) === DEFAULT_PAGE_SIZE) delete query.per_page
  if (Number(query.page) <= 1) delete query.page

  router.replace({ query })
}

function setSort(value) {
  updateQuery({ sort: value, page: 1 })
}

function toggleSort(field) {
  const next = sort.value === field ? `-${field}` : sort.value === `-${field}` ? field : (FIRST_DIRECTION[field] ?? field)
  setSort(next)
}

function ariaSort(field) {
  return sort.value === field ? 'ascending' : sort.value === `-${field}` ? 'descending' : undefined
}

function goToPage(number) {
  updateQuery({ page: number })
}

// Requests chosen for export, by id. A new filter or search starts a new selection.
const selected = ref(new Set())
const allOnPageSelected = computed(() => requests.value.length > 0 && requests.value.every((request) => selected.value.has(request.id)))

function toggleSelected(id) {
  const next = new Set(selected.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selected.value = next
}

function toggleAllOnPage() {
  const next = new Set(selected.value)
  for (const request of requests.value) {
    allOnPageSelected.value ? next.delete(request.id) : next.add(request.id)
  }
  selected.value = next
}

// The export gets the chosen ids, or else the list's filters and sort order.
const exportQuery = computed(() => {
  if (selected.value.size > 0) {
    return `ids=${[...selected.value].join(',')}`
  }

  const filterQuery = apiPath.value.split('?')[1] ?? ''
  return [filterQuery, sort.value !== DEFAULT_SORT ? `sort=${sort.value}` : ''].filter(Boolean).join('&')
})

watch(apiPath, () => (selected.value = new Set()))

async function loadRequests() {
  loading.value = true
  error.value = ''

  try {
    const response = await apiGet(listPath.value)
    requests.value = response.data
    meta.value = response.meta ?? null

    // A page past the end (e.g. after deleting its last request) shows the last page instead.
    if (meta.value && requests.value.length === 0 && meta.value.total > 0 && page.value > meta.value.last_page) {
      goToPage(meta.value.last_page)
    }
  } catch (e) {
    // An invalid filter typed into the address gets the API's Finnish message.
    error.value = e.status === 422 ? Object.values(e.errors).flat()[0] : 'Pyyntöjen lataaminen epäonnistui.'
  } finally {
    loading.value = false
  }
}

async function deleteRequest(request) {
  if (!window.confirm(`Poistetaanko pyyntö "${request.title}"?`)) {
    return
  }

  try {
    await apiDelete(`/requests/${request.id}`)
    // Reload, so the page fills up from the next one and the counts stay right.
    await loadRequests()
  } catch {
    error.value = 'Pyynnön poistaminen epäonnistui.'
  }
}

watch(listPath, loadRequests, { immediate: true })
</script>

<template>
  <section>
    <header class="page-header">
      <div>
        <h1 class="page-title">Pyynnöt</h1>
        <p v-if="!loading && total > 0" class="page-subtitle mt-1">
          {{ total }} {{ total === 1 ? 'pyyntö' : 'pyyntöä' }}
        </p>
      </div>
      <RouterLink to="/requests/new" class="btn btn-primary w-full sm:w-auto">+ Uusi pyyntö</RouterLink>
    </header>

    <div
      class="card mb-4 flex flex-wrap items-end gap-x-4 gap-y-3 p-4"
      role="search"
      aria-label="Suodata pyyntöjä"
    >
      <RequestSearch
        id="filter-search"
        class="flex-[2_1_16rem] sm:max-w-96"
        :model-value="filters.search"
        @search="setSearch"
      />

      <div class="grid flex-[1_1_10rem] gap-1 sm:max-w-64">
        <label for="filter-customer" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Asiakas</label>
        <select
          id="filter-customer"
          class="form-control lg:min-h-9 lg:py-1.5"
          :value="filters.customer"
          @change="setFilter('customer', $event.target.value)"
        >
          <option value="">Kaikki</option>
          <option v-for="customer in customers" :key="customer.id" :value="String(customer.id)">{{ customer.name }}</option>
        </select>
      </div>

      <div class="grid flex-[1_1_10rem] gap-1 sm:max-w-64">
        <label for="filter-status" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Tila</label>
        <select
          id="filter-status"
          class="form-control lg:min-h-9 lg:py-1.5"
          :value="filters.status"
          @change="setFilter('status', $event.target.value)"
        >
          <option value="">Kaikki</option>
          <option v-for="(label, value) in statusLabels" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
      </div>

      <div class="grid flex-[1_1_10rem] gap-1 sm:max-w-64">
        <label for="filter-priority" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Prioriteetti</label>
        <select
          id="filter-priority"
          class="form-control lg:min-h-9 lg:py-1.5"
          :value="filters.priority"
          @change="setFilter('priority', $event.target.value)"
        >
          <option value="">Kaikki</option>
          <option v-for="(label, value) in priorityLabels" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
      </div>

      <div class="grid flex-[1_1_10rem] gap-1 sm:max-w-64">
        <label for="filter-due" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Määräpäivä</label>
        <select
          id="filter-due"
          class="form-control lg:min-h-9 lg:py-1.5"
          :value="filters.due"
          @change="setFilter('due', $event.target.value)"
        >
          <option value="">Kaikki</option>
          <option v-for="(label, value) in duePeriodLabels" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
      </div>

      <template v-if="filters.due === 'range'">
        <div class="grid flex-[1_1_9rem] gap-1 sm:max-w-48">
          <label for="filter-due-from" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Alkaen</label>
          <input
            id="filter-due-from"
            class="form-control lg:min-h-9 lg:py-1.5"
            type="date"
            :value="filters.due_from"
            :max="filters.due_to || undefined"
            @change="setFilter('due_from', $event.target.value)"
          />
        </div>
        <div class="grid flex-[1_1_9rem] gap-1 sm:max-w-48">
          <label for="filter-due-to" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Asti</label>
          <input
            id="filter-due-to"
            class="form-control lg:min-h-9 lg:py-1.5"
            type="date"
            :value="filters.due_to"
            :min="filters.due_from || undefined"
            @change="setFilter('due_to', $event.target.value)"
          />
        </div>
      </template>

      <!-- On phones the table becomes cards without headers, so the order is chosen here. -->
      <div class="grid flex-[1_1_10rem] gap-1 sm:hidden">
        <label for="list-sort" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Järjestys</label>
        <select id="list-sort" class="form-control" :value="sort" @change="setSort($event.target.value)">
          <option v-for="(label, value) in sortOptions" :key="value" :value="value">{{ label }}</option>
        </select>
      </div>

      <button v-if="hasFilters" type="button" class="btn btn-secondary w-full sm:w-auto" @click="clearFilters">
        Tyhjennä suodattimet
      </button>
    </div>

    <p v-if="error" class="alert-error" role="alert">{{ error }}</p>

    <div v-if="loading" class="card card-body text-slate-500 dark:text-slate-400">Ladataan pyyntöjä…</div>

    <div v-else-if="requests.length === 0 && !error" class="card px-6 py-12 text-center">
      <template v-if="hasFilters">
        <p class="font-semibold">Ei suodattimia vastaavia pyyntöjä.</p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Kokeile muita suodattimia tai tyhjennä ne.</p>
      </template>
      <template v-else>
        <p class="font-semibold">Ei pyyntöjä.</p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Luo ensimmäinen pyyntö yllä olevasta painikkeesta.</p>
      </template>
    </div>

    <template v-else-if="requests.length > 0">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
      <p class="text-sm text-slate-500 dark:text-slate-400" aria-live="polite">
        <template v-if="selected.size > 0">
          <strong class="text-slate-900 dark:text-white">{{ selected.size }}</strong> valittu ·
          <button type="button" class="font-semibold text-primary-700 hover:underline dark:text-primary-400" @click="selected = new Set()">
            Poista valinnat
          </button>
        </template>
        <template v-else>Valitse pyyntöjä, jos haluat viedä vain ne.</template>
      </p>
      <div class="flex gap-2 max-sm:w-full max-sm:*:flex-1">
        <ExportMenu :query="exportQuery" :label="selected.size > 0 ? `Vie valitut (${selected.size})` : `Vie kaikki (${total})`" />
        <RouterLink to="/tools#tuo" class="btn btn-secondary">Tuo</RouterLink>
      </div>
    </div>

    <div
      class="card max-sm:border-0 max-sm:bg-transparent max-sm:shadow-none max-sm:dark:bg-transparent"
    >
      <table class="request-table">
        <thead>
          <tr>
            <th class="cell-select">
              <input
                type="checkbox"
                class="size-4 cursor-pointer accent-primary-700"
                :checked="allOnPageSelected"
                aria-label="Valitse kaikki tämän sivun pyynnöt"
                @change="toggleAllOnPage"
              />
            </th>
            <th v-for="column in [['title', 'Otsikko'], ['priority', 'Prioriteetti'], ['status', 'Tila'], ['due_date', 'Määräpäivä']]" :key="column[0]" :aria-sort="ariaSort(column[0])">
              <button type="button" class="sort-button" @click="toggleSort(column[0])">
                {{ column[1] }}
                <span class="sort-icon" :class="{ 'opacity-100': ariaSort(column[0]) }" aria-hidden="true">
                  {{ ariaSort(column[0]) === 'descending' ? '▼' : '▲' }}
                </span>
              </button>
            </th>
            <th><span class="sr-only">Toiminnot</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="request in requests" :key="request.id">
            <td class="cell-select">
              <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 sm:min-h-0">
                <input
                  type="checkbox"
                  class="size-4 cursor-pointer accent-primary-700"
                  :checked="selected.has(request.id)"
                  :aria-label="`Valitse: ${request.title}`"
                  @change="toggleSelected(request.id)"
                />
                <span class="text-sm text-slate-500 sm:sr-only dark:text-slate-400" aria-hidden="true">Valitse</span>
              </label>
            </td>
            <td class="cell-title">
              <RouterLink :to="`/requests/${request.id}`" class="hover:text-primary-700 hover:underline dark:hover:text-primary-400">
                {{ request.title }}
              </RouterLink>
              <span v-if="isNew(request)" class="badge badge-new ml-2 align-middle">Uusi</span>
              <span v-if="formatAddress(request)" class="block text-sm font-normal text-slate-500 dark:text-slate-400">
                {{ formatAddress(request) }}
              </span>
            </td>
            <td data-label="Prioriteetti">
              <span class="badge" :class="`priority-${request.priority}`">
                {{ priorityLabels[request.priority] }}
              </span>
            </td>
            <td data-label="Tila">
              <span class="badge" :class="`status-${request.status}`">
                {{ statusLabels[request.status] }}
              </span>
            </td>
            <td data-label="Määräpäivä" class="tabular-nums">{{ formatDate(request.due_date) }}</td>
            <td class="cell-actions">
              <!-- Phones: the actions form a 2×2 grid, so four buttons fit on narrow screens. -->
              <div class="flex justify-end gap-2 max-sm:grid max-sm:grid-cols-2">
                <RouterLink
                  v-if="hasLocation(request)"
                  :to="`/map?request=${request.id}`"
                  class="btn btn-secondary"
                  :aria-label="`Näytä kartalla: ${request.title}`"
                >
                  Kartalla
                </RouterLink>
                <RequestDownloads variant="menu" :request-id="request.id" :title="request.title" />
                <RouterLink :to="`/requests/${request.id}/edit`" class="btn btn-secondary">
                  Muokkaa
                </RouterLink>
                <button type="button" class="btn btn-danger" @click="deleteRequest(request)">
                  Poista
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </template>
    <nav
      v-if="meta && meta.total > 0 && !error"
      class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
      aria-label="Sivutus"
    >
      <p class="text-sm text-slate-500 tabular-nums dark:text-slate-400">
        Näytetään {{ meta.from }}–{{ meta.to }} / {{ meta.total }}
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <label for="per-page" class="text-sm text-slate-500 dark:text-slate-400">Näytä</label>
        <select
          id="per-page"
          class="form-control w-auto lg:min-h-9 lg:py-1.5"
          :value="perPage"
          @change="updateQuery({ per_page: $event.target.value, page: 1 })"
        >
          <option v-for="size in PAGE_SIZES" :key="size" :value="size">{{ size }}</option>
        </select>
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="meta.current_page <= 1"
          aria-label="Edellinen sivu"
          @click="goToPage(meta.current_page - 1)"
        >
          ←
        </button>
        <span class="text-sm tabular-nums" aria-live="polite">Sivu {{ meta.current_page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="meta.current_page >= meta.last_page"
          aria-label="Seuraava sivu"
          @click="goToPage(meta.current_page + 1)"
        >
          →
        </button>
      </div>
    </nav>
  </section>
</template>
