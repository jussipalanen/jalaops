<script setup>
// The privacy policy, for the web app and the Android app. Google Play links
// to this page from the store listing, so keep it accurate when the app starts
// sending data somewhere new.
const updated = '20.10.2026'

const services = [
  {
    name: 'Karttapohja',
    provider: 'OpenFreeMap',
    url: 'https://openfreemap.org',
    what: 'Selain lataa karttakuvat suoraan palvelusta, joten palvelu näkee IP-osoitteesi ja katsomasi kartan alueen.',
  },
  {
    name: 'Osoitehaku',
    provider: 'OpenStreetMap Nominatim ja Photon',
    url: 'https://nominatim.openstreetmap.org',
    what: 'Palvelin lähettää kirjoittamasi osoitteen hakuun. IP-osoitettasi ei välitetä.',
  },
  {
    name: 'AI-tilannekatsaus',
    provider: 'Google Gemini tai Puter',
    url: 'https://ai.google.dev',
    what: 'Palvelin lähettää keskeneräisten pyyntöjen otsikot, prioriteetit, tilat ja eräpäivät sekä lukumäärät. Asiakkaita, osoitteita, kuvauksia tai allekirjoituksia ei lähetetä.',
  },
]
</script>

<template>
  <section class="max-w-3xl">
    <header class="page-header">
      <div>
        <h1 class="page-title">Tietosuoja</h1>
        <p class="page-subtitle mt-1">Päivitetty {{ updated }}. Koskee verkkosovellusta ja Android-sovellusta.</p>
      </div>
    </header>

    <div class="grid gap-4">
      <div class="card card-body">
        <h2 class="text-lg font-bold">Demosovellus</h2>
        <p class="mt-2">
          JalaOps on demosovellus huoltopyyntöjen hallintaan. Siinä ei ole käyttäjätilejä eikä kirjautumista, ja kaikki
          sovelluksen tiedot näkyvät kaikille käyttäjille. Tiedot palautuvat esimerkkidataksi, kun palvelin käynnistyy
          uudelleen. <strong>Älä tallenna sovellukseen oikeita henkilötietoja.</strong>
        </p>
      </div>

      <div class="card card-body">
        <h2 class="text-lg font-bold">Mitä tietoja käsitellään</h2>
        <ul class="mt-2 grid list-disc gap-1 pl-5">
          <li>
            Sovellukseen kirjoittamasi tiedot: pyynnöt, asiakkaat, laskut, osoitteet, sijainnit ja allekirjoitukset.
            Ne tallentuvat sovelluksen palvelimelle (Google Cloud, Suomi).
          </li>
          <li>
            Palvelimien tavalliset lokit, kuten IP-osoite, selaimen tiedot ja pyydetty osoite. Niitä käytetään vain
            vikojen selvittämiseen ja väärinkäytön estämiseen.
          </li>
          <li>
            Selaimeen tallennetaan teeman valinta ja etusivun asettelu. Ne pysyvät laitteellasi, eikä niitä lähetetä
            palvelimelle.
          </li>
        </ul>
        <p class="mt-3">
          Sovellus ei käytä evästeitä seurantaan, analytiikkaa eikä mainoksia, eikä tietoja myydä tai luovuteta
          markkinointiin.
        </p>
      </div>

      <div class="card card-body">
        <h2 class="text-lg font-bold">Ulkopuoliset palvelut</h2>
        <dl class="mt-3 grid gap-3">
          <div v-for="service in services" :key="service.name">
            <dt class="font-semibold">
              {{ service.name }}:
              <a :href="service.url" class="text-primary-700 hover:underline dark:text-primary-400" target="_blank" rel="noopener">{{ service.provider }} ↗</a>
            </dt>
            <dd class="mt-0.5">{{ service.what }}</dd>
          </div>
        </dl>
        <p class="mt-3">
          Verkkosovellus toimii Vercelissä ja palvelin Google Cloud Runissa. Katunäkymälinkki avaa Google Mapsin vain,
          jos painat sitä.
        </p>
      </div>

      <div class="card card-body">
        <h2 class="text-lg font-bold">Android-sovellus</h2>
        <p class="mt-2">
          Android-sovellus näyttää saman verkkosovelluksen Chromen kautta. Se ei pyydä lupia sijaintiin, kameraan tai
          yhteystietoihin eikä kerää muita tietoja kuin verkkosovellus.
        </p>
      </div>

      <div class="card card-body">
        <h2 class="text-lg font-bold">Yhteydenotot</h2>
        <p class="mt-2">
          Kysymykset ja pyynnöt tietojen poistamisesta voi lähettää
          <a href="https://github.com/jussipalanen/jalaops/issues" class="font-semibold text-primary-700 hover:underline dark:text-primary-400" target="_blank" rel="noopener">GitHubissa ↗</a>
          tai Google Playn kehittäjän yhteystietoihin.
        </p>
      </div>
    </div>
  </section>
</template>
