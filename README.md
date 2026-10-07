# JalaOps

**English** | [Suomi](README.fi.md)

[Overview](#overview) · [Tech stack](#tech-stack) · [Getting started](#getting-started) · [Architecture](#architecture) · [API](#api) · [Roadmap](#roadmap)

> **Status:** in early development. See the [roadmap](ROADMAP.md) and [issues](https://github.com/jussipalanen/jalaops/issues).

---

## Overview

JalaOps is a small operations management demo application for handling service requests.

A user can:

- view, create, edit and delete requests
- change a request's status
- filter requests by status and priority
- view a simple dashboard

The application UI is in Finnish.

## Tech stack

| Layer    | Technology                     |
|----------|--------------------------------|
| Backend  | PHP, Laravel, REST API         |
| Frontend | Vue 3, Vite, Vue Router        |
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

Filters: `/api/requests?status=open`, `/api/requests?priority=high`

## Roadmap

The development plan is in [ROADMAP.md](ROADMAP.md).
