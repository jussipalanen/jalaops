# JalaOps

<img src="docs/logo.svg" alt="JalaOps logo" height="56">

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Vue](https://img.shields.io/badge/Vue-3-4FC08D?style=flat-square&logo=vuedotjs&logoColor=white)](https://vuejs.org)
[![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=flat-square&logo=vite&logoColor=white)](https://vite.dev)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MariaDB](https://img.shields.io/badge/MariaDB-11.8-003545?style=flat-square&logo=mariadb&logoColor=white)](https://mariadb.org)

**English** | [Suomi](README.fi.md)

[Overview](#overview) · [Tech stack](#tech-stack) · [Getting started](#getting-started) · [Architecture](#architecture) · [API](#api) · [Deployment](#deployment) · [Roadmap](#roadmap)

> **Status:** in early development. See the [roadmap](ROADMAP.md) and [issues](https://github.com/jussipalanen/jalaops/issues).

---

## Overview

JalaOps is a small operations management demo application for handling service requests.

A user can:

- view, create, edit and delete requests
- change a request's status
- filter requests by status and priority
- view a simple dashboard
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

### The `dev` helper

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

Tests always use an in-memory SQLite database, never the MariaDB development data.

## Architecture

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
├── frontend/            Vue application
├── docker/              Docker configuration
├── docker-compose.yml
└── dev                  Docker command shortcuts
```

## API

Interactive API documentation: http://localhost:8000/docs (OpenAPI JSON: `/docs/api.json`).

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET    | `/api/health`        | API and database health     |
| GET    | `/api/requests`      | List requests               |
| GET    | `/api/requests/{id}` | Get a single request        |
| POST   | `/api/requests`      | Create a request            |
| PUT    | `/api/requests/{id}` | Update a request            |
| DELETE | `/api/requests/{id}` | Delete a request            |
| GET    | `/api/dashboard`     | Request counts by status    |
| GET    | `/api/dashboard/ai-overview` | AI status overview (when enabled) |
| POST   | `/api/dashboard/ai-overview/refresh` | Generate a new AI status overview |

Filters: `/api/requests?status=open`, `/api/requests?priority=high`

## AI status overview

The home page can show **AI-tilannekatsaus**, a short Finnish summary of the requests with suggested actions and things to watch. Laravel computes the facts (counts, overdue requests, requests due within 7 days, open high-priority requests) and the AI only writes the text, so the numbers come from the database.

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

The key stays on the backend; the browser only talks to `/api/dashboard/ai-overview`. Overviews are cached until the requests change, refreshing is limited to three times per minute per visitor, and past the hourly limit the previous overview is shown. Request titles are sent to the AI provider, so use demo data only with free tiers that may use the data for training.

## Deployment

The app can be hosted for free with the frontend on Vercel and the backend on Render. By default the hosted backend runs on demo data that resets on every restart (`DEMO_MODE=true`), so no database service is needed. Set `DEMO_MODE=false` and the `DB_*` variables to use a real database.

Step-by-step guide: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Roadmap

The development plan is in [ROADMAP.md](ROADMAP.md).
