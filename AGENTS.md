# AGENTS.md

Instructions for AI coding agents working on JalaOps. `CLAUDE.md` imports this file, so all tools follow the same rules.

## Project

JalaOps is a demo application for managing maintenance and service requests: a Laravel REST API (`backend/`), a Vue 3 + Vite + Tailwind CSS frontend (`frontend/`) and MariaDB, run locally with Docker Compose. See README.md for the architecture and ROADMAP.md for the plan.

## Commands

Start everything with `docker compose up`. Backend commands run inside the backend container.

```bash
# Backend
docker compose exec backend vendor/bin/pint --test
docker compose exec backend php artisan test
docker compose exec backend composer audit

# Frontend (in frontend/)
npm run lint:check
npm test
npm run build
npm audit --audit-level=high
```

These are the same checks GitHub Actions runs (see "Continuous integration" in README.md).

## Language

- Project documentation, code comments and development instructions should be written in English.
- Source code identifiers should use English names.
- All user-facing application text must be in Finnish.
- Demo data should preferably be in Finnish where appropriate.
- Do not translate technical identifiers, database columns, API endpoints or class names into Finnish.

## Branches and pull requests

- Create one branch per GitHub issue from the latest `main`, named by type: `feature/…`, `fix/…`, `docs/…` or `chore/…`.
- Run the CI checks locally before opening a pull request (see "Continuous integration" in README.md); pull requests can't be merged while CI fails.
- Open a pull request when the work is ready. Never merge pull requests; the developer reviews and merges them.
- Commits and pull requests are authored as the developer and must not mention AI tools.

## Versioning and changelog

- Follow semantic versioning (`MAJOR.MINOR.PATCH`). Until `1.0.0`, every merged feature is a new minor version (`0.x.0`) and a fix-only release is a patch (`0.x.y`). `1.0.0` is the release defined in ROADMAP.md's Definition of Done.
- Every pull request that changes behaviour adds its entry to `CHANGELOG.md` under the next version (Added, Changed, Fixed, Removed), and updates the version in `frontend/package.json` and the API docs version in `backend/config/scramble.php`.
- After a pull request is merged, tag the merge commit on `main` with an annotated tag such as `v0.4.0`.
- Then publish a GitHub release for that tag, titled with the plain version number (`0.4.0`), with the version's `CHANGELOG.md` section as the notes and a "Full changelog" compare link to the previous tag.
- Every pull request that adds a version also adds its compare link at the bottom of `CHANGELOG.md` and moves the `[Unreleased]` link to start from the new tag.

## Documentation

- Keep `README.md` (English) and `README.fi.md` (Finnish) in sync.
- Document new environment variables in `backend/.env.example` (and `docs/DEPLOYMENT.md` if they matter for hosting).

## Secrets and staging

- Never commit `.env` files or print secret values (API keys, tokens) in output. When checking settings, show variable names only.
- Stage only the files you changed (`git add <path>`); don't use `git add -A`, which can pick up the developer's own local edits.
