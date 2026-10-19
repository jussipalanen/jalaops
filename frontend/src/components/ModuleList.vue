<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiGet } from '@/api/client'

// The backend's Python modules (backend/modules) and whether each one is on.
// An enabled module has its own page, drawn from its module.json. Modules are
// turned on and off on the server, not here, because the app has no login.

const modules = ref([])
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    modules.value = (await apiGet('/modules')).data ?? []
  } catch {
    error.value = 'Moduulien haku epäonnistui. Yritä hetken kuluttua uudelleen.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="card card-body">
    <h2 class="text-lg font-bold">Moduulit</h2>
    <p class="mt-1 text-slate-500 dark:text-slate-400">
      Palvelimen Python-moduulit. Ne otetaan käyttöön ja poistetaan käytöstä palvelimella.
    </p>

    <p v-if="loading" class="mt-3 text-slate-500 dark:text-slate-400">Haetaan moduuleja…</p>
    <p v-else-if="error" class="alert-error mt-3 mb-0" role="alert">{{ error }}</p>
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
          <span
            class="badge"
            :class="module.enabled ? 'status-completed' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
          >
            {{ module.enabled ? 'Käytössä' : 'Pois käytöstä' }}
          </span>
          <RouterLink v-if="module.enabled" :to="`/tools/modules/${module.id}`" class="btn btn-secondary">Avaa</RouterLink>
        </div>
      </li>
    </ul>
  </div>
</template>
