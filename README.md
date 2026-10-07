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
docker compose up
```

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
└── docker-compose.yml
```

## API

| Method | Endpoint             | Description                 |
|--------|----------------------|-----------------------------|
| GET    | `/api/requests`      | List requests               |
| GET    | `/api/requests/{id}` | Get a single request        |
| POST   | `/api/requests`      | Create a request            |
| PUT    | `/api/requests/{id}` | Update a request            |
| DELETE | `/api/requests/{id}` | Delete a request            |
| GET    | `/api/dashboard`     | Request counts by status    |

Filters: `/api/requests?status=open`, `/api/requests?priority=high`

## Roadmap

The development plan is in [ROADMAP.md](ROADMAP.md).
