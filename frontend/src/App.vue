<script setup>
import { RouterLink, RouterView } from 'vue-router'
import AppLogo from '@/components/AppLogo.vue'
import DemoBanner from '@/components/DemoBanner.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { version } from '../package.json'

// The API docs are served by the Laravel backend, not by this app.
const apiDocsUrl = import.meta.env.VITE_API_DOCS_URL || 'http://localhost:8000/docs'
const year = new Date().getFullYear()
</script>

<template>
  <DemoBanner />

  <header
    class="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/80"
  >
    <div class="mx-auto flex min-h-16 w-full max-w-6xl items-center justify-between gap-2 px-4 sm:px-6">
      <RouterLink to="/" class="inline-flex items-center gap-2.5 text-lg font-bold text-slate-900 dark:text-white">
        <AppLogo class="size-8 shrink-0 drop-shadow-sm" />
        <!-- Very narrow phones show only the logo, so the navigation fits. -->
        <span class="max-[24rem]:sr-only">Jala<span class="text-primary-600 dark:text-primary-400">Ops</span></span>
      </RouterLink>

      <div class="flex items-center gap-1">
        <nav class="flex gap-1" aria-label="Päävalikko">
          <RouterLink to="/" class="nav-link" exact-active-class="active">Etusivu</RouterLink>
          <RouterLink to="/requests" class="nav-link" active-class="active">Pyynnöt</RouterLink>
        </nav>
        <ThemeToggle />
      </div>
    </div>
  </header>

  <main class="mx-auto w-full max-w-6xl flex-1 px-4 pt-6 pb-12 sm:px-6 sm:pt-8">
    <RouterView />
  </main>

  <footer class="border-t border-slate-200 dark:border-slate-800">
    <div
      class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:text-slate-400"
    >
      <p>
        <strong class="font-semibold text-slate-700 dark:text-slate-200">JalaOps</strong> on demosovellus
        huoltopyyntöjen hallintaan. Sovelluksen tiedot ovat esimerkkidataa.
      </p>
      <nav class="flex flex-wrap items-center gap-x-4 gap-y-1" aria-label="Alatunniste">
        <a
          :href="apiDocsUrl"
          class="font-semibold text-primary-700 hover:underline dark:text-primary-400"
          target="_blank"
          rel="noopener"
        >
          API-dokumentaatio
        </a>
        <span>Versio {{ version }}</span>
        <span>© {{ year }}</span>
      </nav>
    </div>
  </footer>
</template>
