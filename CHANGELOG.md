# Changelog

All notable changes to JalaOps are listed here, newest first. Each version lists what was **Added**, **Changed**, **Fixed** and **Removed**.

Versions follow [semantic versioning](https://semver.org): until 1.0.0, a new minor version (0.**x**.0) is a feature milestone and a patch version (0.x.**y**) holds only fixes. 1.0.0 is the first complete release, as defined in [ROADMAP.md](ROADMAP.md). The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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

[Unreleased]: https://github.com/jussipalanen/jalaops/compare/v0.8.0...HEAD
[0.8.0]: https://github.com/jussipalanen/jalaops/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/jussipalanen/jalaops/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/jussipalanen/jalaops/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/jussipalanen/jalaops/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/jussipalanen/jalaops/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/jussipalanen/jalaops/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/jussipalanen/jalaops/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/jussipalanen/jalaops/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/jussipalanen/jalaops/releases/tag/v0.1.0
