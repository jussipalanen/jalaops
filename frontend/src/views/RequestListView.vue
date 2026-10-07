<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { apiDelete, apiGet } from '@/api/client'
import { formatDate, priorityLabels, statusLabels } from '@/labels'

const route = useRoute()
const router = useRouter()

const requests = ref([])
const loading = ref(true)
const error = ref('')

// The filters live in the URL (?status=open&priority=high), so a filtered
// list survives a reload and can be shared. Unknown values are ignored.
const filters = computed(() => ({
  status: route.query.status in statusLabels ? route.query.status : '',
  priority: route.query.priority in priorityLabels ? route.query.priority : '',
}))

const hasFilters = computed(() => Boolean(filters.value.status || filters.value.priority))

function setFilter(name, value) {
  const query = { ...filters.value, [name]: value }

  // Leave empty filters out of the URL.
  router.replace({
    query: Object.fromEntries(Object.entries(query).filter(([, filterValue]) => filterValue)),
  })
}

function clearFilters() {
  router.replace({ query: {} })
}

async function loadRequests() {
  loading.value = true
  error.value = ''

  const params = new URLSearchParams(
    Object.entries(filters.value).filter(([, filterValue]) => filterValue),
  ).toString()

  try {
    const response = await apiGet(params ? `/requests?${params}` : '/requests')
    requests.value = response.data
  } catch {
    error.value = 'Pyyntöjen lataaminen epäonnistui.'
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
    requests.value = requests.value.filter((item) => item.id !== request.id)
  } catch {
    error.value = 'Pyynnön poistaminen epäonnistui.'
  }
}

watch(filters, loadRequests, { immediate: true, deep: true })
</script>

<template>
  <section>
    <header class="page-header">
      <div>
        <h1 class="page-title">Pyynnöt</h1>
        <p v-if="!loading && requests.length > 0" class="page-subtitle mt-1">
          {{ requests.length }} {{ requests.length === 1 ? 'pyyntö' : 'pyyntöä' }}
        </p>
      </div>
      <RouterLink to="/requests/new" class="btn btn-primary w-full sm:w-auto">+ Uusi pyyntö</RouterLink>
    </header>

    <div
      class="card mb-4 flex flex-wrap items-end gap-x-4 gap-y-3 p-4"
      role="search"
      aria-label="Suodata pyyntöjä"
    >
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

    <div
      v-else-if="requests.length > 0"
      class="card overflow-hidden max-sm:overflow-visible max-sm:border-0 max-sm:bg-transparent max-sm:shadow-none max-sm:dark:bg-transparent"
    >
      <table class="request-table">
        <thead>
          <tr>
            <th>Otsikko</th>
            <th>Prioriteetti</th>
            <th>Tila</th>
            <th>Määräpäivä</th>
            <th><span class="sr-only">Toiminnot</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="request in requests" :key="request.id">
            <td class="cell-title">{{ request.title }}</td>
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
              <div class="flex justify-end gap-2 max-sm:*:flex-1">
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
  </section>
</template>
