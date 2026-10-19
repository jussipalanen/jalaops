<script setup>
import { computed, ref, useTemplateRef } from 'vue'
import { RouterLink } from 'vue-router'
import { apiUpload } from '@/api/client'
import ExportMenu from '@/components/ExportMenu.vue'
import ModuleList from '@/components/ModuleList.vue'
import { formatDate, priorityLabels, statusLabels } from '@/labels'

// Export: all requests, or those with a status and/or priority.
const exportStatus = ref('')
const exportPriority = ref('')
const exportQuery = computed(() =>
  new URLSearchParams(
    Object.entries({ status: exportStatus.value, priority: exportPriority.value }).filter(([, value]) => value),
  ).toString(),
)

// Import: preview the file first (dry run), then import it.
const fileInput = useTemplateRef('fileInput')
const file = ref(null)
const report = ref(null)
const working = ref(false)
const error = ref('')
const imported = ref(0)

const canImport = computed(() => report.value?.dry_run && report.value.invalid === 0 && report.value.valid > 0)

function chooseFile(event) {
  file.value = event.target.files[0] ?? null
  report.value = null
  error.value = ''
  imported.value = 0
}

async function send(dryRun) {
  working.value = true
  error.value = ''

  const form = new FormData()
  form.append('file', file.value)
  form.append('dry_run', dryRun ? '1' : '0')

  try {
    const result = await apiUpload('/requests/import', form)
    report.value = result

    if (!dryRun) {
      imported.value = result.imported
      file.value = null
      fileInput.value.value = ''
    }
  } catch (e) {
    // 422: an unreadable file, or invalid rows when importing for real.
    report.value = e.data?.total !== undefined ? e.data : null
    error.value =
      e.status === 422
        ? (e.data?.message ?? 'Tiedostoa ei voitu tuoda.')
        : e.status === 429
          ? 'Liian monta tuontia. Odota hetki ja yritä uudelleen.'
          : 'Tuonti epäonnistui.'
  } finally {
    working.value = false
  }
}
</script>

<template>
  <section class="max-w-3xl">
    <header class="page-header">
      <div>
        <h1 class="page-title">Työkalut</h1>
        <p class="page-subtitle mt-1">Vie pyynnöt tiedostoon, tuo niitä tiedostosta tai käytä palvelimen moduuleja.</p>
      </div>
    </header>

    <div class="grid gap-4">
      <div class="card card-body">
        <h2 class="text-lg font-bold">Vie pyynnöt</h2>
        <p class="mt-1 text-slate-600 dark:text-slate-300">
          CSV avautuu Excelissä. JSON on täysi kopio allekirjoituksineen, ja sen voi tuoda takaisin sellaisenaan.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
          <div class="grid flex-[1_1_10rem] gap-1">
            <label for="export-status" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Tila</label>
            <select id="export-status" v-model="exportStatus" class="form-control lg:min-h-9 lg:py-1.5">
              <option value="">Kaikki</option>
              <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <div class="grid flex-[1_1_10rem] gap-1">
            <label for="export-priority" class="text-[0.8125rem] font-semibold text-slate-500 dark:text-slate-400">Prioriteetti</label>
            <select id="export-priority" v-model="exportPriority" class="form-control lg:min-h-9 lg:py-1.5">
              <option value="">Kaikki</option>
              <option v-for="(label, value) in priorityLabels" :key="value" :value="value">{{ label }}</option>
            </select>
          </div>
          <ExportMenu :query="exportQuery" label="Vie pyynnöt" class="max-sm:w-full" />
        </div>

        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
          Yksittäiset pyynnöt voit valita ja viedä
          <RouterLink to="/requests" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">pyyntölistalta</RouterLink>.
        </p>
      </div>

      <div id="tuo" class="card card-body">
        <h2 class="text-lg font-bold">Tuo pyyntöjä</h2>
        <p class="mt-1 text-slate-600 dark:text-slate-300">
          Valitse CSV- tai JSON-tiedosto, esimerkiksi aiemmin viety. Tiedosto tarkistetaan ensin, ja pyynnöt tuodaan uusina
          vasta, kun kaikki rivit ovat kunnossa.
        </p>

        <div class="mt-4 grid gap-1.5">
          <span id="import-file-label" class="text-[0.9375rem] font-semibold">Tiedosto</span>
          <!-- The browser's own file button follows the browser's language, so the
               visible button is a Finnish label for a visually hidden file input. -->
          <div class="flex flex-wrap items-center gap-3">
            <input
              id="import-file"
              ref="fileInput"
              type="file"
              accept=".csv,.json,text/csv,application/json"
              class="peer sr-only"
              aria-labelledby="import-file-label import-file-button"
              @change="chooseFile"
            />
            <label
              id="import-file-button"
              for="import-file"
              class="btn btn-secondary peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-500/50"
            >
              Valitse tiedosto
            </label>
            <span class="min-w-0 break-all text-slate-600 dark:text-slate-300">{{ file ? file.name : 'Ei valittua tiedostoa' }}</span>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            CSV:n ensimmäisellä rivillä ovat sarakkeiden nimet: <code>title</code>, <code>priority</code> ja <code>status</code>
            ovat pakollisia; lisäksi voi olla <code>description</code>, <code>due_date</code>, <code>street_address</code>,
            <code>postal_code</code>, <code>city</code>, <code>latitude</code> ja <code>longitude</code>. Helpointa on viedä
            ensin CSV ja käyttää sitä pohjana. Enintään 1000 riviä ja 2 Mt.
          </p>
        </div>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
          <button type="button" class="btn btn-secondary" :disabled="!file || working" @click="send(true)">
            {{ working && !canImport ? 'Tarkistetaan…' : 'Tarkista tiedosto' }}
          </button>
          <button type="button" class="btn btn-primary" :disabled="!canImport || working" @click="send(false)">
            {{
              working && canImport
                ? 'Tuodaan…'
                : canImport
                  ? `Tuo ${report.valid} ${report.valid === 1 ? 'pyyntö' : 'pyyntöä'}`
                  : 'Tuo pyynnöt'
            }}
          </button>
        </div>

        <p v-if="error" class="alert-error mt-4 mb-0" role="alert">{{ error }}</p>

        <p v-if="imported" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" role="status">
          {{ imported }} {{ imported === 1 ? 'pyyntö tuotiin' : 'pyyntöä tuotiin' }}.
          <RouterLink to="/requests?sort=-created_at" class="font-semibold underline">Näytä pyynnöt</RouterLink>
        </p>

        <div v-if="report && report.dry_run" class="mt-4 grid gap-3" aria-live="polite">
          <p>
            Tiedostossa on <strong>{{ report.total }}</strong> {{ report.total === 1 ? 'rivi' : 'riviä' }}:
            <span class="badge status-completed">{{ report.valid }} kunnossa</span>
            <span v-if="report.invalid" class="badge priority-high ml-1">{{ report.invalid }} virheellistä</span>
          </p>

          <div v-if="report.errors.length" class="rounded-xl border border-red-200 p-4 dark:border-red-500/30">
            <p class="font-semibold">Korjaa nämä rivit ja tarkista tiedosto uudelleen:</p>
            <ul class="mt-2 grid gap-2 text-sm">
              <li v-for="rowError in report.errors" :key="rowError.row">
                <strong>Rivi {{ rowError.row }}</strong><template v-if="rowError.title"> ({{ rowError.title }})</template>:
                {{ rowError.messages.join(' ') }}
              </li>
            </ul>
          </div>

          <div v-if="report.preview.length" class="overflow-x-auto">
            <p class="mb-2 text-sm font-semibold text-slate-500 dark:text-slate-400">
              Esikatselu{{ report.valid > report.preview.length ? ` (ensimmäiset ${report.preview.length})` : '' }}
            </p>
            <table class="w-full text-left text-sm">
              <thead class="text-slate-500 dark:text-slate-400">
                <tr><th class="py-1.5 pr-3">Otsikko</th><th class="py-1.5 pr-3">Prioriteetti</th><th class="py-1.5 pr-3">Tila</th><th class="py-1.5 pr-3">Määräpäivä</th><th class="py-1.5">Kaupunki</th></tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in report.preview" :key="index" class="border-t border-slate-200 dark:border-slate-800">
                  <td class="py-1.5 pr-3 font-semibold">{{ row.title }}</td>
                  <td class="py-1.5 pr-3">{{ priorityLabels[row.priority] }}</td>
                  <td class="py-1.5 pr-3">{{ statusLabels[row.status] }}</td>
                  <td class="py-1.5 pr-3 tabular-nums">{{ formatDate(row.due_date) }}</td>
                  <td class="py-1.5">{{ row.city ?? '–' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <ModuleList />
    </div>
  </section>
</template>
