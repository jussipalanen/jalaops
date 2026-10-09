# <img src="docs/logo.svg" alt="JalaOps" height="48">

[English](README.md) | **Suomi**

[Yleiskuvaus](#yleiskuvaus) · [Teknologiat](#teknologiat) · [Käyttöönotto](#käyttöönotto) · [Arkkitehtuuri](#arkkitehtuuri) · [API](#api) · [Julkaisu](#julkaisu) · [Tiekartta](#tiekartta)

> **Tila:** kehitys on alkuvaiheessa. Katso [tiekartta](ROADMAP.md) ja [issuet](https://github.com/jussipalanen/jalaops/issues).

---

## Yleiskuvaus

JalaOps on pieni toiminnanohjauksen demosovellus huoltopyyntöjen hallintaan.

Käyttäjä voi:

- tarkastella, luoda, muokata ja poistaa pyyntöjä
- vaihtaa pyynnön tilaa
- suodattaa pyyntöjä tilan ja prioriteetin mukaan
- tarkastella yksinkertaista koontinäkymää

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

### `dev`-apukomento

`./dev` on oikotie yleisimpiin Docker-komentoihin. Koko lista: `./dev help`.

| Komento                | Toiminto                                     |
|------------------------|----------------------------------------------|
| `./dev up` / `down`    | Käynnistä / pysäytä palvelut                 |
| `./dev restart`        | Luo palvelut uudelleen (ajaa käynnistysvaiheet uudelleen) |
| `./dev rebuild`        | Rakenna imaget uudelleen ja luo palvelut uudelleen |
| `./dev logs [palvelu]` | Seuraa lokeja                                |
| `./dev artisan <args>` | Aja Artisan-komento                          |
| `./dev composer <args>`| Aja Composer backend-kontissa                |
| `./dev npm <args>`     | Aja npm frontend-kontissa                    |
| `./dev migrate`        | Aja tietokantamigraatiot                     |
| `./dev fresh`          | Luo tietokanta uudelleen ja lisää demodata   |
| `./dev db`             | Avaa MariaDB-asiakasohjelma                  |
| `./dev test`           | Aja backendin ja frontendin testit           |
| `./dev reset`          | Poista kontit ja tietokannan data            |

Windowsissa aja `./dev` Git Bashissa tai WSL:ssä, tai käytä `docker compose` -komentoja suoraan.

Testit käyttävät aina muistissa olevaa SQLite-tietokantaa, eivät koskaan MariaDB:n kehitysdataa.

## Arkkitehtuuri

```text
Vue (frontend)
  ↓
Laravel REST API (backend)
  ↓
MariaDB
```

```text
jalaops/
├── backend/             Laravel API
├── frontend/            Vue-sovellus
├── docker/              Docker-konfiguraatio
├── docker-compose.yml
└── dev                  Docker-komentojen oikotiet
```

## API

Interaktiivinen API-dokumentaatio: http://localhost:8000/docs (OpenAPI JSON: `/docs/api.json`).

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/health`        | API:n ja tietokannan tila     |
| GET    | `/api/requests`      | Listaa pyynnöt                |
| GET    | `/api/requests/{id}` | Hae yksittäinen pyyntö        |
| POST   | `/api/requests`      | Luo pyyntö                    |
| PUT    | `/api/requests/{id}` | Päivitä pyyntö                |
| DELETE | `/api/requests/{id}` | Poista pyyntö                 |
| GET    | `/api/dashboard`     | Pyyntöjen määrät tiloittain   |

Suodattimet: `/api/requests?status=open`, `/api/requests?priority=high`

## Julkaisu

Sovelluksen voi julkaista ilmaiseksi: frontend Verceliin ja backend Renderiin. Oletuksena julkaistu backend käyttää demodataa, joka palautuu jokaisella uudelleenkäynnistyksellä (`DEMO_MODE=true`), joten erillistä tietokantapalvelua ei tarvita. Asettamalla `DEMO_MODE=false` ja `DB_*`-muuttujat käyttöön tulee oikea tietokanta.

Vaiheittainen ohje (englanniksi): [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Tiekartta

Kehityssuunnitelma löytyy tiedostosta [ROADMAP.md](ROADMAP.md) (englanniksi).
