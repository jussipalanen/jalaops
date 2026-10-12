<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiGet } from '@/api/client'

// The dashboard's request counts. Each card opens the request list filtered to
// the same requests.
const counts = ref(null)
const failed = ref(false)

const cards = computed(() => [
  { key: 'open', label: 'Avoimet', to: '/requests?status=open' },
  { key: 'in_progress', label: 'Työn alla', to: '/requests?status=in_progress' },
  { key: 'completed', label: 'Valmiit', to: '/requests?status=completed' },
  { key: 'overdue', label: 'Myöhässä', to: '/requests?due=overdue', alert: (counts.value?.overdue ?? 0) > 0 },
])

onMounted(async () => {
  try {
    counts.value = await apiGet('/dashboard')
  } catch {
    failed.value = true
  }
})
</script>

<template>
  <section aria-labelledby="dashboard-counts-title">
    <h2 id="dashboard-counts-title" class="mb-3 text-lg font-semibold">Tilanne</h2>

    <p v-if="failed" class="alert-error">Lukumäärien lataaminen epäonnistui.</p>

    <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
      <li v-for="card in cards" :key="card.key">
        <RouterLink
          :to="card.to"
          :aria-label="`${card.label}: ${counts ? counts[card.key] : 'ladataan'}`"
          class="card block h-full p-4 transition-colors hover:border-primary-300 sm:p-5 dark:hover:border-primary-500/50"
          :class="card.alert ? 'border-red-200 bg-red-50 hover:border-red-300 dark:border-red-500/30 dark:bg-red-500/10' : ''"
        >
          <span
            class="block text-3xl font-bold tabular-nums sm:text-4xl"
            :class="card.alert ? 'text-red-700 dark:text-red-300' : ''"
            aria-live="polite"
          >
            {{ counts ? counts[card.key] : '–' }}
          </span>
          <span class="mt-1 block text-sm font-semibold text-slate-600 dark:text-slate-300">{{ card.label }}</span>
        </RouterLink>
      </li>
    </ul>
  </section>
</template>
