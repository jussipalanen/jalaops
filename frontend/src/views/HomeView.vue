<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiGet } from '@/api/client'
import AiStatusOverview from '@/components/AiStatusOverview.vue'
import DashboardCounts from '@/components/DashboardCounts.vue'
import DashboardLayoutEditor from '@/components/DashboardLayoutEditor.vue'
import ModuleHook from '@/components/ModuleHook.vue'
import { useDashboardWidgets } from '@/composables/useDashboardWidgets'
import { priorityLabels, statusLabels } from '@/labels'

// Module widgets go in the dashboard's hooks (ModuleHook); users can move or
// hide them for themselves with "Muokkaa etusivua".
const { hooks, widgets, customized, load: loadWidgets, inHook, place, reset } = useDashboardWidgets()
const editing = ref(false)

// 'loading' | 'ok' | 'error'
const apiStatus = ref('loading')

const apiStatusClasses = {
  loading: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
  ok: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  error: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
}

onMounted(loadWidgets)

onMounted(async () => {
  try {
    const data = await apiGet('/health')
    apiStatus.value = data.status === 'ok' ? 'ok' : 'error'
  } catch {
    apiStatus.value = 'error'
  }
})
</script>

<template>
  <section class="space-y-6">
    <div class="card relative overflow-hidden">
      <!-- Decorative glow behind the hero text. -->
      <div
        class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-primary-400/20 blur-3xl dark:bg-primary-500/15"
        aria-hidden="true"
      ></div>

      <div class="relative p-6 sm:p-10">
        <p class="mb-2 text-sm font-semibold tracking-wide text-primary-700 uppercase dark:text-primary-400">
          Huoltopyyntöjen hallinta
        </p>
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Tervetuloa JalaOpsiin</h1>
        <p class="mt-3 max-w-xl text-lg text-slate-600 dark:text-slate-300">
          Kirjaa huoltotarpeet, seuraa niiden etenemistä ja pidä määräpäivät hallinnassa yhdessä
          paikassa.
        </p>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
          <RouterLink to="/requests" class="btn btn-primary">Näytä pyynnöt</RouterLink>
          <RouterLink to="/requests/new" class="btn btn-secondary">+ Uusi pyyntö</RouterLink>
        </div>

        <p
          class="mt-6 inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold"
          :class="apiStatusClasses[apiStatus]"
          role="status"
        >
          <span
            class="size-2 rounded-full bg-current"
            :class="{ 'animate-pulse': apiStatus === 'loading' }"
            aria-hidden="true"
          ></span>
          <template v-if="apiStatus === 'loading'">Tarkistetaan API-yhteyttä…</template>
          <template v-else-if="apiStatus === 'ok'">API-yhteys toimii.</template>
          <template v-else>API-yhteys ei toimi.</template>
        </p>
      </div>
    </div>

    <ModuleHook name="dashboard.top" :widgets="inHook('dashboard.top')" />

    <DashboardCounts />

    <ModuleHook name="dashboard.after_counts" :widgets="inHook('dashboard.after_counts')" />

    <AiStatusOverview />

    <ModuleHook name="dashboard.after_overview" :widgets="inHook('dashboard.after_overview')" />

    <div>
      <h2 class="mb-3 text-lg font-semibold">Näin JalaOps toimii</h2>

      <div class="grid gap-4 md:grid-cols-3">
        <article class="card card-body">
          <span
            class="mb-3 grid size-9 place-items-center rounded-lg bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
            aria-hidden="true"
          >
            1
          </span>
          <h3 class="font-semibold">Kirjaa pyyntö</h3>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Lisää otsikko, kuvaus, prioriteetti ja määräpäivä. Pyyntö näkyy heti listalla.
          </p>
        </article>

        <article class="card card-body">
          <span
            class="mb-3 grid size-9 place-items-center rounded-lg bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
            aria-hidden="true"
          >
            2
          </span>
          <h3 class="font-semibold">Priorisoi</h3>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Merkitse kiireelliset työt, jotta ne erottuvat muista.
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <span v-for="(label, value) in priorityLabels" :key="value" class="badge" :class="`priority-${value}`">
              {{ label }}
            </span>
          </div>
        </article>

        <article class="card card-body">
          <span
            class="mb-3 grid size-9 place-items-center rounded-lg bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
            aria-hidden="true"
          >
            3
          </span>
          <h3 class="font-semibold">Seuraa tilaa</h3>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Päivitä tila työn edetessä, kunnes pyyntö on valmis.
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <span v-for="(label, value) in statusLabels" :key="value" class="badge" :class="`status-${value}`">
              {{ label }}
            </span>
          </div>
        </article>
      </div>
    </div>
    <ModuleHook name="dashboard.bottom" :widgets="inHook('dashboard.bottom')" />

    <DashboardLayoutEditor
      v-if="editing"
      :hooks="hooks"
      :widgets="widgets"
      :customized="customized"
      @place="place"
      @reset="reset"
      @close="editing = false"
    />
    <div v-else-if="widgets.length" class="flex justify-end">
      <button type="button" class="btn btn-secondary" @click="editing = true">Muokkaa etusivua</button>
    </div>
  </section>
</template>
