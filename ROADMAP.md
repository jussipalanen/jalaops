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