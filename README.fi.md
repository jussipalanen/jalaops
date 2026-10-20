# JalaOps

<img src="docs/logo.svg" alt="JalaOps logo" height="56">

[![CI](https://github.com/jussipalanen/jalaops/actions/workflows/ci.yml/badge.svg)](https://github.com/jussipalanen/jalaops/actions/workflows/ci.yml)
[![Security](https://github.com/jussipalanen/jalaops/actions/workflows/security.yml/badge.svg)](https://github.com/jussipalanen/jalaops/actions/workflows/security.yml)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?style=flat-square&logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=flat-square&logo=vite&logoColor=white)](https://vite.dev)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.8-003545?style=flat-square&logo=mariadb&logoColor=white)](https://mariadb.org)

[English](README.md) | **Suomi**

📋 [Yleiskuvaus](#yleiskuvaus) · 🧱 [Teknologiat](#teknologiat) · 🚀 [Käyttöönotto](#käyttöönotto) · 🏛️ [Arkkitehtuuri](#arkkitehtuuri) · 🔌 [API](#api) · 🗺️ [Kartta](#kartta-ja-sijainnit) · 🔳 [QR-koodit](#qr-koodit) · 📱 [Android](#android-sovellus) · ☁️ [Julkaisu](#julkaisu) · 🛣️ [Tiekartta](#tiekartta)

> **Tila:** kehitys on alkuvaiheessa. Katso [tiekartta](ROADMAP.md) ja [issuet](https://github.com/jussipalanen/jalaops/issues).

---

## Yleiskuvaus

JalaOps on pieni toiminnanohjauksen demosovellus huoltopyyntöjen hallintaan.

Käyttäjä voi:

- tarkastella, luoda, muokata ja poistaa pyyntöjä
- vaihtaa pyynnön tilaa ja kuitata valmiin pyynnön nimellä ja piirretyllä allekirjoituksella
- esikatsella pyyntöä PDF- tai HTML-tiedostona selaimen välilehdessä tai ladata sen
- viedä pyyntöjä CSV- (Excel) tai JSON-tiedostoon ja tuoda niitä takaisin Työkalut-sivulla
- suodattaa pyyntöjä tilan, prioriteetin ja määräpäivän mukaan ja hakea niitä otsikolla tai osoitteella
- antaa pyynnölle osoitteen ja nähdä pyynnöt koko näytön kartalla
- tarkastella koontinäkymää, jonka lukumäärät vievät vastaavaan listaan
- pitää asiakasrekisteriä (Asiakkaat) ja valita jokaiselle pyynnölle asiakas
- kirjata pyynnön työt ja tarvikkeet arvonlisäveroineen ja summineen (laskutuksen perusta)
- laskuttaa valmiit pyynnöt: laskunumerot, viitenumerot, eräpäivät, tilat, lasku PDF- ja HTML-muodossa JalaOps-logolla ja sivunumeroilla sekä vienti kirjanpitoon
- avata pyynnön puhelimella sen QR-koodista ja kopioida laskun maksutiedot pankkisovellukseen
- lukea koontinäkymästä valinnaisen tekoälyn kirjoittaman tilannekatsauksen (AI-tilannekatsaus)

Sovelluksen käyttöliittymä on suomeksi.

## Teknologiat

| Kerros       | Teknologia                     |
|--------------|--------------------------------|
| Backend      | PHP, Laravel, REST API         |
| Frontend     | Vue 3, Vite, Vue Router, Tailwind CSS |
| Tietokanta   | MariaDB                        |
| Kehitysympäristö | Docker, Docker Compose     |
| CI           | GitHub Actions                 |

## Käyttöönotto

Vaatimukset: Docker ja Docker Compose.

```bash
git clone git@github.com:jussipalanen/jalaops.git
cd jalaops
./dev up        # tai: docker compose up -d
```

Ensimmäinen käynnistys asentaa riippuvuudet ja ajaa tietokantamigraatiot, joten se kestää hetken. Avaa sen jälkeen:

| Palvelu  | Osoite                           |
|----------|----------------------------------|
| Frontend | http://localhost:5173            |
| API      | http://localhost:8000/api/health |
| MariaDB  | `localhost:3306` (käyttäjä `jalaops`, salasana `secret`, tietokanta `jalaops`) |

Jos portti 3306 on jo käytössä, käynnistä komennolla `DB_HOST_PORT=3307 ./dev up`.

<details>
<summary><strong>🧰 <code>dev</code>-apukomento: oikotiet Docker-komentoihin</strong></summary>

`./dev` on oikotie yleisimpiin Docker-komentoihin. Koko lista: `./dev help`.

| Komento                | Toiminto                                     |
|------------------------|----------------------------------------------|
| `./dev up` / `down`    | Käynnistä / pysäytä palvelut                 |
| `./dev restart`        | Luo palvelut uudelleen (ajaa käynnistysvaiheet uudelleen) |
| `./dev rebuild`        | Rakenna imaget uudelleen ja luo palvelut uudelleen |
| `./dev logs [palvelu]` | Seuraa lokeja                                |
| `./dev artisan <args>` | Aja Artisan-komento                          |
| `./dev composer <args>`| Aja Composer backend-kontissa                |
| `./dev modules:search` | Etsi Python-moduulipaketteja GitHubista ([lisää](#python-moduulit)) |
| `./dev modules:add <lähde>` | Tarkista ja lisää Python-moduuli hakemistosta, arkistosta tai git-osoitteesta ([lisää](#python-moduulit)) |
| `./dev npm <args>`     | Aja npm frontend-kontissa                    |
| `./dev migrate`        | Aja tietokantamigraatiot                     |
| `./dev fresh`          | Luo tietokanta uudelleen ja lisää demodata   |
| `./dev db`             | Avaa MariaDB-asiakasohjelma                  |
| `./dev test`           | Aja backendin ja frontendin testit           |
| `./dev reset`          | Poista kontit ja tietokannan data            |
| `./dev deploy`, `./dev gcloud:*`, `./dev vercel:*` | Julkaise ja tarkista Cloud Run -backend ja Vercelin frontend ([lisää](docs/DEPLOYMENT.md#manual-deploys-and-dev-commands)) |

Windowsissa aja `./dev` Git Bashissa tai WSL:ssä, tai käytä `docker compose` -komentoja suoraan.

</details>

Testit käyttävät aina muistissa olevaa SQLite-tietokantaa, eivät koskaan MariaDB:n kehitysdataa.

## Arkkitehtuuri

```text
Vue (frontend)
  ↓
Laravel REST API (backend)
  ↓
MariaDB
```

<details>
<summary><strong>📁 Hakemistorakenne</strong></summary>

```text
jalaops/
├── backend/             Laravel API
├── frontend/            Vue-sovellus
├── docker/              Docker-konfiguraatio
├── docker-compose.yml
└── dev                  Docker-komentojen oikotiet
```

</details>

## API

Interaktiivinen API-dokumentaatio: http://localhost:8000/docs (OpenAPI JSON: `/docs/api.json`).

<details>
<summary><strong>🩺 Järjestelmä</strong></summary>

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/health`        | API:n ja tietokannan tila     |
| GET    | `/api/system`        | Sovelluksen tiedot ja tila Tietoja-sivulle (palvelimen versiot vain asetuksella `SHOW_SYSTEM_DETAILS=true`) |

</details>

<details>
<summary><strong>🛠️ Pyynnöt</strong></summary>

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/requests`      | Listaa pyynnöt                |
| GET    | `/api/requests/{id}` | Hae yksittäinen pyyntö        |
| POST   | `/api/requests`      | Luo pyyntö                    |
| PUT    | `/api/requests/{id}` | Päivitä pyyntö                |
| DELETE | `/api/requests/{id}` | Poista pyyntö                 |
| GET    | `/api/requests/export?format=csv\|json` | Vie suodattimia vastaavat pyynnöt, tai `ids=1,2` |
| POST   | `/api/requests/import` | Tuo pyyntöjä CSV- tai JSON-tiedostosta (`dry_run` tarkistaa ensin) |
| GET    | `/api/requests/{id}/signature` | Valmiin pyynnön allekirjoitus PNG-kuvana |
| GET/POST | `/api/requests/{id}/items` | Pyynnön työt ja tarvikkeet summineen, tai lisää rivi |
| PUT/DELETE | `/api/requests/{id}/items/{item}` | Päivitä tai poista rivi |
| GET    | `/api/requests/{id}/export/pdf` | Lataa pyyntö PDF-tiedostona (`inline=1` avaa sen selaimessa) |
| GET    | `/api/requests/{id}/export/html` | Lataa pyyntö itsenäisenä HTML-tiedostona (`inline=1` avaa sen selaimessa) |

</details>

<details>
<summary><strong>👥 Asiakkaat</strong></summary>

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/customers`     | Listaa asiakkaat (`search`) pyyntöjen määrineen |
| POST   | `/api/customers`     | Luo asiakas                   |
| GET/PUT/DELETE | `/api/customers/{id}` | Hae, päivitä tai poista asiakas (vain ilman pyyntöjä) |
| GET    | `/api/customers/{id}/billable-requests` | Asiakkaan laskutettavat pyynnöt |

</details>

<details>
<summary><strong>🧾 Laskut</strong></summary>

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET/POST | `/api/invoices`    | Listaa laskut (`status`, `customer`) tai luo lasku laskutettavista pyynnöistä |
| GET/DELETE | `/api/invoices/{id}` | Hae lasku (maksutietoineen) tai poista luonnos |
| PATCH  | `/api/invoices/{id}/status` | Luonnos → lähetetty → maksettu |
| GET    | `/api/invoices/{id}/export/pdf` | Lataa lasku PDF-tiedostona (`inline=1` avaa sen selaimessa) |
| GET    | `/api/invoices/{id}/export/html` | Lataa lasku itsenäisenä HTML-tiedostona (`inline=1` avaa sen selaimessa) |
| GET    | `/api/invoices/export?format=csv\|json` | Laskut kirjanpitoon, rivi ALV-kantaa kohden |

</details>

<details>
<summary><strong>📊 Koontinäkymä</strong></summary>

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/dashboard`     | Pyyntöjen määrät: avoimet, työn alla, valmiit, myöhässä ja yhteensä |
| GET    | `/api/dashboard/ai-overview` | AI-tilannekatsaus (kun käytössä) |
| POST   | `/api/dashboard/ai-overview/refresh` | Luo uusi AI-tilannekatsaus |

</details>

<details>
<summary><strong>🧩 Moduulit</strong></summary>

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/modules`       | Python-moduulit ja onko kukin käytössä |
| GET    | `/api/modules/{id}`  | Moduulin lomake, tulokset ja oma näkymä |
| PATCH  | `/api/modules/{id}`  | Ota moduuli käyttöön tai poista se käytöstä (vain sovelluksen omilta sivuilta) |
| POST   | `/api/modules/{id}/run` | Suorita moduuli: runko on sen syöte (vain sovelluksen omilta sivuilta) |
| GET    | `/api/dashboard/widgets` | Etusivun moduulien sisällöt ja niiden paikat (vain sovelluksen omilta sivuilta) |

</details>

<details>
<summary><strong>📍 Geokoodaus</strong></summary>

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/geocode?q=…`   | Osoitteen koordinaatit (Nominatim) |
| GET    | `/api/geocode/suggest?q=…` | Osoite-ehdotukset kirjoitettaessa (Photon) |

</details>

<details>
<summary><strong>🔎 Suodatus, järjestys ja sivutus</strong></summary>

`GET /api/requests` -kutsun parametrit:

- **Tila ja prioriteetti:** `status=open`, `priority=high`
- **Määräpäivä:** `due=overdue` (`overdue`, `today`, `week`, `month`, `none`) tai `due_from=2026-10-01&due_to=2026-10-31`
- **Haku:** `search=tampere` (otsikko, katuosoite, postinumero tai kaupunki)
- **Järjestys:** `sort=-priority` (`due_date`, `title`, `priority`, `status`, `created_at`; `-` laskevaan järjestykseen)
- **Sivutus:** `per_page=10|25|50|100&page=2`; ilman `per_page`-parametria palautetaan kaikki osumat

</details>

## Kartta ja sijainnit

Pyynnöllä voi olla katuosoite, postinumero, kaupunki ja karttakoordinaatit. **Kartta**-sivu (`/map`) näyttää sijainnilliset pyynnöt koko näytön kartalla: merkit on väritetty prioriteetin mukaan, uusien pyyntöjen (luotu 7 päivän sisällä eikä valmis) merkin ympärillä sykkii ympyrä, ja merkkiä napsauttamalla näkyy pyyntö ja linkki sen tietoihin. Pyyntölomake ehdottaa osoitteita kirjoitettaessa ja täyttää koordinaatit, ja merkin voi myös asettaa napsauttamalla karttaa. Ponnahdusikkunasta ja tietosivulta pääsee Google Street View'hun pyynnön sijainnissa (pelkkä linkki, ei API-avainta).

<details>
<summary><strong>🗺️ Karttapalvelut ja geokoodaus</strong></summary>

Kaikki käytetyt palvelut ovat ilmaisia, perustuvat [OpenStreetMapin](https://www.openstreetmap.org/copyright) aineistoon eivätkä vaadi API-avaimia:

| Palvelu | Käyttö |
|---|---|
| [OpenFreeMap](https://openfreemap.org) | Taustakartta, piirretään Leafletillä ja MapLibre GL:llä (ilman WebGL:ää OpenStreetMapin omat karttatiilet) |
| [Nominatim](https://nominatim.org) | "Hae koordinaatit osoitteesta": osoitteesta koordinaatit, enintään yksi kutsu sekunnissa |
| [Photon](https://photon.komoot.io) | Osoite-ehdotukset kirjoitettaessa (Nominatim ei salli automaattista täydennystä) |

Backend kutsuu Nominatimia ja Photonia, tallentaa tulokset välimuistiin ja rajoittaa kutsuja kävijää kohden. Nominatimin käyttöehdot vaativat sovelluksen tunnistavan User-Agentin: aseta oma arvo muuttujaan `NOMINATIM_USER_AGENT`. Esimerkkiosoitteet ovat oikeita suomalaisia katuosoitteita OpenStreetMapin koordinaatein; itse pyynnöt ovat keksittyjä.

</details>

## QR-koodit

Pyynnöissä on QR-koodit, jotka toimivat puhelimella ja tabletilla siinä missä tietokoneellakin. Tietokoneella koodi on sivulla; puhelimella ja tabletilla se aukeaa koko näytölle, jotta toinen puhelin voi skannata sen. Laskuissa on myös maksun QR-koodi, joka on toistaiseksi pois päältä.

<details>
<summary><strong>💳 Maksun QR-koodi (laskut, toistaiseksi pois päältä)</strong></summary>

Maksun QR-koodi on toistaiseksi pois päältä: `INVOICE_PAYMENT_QR=true` ottaa sen käyttöön. Ilman sitä laskun sivun **Maksutiedoissa** ovat silti pankkitiedot **Kopioi**-painikkeineen.

EPC QR -koodi ("SEPA credit transfer"), jossa ovat myyjän IBAN, BIC ja nimi, summa ja viite RF-viitteenä (ISO 11649). Pankkisovellukset lukevat siitä maksun tiedot.

- Koodi on laskun PDF- ja HTML-tiedostossa maksutietojen vieressä ja laskun sivulla kohdassa **Maksutiedot**.
- **Maksutiedoissa** on myös **Kopioi**-painikkeet IBANille, viitenumerolle ja summalle pankkisovellukseen liittämistä varten.
- Puhelimella ja tabletilla **Näytä maksukoodi** näyttää koodin koko näytöllä, jotta asiakas voi skannata sen omalla puhelimellaan.
- Käyttöön otettuna koodi näytetään vain, kun `SELLER_IBAN` on asetettu, summa on yli nollan eikä laskua ole maksettu.

</details>

<details>
<summary><strong>📱 Pyynnön QR-koodi</strong></summary>

- Pyynnön PDF- ja HTML-tiedostossa koodi avaa pyynnön sovelluksessa (`FRONTEND_URL/requests/{id}`; jätetään pois, jos `FRONTEND_URL` ei ole asetettu).
- Tietokoneella pyynnön sivu näyttää koodin kohdassa **Avaa puhelimella**.
- Puhelimella ja tabletilla **Jaa** avaa laitteen jakovalikon (tai **Kopioi linkki** kopioi linkin), ja **Näytä QR-koodi** näyttää koodin koko näytöllä työkaverin skannattavaksi.

</details>

<details>
<summary><strong>⚙️ Miten koodit tehdään</strong></summary>

- QR-koodi on aina tumma valkoisella pohjalla, myös tummassa teemassa.
- Koko näytön näkymä sopii pysty- ja vaakasuuntaan, pitää näytön päällä, jos selain sallii, ja sulkeutuu **Sulje**-painikkeella, Esc-näppäimellä tai napauttamalla koodin ulkopuolelle.
- Backend muodostaa maksukoodin tekstin (`payment.qr` osoitteessa `/api/invoices/{id}`), joten sovellus ja tiedostot näyttävät saman koodin.
- Sovellus piirtää QR-koodit `qrcode`-paketilla ja tiedostot [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode)-kirjastolla.

</details>

## Python-moduulit

Moduuleilla lisätään ominaisuuksia Pythonilla: ne ovat pieniä ohjelmia hakemistossa [`backend/modules`](backend/modules), ja Laravel ajaa niitä erillisinä prosesseina, JSON sisään ja JSON ulos. **Työkalut → Moduulit** näyttää moduulit ja sen, onko kukin käytössä, ja jokaisella käytössä olevalla moduulilla on sovelluksessa oma sivu, joka piirretään sen `module.json`-tiedostosta tai sen omasta `view.html`-näkymästä. Sovelluksen mukana tulee kaksi esimerkkiä: **ALV-laskuri** ja **Hei maailma**, jolla on oma näkymä ja pylväskaavio. Kaksi asennetaan paketteina omista repositorioistaan: **Työaikalaskuri** (tehdyt tunnit, ylityö ja työn hinta, [jalaops-module-worktime](https://github.com/jussipalanen/jalaops-module-worktime)) ja **Uutiset**, uutissyöte tunnisteineen ja kuvineen, jonka uusimmat uutiset näkyvät etusivulla ([jalaops-module-news](https://github.com/jussipalanen/jalaops-module-news)).

Moduulit ovat valinnaisia: kunkin voi ottaa käyttöön tai poistaa käytöstä sen kytkimellä kohdassa **Työkalut → Moduulit**. Uutiset on aluksi pois käytöstä. Moduulin sivuilla voi olla omat osoitteet (reitit), kuten `/news` ja `/news/syyshuollot-alkavat`, ja sivun otsikko selaimen välilehdellä. Moduuli voi näyttää sisältöä etusivulla (widgetit, paikoissa eli hookeissa); etusivun alareunan **Muokkaa etusivua** siirtää tai piilottaa ne omassa selaimessa, ja `modules:place` kaikilta. Moduuli voi myös tallentaa tietoja omaan hakemistoonsa.

Moduulit tulevat koodin mukana pull requestien kautta, eikä niitä voi ladata sovellukseen. Jokainen ajetaan ilman taustapalvelun API-avaimia, ja se pysäytetään aikakatkaisun jälkeen. Moduulin oma näkymä toimii eristetyssä kehyksessä, josta ei pääse sovellukseen eikä verkkoon.

Moduuli voi olla myös omassa git-repositoriossaan, esimerkiksi omalla palvelimella, `jalaops-module`-tyyppisenä Composer-pakettina. Sen git-tagit ovat sen versiot ja `composer.lock` lukitsee commitin, joten paketin asentaminen tai päivittäminen on sekin pull request tähän repositorioon. Composer asentaa sen hakemistoon `backend/modules/packages/<nimi>`, jota git ei seuraa:

```bash
./dev modules:add https://git.example.com/jalaops/worktime.git
```

`modules:add` tarkistaa moduulin ennen sen lisäämistä: sen `module.json`-tiedoston, tiedostot, reitit, Python-koodin ja sen omat testit. Git-osoitteiden lisäksi se ottaa hakemiston tai arkiston (`.zip`, `.tar`, `.tar.gz`, `.tgz`, `.gz`; tiedosto tai osoite), jonka se kopioi hakemistoon `backend/modules/<id>`. `modules:remove` poistaa moduulin. Kummassakin tapauksessa muutos viedään tuotantoon pull requestilla ja julkaisulla. GitHubissa moduulipaketin repositoriolle annetaan aihe (topic) `jalaops-module`, ja `./dev modules:search` listaa ne ja niiden asennuskomennot.

```bash
./dev modules:add ~/Downloads/weather.zip --check   # vain tarkistus
./dev modules:remove weather                        # --purge poistaa myös sen tiedot
./dev artisan modules:list
./dev artisan modules:run vat-calculator '{"amount": 100}'
./dev artisan modules:disable hello
./dev artisan modules:place news.latest dashboard.bottom   # tai hidden tai default
```

Ohje moduulin ja moduulipaketin tekemiseen (englanniksi): [backend/modules/README.md](backend/modules/README.md).

## AI-tilannekatsaus

Etusivulla voi näkyä **AI-tilannekatsaus**: lyhyt suomenkielinen yhteenveto pyynnöistä, suositellut toimet ja huomioitavat asiat. Laravel laskee tiedot (määrät, myöhässä olevat pyynnöt, seuraavan 7 päivän aikana erääntyvät ja avoimet korkean prioriteetin pyynnöt), ja tekoäly kirjoittaa vain tekstin, joten luvut tulevat tietokannasta.

<details>
<summary><strong>⚙️ Asetukset ja rajoitukset</strong></summary>

Ominaisuus on oletuksena pois päältä. Ota se käyttöön tiedostossa `backend/.env` ja valitse palveluntarjoaja:

| Muuttuja | Arvo |
|---|---|
| `AI_INSIGHTS_ENABLED` | `true` näyttää katsauksen |
| `AI_INSIGHTS_PROVIDER` | `gemini` (Google Gemini, oletus; ilmainen käyttötaso) tai `puter` (Puter AI) |
| `GEMINI_API_KEY` | Gemini-API-avain [Google AI Studiosta](https://aistudio.google.com/apikey), kun palveluntarjoaja on `gemini` |
| `PUTER_AUTH_TOKEN` | Puterin tunnus [Puterin hallintapaneelista](https://puter.com/dashboard), kun palveluntarjoaja on `puter`. Puterin kutsuminen taustapalvelusta vaatii Puter-tilauksen. |
| `GEMINI_MODEL` / `PUTER_MODEL` | Valinnainen malli (oletukset: `gemini-3.1-flash-lite` ja Puterissa hintaan $0 listattu `google/gemma-4-31b-it`) |
| `AI_INSIGHTS_CACHE_MINUTES` | Kuinka kauan katsausta käytetään uudelleen, kun pyynnöt eivät muutu (oletus 60) |
| `AI_INSIGHTS_MAX_PER_HOUR` | Tekoälykutsujen enimmäismäärä tunnissa koko sovellukselle (oletus 20) |

Avain pysyy taustapalvelussa; selain kutsuu vain osoitetta `/api/dashboard/ai-overview`. Tekoälyltä voi kulua useita sekunteja, joten viimeisin katsaus näytetään aina heti: jos pyyntöjä on muutettu, se merkitään vanhentuneeksi ja uusi luodaan taustalla. Tuotantokuva kirjoittaa ensimmäisen katsauksen käynnistyksen yhteydessä (`php artisan ai-overview:warm`). Katsaus vastaa vain sovelluksen omille sivuille (`FRONTEND_URL`, `APP_URL` ja valinnainen `ALLOWED_ORIGINS`, tunnistetaan selaimen `Origin`- tai `Referer`-otsakkeesta; kun `APP_ENV=local`, mikä tahansa sivu), joten muut sivustot eivät voi käyttää tekoälykutsuja. Päivitys on rajattu kolmeen kertaan minuutissa kävijää kohden, ja kun tuntiraja täyttyy, näytetään edellinen katsaus. Pyyntöjen otsikot lähetetään tekoälypalveluun, joten ilmaisversioissa, joissa dataa voidaan käyttää mallien kehittämiseen, kannattaa käyttää vain demodataa.

</details>

## Jatkuva integraatio

GitHub Actions tarkistaa jokaisen pull requestin ja jokaisen `main`-haaraan tehdyn pushin. Pull requestia ei voi yhdistää, jos jokin tarkistus epäonnistuu.

<details>
<summary><strong>✅ Työnkulut ja tarkistukset</strong></summary>

| Työnkulku | Työ | Tarkistukset |
|---|---|---|
| [CI](.github/workflows/ci.yml) | Backend | Koodityyli (Pint), Laravel-testit, Composer-pakettien tunnetut haavoittuvuudet (`composer audit`) |
| | Frontend | Koodityyli (ESLint), Vitest-testit, tuotantokäännös, npm-pakettien tunnetut haavoittuvuudet (`npm audit`, vakavat ja kriittiset) |
| [Security](.github/workflows/security.yml) | CodeQL | JavaScript/Vue-koodin ja työnkulkujen staattinen tietoturva-analyysi; myös viikoittain |
| | Dependency review | Estää pull requestit, jotka lisäävät riippuvuuksia, joissa on tunnettuja vakavia tai kriittisiä haavoittuvuuksia |

Dependabot ([`.github/dependabot.yml`](.github/dependabot.yml)) avaa kuukausittain ryhmitellyt päivitys-pull requestit Composer-, npm-, GitHub Actions- ja Docker-riippuvuuksille sekä tietoturvakorjaukset heti, kun haavoittuvuus julkaistaan. GitHubin salaisuuksien skannaus estää pushit, joissa on avaimia tai tunnuksia.

</details>

<details>
<summary><strong>💻 Tarkistukset paikallisesti</strong></summary>

```bash
cd backend && vendor/bin/pint --test && php artisan test && composer audit
cd frontend && npm run lint:check && npm test && npm run build && npm audit --audit-level=high
```

</details>

## Android-sovellus

JalaOps on myös Android-sovellus: [Trusted Web Activity](https://developer.chrome.com/docs/android/trusted-web-activity), joka avaa verkkosovelluksen koko näytölle ilman selaimen osoiteriviä. Verkkosovelluksen päivitys päivittää myös Android-sovelluksen.

<details>
<summary><strong>🤖 Asennus Android-puhelimeen</strong></summary>

1. Lataa puhelimella **jalaops.apk** [uusimmasta julkaisusta](https://github.com/jussipalanen/jalaops/releases/latest).
2. Avaa ladattu tiedosto. Ensimmäisellä kerralla Android pyytää sallimaan sovellusten asentamisen selaimesta; salli se ja palaa takaisin.
3. Valitse **Asenna** ja avaa JalaOps aloitusnäytöltä. Kuvaketta pitkään painamalla saat pikalinkit uuteen pyyntöön, listaan ja karttaan.

Puhelimessa tarvitaan Chrome (tai muu Trusted Web Activityä tukeva selain). Päivitys: asenna uudempi `jalaops.apk` vanhan päälle. Google Play Protect saattaa varoittaa sovelluksesta, koska sitä ei asenneta Google Playsta.

Verkkosovelluksen **Mobiilisovellukset**-sivu (`/mobile-apps`), jossa on lataus ja iPhonen ohjeet suomeksi, on toistaiseksi pois käytöstä. Sen saa näkyviin kääntämällä frontendin asetuksella `VITE_MOBILE_APPS_PAGE=true`.

**Onko lataus turvallinen?** Sovellus ei pyydä Androidin käyttöoikeuksia, vaan avaa verkkosovelluksen Chromessa. Jokaisessa julkaisussa on APK:n SHA-256-tarkistussumma (`SHA256SUMS`-tiedostossa) sekä [alkuperätodistus](https://docs.github.com/en/actions/security-for-github-actions/using-artifact-attestations) (build provenance), joka osoittaa, että Android-työnkulku on kääntänyt tiedoston tämän repositorion lähdekoodista. Ladatun tiedoston tarkistus:

```bash
sha256sum jalaops.apk
gh attestation verify jalaops.apk -R jussipalanen/jalaops
```

</details>

<details>
<summary><strong>🍎 Asennus iPhoneen ja iPadiin</strong></summary>

App Storessa ei ole sovellusta, vaan JalaOps lisätään Koti-valikkoon verkkosovelluksena. Avaa sivu Safarissa, napauta **Jaa** (tarvittaessa ensin **•••**-valikosta) ja valitse **Lisää Koti-valikkoon**. Sovellus avautuu kuvakkeesta koko näytölle, ja verkkosovelluksen päivitykset näkyvät siinä heti.

</details>

<details>
<summary><strong>🏗️ Android-sovelluksen rakentaminen ja julkaisu</strong></summary>

```bash
./dev android
```

Komento rakentaa Dockerissa tiedostot `android/dist/jalaops.apk` (asennus puhelimeen) ja `android/dist/jalaops.aab` (Google Playhin), joten vain Docker tarvitaan. Se käyttää [Bubblewrapia](https://github.com/GoogleChromeLabs/bubblewrap) ja tiedoston [`android/twa-manifest.json`](android/twa-manifest.json) asetuksia; sovelluksen versio seuraa frontendin versiota (0.24.0 → versiokoodi 2400). Ensimmäinen rakennus lataa Javan, Android SDK:n ja Gradlen (noin 2 Gt) ja kestää useita minuutteja; myöhemmin noin minuutin.

**Allekirjoitusavain:** ensimmäinen rakennus luo latausavaimen kansioon `~/.jalaops/android` (repositorion ulkopuolelle). Varmuuskopioi kansio: Google Play hyväksyy päivitykset vain samalla avaimella allekirjoitettuina. Avaimen SHA-256-sormenjälki on tiedostossa [`frontend/public/.well-known/assetlinks.json`](frontend/public/.well-known/assetlinks.json); ilman sitä sovellus avautuu osoiterivin kanssa. Siinä on myös Google Playn sovelluksen allekirjoitusavain (Play Consolen Sovelluksen eheys -sivulla), koska Google allekirjoittaa Google Playsta jaettavan sovelluksen uudelleen. Eri avaimen (esimerkiksi toisella koneella) sormenjälki pitää lisätä sinne.

**Julkaisut:** [Android-työnkulku](.github/workflows/android.yml) liittää `jalaops.apk`-, `jalaops.aab`- ja `SHA256SUMS`-tiedostot jokaiseen GitHub-julkaisuun alkuperätodistuksen kanssa, ja sen voi käynnistää myös käsin Actions-välilehdeltä. Avain tallennetaan kerran GitHubin salaisuuksiin komennolla `./dev android-secrets` (käyttää `gh`-työkalua).

**Google Play:** lataa `jalaops.aab` sisäiseen testaukseen [Play Consolessa](https://play.google.com/console). Kauppasivun tietosuojaselosteena on sovelluksen Tietosuoja-sivu, `https://jalaops-demo.jussialanen.com/privacy`. Suunnitelma on tiedostossa [ROADMAP.md](ROADMAP.md#android-app-google-play-testing).

</details>

## Julkaisu

Frontend toimii Vercelissä ja backend Google Cloud Runissa (aina päällä, muutama euro kuukaudessa) tai Renderissä (ilmainen, nukahtaa käyttämättömänä). Kun `main`-haaraan tulee backendin muutos, se julkaistaan Cloud Runiin automaattisesti CI-tarkistusten jälkeen ([deploy-työnkulku](.github/workflows/deploy.yml)), ja `./dev deploy` julkaisee sen käsin. Oletuksena julkaistu backend käyttää demodataa, joka palautuu jokaisella uudelleenkäynnistyksellä (`DEMO_MODE=true`), joten erillistä tietokantapalvelua ei tarvita. Asettamalla `DEMO_MODE=false` ja `DB_*`-muuttujat käyttöön tulee oikea tietokanta.

Molemmat palvelut pyytävät hakukoneita olemaan indeksoimatta niitä (`X-Robots-Tag: noindex` ja `noindex`-metatagi), koska sovellus on demo. `index.html` sisältää silti kuvauksen sekä Open Graph- ja X-korttitagit, joten jaetuista linkeistä näkyy esikatselukortti; aseta frontendin julkinen osoite muuttujaan `VITE_SITE_URL` (`frontend/.env.production`).

Vaiheittainen ohje (englanniksi): [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Tiekartta

Kehityssuunnitelma löytyy tiedostosta [ROADMAP.md](ROADMAP.md) (englanniksi).
