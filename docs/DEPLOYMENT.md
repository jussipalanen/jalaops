# Deployment: Vercel and Render (free plans)

JalaOps can be hosted for free with the frontend on **Vercel** and the backend on **Render**:

```text
Browser
  ↓
Vercel: Vue app (static files)
  ↓  /api/* is forwarded by a rewrite in frontend/vercel.json
Render: Laravel API (Docker image from docker/production/Dockerfile)
  ↓
SQLite demo data (DEMO_MODE=true)  or  your own MySQL/MariaDB (DEMO_MODE=false)
```

Because Vercel forwards `/api/*` to Render, the browser talks to a single address and no CORS setup is needed.

## Data modes

The backend's data is chosen with one environment variable on Render:

| `DEMO_MODE` | Data | Database needed |
|---|---|---|
| `true` (default) | Fresh demo data in SQLite on every start. Visitors can create, edit and delete, but the changes disappear when the server restarts. | No |
| `false` | The database configured with the `DB_*` variables. Data is kept. | Yes |

In demo mode the app shows the visitor a notice that changes are not kept permanently.

> On Render's free plan, a service that gets no traffic for 15 minutes is stopped and starts again on the next request. In demo mode this also resets the demo data, which keeps a public demo tidy.

## 1. Backend on Render

1. Sign in at [render.com](https://render.com) with GitHub.
2. Choose **New → Web Service** and select the `jalaops` repository.
3. Fill in:

   | Setting | Value |
   |---|---|
   | Name | `jalaops-api` (this becomes the address `https://jalaops-api.onrender.com`) |
   | Language | Docker |
   | Branch | `main` |
   | Dockerfile Path | `./docker/production/Dockerfile` |
   | Docker Build Context Directory | `.` |
   | Instance Type | Free |
   | Health Check Path | `/api/health` |

4. Under **Environment Variables**, add:

   | Key | Value |
   |---|---|
   | `APP_URL` | `https://jalaops-api.onrender.com` (your service's address) |

   That is all demo mode needs. `DEMO_MODE=true` is the image's default, and an app key is generated on start if `APP_KEY` is not set.

5. Choose **Deploy Web Service**. The first build takes a few minutes.
6. Check that `https://jalaops-api.onrender.com/api/health` returns `{"status":"ok","database":"ok","demo":true}` and that the API documentation opens at `https://jalaops-api.onrender.com/docs`.

If the name `jalaops-api` was taken, Render gives the service a different address. Note it for the next step.

## 2. Frontend on Vercel

1. If your Render address is not `https://jalaops-api.onrender.com`, change the `destination` in [`frontend/vercel.json`](../frontend/vercel.json) to your address and push the change.
2. Sign in at [vercel.com](https://vercel.com) with GitHub.
3. Choose **Add New → Project** and import the `jalaops` repository.
4. Fill in:

   | Setting | Value |
   |---|---|
   | Root Directory | `frontend` |
   | Framework Preset | Vite (detected automatically) |
   | Build Command | `npm run build` (default) |
   | Output Directory | `dist` (default) |

5. Choose **Deploy**.
6. Open the Vercel address. The home page should say "API-yhteys toimii." and show the demo notice.

`frontend/vercel.json` also sends every other path to `index.html`, so addresses like `/requests/1/edit` work when opened directly or reloaded.

## 3. Optional: a real database (DEMO_MODE=false)

Render's free plan has no MySQL or MariaDB, so use a free MySQL service elsewhere, for example [Aiven's free MySQL plan](https://aiven.io/free-mysql-database). The app works with MySQL as well as MariaDB.

1. Create a free MySQL service at Aiven and open its **Overview** page for the connection details.
2. Download the service's **CA certificate**. Aiven requires SSL.
3. On Render, open the service and go to **Environment**:
   - Under **Secret Files**, add a file named `ca.pem` with the certificate's contents. Render makes it available at `/etc/secrets/ca.pem`.
   - Add these environment variables:

   | Key | Value |
   |---|---|
   | `DEMO_MODE` | `false` |
   | `APP_KEY` | Generate one with `./dev artisan key:generate --show` |
   | `DB_CONNECTION` | `mysql` |
   | `DB_HOST` | Host from Aiven |
   | `DB_PORT` | Port from Aiven |
   | `DB_DATABASE` | Database name from Aiven (e.g. `defaultdb`) |
   | `DB_USERNAME` | User from Aiven (e.g. `avnadmin`) |
   | `DB_PASSWORD` | Password from Aiven |
   | `MYSQL_ATTR_SSL_CA` | `/etc/secrets/ca.pem` |
   | `SEED_DEMO_DATA` | `true` to add the demo requests once (only into an empty table) |

4. Save. Render redeploys, and the start script runs the migrations against the database.
5. `/api/health` should now return `"demo":false`.

To return to demo mode, set `DEMO_MODE` back to `true`.

## Environment variables

| Key | Default in the image | Purpose |
|---|---|---|
| `DEMO_MODE` | `true` | `true`: demo data in SQLite, reset on every start. `false`: use the `DB_*` database. |
| `SEED_DEMO_DATA` | (unset) | With `DEMO_MODE=false`: `true` seeds the demo requests once, into an empty table. |
| `APP_KEY` | random on each start | Laravel's encryption key. Set a fixed one when using a real database. |
| `APP_URL` | (unset) | The backend's public address, used e.g. in the API documentation. |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Don't enable debug on a public server. |
| `DB_*`, `MYSQL_ATTR_SSL_CA` | (unset) | Database connection when `DEMO_MODE=false`. |
| `PORT` | set by Render | The port the server listens on (`10000` if unset). |

All keys are also listed in [`backend/.env.example`](../backend/.env.example).

## Updating

Both services deploy automatically when `main` changes. In demo mode every deploy also resets the demo data.

## Troubleshooting

- **The first request is slow or fails after a quiet period:** the free Render service is starting up, which takes about a minute. Reload the page.
- **"API-yhteys ei toimi." on Vercel:** check the `destination` in `frontend/vercel.json` and that `/api/health` answers on the Render address.
- **The backend fails with a database error in database mode:** check the `DB_*` values and that the secret file is named `ca.pem`.
