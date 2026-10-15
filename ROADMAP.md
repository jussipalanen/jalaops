# JalaOps – Development Roadmap

## 1. Project Overview

Build a small operations management demo application called **JalaOps**.

The purpose of the project is to demonstrate a clean full-stack application using:

- Laravel
- Vue
- MariaDB
- REST API
- Docker
- Automated tests
- GitHub Actions

Keep the project intentionally small and easy to understand.

Do not over-engineer the application.

The application should demonstrate realistic business software development rather than being a large production SaaS platform.

---

## 2. Core Use Case

JalaOps manages simple operational service requests.

A user can:

- view requests
- create a request
- edit a request
- change its status
- filter requests
- view a small dashboard

Example request:

```text
Title: Replace air filter
Description: Replace the air filter in warehouse ventilation unit.
Priority: High
Status: Open
Due date: 2026-10-15
```

---

## 3. Technology Stack

### Backend

- PHP
- Laravel
- REST API
- Eloquent ORM
- Laravel validation
- PHPUnit or Pest

### Frontend

- Vue 3
- Vite
- Vue Router
- Axios or Fetch API

Use Pinia only if application state actually requires it.

### Database

- MariaDB

### Development

- Docker
- Docker Compose

### CI

- GitHub Actions

---

## 4. Repository Structure

Use a single GitHub repository.

Suggested structure:

```text
jalaops/
├── backend/
├── frontend/
├── docker/
├── docker-compose.yml
├── README.md
├── CLAUDE.md
└── .github/
    └── workflows/
```

Avoid unnecessary infrastructure.

The entire project should be easy to start locally with Docker.

---

# Development Roadmap

## Phase 1 – Project Setup

Create the initial development environment.

### Tasks

- Create Laravel backend
- Create Vue frontend
- Configure MariaDB
- Create Docker environment
- Configure frontend-to-backend API communication
- Add environment configuration
- Add basic README

The application should start with:

```bash
docker compose up
```

### Acceptance Criteria

- Laravel API is running
- Vue application is running
- MariaDB connection works
- Vue can successfully call Laravel API

---

## Phase 2 – Database Model

Create the main `requests` entity.

### Request fields

```text
id
title
description
priority
status
due_date
created_at
updated_at
```

### Priority values

```text
low
normal
high
```

### Status values

```text
open
in_progress
completed
```

Create:

- migration
- Eloquent model
- factory
- seeder

Add realistic demo data.

### Acceptance Criteria

Running database migrations and seeders should create example requests.

---

## Phase 3 – REST API

Implement REST endpoints for requests.

### Endpoints

```text
GET    /api/requests
GET    /api/requests/{id}
POST   /api/requests
PUT    /api/requests/{id}
DELETE /api/requests/{id}
```

Support optional filters:

```text
/api/requests?status=open

/api/requests?priority=high
```

Use Laravel request validation.

Return consistent JSON responses.

### Acceptance Criteria

The API must support:

- listing requests
- creating requests
- updating requests
- deleting requests
- filtering requests

---

## Phase 4 – Request List

Create the main Vue view.

Display requests in a simple table.

Example:

```text
Requests

Title                    Priority      Status
------------------------------------------------
Replace air filter       High          Open
Inspect warehouse door   Normal        In Progress
Replace office lights    Low           Completed
```

Display:

- title
- priority
- status
- due date
- edit action

Add filters for:

- status
- priority

### Acceptance Criteria

The frontend retrieves request data from the Laravel API and displays it correctly.

---

## Phase 5 – Create and Edit Request

Create a reusable request form.

Fields:

- title
- description
- priority
- status
- due date

Create views for:

```text
/requests/new

/requests/:id/edit
```

Display backend validation errors clearly.

### Acceptance Criteria

A user must be able to:

- create a request
- edit an existing request
- see validation errors

---

## Phase 6 – Simple Dashboard

Create a small dashboard.

Show only a few useful metrics.

Example:

```text
Open             8
In Progress      3
Completed       15
Overdue          2
```

The statistics can come from a dedicated backend endpoint.

Example:

```text
GET /api/dashboard
```

Response:

```json
{
  "open": 8,
  "in_progress": 3,
  "completed": 15,
  "overdue": 2
}
```

Do not add complex graphs unless they provide meaningful value.

---

## Phase 7 – Testing

Add a small but useful automated test suite.

### Backend tests

Test:

- request listing
- request creation
- request validation
- request updating
- filtering

### Frontend tests

Add only a few important frontend tests.

For example:

- request list renders API results
- request form validation works

Avoid excessive test coverage for this demo.

---

## Phase 8 – GitHub Actions

Create a basic CI workflow.

Run on:

- pull requests
- pushes to `main`

CI should run:

### Backend

```text
composer install
Laravel tests
```

### Frontend

```text
npm install
npm run build
npm run test
```

The pull request should fail if tests fail.

---

## Phase 9 – Documentation

Improve `README.md`.

Include:

### Project description

Explain what JalaOps is.

### Technology stack

List:

- Laravel
- Vue
- MariaDB
- Docker

### Installation

Example:

```bash
git clone ...
cd jalaops

docker compose up
```

### Architecture

Briefly explain:

```text
Vue
 ↓
Laravel REST API
 ↓
MariaDB
```

### API

Document the main API endpoints.

---

# GitHub Development Workflow

Development should be divided into small GitHub issues.

Suggested issues:

```text
#1 Initial project setup
#2 Docker and MariaDB environment
#3 Request database model
#4 Request REST API
#5 Request list UI
#6 Create and edit request
#7 Request filtering
#8 Dashboard
#9 Automated tests
#10 GitHub Actions and documentation
```

Each feature should use its own branch.

Example:

```text
feature/request-api
feature/request-list
feature/request-form
feature/dashboard
```

Development workflow:

```text
Issue
  ↓
Feature branch
  ↓
Implementation
  ↓
Tests
  ↓
Pull Request
  ↓
Human review
  ↓
Merge
```

Do not automatically merge pull requests.

The developer should review the code before merging.

---

# Claude Code Instructions

When implementing JalaOps:

1. Work on one GitHub issue at a time.
2. Do not implement future roadmap features unless they are required by the current task.
3. Keep implementations simple.
4. Avoid unnecessary abstractions.
5. Avoid adding libraries unless they solve a clear problem.
6. Add tests for important backend behaviour.
7. Keep controllers small.
8. Use Laravel validation.
9. Use Eloquent relationships and query scopes where appropriate.
10. Keep Vue components small and readable.
11. Do not introduce unnecessary global state.
12. Update documentation when architecture or setup changes.
13. Never automatically merge pull requests.
14. Stop after completing the requested issue and summarize the changes.

---

# Scope Limits

The first version should NOT include:

- multi-tenancy
- payment processing
- complex permissions
- real-time WebSockets
- Kubernetes
- microservices
- Elasticsearch
- advanced analytics
- complex notification systems
- large AI features
- mobile applications

These can be considered later only if there is a clear reason.

The goal is a **small, polished full-stack demo**, not a large enterprise platform.

---

# Definition of Done

JalaOps v1.0 is complete when a user can:

1. Open the dashboard.
2. View operational requests.
3. Filter requests.
4. Create a request.
5. Edit a request.
6. Change its status.
7. Delete a request.
8. Run the project locally with Docker.
9. Run automated tests.
10. See passing CI checks in GitHub.

At that point, stop adding features and release:

```text
v1.0.0
```

**Status:** done. Every point above works, and v1.0.0 was released on 13 October 2026. The phases were also extended with locations and a map, signatures, PDF and HTML downloads, export and import, and an Android app (versions 0.13–0.24, see CHANGELOG.md).

---

# Upcoming Features

Planned after the features above. Each is developed through its own GitHub issues, like the phases.

| Feature | Status |
|---|---|
| Android app (Google Play testing) | Steps 1–5 done in 0.24.0; Google Play submission is next. |
| Lähellä minua: nearest requests first on the map and the list ([#61](https://github.com/jussipalanen/jalaops/issues/61)) | Planned |
| Statistics and charts on the dashboard | Planned |
| Invoicing (laskutus) of the maintenance work: 1. Asiakkaat ([#63](https://github.com/jussipalanen/jalaops/issues/63)) | Done in 1.1.0 |
| Invoicing: 2. Työt ja tarvikkeet, work hours and materials on requests ([#64](https://github.com/jussipalanen/jalaops/issues/64)) | Done in 1.2.0 |
| Invoicing: 3. Laskut, invoices from completed requests, with a PDF and export to accounting software ([#65](https://github.com/jussipalanen/jalaops/issues/65)) | Done in 1.3.0 |
| Backend on Google Cloud Run: always on at a low cost, deployed automatically from `main` ([#69](https://github.com/jussipalanen/jalaops/issues/69)) | Done in 1.4.0 |
| Mobile app downloads: Android download and iPhone install steps, with icons and the version, on the About page and in the footer | Done in 1.5.0 |

Invoicing hands finished invoices to accounting software; the app doesn't process payments (see Scope Limits).

**Ideas, not scheduled yet:** a daily route through the open requests, pin clustering on the map, bulk editing of selected requests, Excel (.xlsx) import, calendar (.ics) export, photos on requests, activity history, recurring maintenance, email reminders.

## Android App (Google Play Testing)

Goal: JalaOps as an Android app that testers install from Google Play, built with **one command**.

**Status:** steps 1–5 are done (version 0.24.0): `./dev android` builds the app, and each GitHub release gets a `jalaops.apk`. Step 6, Google Play, is next.

### Approach

Wrap the existing web app in a **Trusted Web Activity (TWA)** with Google's [Bubblewrap](https://github.com/GoogleChromeLabs/bubblewrap) tool. A TWA is a real Android app that shows the web app full screen through Chrome, without the browser's address bar. It suits JalaOps because:

- the app is already responsive and works on phones
- it already has a web app manifest (`site.webmanifest`), app icons and a theme colour
- the app and the web version stay the same code; publishing the web app updates the Android app too

**Alternative: [Capacitor](https://capacitorjs.com/)** bundles the built web app inside a native Android project and gives access to native features (camera, files, push notifications). Choose it only if such features are needed; it means building and publishing a new app version for every change.

### The Command

```bash
./dev android
```

- Runs Bubblewrap in a Docker container with Java and the Android SDK, so nothing has to be installed on the developer's machine.
- Reads its settings from `android/twa-manifest.json` (app name, package id `com.jussialanen.jalaops`, start address, colours, icons; generated from `site.webmanifest`).
- Produces `android/app-release-bundle.aab` (for Google Play) and `android/app-release-signed.apk` (to install directly on a test phone).
- Signs both with an upload key. The key file and its passwords stay outside the repository (environment variables locally, GitHub secrets in CI).

### Steps

1. **Installable web app:** add a service worker with an offline page ("Ei verkkoyhteyttä"), so the app meets Chrome's installability rules. Check with Lighthouse.
2. **Digital Asset Links:** serve `/.well-known/assetlinks.json` from the frontend with the SHA-256 fingerprints of both the upload key (for the downloadable `.apk`) and Google Play's app signing key (for the Play version), so Android trusts the app to open the site without the address bar.
3. **Bubblewrap project:** `android/twa-manifest.json` and the `./dev android` command; document it in the READMEs.
4. **Signing:** create the upload key (Play App Signing keeps the final key at Google); document how to store and back it up.
5. **APK download for testing:** a GitHub Actions workflow builds the app for each release tag and attaches `jalaops.apk` (and the `.aab`) to the GitHub release. It can also be started by hand from the Actions tab, with the files downloadable from the run. To test on a phone: open the release page on the phone, download `jalaops.apk` and install it (Android asks once to allow installing apps from the browser). The READMEs get a short "Install on Android" guide.
6. **Google Play testing:**
   - Create a Google Play Console developer account (one-time registration fee, USD 25).
   - Create the app, fill in the store listing, privacy policy, content rating and data safety form.
   - Upload the `.aab` to the **Internal testing** track (up to 100 testers by email; available within minutes).
   - Move to **Closed testing**. New personal developer accounts must run a closed test with at least 12 testers for 14 days before the app can be published to everyone.

### Acceptance Criteria

- `./dev android` builds a signed `.aab` and `.apk` on a clean machine with only Docker installed.
- Each release on GitHub has a `jalaops.apk` that can be downloaded and installed directly on a phone.
- The `.apk` installs on an Android phone and opens JalaOps full screen, without an address bar.
- The app is available to testers through Google Play's internal testing track.
- Updating the web app updates the Android app without a new release.

