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

**English** | [Suomi](README.fi.md)

📋 [Overview](#overview) · 🧱 [Tech stack](#tech-stack) · 🚀 [Getting started](#getting-started) · 🏛️ [Architecture](#architecture) · 🔌 [API](#api) · 🗺️ [Map](#map-and-locations) · 📱 [Android](#android-app) · ☁️ [Deployment](#deployment) · 🛣️ [Roadmap](#roadmap)

> **Status:** in early development. See the [roadmap](ROADMAP.md) and [issues](https://github.com/jussipalanen/jalaops/issues).

---

## Overview

JalaOps is a small operations management demo application for handling service requests.

A user can:

- view, create, edit and delete requests
- change a request's status, and sign off a completed request with a name and a drawn signature
- preview a request as a PDF or HTML file in a browser tab, or download it
- export requests to CSV (Excel) or JSON and import them back on the Työkalut page
- filter requests by status, priority and due date, and search them by title or address
- give a request an address and see the requests on a full-screen map
- view a dashboard with the request counts, each linking to the matching list
- keep a register of customers (Asiakkaat) and choose the customer for each request
- record the work and materials of a request, with VAT and totals (the basis for invoicing)
- invoice completed requests: invoice numbers, Finnish reference numbers, due dates, statuses, an invoice PDF and HTML page with the JalaOps logo and page numbers, and an export for accounting software
- read an optional AI-written status overview (AI-tilannekatsaus) on the dashboard

The application UI is in Finnish.

## Tech stack

| Layer    | Technology                     |
|----------|--------------------------------|
| Backend  | PHP, Laravel, REST API         |
| Frontend | Vue 3, Vite, Vue Router, Tailwind CSS |
| Database | MariaDB                        |
| Dev env  | Docker, Docker Compose         |
| CI       | GitHub Actions                 |

## Getting started

Requirements: Docker and Docker Compose.

```bash
git clone git@github.com:jussipalanen/jalaops.git
cd jalaops
./dev up        # or: docker compose up -d
```

The first start installs the dependencies and runs the database migrations, so it takes a moment. Then open:

| Service  | URL                              |
|----------|----------------------------------|
| Frontend | http://localhost:5173            |
| API      | http://localhost:8000/api/health |
| MariaDB  | `localhost:3306` (user `jalaops`, password `secret`, database `jalaops`) |

If port 3306 is already in use, start with `DB_HOST_PORT=3307 ./dev up`.

<details>
<summary><strong>🧰 The <code>dev</code> helper: shortcuts for Docker commands</strong></summary>

`./dev` is a shortcut for common Docker commands. Run `./dev help` for the full list.

| Command                | Does                                         |
|------------------------|----------------------------------------------|
| `./dev up` / `down`    | Start / stop the services                    |
| `./dev restart`        | Recreate the services (reruns their startup steps) |
| `./dev rebuild`        | Rebuild the images and recreate the services |
| `./dev logs [service]` | Follow the logs                              |
| `./dev artisan <args>` | Run an Artisan command                       |
| `./dev composer <args>`| Run Composer in the backend container        |
| `./dev npm <args>`     | Run npm in the frontend container            |
| `./dev migrate`        | Run database migrations                      |
| `./dev fresh`          | Recreate the database and seed it            |
| `./dev db`             | Open the MariaDB client                      |
| `./dev test`           | Run the backend and frontend tests           |
| `./dev reset`          | Remove the containers and the database data  |

On Windows, run `./dev` in Git Bash or WSL, or use the `docker compose` commands directly.

</details>

Tests always use an in-memory SQLite database, never the MariaDB development data.

## Architecture

```text
Vue (frontend)
  ↓
Laravel REST API (backend)
  ↓
MariaDB
```

<details>
<summary><strong>📁 Project structure</strong></summary>

```text
jalaops/
├── backend/             Laravel API
├── frontend/            Vue application
├── docker/              Docker configuration
├── docker-compose.yml
└── dev                  Docker command shortcuts
```

</details>

## API

Interactive API documentation: http://localhost:8000/docs (OpenAPI JSON: `/docs/api.json`).

<details>
<summary><strong>🩺 System</strong></summary>

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET    | `/api/health`        | API and database health     |
| GET    | `/api/system`        | App details and health for the "Tietoja" page (server versions only with `SHOW_SYSTEM_DETAILS=true`) |

</details>

<details>
<summary><strong>🛠️ Requests</strong></summary>

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET    | `/api/requests`      | List requests               |
| GET    | `/api/requests/{id}` | Get a single request        |
| POST   | `/api/requests`      | Create a request            |
| PUT    | `/api/requests/{id}` | Update a request            |
| DELETE | `/api/requests/{id}` | Delete a request            |
| GET    | `/api/requests/export?format=csv\|json` | Export the requests matching the filters, or `ids=1,2` |
| POST   | `/api/requests/import` | Import requests from a CSV or JSON file (`dry_run` to check it first) |
| GET    | `/api/requests/{id}/signature` | The completion signature as a PNG |
| GET/POST | `/api/requests/{id}/items` | A request's work and materials with totals, or add a row |
| PUT/DELETE | `/api/requests/{id}/items/{item}` | Update or delete a row |
| GET    | `/api/requests/{id}/export/pdf` | Download the request as a PDF (`inline=1` opens it in the browser) |
| GET    | `/api/requests/{id}/export/html` | Download the request as a self-contained HTML file (`inline=1` opens it in the browser) |

</details>

<details>
<summary><strong>👥 Customers</strong></summary>

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET    | `/api/customers`     | List customers (`search`), with their request counts |
| POST   | `/api/customers`     | Create a customer           |
| GET/PUT/DELETE | `/api/customers/{id}` | Get, update or delete a customer (only one without requests can be deleted) |
| GET    | `/api/customers/{id}/billable-requests` | A customer's requests that can be invoiced |

</details>

<details>
<summary><strong>🧾 Invoices</strong></summary>

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET/POST | `/api/invoices`    | List invoices (`status`, `customer`) or create one from billable requests |
| GET/DELETE | `/api/invoices/{id}` | Get an invoice or delete a draft |
| PATCH  | `/api/invoices/{id}/status` | Draft → sent → paid |
| GET    | `/api/invoices/{id}/export/pdf` | Download the invoice as a PDF (`inline=1` opens it in the browser) |
| GET    | `/api/invoices/{id}/export/html` | Download the invoice as a self-contained HTML file (`inline=1` opens it in the browser) |
| GET    | `/api/invoices/export?format=csv\|json` | Invoices for accounting, one row per VAT rate |

</details>

<details>
<summary><strong>📊 Dashboard</strong></summary>

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET    | `/api/dashboard`     | Request counts: open, in progress, completed, overdue and total |
| GET    | `/api/dashboard/ai-overview` | AI status overview (when enabled) |
| POST   | `/api/dashboard/ai-overview/refresh` | Generate a new AI status overview |

</details>

<details>
<summary><strong>📍 Geocoding</strong></summary>

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET    | `/api/geocode?q=…`   | Coordinates of an address (Nominatim) |
| GET    | `/api/geocode/suggest?q=…` | Address suggestions while typing (Photon) |

</details>

<details>
<summary><strong>🔎 Filtering, sorting and pagination</strong></summary>

Query parameters for `GET /api/requests`:

- **Status and priority:** `status=open`, `priority=high`
- **Due date:** `due=overdue` (`overdue`, `today`, `week`, `month`, `none`) or `due_from=2026-10-01&due_to=2026-10-31`
- **Search:** `search=tampere` (title, street address, postal code or city)
- **Sort:** `sort=-priority` (`due_date`, `title`, `priority`, `status`, `created_at`; `-` for descending)
- **Pagination:** `per_page=10|25|50|100&page=2`; without `per_page` all matching requests are returned

</details>

## Map and locations

A request can have a street address, postal code, city and map coordinates. The **Kartta** page (`/map`) shows the requests with a location on a full-screen map: pins are coloured by priority, new requests (created in the last 7 days and not completed) have a pulsing circle, and clicking a pin shows the request with a link to its details. The request form suggests addresses while typing and fills in the coordinates, and the pin can also be placed by clicking the map. The popup and the details page link to Google Street View at the request's location (a plain link, no API key).

<details>
<summary><strong>🗺️ Map services and geocoding</strong></summary>

Everything uses free services built on [OpenStreetMap](https://www.openstreetmap.org/copyright) data, with no API keys:

| Service | Used for |
|---|---|
| [OpenFreeMap](https://openfreemap.org) | The basemap, drawn with Leaflet and MapLibre GL (OpenStreetMap's own tiles without WebGL) |
| [Nominatim](https://nominatim.org) | "Hae koordinaatit osoitteesta": address to coordinates, at most one call per second |
| [Photon](https://photon.komoot.io) | Address suggestions while typing (Nominatim doesn't allow autocomplete) |

The backend calls Nominatim and Photon, caches the results and limits calls per visitor. Nominatim's usage policy requires a User-Agent that identifies the app: set your own in `NOMINATIM_USER_AGENT`. The demo addresses are real Finnish street addresses with OpenStreetMap's coordinates; the requests themselves are made up.

</details>

## AI status overview

The home page can show **AI-tilannekatsaus**, a short Finnish summary of the requests with suggested actions and things to watch. Laravel computes the facts (counts, overdue requests, requests due within 7 days, open high-priority requests) and the AI only writes the text, so the numbers come from the database.

<details>
<summary><strong>⚙️ Settings and limits</strong></summary>

The feature is off by default. Turn it on in `backend/.env` and pick the provider:

| Variable | Value |
|---|---|
| `AI_INSIGHTS_ENABLED` | `true` to show the overview |
| `AI_INSIGHTS_PROVIDER` | `gemini` (Google Gemini, default; has a free tier) or `puter` (Puter AI) |
| `GEMINI_API_KEY` | Gemini API key from [Google AI Studio](https://aistudio.google.com/apikey), when the provider is `gemini` |
| `PUTER_AUTH_TOKEN` | Puter auth token from the [Puter dashboard](https://puter.com/dashboard), when the provider is `puter`. Calling Puter from the backend needs a Puter subscription. |
| `GEMINI_MODEL` / `PUTER_MODEL` | Optional model override (defaults: `gemini-3.1-flash-lite`, and `google/gemma-4-31b-it`, listed at $0 on Puter) |
| `AI_INSIGHTS_CACHE_MINUTES` | How long an overview is reused while the requests stay the same (default 60) |
| `AI_INSIGHTS_MAX_PER_HOUR` | Upper limit of AI calls per hour for the whole app (default 20) |

The key stays on the backend; the browser only talks to `/api/dashboard/ai-overview`. The AI can take several seconds, so the latest overview is always shown right away: when the requests have changed, it is marked as outdated and a new one is generated in the background. The production image writes the first overview at startup (`php artisan ai-overview:warm`). Refreshing is limited to three times per minute per visitor, and past the hourly limit the previous overview is shown. Request titles are sent to the AI provider, so use demo data only with free tiers that may use the data for training.

</details>

## Continuous integration

GitHub Actions checks every pull request and every push to `main`. A pull request can't be merged while a check fails.

<details>
<summary><strong>✅ Workflows and checks</strong></summary>

| Workflow | Job | Checks |
|---|---|---|
| [CI](.github/workflows/ci.yml) | Backend | Code style (Pint), Laravel tests, known vulnerabilities in Composer packages (`composer audit`) |
| | Frontend | Code style (ESLint), Vitest tests, production build, known vulnerabilities in npm packages (`npm audit`, high and critical) |
| [Security](.github/workflows/security.yml) | CodeQL | Static security analysis of the JavaScript/Vue code and the workflows; also weekly |
| | Dependency review | Blocks pull requests that add dependencies with known high or critical vulnerabilities |

Dependabot ([`.github/dependabot.yml`](.github/dependabot.yml)) opens monthly grouped update pull requests for Composer, npm, GitHub Actions and Docker images, and security fixes as soon as an advisory is published. GitHub secret scanning with push protection blocks pushes that contain keys or tokens.

</details>

<details>
<summary><strong>💻 Run the checks locally</strong></summary>

```bash
cd backend && vendor/bin/pint --test && php artisan test && composer audit
cd frontend && npm run lint:check && npm test && npm run build && npm audit --audit-level=high
```

</details>

## Android app

JalaOps is also an Android app: a [Trusted Web Activity](https://developer.chrome.com/docs/android/trusted-web-activity) that opens the live web app full screen, without the browser's address bar. Updating the web app updates the Android app too.

<details>
<summary><strong>🤖 Install on Android</strong></summary>

1. On your phone, open the web app's **Mobiilisovellukset** page (linked from the footer) and choose **Lataa Android-sovellus (APK)**. It downloads **jalaops.apk** from the [latest release](https://github.com/jussipalanen/jalaops/releases/latest).
2. Open the downloaded file. The first time, Android asks you to allow your browser to install apps; allow it and return.
3. Choose **Install** and open JalaOps from the home screen. Long-press the icon for shortcuts to a new request, the list and the map.

The app needs Chrome (or another browser that supports Trusted Web Activities) on the phone. Updates: install the newer `jalaops.apk` over the old one.

**Is the download safe?** The app asks for no Android permissions; it only opens the web app in Chrome. Each release has the APK's SHA-256 checksum (in `SHA256SUMS` and on the Mobiilisovellukset page) and a [build provenance attestation](https://docs.github.com/en/actions/security-for-github-actions/using-artifact-attestations) proving it was built from this repository by the Android workflow. To check a downloaded file:

```bash
sha256sum jalaops.apk
gh attestation verify jalaops.apk -R jussipalanen/jalaops
```

</details>

<details>
<summary><strong>🍎 Install on iPhone and iPad</strong></summary>

There is no App Store app; JalaOps is added to the home screen as a web app instead. Open the site in Safari, tap **Share** (behind **•••** if needed) and choose **Add to Home Screen**. It then opens full screen from its icon, and web app updates reach it right away. The **Mobiilisovellukset** page shows these steps in Finnish.

</details>

<details>
<summary><strong>🏗️ Build and release the Android app</strong></summary>

```bash
./dev android
```

This builds `android/dist/jalaops.apk` (install on a phone) and `android/dist/jalaops.aab` (upload to Google Play) in Docker, so only Docker is needed. It uses [Bubblewrap](https://github.com/GoogleChromeLabs/bubblewrap) with the settings in [`android/twa-manifest.json`](android/twa-manifest.json); the app's version follows the frontend's (0.24.0 → version code 2400). The first build downloads Java, the Android SDK and Gradle (about 2 GB) and takes several minutes; later builds take about a minute.

**Signing key:** the first build creates an upload key in `~/.jalaops/android` (outside the repository). Back that folder up: Google Play only accepts updates signed with the same key. The key's SHA-256 fingerprint is listed in [`frontend/public/.well-known/assetlinks.json`](frontend/public/.well-known/assetlinks.json); without it the app opens with an address bar. A different key (for example on another machine) needs its fingerprint added there.

**Release builds:** the [Android workflow](.github/workflows/android.yml) attaches `jalaops.apk`, `jalaops.aab` and `SHA256SUMS` to every GitHub release with a build provenance attestation, and can be started by hand from the Actions tab. It needs the key in GitHub secrets once: run `./dev android-secrets` (uses the `gh` CLI).

**Google Play:** upload `jalaops.aab` to the internal testing track in the [Play Console](https://play.google.com/console). See the plan in [ROADMAP.md](ROADMAP.md#android-app-google-play-testing).

</details>

## Deployment

The frontend runs on Vercel and the backend on Google Cloud Run (always on, a few euros a month) or Render (free, sleeps when idle). Pushes to `main` that change the backend deploy it to Cloud Run automatically after CI passes ([deploy workflow](.github/workflows/deploy.yml)), and `./dev deploy` deploys it by hand. By default the hosted backend runs on demo data that resets on every restart (`DEMO_MODE=true`), so no database service is needed. Set `DEMO_MODE=false` and the `DB_*` variables to use a real database.

Both services ask search engines not to index them (`X-Robots-Tag: noindex` and a `noindex` meta tag), because the app is a demo. `index.html` still has a description, Open Graph and X card tags, so shared links show a preview card; set the frontend's public address in `VITE_SITE_URL` (`frontend/.env.production`).

Step-by-step guide: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Roadmap

The development plan is in [ROADMAP.md](ROADMAP.md).
