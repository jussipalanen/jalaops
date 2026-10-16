<script setup>
import { computed, onMounted, ref, version as vueVersion } from 'vue'
import { apiGet } from '@/api/client'
import { mobileAppsPage } from '@/features'
import { version as frontendVersion } from '../../package.json'

// The API docs and the backend home page are served by the Laravel backend.
const apiDocsUrl = import.meta.env.VITE_API_DOCS_URL || 'http://localhost:8000/docs'
const apiHomeUrl = new URL('/', apiDocsUrl).href

const system = ref(null)
const loading = ref(true)
const failed = ref(false)

const environmentLabels = { production: 'Tuotanto', local: 'Kehitys', testing: 'Testaus', staging: 'Testipalvelin' }

const appRows = computed(() => {
  const app = system.value?.app

  return [
    ['Frontendin versio', frontendVersion],
    ['API:n versio', app?.api_version ?? '–'],
    ['Ympäristö', app ? (environmentLabels[app.environment] ?? app.environment) : '–'],
    ['Demotila', app ? (app.demo ? 'Käytössä: tiedot palautuvat käynnistyksessä' : 'Ei käytössä') : '–'],
    ['Aikavyöhyke', app?.timezone ?? '–'],
    ['Palvelimen aika', app ? new Date(app.server_time).toLocaleString('fi-FI') : '–'],
  ]
})

const healthRows = computed(() => {
  const health = system.value?.health

  return [
    { name: 'API', ok: !failed.value && Boolean(system.value) },
    {
      name: 'Tietokanta',
      ok: health?.database.status === 'ok',
      note: health?.database.response_ms != null ? `${health.database.response_ms} ms` : null,
    },
    { name: 'Välimuisti', ok: health?.cache.status === 'ok' },
  ]
})

const detailRows = computed(() => {
  const details = system.value?.details

  return details
    ? [
        ['PHP', details.php],
        ['Laravel', details.laravel],
        ['Tietokanta', [details.database.driver, details.database.version].filter(Boolean).join(' ')],
        ['Välimuisti', details.cache_driver],
        ['Jono', details.queue_driver],
        ['Vue', vueVersion],
      ]
    : []
})

async function load() {
  loading.value = true
  failed.value = false

  try {
    system.value = await apiGet('/system')
  } catch {
    failed.value = true
    system.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="max-w-3xl">
    <header class="page-header">
      <div>
        <h1 class="page-title">Tietoja sovelluksesta</h1>
        <p class="page-subtitle mt-1">JalaOps on demosovellus huoltopyyntöjen hallintaan.</p>
      </div>
      <button type="button" class="btn btn-secondary w-full sm:w-auto" :disabled="loading" @click="load">
        {{ loading ? 'Päivitetään…' : 'Päivitä' }}
      </button>
    </header>

    <div class="grid gap-4">
      <div class="card card-body">
        <h2 class="text-lg font-bold">Tila</h2>
        <ul class="mt-3 grid gap-2 sm:grid-cols-3" aria-live="polite">
          <li
            v-for="row in healthRows"
            :key="row.name"
            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-800"
          >
            <span class="font-semibold">{{ row.name }}</span>
            <span v-if="loading" class="text-sm text-slate-500 dark:text-slate-400">Tarkistetaan…</span>
            <span v-else class="badge" :class="row.ok ? 'status-completed' : 'priority-high'">
              {{ row.ok ? 'Toimii' : 'Ei vastaa' }}<template v-if="row.ok && row.note"> · {{ row.note }}</template>
            </span>
          </li>
        </ul>
        <p v-if="failed" class="alert-error mt-4 mb-0" role="alert">API ei vastaa. Tarkista, että backend on käynnissä.</p>
      </div>

      <div class="card card-body">
        <h2 class="text-lg font-bold">Sovellus</h2>
        <dl class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2">
          <div v-for="[label, value] in appRows" :key="label">
            <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ label }}</dt>
            <dd class="mt-0.5 tabular-nums">{{ value }}</dd>
          </div>
        </dl>
      </div>

      <div class="card card-body">
        <h2 class="text-lg font-bold">Palvelinympäristö</h2>
        <dl v-if="detailRows.length" class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2">
          <div v-for="[label, value] in detailRows" :key="label">
            <dt class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ label }}</dt>
            <dd class="mt-0.5 tabular-nums">{{ value }}</dd>
          </div>
        </dl>
        <p v-else-if="!loading" class="mt-2 text-slate-500 dark:text-slate-400">
          Tarkemmat versiotiedot on piilotettu tässä ympäristössä. Ne saa näkyviin asetuksella
          <code class="rounded bg-slate-100 px-1 py-0.5 text-sm dark:bg-slate-800">SHOW_SYSTEM_DETAILS=true</code>.
        </p>
      </div>

      <div class="card card-body">
        <h2 class="text-lg font-bold">Linkit</h2>
        <ul class="mt-2 grid gap-1">
          <li v-if="mobileAppsPage"><RouterLink to="/mobile-apps" class="inline-flex min-h-11 items-center font-semibold text-primary-700 hover:underline lg:min-h-8 dark:text-primary-400">Mobiilisovellukset</RouterLink></li>
          <li><a :href="apiDocsUrl" class="inline-flex min-h-11 items-center font-semibold text-primary-700 hover:underline lg:min-h-8 dark:text-primary-400" target="_blank" rel="noopener">API-dokumentaatio ↗</a></li>
          <li><a :href="apiHomeUrl" class="inline-flex min-h-11 items-center font-semibold text-primary-700 hover:underline lg:min-h-8 dark:text-primary-400" target="_blank" rel="noopener">API-etusivu ↗</a></li>
          <li>
            <a href="https://github.com/jussipalanen/jalaops" class="inline-flex min-h-11 items-center font-semibold text-primary-700 hover:underline lg:min-h-8 dark:text-primary-400" target="_blank" rel="noopener">Lähdekoodi GitHubissa ↗</a>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

