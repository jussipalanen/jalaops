## Language

- Project documentation, code comments and development instructions should be written in English.
- Source code identifiers should use English names.
- All user-facing application text must be in Finnish.
- Demo data should preferably be in Finnish where appropriate.
- Do not translate technical identifiers, database columns, API endpoints or class names into Finnish.

## Branches and pull requests

- Create one branch per GitHub issue from the latest `main`, named by type: `feature/…`, `fix/…`, `docs/…` or `chore/…`.
- Open a pull request when the work is ready. Never merge pull requests; the developer reviews and merges them.
- Commits and pull requests are authored as the developer and must not mention AI tools.

## Versioning and changelog

- Follow semantic versioning (`MAJOR.MINOR.PATCH`). Until `1.0.0`, every merged feature is a new minor version (`0.x.0`) and a fix-only release is a patch (`0.x.y`). `1.0.0` is the release defined in ROADMAP.md's Definition of Done.
- Every pull request that changes behaviour adds its entry to `CHANGELOG.md` under the next version (Added, Changed, Fixed, Removed), and updates the version in `frontend/package.json` and the API docs version in `backend/config/scramble.php`.
- After a pull request is merged, tag the merge commit on `main` with an annotated tag such as `v0.4.0`.
