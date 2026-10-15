<script setup>
import { computed, onMounted, ref } from 'vue'
import { version as frontendVersion } from '../../package.json'
import assetLinks from '../../public/.well-known/assetlinks.json'

const repoUrl = 'https://github.com/jussipalanen/jalaops'
// The Android app (jalaops.apk) is attached to every GitHub release, and this
// address always points to the newest one.
const apkUrl = `${repoUrl}/releases/latest/download/jalaops.apk`
const releasesUrl = `${repoUrl}/releases/latest`
const workflowUrl = `${repoUrl}/actions/workflows/android.yml`

// The key that signs the app. Android trusts the app to open the site without
// an address bar only when the site lists this same fingerprint.
const fingerprint = assetLinks[0].target.sha256_cert_fingerprints[0]

// Inside the installed app (the Android app or an iPhone home screen icon)
// there's nothing to install.
const installed = Boolean(window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone)

// The newest release's version, date, size and SHA-256 checksum from GitHub.
// Without them (offline, rate limit) the page shows the web app's version,
// which the Android app's version follows.
const release = ref(null)

async function loadRelease() {
  try {
    const response = await fetch('https://api.github.com/repos/jussipalanen/jalaops/releases/latest', {
      headers: { Accept: 'application/vnd.github+json' },
    })
    if (!response.ok) return
    const data = await response.json()
    const apk = data.assets?.find((asset) => asset.name === 'jalaops.apk')
    if (!apk) return
    release.value = {
      version: String(data.tag_name).replace(/^v/, ''),
      date: new Date(data.published_at).toLocaleDateString('fi-FI'),
      size: `${(apk.size / 1e6).toLocaleString('fi-FI', { maximumFractionDigits: 1 })} Mt`,
      sha256: /^sha256:[0-9a-f]{64}$/.test(apk.digest ?? '') ? apk.digest.slice(7) : null,
    }
  } catch {
    // The page works without the release details.
  }
}

const version = computed(() => release.value?.version ?? frontendVersion)
const androidDetails = computed(() =>
  [`Versio ${version.value}`, release.value?.size, release.value && `julkaistu ${release.value.date}`].filter(Boolean).join(' · '),
)

onMounted(loadRelease)

// The Android head and the Apple logo, drawn in a 24 × 24 box (the Android
// head's viewBox crops it to the same visual size).
const androidIcon =
  'M5 17a7 7 0 0 1 14 0Z M8.25 14a1 1 0 1 0 2 0a1 1 0 1 0-2 0Z M13.75 14a1 1 0 1 0 2 0a1 1 0 1 0-2 0Z'
const appleIcon =
  'M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701'

const linkClass = 'font-semibold text-primary-700 hover:underline dark:text-primary-400'
</script>

<template>
  <section class="max-w-4xl">
    <header class="page-header">
      <div>
        <h1 class="page-title">Mobiilisovellukset</h1>
        <p class="page-subtitle mt-1">
          Käytä JalaOpsia puhelimessa omana sovelluksenaan, koko näytöllä ilman selaimen osoiteriviä.
        </p>
      </div>
    </header>

    <p
      v-if="installed"
      class="mb-4 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-primary-900 dark:border-primary-900 dark:bg-primary-950/40 dark:text-primary-100"
    >
      Käytät jo JalaOpsia asennettuna sovelluksena.
    </p>

    <div class="grid gap-4">
      <div class="grid items-start gap-4 md:grid-cols-2">
        <div class="card card-body" data-test="android">
          <div class="flex items-center gap-3">
            <svg class="size-10 shrink-0 text-[#3ddc84]" viewBox="4 4.5 16 16" aria-hidden="true">
              <path :d="androidIcon" fill="currentColor" fill-rule="evenodd" />
              <path d="M8.5 10.9 7 8.3M15.5 10.9 17 8.3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
            <div class="min-w-0">
              <h2 class="text-lg font-bold">Android</h2>
              <p class="m-0 text-sm text-slate-500 tabular-nums dark:text-slate-400">{{ androidDetails }}</p>
            </div>
          </div>

          <a v-if="!installed" :href="apkUrl" class="btn btn-primary mt-4 w-full">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 4v11m-5-5 5 5 5-5M5 20h14" />
            </svg>
            Lataa Android-sovellus (APK)
          </a>

          <ol class="mt-4 mb-0 grid list-decimal gap-1 pl-5 text-sm text-slate-600 dark:text-slate-300">
            <li>Lataa tiedosto puhelimella.</li>
            <li>
              Avaa ladattu tiedosto ja valitse <strong>Asenna</strong>. Ensimmäisellä kerralla puhelin pyytää sallimaan
              sovellusten asentamisen selaimesta.
            </li>
            <li>Avaa JalaOps aloitusnäytöltä.</li>
          </ol>
          <p class="mt-3 mb-0 text-sm text-slate-500 dark:text-slate-400">
            Tarvitaan Chrome-selain. Koska sovellus ei tule Google Playsta, Android voi varoittaa tuntemattomasta
            sovelluksesta; se on tavallista suoraan ladatuille sovelluksille. Päivitä asentamalla uudempi tiedosto
            vanhan päälle.
          </p>
        </div>

        <div class="card card-body" data-test="ios">
          <div class="flex items-center gap-3">
            <svg class="size-10 shrink-0 text-slate-900 dark:text-white" viewBox="0 0 24 24" aria-hidden="true">
              <path :d="appleIcon" fill="currentColor" />
            </svg>
            <div class="min-w-0">
              <h2 class="text-lg font-bold">iPhone ja iPad</h2>
              <p class="m-0 text-sm text-slate-500 tabular-nums dark:text-slate-400">Versio {{ version }} · verkkosovellus</p>
            </div>
          </div>

          <ol class="mt-4 mb-0 grid list-decimal gap-1 pl-5 text-sm text-slate-600 dark:text-slate-300">
            <li>Avaa tämä sivu Safarissa.</li>
            <li>Napauta <strong>Jaa</strong>-painiketta (tarvittaessa ensin <strong>•••</strong>-valikosta).</li>
            <li>Valitse <strong>Lisää Koti-valikkoon</strong> ja sitten <strong>Lisää</strong>.</li>
            <li>Avaa JalaOps Koti-valikon kuvakkeesta.</li>
          </ol>
          <p class="mt-3 mb-0 text-sm text-slate-500 dark:text-slate-400">
            Mitään ei ladata eikä App Storea tarvita: Safari avaa saman verkkosovelluksen omassa ikkunassaan, ja se
            päivittyy itsestään.
          </p>
        </div>
      </div>

      <div class="card card-body" data-test="security">
        <h2 class="text-lg font-bold">Turvallisuus</h2>
        <ul class="mt-3 mb-0 grid gap-3">
          <li class="flex gap-3">
            <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
            <div class="min-w-0">
              <strong>Virallinen lähde.</strong> Tiedosto ladataan suojatulla HTTPS-yhteydellä JalaOpsin
              <a :href="releasesUrl" :class="linkClass" target="_blank" rel="noopener">GitHub-julkaisusta</a>.
            </div>
          </li>
          <li class="flex gap-3">
            <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
            <div class="min-w-0">
              <strong>Avoin lähdekoodi, automaattinen käännös.</strong> Sovellus käännetään julkisesta lähdekoodista
              <a :href="workflowUrl" :class="linkClass" target="_blank" rel="noopener">GitHub Actionsissa</a>, ja
              GitHub todistaa tiedoston alkuperän (build provenance).
            </div>
          </li>
          <li class="flex gap-3">
            <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
            <div class="min-w-0">
              <strong>Allekirjoitettu.</strong> Android hyväksyy päivitykset vain samalla avaimella allekirjoitettuina.
              Avaimen SHA-256-sormenjälki:
              <code class="mt-1 block rounded-lg bg-slate-100 px-2 py-1 text-xs break-all dark:bg-slate-800" data-test="fingerprint">{{ fingerprint }}</code>
            </div>
          </li>
          <li class="flex gap-3">
            <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
            <div class="min-w-0">
              <strong>Ei käyttöoikeuksia.</strong> Sovellus ei pyydä pääsyä sijaintiin, kameraan, yhteystietoihin,
              tiedostoihin eikä ilmoituksiin. Se avaa verkkosovelluksen Chromessa, joten tiedot ja suojaus ovat samat
              kuin selaimessa.
            </div>
          </li>
          <li class="flex gap-3">
            <svg class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10" /></svg>
            <div class="min-w-0">
              <strong>Tarkistussumma.</strong>
              <template v-if="release?.sha256">
                Version {{ release.version }} tiedoston SHA-256:
                <code class="mt-1 block rounded-lg bg-slate-100 px-2 py-1 text-xs break-all dark:bg-slate-800" data-test="checksum">{{ release.sha256 }}</code>
              </template>
              <template v-else>
                Tiedoston SHA-256-tarkistussumma näkyy
                <a :href="releasesUrl" :class="linkClass" target="_blank" rel="noopener">julkaisusivulla</a>.
              </template>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
