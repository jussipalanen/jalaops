<script setup>
import { computed, useTemplateRef, watch } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useDropdown } from '@/composables/useDropdown'
import AppLogo from '@/components/AppLogo.vue'
import DemoBanner from '@/components/DemoBanner.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'
import { mobileAppsPage } from '@/features'
import { version } from '../package.json'

// The API docs are served by the Laravel backend, not by this app.
const apiDocsUrl = import.meta.env.VITE_API_DOCS_URL || 'http://localhost:8000/docs'
// The backend's home page (API version, database status) is at the root of the same host.
const apiHomeUrl = new URL('/', apiDocsUrl).href
const year = new Date().getFullYear()

// Pages like the map fill the window below the header, without the footer.
const route = useRoute()
const fullScreen = computed(() => Boolean(route.meta.fullScreen))

const navLinks = [
  { to: '/', label: 'Etusivu', exact: true },
  { to: '/requests', label: 'Pyynnöt' },
  { to: '/map', label: 'Kartta' },
  { to: '/customers', label: 'Asiakkaat' },
  { to: '/invoices', label: 'Laskut' },
  { to: '/tools', label: 'Työkalut' },
]

// On phones and tablets (below 1024 px) the links are behind a menu button;
// it closes when a page opens, and when its link to the current page is
// tapped (the address doesn't change then).
const mobileMenu = useTemplateRef('mobileMenu')
const { open: menuOpen, toggle: toggleMenu, close: closeMenu } = useDropdown(mobileMenu)
watch(() => route.fullPath, closeMenu)
</script>

<template>
  <DemoBanner />

  <header
    class="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/80"
  >
    <div class="mx-auto flex min-h-16 w-full max-w-6xl items-center justify-between gap-2 px-4 sm:px-6">
      <RouterLink to="/" class="inline-flex items-center gap-2.5 text-lg font-bold text-slate-900 dark:text-white">
        <AppLogo class="size-8 shrink-0 drop-shadow-sm" />
        <span>Jala<span class="text-primary-700 dark:text-primary-400">Ops</span></span>
      </RouterLink>

      <div class="flex min-w-0 items-center gap-1">
        <nav class="hidden gap-1 lg:flex" aria-label="Päävalikko">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            class="nav-link"
            v-bind="link.exact ? { 'exact-active-class': 'active' } : { 'active-class': 'active' }"
          >
            {{ link.label }}
          </RouterLink>
        </nav>
        <ThemeToggle />

        <div ref="mobileMenu" class="lg:hidden">
          <button
            type="button"
            class="grid size-10 cursor-pointer place-items-center rounded-full text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
            :aria-expanded="menuOpen"
            aria-controls="mobile-nav"
            aria-label="Valikko"
            @click="toggleMenu"
          >
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <path v-if="menuOpen" d="M6 6l12 12M18 6L6 18" />
              <path v-else d="M4 7h16M4 12h16M4 17h16" />
            </svg>
          </button>
          <!-- A full-width panel under the header, with large tap targets. -->
          <nav
            v-show="menuOpen"
            id="mobile-nav"
            aria-label="Päävalikko"
            class="absolute inset-x-0 top-full z-30 border-b border-slate-200 bg-white shadow-lg dark:border-slate-800 dark:bg-slate-900"
          >
            <div class="mx-auto grid w-full max-w-6xl gap-1 px-4 py-3 sm:grid-cols-2 sm:px-6">
              <RouterLink
                v-for="link in navLinks"
                :key="link.to"
                :to="link.to"
                class="nav-link min-h-12 w-full rounded-xl px-4 text-base"
                v-bind="link.exact ? { 'exact-active-class': 'active' } : { 'active-class': 'active' }"
                @click="closeMenu"
              >
                {{ link.label }}
              </RouterLink>
            </div>
          </nav>
        </div>
      </div>
    </div>
  </header>

  <!-- isolate: the map page's panels (z-index 1000) stay below the header menu. -->
  <main v-if="fullScreen" class="full-screen-page relative isolate min-h-0 flex-1">
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
        <RouterLink to="/privacy" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">
          Tietosuoja
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
        <RouterLink v-if="mobileAppsPage" to="/mobile-apps" class="font-semibold text-primary-700 hover:underline dark:text-primary-400">
          Mobiilisovellukset
        </RouterLink>
        <span>Versio {{ version }}</span>
        <span>© {{ year }}</span>
      </nav>
    </div>
  </footer>
</template>
