<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiGet, apiRequestMethod } from '@/api/client'

// The backend's Python modules and whether each one is on. Modules are
// optional: users turn them on and off here, and the setting is the same for
// everyone. An enabled module has its own page, and its widgets show on the
// dashboard.

const modules = ref([])
const loading = ref(true)
const error = ref('')
// The id of the module being switched.
const switching = ref('')

onMounted(async () => {
  try {
    modules.value = (await apiGet('/modules')).data ?? []
  } catch {
    error.value = 'Moduulien haku epäonnistui. Yritä hetken kuluttua uudelleen.'
  } finally {
    loading.value = false
  }
})

async function toggle(module) {
  switching.value = module.id
  error.value = ''

  try {
    const updated = (await apiRequestMethod('PATCH', `/modules/${encodeURIComponent(module.id)}`, { enabled: !module.enabled })).data
    module.enabled = updated.enabled
  } catch {
    error.value = `Moduulin ${module.name} ${module.enabled ? 'poistaminen käytöstä' : 'käyttöönotto'} epäonnistui. Yritä hetken kuluttua uudelleen.`
  } finally {
    switching.value = ''
  }
}
</script>

<template>
  <div class="card card-body">
    <h2 class="text-lg font-bold">Moduulit</h2>
    <p class="mt-1 text-slate-500 dark:text-slate-400">
      Lisäosat, jotka voit ottaa käyttöön tai poistaa käytöstä. Valinta koskee kaikkia käyttäjiä.
    </p>

    <p v-if="loading" class="mt-3 text-slate-500 dark:text-slate-400">Haetaan moduuleja…</p>
    <p v-else-if="error && !modules.length" class="alert-error mt-3 mb-0" role="alert">{{ error }}</p>
    <p v-else-if="!modules.length" class="mt-3 text-slate-500 dark:text-slate-400">Moduuleja ei ole.</p>
    <ul v-else class="mt-3 grid gap-2">
      <li
        v-for="module in modules"
        :key="module.id"
        class="module-row flex flex-wrap items-center justify-between gap-x-3 gap-y-2 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-800"
      >
        <div class="min-w-0">
          <p class="font-semibold">
            {{ module.name }}
            <span v-if="module.version" class="text-sm font-normal text-slate-500 dark:text-slate-400">{{ module.version }}</span>
          </p>
          <p v-if="module.description" class="text-sm text-slate-500 dark:text-slate-400">{{ module.description }}</p>
        </div>
        <div class="flex items-center gap-3">
          <button
            type="button"
            role="switch"
            class="inline-flex min-h-11 items-center gap-2 rounded-lg px-1 text-sm font-semibold disabled:opacity-60 lg:min-h-8"
            :aria-checked="module.enabled"
            :aria-label="`${module.name}: ${module.enabled ? 'käytössä' : 'pois käytöstä'}`"
            :disabled="switching === module.id"
            @click="toggle(module)"
          >
            <span
              class="relative inline-block h-6 w-11 rounded-full transition-colors"
              :class="module.enabled ? 'bg-primary-600 dark:bg-primary-500' : 'bg-slate-300 dark:bg-slate-700'"
              aria-hidden="true"
            >
              <span
                class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform"
                :class="module.enabled ? 'translate-x-5' : ''"
              ></span>
            </span>
            {{ module.enabled ? 'Käytössä' : 'Pois käytöstä' }}
          </button>
          <RouterLink v-if="module.enabled" :to="`/tools/modules/${module.id}`" class="btn btn-secondary">Avaa</RouterLink>
        </div>
      </li>
    </ul>
    <p v-if="error && modules.length" class="alert-error mt-3 mb-0" role="alert">{{ error }}</p>
  </div>
</template>
