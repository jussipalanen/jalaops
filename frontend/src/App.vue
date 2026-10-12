<script setup>
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import DemoBanner from '@/components/DemoBanner.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { version } from '../package.json'

// The API docs are served by the Laravel backend, not by this app.
const apiDocsUrl = import.meta.env.VITE_API_DOCS_URL || 'http://localhost:8000/docs'
// The backend's home page (API version, database status) is at the root of the same host.
const apiHomeUrl = new URL('/', apiDocsUrl).href
const year = new Date().getFullYear()

// Pages like the map fill the window below the header, without the footer.
const route = useRoute()
const fullScreen = computed(() => Boolean(route.meta.fullScreen))
</script>

<template>
  <DemoBanner />

  <header
    class="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/80"
  >
    <div class="mx-auto flex min-h-16 w-full max-w-6xl items-center justify-between gap-2 px-4 sm:px-6">
      <RouterLink to="/" class="inline-flex items-center gap-2.5 text-lg font-bold text-slate-900 dark:text-white">
        <AppLogo class="size-8 shrink-0 drop-shadow-sm" />
        <!-- Phones show only the logo, so the navigation fits. -->
        <span class="max-[30rem]:sr-only">Jala<span class="text-primary-600 dark:text-primary-400">Ops</span></span>
      </RouterLink>

      <div class="flex min-w-0 items-center gap-0.5 sm:gap-1">
        <nav class="flex gap-0.5 sm:gap-1" aria-label="Päävalikko">
          <RouterLink to="/" class="nav-link" exact-active-class="active">Etusivu</RouterLink>
          <RouterLink to="/requests" class="nav-link" active-class="active">Pyynnöt</RouterLink>
          <RouterLink to="/map" class="nav-link" active-class="active">Kartta</RouterLink>
        </nav>
        <ThemeToggle />
      </div>
    </div>
  </header>

  <main v-if="fullScreen" class="full-screen-page relative min-h-0 flex-1">
    <RouterView />
  </main>

  <main v-else class="mx-auto w-full max-w-6xl flex-1 px-4 pt-6 pb-12 sm:px-6 sm:pt-8">
    <RouterView />
  </main>

  <footer v-if="!fullScreen" class="border-t border-slate-200 dark:border-slate-800">
    <div
      class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:text-slate-400"
    >
      <p>
        <strong class="font-semibold text-slate-700 dark:text-slate-200">JalaOps</strong> on demosovellus
        huoltopyyntöjen hallintaan. Sovelluksen tiedot ovat esimerkkidataa.
      </p>
      <nav class="flex flex-wrap items-center gap-x-4 gap-y-1" aria-label="Alatunniste">
        <RouterLink to="/about" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">
          Tietoja
        </RouterLink>
        <a
          :href="apiDocsUrl"
          class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
          target="_blank"
          rel="noopener"
        >
          API-dokumentaatio
        </a>
        <a
          :href="apiHomeUrl"
          class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
          target="_blank"
          rel="noopener"
        >
          API-etusivu
        </a>
        <span>Versio {{ version }}</span>
        <span>© {{ year }}</span>
      </nav>
    </div>
  </footer>
</template>
