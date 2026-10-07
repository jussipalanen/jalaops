# JalaOps

A small operations management demo application for handling service requests. The user interface is in Finnish.

See [ROADMAP.md](ROADMAP.md) for the development plan.

## Technology stack

- **Backend:** PHP 8.3+, Laravel 13 (REST API) in `backend/`
- **Frontend:** Vue 3, Vite and Vue Router in `frontend/`
- **Database:** MariaDB (Docker setup comes in a later issue; SQLite is used locally until then)

## Architecture

```text
Vue (frontend, port 5173)
 ↓  /api/* via the Vite dev proxy
Laravel REST API (backend, port 8000)
 ↓
Database
```

The frontend calls relative `/api/...` URLs. In development the Vite dev server forwards them to Laravel, so no CORS setup is needed. The target is set with `API_PROXY_TARGET` in `frontend/.env` (default `http://localhost:8000`).

## Local development

Requirements: PHP 8.3+, Composer, Node.js 22.18+ and npm.

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

The API runs at http://localhost:8000.

### Frontend

```bash
cd frontend
npm install
npm run dev
```

The app runs at http://localhost:5173. The home page shows whether the API connection works.

## Tests

```bash
cd backend && php artisan test
cd frontend && npm run test
```

## API

| Method | Endpoint      | Description      |
|--------|---------------|------------------|
| GET    | `/api/health` | API health check |
