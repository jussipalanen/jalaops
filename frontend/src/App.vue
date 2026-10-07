<script setup>
import { RouterLink, RouterView } from 'vue-router'
import DemoBanner from '@/components/DemoBanner.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
</script>

<template>
  <DemoBanner />

  <header
    class="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/80"
  >
    <div class="mx-auto flex min-h-16 w-full max-w-6xl items-center justify-between gap-2 px-4 sm:px-6">
      <RouterLink to="/" class="inline-flex items-center gap-2.5 text-lg font-bold text-slate-900 dark:text-white">
        <span
          class="grid size-8 place-items-center rounded-lg bg-linear-to-br from-sky-400 to-sky-600 text-base text-white shadow-sm"
          aria-hidden="true"
        >
          J
        </span>
        <!-- Very narrow phones show only the logo, so the navigation fits. -->
        <span class="max-[24rem]:sr-only">JalaOps</span>
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

  <main class="mx-auto w-full max-w-6xl px-4 pt-6 pb-12 sm:px-6 sm:pt-8">
    <RouterView />
  </main>
</template>
