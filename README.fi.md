# JalaOps

[English](README.md) | **Suomi**

[Yleiskuvaus](#yleiskuvaus) · [Teknologiat](#teknologiat) · [Käyttöönotto](#käyttöönotto) · [Arkkitehtuuri](#arkkitehtuuri) · [API](#api) · [Tiekartta](#tiekartta)

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
| Frontend     | Vue 3, Vite, Vue Router        |
| Tietokanta   | MariaDB                        |
| Kehitysympäristö | Docker, Docker Compose     |
| CI           | GitHub Actions                 |

## Käyttöönotto

Vaatimukset: Docker ja Docker Compose.

```bash
git clone git@github.com:jussipalanen/jalaops.git
cd jalaops
docker compose up
```

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
└── docker-compose.yml
```

## API

| Metodi | Endpoint             | Kuvaus                        |
|--------|----------------------|-------------------------------|
| GET    | `/api/requests`      | Listaa pyynnöt                |
| GET    | `/api/requests/{id}` | Hae yksittäinen pyyntö        |
| POST   | `/api/requests`      | Luo pyyntö                    |
| PUT    | `/api/requests/{id}` | Päivitä pyyntö                |
| DELETE | `/api/requests/{id}` | Poista pyyntö                 |
| GET    | `/api/dashboard`     | Pyyntöjen määrät tiloittain   |

Suodattimet: `/api/requests?status=open`, `/api/requests?priority=high`

## Tiekartta

Kehityssuunnitelma löytyy tiedostosta [ROADMAP.md](ROADMAP.md) (englanniksi).
