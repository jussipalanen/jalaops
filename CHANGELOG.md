# Changelog

All notable changes to JalaOps are listed here, newest first. Each version lists what was **Added**, **Changed**, **Fixed** and **Removed**.

Versions follow [semantic versioning](https://semver.org): until 1.0.0, a new minor version (0.**x**.0) is a feature milestone and a patch version (0.x.**y**) holds only fixes. 1.0.0 is the first complete release, as defined in [ROADMAP.md](ROADMAP.md). The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.17.0] - 2026-10-12

### Added
- Link preview and page metadata in the HTML source (`index.html`, #41): a Finnish title and description, application name, author and keywords, canonical address, Open Graph tags (title, description, locale, address and a 1200×627 preview image) and X (Twitter) card tags, so shared links show a card in Slack, Teams, LinkedIn, WhatsApp and similar services. Also structured data (schema.org `WebApplication`), a web app manifest with app icons, an Apple touch icon, `color-scheme` and `format-detection`.
- Each page has its own browser tab title, e.g. "Kartta – JalaOps"; the details page uses the request's title.
- A meta description on the backend home page.
- `VITE_SITE_URL` setting for the frontend's public address, used in the absolute preview URLs (set in `.env.production`).

### Changed
- The app stays out of search results: `noindex` is unchanged; the new tags only make shared links look better.

## [0.16.0] - 2026-10-12

### Added
- New requests on the map (#39): requests created in the last 7 days that aren't completed get a pulsing violet circle around their pin (a still circle when the user prefers reduced motion) and an "Uusi" badge in the map popup, the map's request list, the request list and the details page. The map panel shows how many of its requests are new and has a legend for the pin colours and the circle.

### Changed
- The demo requests get creation dates spread over the past weeks, so five of them are new instead of all.

## [0.15.0] - 2026-10-12

### Added
- Street View links for requests with a location (#37): "Street View ↗" in the map popup and "Avaa Street View" on the details page open Google Street View at the coordinates in a new tab. It uses a free Google Maps URL, so no API key is needed.

## [0.14.1] - 2026-10-12

### Fixed
- Search engines could index the hosted demo (#35). Every frontend and backend response now sends `X-Robots-Tag: noindex, nofollow` (Vercel headers and a Laravel middleware), and the frontend and the backend home page have a `noindex` meta tag. `robots.txt` still allows crawling, so crawlers can see the noindex.

## [0.14.0] - 2026-10-12

### Added
- Määräpäivä filter on the request list (#33): Myöhässä (not completed and due before today), Tänään, Seuraavat 7 päivää, Seuraavat 30 päivää, Ei määräpäivää, or Aikaväli… with Alkaen and Asti date fields. The choice is kept in the address like the other filters.
- `GET /api/requests?due=overdue|today|week|month|none` and `due_from`/`due_to` (YYYY-MM-DD, either end can be left open), with Finnish validation messages.

### Changed
- The search ("Haku", formerly "Osoitehaku") also matches the request title, on the list and the map.
- An invalid filter in the address shows the API's Finnish validation message on the request list.

## [0.13.0] - 2026-10-12

### Added
- Request locations (#31): street address, postal code, city and map coordinates on each request, all optional. Coordinates are given together or not at all.
- Kartta, a full-screen map view at `/map` with its own link in the main navigation. Requests with a location are shown as pins coloured by priority (completed requests are faded). Clicking a pin opens a popup with the title, priority, status, description, address, due date and a "Näytä tiedot" link. A side panel lists the requests on the map; on phones it opens from the bottom of the screen. `/map?request=12` focuses one request.
- The map uses OpenFreeMap's free vector basemap (built on OpenStreetMap data) with Google Maps-like colours, and a dark basemap in the dark theme. It needs no API key; MapLibre GL draws it inside Leaflet, and browsers without WebGL get OpenStreetMap's own tiles.
- Request details page at `/requests/:id` with all fields, a small map and Muokkaa/Poista buttons. Request titles in the list link to it.
- "Sijainti" section in the request form: address fields, a small map where clicking or dragging the pin sets the coordinates, and "Hae koordinaatit osoitteesta", which looks the address up with Nominatim (OpenStreetMap).
- Address suggestions while typing the street address. Choosing one fills in the address, postal code, city and coordinates. The suggestions come from Photon (OpenStreetMap), because Nominatim doesn't allow autocomplete.
- Osoitehaku on the request list and the map: search by street address, postal code or city. The search is kept in the address like the other filters (`?search=tampere`).
- `GET /api/requests?search=…`, `GET /api/geocode?q=…` and `GET /api/geocode/suggest?q=…`. Lookups and suggestions are cached and rate-limited per visitor, and Nominatim gets at most one call per second from the whole app.
- Finnish addresses with coordinates for the demo requests, around Finland from Helsinki to Rovaniemi. Seeding an existing database adds the locations to the demo requests that don't have one.
- "Kartalla" links in the request list for requests with a location.
- "API-etusivu" link in the footer to the backend home page.
- `NOMINATIM_USER_AGENT` setting in `backend/.env.example`.

### Changed
- The request list's filters are shared with the map (`useRequestFilters`).
- On phones the header shows only the logo, so the three navigation links and the theme button fit.

## [0.12.0] - 2026-10-09

### Added
- GitHub Actions CI (`.github/workflows/ci.yml`) for every pull request and push to `main` (#10). The backend job runs Pint, the Laravel tests and `composer audit`; the frontend job runs ESLint, the Vitest tests, the production build and `npm audit` (high and critical).
- Security workflow (`.github/workflows/security.yml`): CodeQL analysis of the JavaScript/Vue code and the workflows on every change and weekly, and dependency review that blocks pull requests adding dependencies with known high or critical vulnerabilities.
- Dependabot configuration for monthly grouped updates of Composer, npm, GitHub Actions and Docker images.
- `npm run lint:check` runs ESLint without fixing files, for CI.
- CI and Security badges and a "Continuous integration" section in the READMEs.
- `AGENTS.md` with the instructions for AI coding agents (project overview, check commands, language, branch, versioning and secret-handling rules). `CLAUDE.md` now imports it, so all tools share one set of rules.

### Fixed
- `php artisan test` failed because `phpunit.xml` listed a `tests/Unit` suite whose folder doesn't exist. The empty suite is removed.

## [0.11.1] - 2026-10-09

### Fixed
- The AI-tilannekatsaus no longer makes visitors wait for the AI after the requests change. The previous overview is shown right away with a "Päivitetään…" note, and the new one replaces it when ready. Gemini can take from a couple of seconds to over ten seconds per overview.
- The first visitor after a deploy or restart no longer waits for the first overview: the production image generates it in the background at startup (`php artisan ai-overview:warm`).

### Changed
- `GET /api/dashboard/ai-overview` returns the cached overview immediately with a new `outdated` flag instead of generating a new one. `POST …/refresh` generates it, and it reuses an overview generated for the same requests in the last minute, so simultaneous visitors cause only one AI call. AI calls run one at a time under a lock.
- The loading state says that the AI is writing the overview.
- Each AI call logs its duration, split into DNS lookup, connection, TLS and waiting for the reply.

## [0.11.0] - 2026-10-09

### Added
- AI-tilannekatsaus on the home page: a short Finnish summary of the requests with up to three suggested actions and things to watch, written by an AI model. Laravel computes the facts (counts, overdue requests, requests due within 7 days, open high-priority requests) and the AI only writes the text.
- Two AI providers, chosen with `AI_INSIGHTS_PROVIDER`: `gemini` (Google Gemini, `GEMINI_API_KEY`) and `puter` (Puter AI through its OpenAI-compatible API, `PUTER_AUTH_TOKEN`). The models can be changed with `GEMINI_MODEL` and `PUTER_MODEL`; the defaults are `gemini-3.1-flash-lite` (Gemini free tier) and `google/gemma-4-31b-it` (listed at $0 on Puter; server-side use of Puter needs a Puter subscription).
- `GET /api/dashboard/ai-overview` and `POST /api/dashboard/ai-overview/refresh`. Without `AI_INSIGHTS_ENABLED=true` and the provider's key, the endpoint returns `{"enabled": false}` and the card stays hidden.
- Overviews are cached until the requests, the provider or the model change (or after `AI_INSIGHTS_CACHE_MINUTES`). Refreshing is limited to three times per minute per visitor, and AI calls are capped per hour for the whole app (`AI_INSIGHTS_MAX_PER_HOUR`); past the cap the previous overview is shown.
- AI settings in `backend/.env.example`, the READMEs and `docs/DEPLOYMENT.md`.

## [0.10.0] - 2026-10-09

### Added
- Backend home page at `/`: a dashboard with the API version, database status, demo mode and the number of requests per status, links to the API documentation (`/docs`), the OpenAPI specification, the health check and the request list, and a "Siirry sovellukseen" link to the frontend. The footer tells it is a demo application powered by Laravel.
- `FRONTEND_URL` setting for the backend (`config('app.frontend_url')`), used by the home page link.

### Changed
- `/` returns HTML in the browser; API clients that ask for JSON still get the previous JSON with the API and docs links.

## [0.9.0] - 2026-10-09

### Added
- Dark mode. The app follows the system setting, and a button in the header switches between light and dark; the choice is remembered in the browser. The dark theme uses an emerald-green primary color, the light theme keeps sky blue.
- JalaOps logo: a gear with a check mark, used in the header, as the SVG favicon and as a wordmark in `docs/logo.svg` below the README title.
- Footer that tells this is a demo application, with a link to the API documentation and the app version. The docs link is set with `VITE_API_DOCS_URL`.
- Redesigned home page: a clearer introduction, shortcuts to the request list and a new request, and a short guide to priorities and statuses.
- A friendlier empty state on the request list.

### Changed
- The frontend is styled with Tailwind CSS (v4, through its Vite plugin) instead of hand-written CSS. Shared building blocks such as cards, buttons, badges and form fields are defined in `src/assets/main.css`.
- Refreshed look across the app with slate neutrals and the existing sky-blue primary color.
- On very narrow phones the header shows only the logo so the navigation fits.

## [0.8.0] - 2026-10-07

### Added
- Filter requests by status and/or priority: `GET /api/requests?status=open&priority=high` (#7). Invalid filter values return a Finnish validation error.
- Status and priority filters on the request list, with a button to clear them. The filters are kept in the address, so a filtered list survives a reload and can be shared as a link.

## [0.7.0] - 2026-10-07

### Added
- Production Docker image for the backend (`docker/production/Dockerfile`) with a start script, for hosting on Render.
- `DEMO_MODE` setting: `true` runs on demo data in SQLite that resets on every start, `false` uses the database configured with `DB_*`. `SEED_DEMO_DATA=true` seeds the demo requests once into an empty database.
- A notice in the app when it runs in demo mode, and a `demo` field in `/api/health`.
- `frontend/vercel.json` for hosting the frontend on Vercel: forwards `/api/*` to the backend and serves `index.html` for app routes.
- Deployment guide for Vercel and Render in `docs/DEPLOYMENT.md`.
- `DEMO_MODE`, `SEED_DEMO_DATA` and `MYSQL_ATTR_SSL_CA` in `backend/.env.example`.

### Changed
- Running the seeder again no longer duplicates the demo requests.

## [0.6.0] - 2026-10-07

### Added
- Create a request at `/requests/new` and edit one at `/requests/:id/edit`, including changing its status (#6).
- Reusable request form with title, description, priority, status and due date.
- The backend's Finnish validation errors are shown next to each field.
- "Uusi pyyntö" button on the request list.
- A "not found" message when editing a request that doesn't exist.

## [0.5.0] - 2026-10-07

### Added
- Request list at `/requests`: title, priority, status and due date in a table, with Finnish labels for priority and status (#5).
- Edit and delete actions for each request; deleting asks for confirmation first.
- Navigation between the home page and the request list.

### Changed
- New light, modern theme with a sky-blue primary color, cards and shared button and badge styles.
- Mobile and tablet friendly layout: on phones each request is shown as a card with large touch targets.

## [0.4.1] - 2026-10-07

### Fixed
- The backend container failed to start when new Composer packages had been added since `vendor/` was installed (for example Scramble in 0.4.0). It now runs `composer install` on every start, which does nothing when everything is already installed.

### Changed
- `./dev restart` recreates the containers instead of only restarting them, so startup steps and changes to `docker-compose.yml` take effect.

### Added
- `./dev rebuild` rebuilds the images and recreates the containers.

## [0.4.0] - 2026-10-07

### Added
- REST API for service requests: `GET /api/requests`, `GET /api/requests/{id}`, `POST /api/requests`, `PUT /api/requests/{id}` and `DELETE /api/requests/{id}` (#4).
- Validation for title, description, priority, status and due date, with Finnish error messages.
- Finnish 404 messages for missing requests and unknown API addresses.
- Interactive API documentation at `/docs`, and the OpenAPI specification at `/docs/api.json`.
- This changelog.

### Changed
- Requests are listed soonest due first; requests without a due date come last.
- The backend defaults to the Finnish locale.

## [0.3.0] - 2026-10-07

### Added
- `service_requests` table and `ServiceRequest` model with priority (`low`, `normal`, `high`) and status (`open`, `in_progress`, `completed`) (#3).
- Factory and a seeder with 15 Finnish demo requests, some of them always overdue.

### Removed
- Laravel's default test user from the database seeder.

## [0.2.0] - 2026-10-07

### Added
- Docker development environment with MariaDB, the Laravel backend and the Vue frontend: the whole app starts with `docker compose up` (#2).
- `./dev` helper script for common Docker commands.
- Database connection status in `/api/health`.

### Fixed
- Tests inside Docker always use an in-memory SQLite database instead of the MariaDB development database.

## [0.1.0] - 2026-10-07

### Added
- Laravel backend with a `/api/health` endpoint (#1).
- Vue 3 frontend with Vue Router that shows whether the API connection works.
- Vite dev proxy from the frontend to the backend API.
- README and environment configuration examples.

[Unreleased]: https://github.com/jussipalanen/jalaops/compare/v0.17.0...HEAD
[0.17.0]: https://github.com/jussipalanen/jalaops/compare/v0.16.0...v0.17.0
[0.16.0]: https://github.com/jussipalanen/jalaops/compare/v0.15.0...v0.16.0
[0.15.0]: https://github.com/jussipalanen/jalaops/compare/v0.14.1...v0.15.0
[0.14.1]: https://github.com/jussipalanen/jalaops/compare/v0.14.0...v0.14.1
[0.14.0]: https://github.com/jussipalanen/jalaops/compare/v0.13.0...v0.14.0
[0.13.0]: https://github.com/jussipalanen/jalaops/compare/v0.12.0...v0.13.0
[0.12.0]: https://github.com/jussipalanen/jalaops/compare/v0.11.1...v0.12.0
[0.11.1]: https://github.com/jussipalanen/jalaops/compare/v0.11.0...v0.11.1
[0.11.0]: https://github.com/jussipalanen/jalaops/compare/v0.10.0...v0.11.0
[0.10.0]: https://github.com/jussipalanen/jalaops/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/jussipalanen/jalaops/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/jussipalanen/jalaops/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/jussipalanen/jalaops/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/jussipalanen/jalaops/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/jussipalanen/jalaops/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/jussipalanen/jalaops/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/jussipalanen/jalaops/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/jussipalanen/jalaops/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/jussipalanen/jalaops/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/jussipalanen/jalaops/releases/tag/v0.1.0
