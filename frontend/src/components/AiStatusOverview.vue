<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ApiError, apiGet, apiPost } from '@/api/client'

// AI-tilannekatsaus: a summary of the requests written on the backend by the
// configured AI provider (Google Gemini or Puter AI). Hidden entirely when the
// feature is off (AI_INSIGHTS_ENABLED).
//
// The backend returns its cached overview right away. When the requests have
// changed since it was written (`outdated`), it stays on screen while a new one
// is generated in the background, because the AI can take several seconds.

const enabled = ref(false)
const loading = ref(true)
// The skeleton appears only if loading takes a while, so it doesn't flash
// when the feature is off (that answer comes back right away).
const showSkeleton = ref(false)
let skeletonTimer = null
const refreshing = ref(false)
const error = ref('')
const overview = ref(null)

const generatedAt = computed(() => {
  if (!overview.value?.generated_at) {
    return ''
  }

  return new Intl.DateTimeFormat('fi-FI', { dateStyle: 'short', timeStyle: 'short' }).format(
    new Date(overview.value.generated_at),
  )
})

function errorMessage(exception) {
  if (exception instanceof ApiError && exception.status === 429) {
    return 'Katsauksia on päivitetty liian usein. Yritä hetken kuluttua uudelleen.'
  }

  return 'AI-tilannekatsauksen luominen epäonnistui. Yritä hetken kuluttua uudelleen.'
}

async function load(refresh = false, { background = false } = {}) {
  error.value = ''

  try {
    const data = refresh
      ? await apiPost('/dashboard/ai-overview/refresh')
      : await apiGet('/dashboard/ai-overview')

    enabled.value = data.enabled === true
    overview.value = enabled.value ? data : null
  } catch (exception) {
    // A background update that fails keeps the earlier overview without an
    // error; the "outdated" note stays visible instead.
    if (background && overview.value) {
      return
    }

    // An error response means the feature is on but the overview failed.
    enabled.value = true
    error.value = errorMessage(exception)
  }
}

async function refresh({ background = false } = {}) {
  refreshing.value = true
  await load(true, { background })
  refreshing.value = false
}

onMounted(async () => {
  skeletonTimer = setTimeout(() => (showSkeleton.value = true), 400)
  await load()
  clearTimeout(skeletonTimer)
  loading.value = false

  if (overview.value?.outdated) {
    refresh({ background: true })
  }
})

onBeforeUnmount(() => clearTimeout(skeletonTimer))
</script>

<template>
  <section
    v-if="loading ? showSkeleton : enabled"
    class="card ai-overview relative overflow-hidden"
    aria-labelledby="ai-overview-title"
    :aria-busy="loading || refreshing"
  >
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:px-6 dark:border-slate-800">
      <h2 id="ai-overview-title" class="flex items-center gap-2 text-lg font-semibold">
        <svg
          class="size-5 text-primary-600 dark:text-primary-400"
          viewBox="0 0 24 24"
          fill="currentColor"
          aria-hidden="true"
        >
          <path
            d="M10 2.5l1.6 4.9 4.9 1.6-4.9 1.6L10 15.5l-1.6-4.9L3.5 9l4.9-1.6L10 2.5zm8 10l.9 2.6 2.6.9-2.6.9-.9 2.6-.9-2.6-2.6-.9 2.6-.9.9-2.6z"
          />
        </svg>
        AI-tilannekatsaus
      </h2>
      <button
        v-if="!loading && enabled"
        type="button"
        class="btn btn-secondary w-full sm:w-auto"
        :disabled="refreshing"
        @click="refresh()"
      >
        {{ refreshing ? 'Päivitetään…' : 'Päivitä' }}
      </button>
    </div>

    <div class="p-5 sm:p-6">
      <div v-if="loading" class="space-y-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Tekoäly kirjoittaa tilannekatsausta…</p>
        <div class="space-y-3" aria-hidden="true">
          <div class="h-4 w-full animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
          <div class="h-4 w-11/12 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
          <div class="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
        </div>
      </div>

      <p v-if="!loading && error" class="alert-error" :class="{ 'mb-0': !overview }" role="alert">
        {{ error }}
      </p>

      <template v-if="!loading && overview">
        <p class="text-[1.0625rem] leading-relaxed">{{ overview.summary }}</p>

        <div v-if="overview.actions.length || overview.risks.length" class="mt-5 grid gap-5 md:grid-cols-2">
          <div v-if="overview.actions.length">
            <h3 class="mb-2 text-sm font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
              Suositellut toimet
            </h3>
            <ol class="space-y-2">
              <li v-for="(action, index) in overview.actions" :key="index" class="flex gap-3">
                <span
                  class="grid size-6 shrink-0 place-items-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300"
                  aria-hidden="true"
                >
                  {{ index + 1 }}
                </span>
                <span>{{ action }}</span>
              </li>
            </ol>
          </div>

          <div v-if="overview.risks.length">
            <h3 class="mb-2 text-sm font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
              Huomioitavaa
            </h3>
            <ul class="space-y-2">
              <li v-for="(risk, index) in overview.risks" :key="index" class="flex gap-3">
                <span class="mt-2 size-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                <span>{{ risk }}</span>
              </li>
            </ul>
          </div>
        </div>

        <p
          v-if="overview.outdated"
          class="mt-5 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
          role="status"
        >
          <span
            v-if="refreshing"
            class="size-2 animate-pulse rounded-full bg-primary-500"
            aria-hidden="true"
          ></span>
          {{
            refreshing
              ? 'Pyyntöjä on muutettu katsauksen jälkeen. Päivitetään…'
              : 'Pyyntöjä on muutettu tämän katsauksen jälkeen.'
          }}
        </p>

        <p v-if="overview.stale" class="mt-5 text-sm text-amber-700 dark:text-amber-300">
          Näytetään aiempi katsaus, koska uusien katsausten tuntiraja on täynnä.
        </p>

        <p class="mt-5 text-xs text-slate-500 dark:text-slate-400">
          Luotu {{ generatedAt }} · {{ overview.provider }} ({{ overview.model }}) · Tekoälyn tuottama yhteenveto voi
          sisältää virheitä.
        </p>
      </template>
    </div>
  </section>
</template>
