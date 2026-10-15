<script setup>
import { RouterLink } from 'vue-router'
import { version } from '../../package.json'

// "card" is the About page's section with the iPhone steps; "links" are the
// compact footer links.
defineProps({
  variant: { type: String, default: 'card' },
})

// The Android app (jalaops.apk) is attached to every GitHub release, and this
// address always points to the newest one. Its version follows the web app's.
const apkUrl = 'https://github.com/jussipalanen/jalaops/releases/latest/download/jalaops.apk'

// Inside the installed app (the Android app or an iPhone home screen icon)
// there's nothing to install.
const installed = Boolean(window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone)

// The Android head and the Apple logo, drawn in a 24 × 24 box (the Android
// head's viewBox crops it to the same visual size).
const androidIcon =
  'M5 17a7 7 0 0 1 14 0Z M8.25 14a1 1 0 1 0 2 0a1 1 0 1 0-2 0Z M13.75 14a1 1 0 1 0 2 0a1 1 0 1 0-2 0Z'
const appleIcon =
  'M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701'
</script>

<template>
  <template v-if="!installed && variant === 'links'">
    <a :href="apkUrl" class="inline-flex items-center gap-1.5 font-semibold text-primary-700 hover:underline dark:text-primary-400">
      <svg class="size-4 shrink-0" viewBox="4 4.5 16 16" aria-hidden="true">
        <path :d="androidIcon" fill="currentColor" fill-rule="evenodd" />
        <path d="M8.5 10.9 7 8.3M15.5 10.9 17 8.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
      </svg>
      Android-sovellus
    </a>
    <RouterLink to="/about" class="inline-flex items-center gap-1.5 font-semibold text-primary-700 hover:underline dark:text-primary-400">
      <svg class="size-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true"><path :d="appleIcon" fill="currentColor" /></svg>
      iPhone-sovellus
    </RouterLink>
  </template>

  <div v-else-if="!installed" class="card card-body">
    <h2 class="text-lg font-bold">Mobiilisovellus</h2>
    <p class="mt-1 text-slate-600 dark:text-slate-400">
      Käytä JalaOpsia puhelimessa omana sovelluksenaan, koko näytöllä ilman selaimen osoiteriviä.
    </p>

    <div class="mt-3 grid items-start gap-3 sm:grid-cols-2">
      <div class="rounded-xl border border-slate-200 dark:border-slate-800">
        <a
          :href="apkUrl"
          class="flex min-h-16 items-center gap-3 rounded-xl px-4 py-3 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
        >
          <svg class="size-9 shrink-0 text-[#3ddc84]" viewBox="4 4.5 16 16" aria-hidden="true">
            <path :d="androidIcon" fill="currentColor" fill-rule="evenodd" />
            <path d="M8.5 10.9 7 8.3M15.5 10.9 17 8.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
          </svg>
          <span class="min-w-0 flex-1">
            <span class="block font-semibold text-primary-700 dark:text-primary-400">Lataa Android-sovellus</span>
            <span class="block text-sm text-slate-500 tabular-nums dark:text-slate-400">Versio {{ version }} · APK-tiedosto</span>
          </span>
          <svg class="size-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4v11m-5-5 5 5 5-5M5 20h14" />
          </svg>
        </a>
        <p class="m-0 px-4 pb-3 text-sm text-slate-500 dark:text-slate-400">
          Avaa ladattu tiedosto ja valitse <strong>Asenna</strong>. Ensimmäisellä kerralla puhelin pyytää sallimaan
          sovellusten asentamisen selaimesta.
        </p>
      </div>

      <details class="group rounded-xl border border-slate-200 dark:border-slate-800">
        <summary
          class="flex min-h-16 cursor-pointer list-none items-center gap-3 rounded-xl px-4 py-3 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 [&::-webkit-details-marker]:hidden"
        >
          <svg class="size-9 shrink-0 text-slate-900 dark:text-white" viewBox="0 0 24 24" aria-hidden="true">
            <path :d="appleIcon" fill="currentColor" />
          </svg>
          <span class="min-w-0 flex-1">
            <span class="block font-semibold text-primary-700 dark:text-primary-400">Asenna iPhoneen tai iPadiin</span>
            <span class="block text-sm text-slate-500 tabular-nums dark:text-slate-400">Versio {{ version }} · verkkosovellus</span>
          </span>
          <svg class="size-5 shrink-0 text-slate-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6" />
          </svg>
        </summary>
        <ol class="m-0 grid list-decimal gap-1 pr-4 pb-3 pl-9 text-sm text-slate-600 dark:text-slate-300">
          <li>Avaa tämä sivu Safarissa.</li>
          <li>Napauta <strong>Jaa</strong>-painiketta (tarvittaessa ensin <strong>•••</strong>-valikosta).</li>
          <li>Valitse <strong>Lisää Koti-valikkoon</strong> ja sitten <strong>Lisää</strong>.</li>
          <li>Avaa JalaOps Koti-valikon kuvakkeesta.</li>
        </ol>
      </details>
    </div>
  </div>
</template>
