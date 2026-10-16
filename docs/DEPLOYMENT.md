# Deployment: Vercel, Google Cloud Run and Render

JalaOps runs with the frontend on **Vercel** and the backend on **Google Cloud Run** (always on, a few euros a month) or **Render** (free, sleeps when idle):

```text
Browser
  ↓
Vercel: Vue app (static files)
  ↓  /api/* is forwarded by a rewrite in frontend/vercel.json
Cloud Run or Render: Laravel API (Docker image from backend/Dockerfile)
  ↓
SQLite demo data (DEMO_MODE=true)  or  your own MySQL/MariaDB (DEMO_MODE=false)
```

Because Vercel forwards `/api/*` to the backend, the browser talks to a single address and no CORS setup is needed.

The backend image is built from the `backend` directory alone: `docker build backend`.

## Data modes

The backend's data is chosen with one environment variable:

| `DEMO_MODE` | Data | Database needed |
|---|---|---|
| `true` (default) | Fresh demo data in SQLite on every start. Visitors can create, edit and delete, but the changes disappear when the server restarts. | No |
| `false` | The database configured with the `DB_*` variables. Data is kept. | Yes |

In demo mode the app shows the visitor a notice that changes are not kept permanently.

## 1. Backend on Google Cloud Run (always on)

The service `jalaops-backend` runs in the `europe-north1` region (Finland) with these settings:

| Setting | Value | Why |
|---|---|---|
| Minimum instances | 1 | One instance is always running, so visitors never wait for a cold start. |
| Maximum instances | 1 | In demo mode the SQLite data lives inside the instance; a second instance would show different data. Also caps the cost. |
| CPU | 1, only during requests (the default) | An idle instance is billed at Cloud Run's much lower idle rate. Don't use `--no-cpu-throttling`: it bills the CPU all the time, several times more. |
| Memory | 512 MiB | Enough for the API and PDF exports. |
| Startup CPU boost | on | Extra CPU while the instance starts and seeds the demo data. |

**Costs:** with these settings, roughly 8–10 US dollars a month, almost all of it the idle instance (see [Cloud Run pricing](https://cloud.google.com/run/pricing)). Building the image uses Cloud Build's free monthly minutes, and the images are kept small by the cleanup policy below. With `--min-instances 0` the service fits in Cloud Run's free tier, but the first visit after a quiet period waits a few seconds while an instance starts and seeds the demo data.

In demo mode the demo data resets whenever the instance restarts: on every deploy, on every settings change, and now and then when Google moves the instance.

You need the [gcloud CLI](https://cloud.google.com/sdk/docs/install) and a Google Cloud project with a billing account linked. All commands run from the repository root.

### One-time setup

`gcloud` uses the project **ID**, which can differ from the project name shown in the Console. `gcloud projects list` shows both.

```bash
PROJECT_ID=my-project-id   # your project ID
REGION=europe-north1

gcloud config set project $PROJECT_ID
PROJECT_NUMBER=$(gcloud projects describe $PROJECT_ID --format='value(projectNumber)')
# Cloud Build builds the image with this account, and the service runs as it.
BUILD_SA=$PROJECT_NUMBER-compute@developer.gserviceaccount.com

gcloud services enable run.googleapis.com cloudbuild.googleapis.com artifactregistry.googleapis.com
gcloud projects add-iam-policy-binding $PROJECT_ID \
  --member=serviceAccount:$BUILD_SA --role=roles/run.builder
```

New permissions can take a few minutes to take effect.

### First deploy

The first deploy creates the service with the settings above. Answer `Y` when it asks to create an Artifact Registry repository for the images.

```bash
gcloud run deploy jalaops-backend \
  --source backend \
  --region $REGION \
  --allow-unauthenticated \
  --min-instances 1 \
  --max-instances 1 \
  --cpu 1 \
  --memory 512Mi \
  --cpu-boost \
  --set-env-vars DEMO_MODE=true
```

Then tell the backend its own address and the frontend's:

```bash
URL=$(gcloud run services describe jalaops-backend --region $REGION --format='value(status.url)')
gcloud run services update jalaops-backend --region $REGION \
  --update-env-vars APP_URL=$URL,FRONTEND_URL=https://jalaops-demo.jussialanen.com
curl $URL/api/health
```

The health check should return `{"status":"ok","database":"ok","demo":true}`, and the API documentation opens at `$URL/docs`.

Keep only the three newest images, so storage stays in Artifact Registry's free 0.5 GB:

```bash
cat > cleanup-policy.json <<'EOF'
[
  {"name": "keep-newest", "action": {"type": "Keep"}, "mostRecentVersions": {"keepCount": 3}},
  {"name": "delete-older", "action": {"type": "Delete"}, "condition": {"tagState": "ANY"}}
]
EOF
gcloud artifacts repositories set-cleanup-policies cloud-run-source-deploy \
  --location $REGION --policy cleanup-policy.json --no-dry-run
rm cleanup-policy.json
```

Finally, set a budget alert in the Console under **Billing → Budgets & alerts** (for example 15 €). It emails you when costs go over the amount; it doesn't stop the service.

### Manual deploys

Deploy the code in your working copy:

```bash
./dev deploy
```

It runs `gcloud run deploy jalaops-backend --source backend --region europe-north1` in gcloud's active project and prints the project first, so you can check it's the right one. The service keeps its settings and environment variables. Extra arguments go to `gcloud run deploy`, for example `./dev deploy --quiet`.

### Automatic deploys from main

The [deploy workflow](../.github/workflows/deploy.yml) deploys the backend on every push to `main` that changes `backend/`, after the CI checks pass. It can also be started by hand from the Actions tab (**Deploy backend → Run workflow**). It signs in to Google Cloud with Workload Identity Federation, so no Google key is stored in GitHub, and only this repository's `main` branch can use it.

Set it up once, with the same `PROJECT_ID`, `PROJECT_NUMBER` and `BUILD_SA` as above:

```bash
REPO=jussipalanen/jalaops
DEPLOYER=github-deployer@$PROJECT_ID.iam.gserviceaccount.com

gcloud services enable iamcredentials.googleapis.com sts.googleapis.com

# The account the workflow deploys as.
gcloud iam service-accounts create github-deployer --display-name="GitHub Actions deployer"
for role in roles/run.sourceDeveloper roles/serviceusage.serviceUsageConsumer; do
  gcloud projects add-iam-policy-binding $PROJECT_ID --member=serviceAccount:$DEPLOYER --role=$role
done
gcloud iam service-accounts add-iam-policy-binding $BUILD_SA \
  --member=serviceAccount:$DEPLOYER --role=roles/iam.serviceAccountUser

# Let GitHub Actions in this repository's main branch act as that account.
gcloud iam workload-identity-pools create github --location=global --display-name="GitHub Actions"
gcloud iam workload-identity-pools providers create-oidc jalaops \
  --location=global --workload-identity-pool=github \
  --issuer-uri=https://token.actions.githubusercontent.com \
  --attribute-mapping=google.subject=assertion.sub,attribute.repository=assertion.repository,attribute.ref=assertion.ref \
  --attribute-condition="assertion.repository == '$REPO' && assertion.ref == 'refs/heads/main'"
gcloud iam service-accounts add-iam-policy-binding $DEPLOYER \
  --role=roles/iam.workloadIdentityUser \
  --member="principalSet://iam.googleapis.com/projects/$PROJECT_NUMBER/locations/global/workloadIdentityPools/github/attribute.repository/$REPO"

# Tell the workflow where to deploy (repository variables, not secrets: none of these is secret).
gh variable set GCP_PROJECT_ID --body $PROJECT_ID
gh variable set GCP_SERVICE_ACCOUNT --body $DEPLOYER
gh variable set GCP_WORKLOAD_IDENTITY_PROVIDER \
  --body projects/$PROJECT_NUMBER/locations/global/workloadIdentityPools/github/providers/jalaops
```

Until the variables are set, the workflow is skipped. GitHub Actions is free for public repositories.

### Settings and secrets

Change a setting or an environment variable without deploying new code (this starts a new instance):

```bash
gcloud run services update jalaops-backend --region $REGION --update-env-vars SELLER_NAME="Oma Yritys Oy"
gcloud run services update jalaops-backend --region $REGION --min-instances 0
```

Keep API keys in Secret Manager instead of plain environment variables. For example, for the **AI-tilannekatsaus** (see the [README](../README.md#ai-status-overview)):

```bash
gcloud services enable secretmanager.googleapis.com
read -rs KEY && printf %s "$KEY" | gcloud secrets create gemini-api-key --data-file=-   # paste the key, press Enter
gcloud secrets add-iam-policy-binding gemini-api-key \
  --member=serviceAccount:$BUILD_SA --role=roles/secretmanager.secretAccessor
gcloud run services update jalaops-backend --region $REGION \
  --update-env-vars AI_INSIGHTS_ENABLED=true,AI_INSIGHTS_PROVIDER=gemini \
  --update-secrets GEMINI_API_KEY=gemini-api-key:latest
```

## 2. Backend on Render (free, sleeps when idle)

> On Render's free plan, a service that gets no traffic for 15 minutes is stopped and starts again on the next request. In demo mode this also resets the demo data, which keeps a public demo tidy.

1. Sign in at [render.com](https://render.com) with GitHub.
2. Choose **New → Web Service** and select the `jalaops` repository.
3. Fill in:

   | Setting | Value |
   |---|---|
   | Name | `jalaops-api` (this becomes the address `https://jalaops-api.onrender.com`) |
   | Language | Docker |
   | Branch | `main` |
   | Dockerfile Path | `./backend/Dockerfile` |
   | Docker Build Context Directory | `backend` |
   | Instance Type | Free |
   | Health Check Path | `/api/health` |

4. Under **Environment Variables**, add:

   | Key | Value |
   |---|---|
   | `APP_URL` | `https://jalaops-api.onrender.com` (your service's address) |
   | `FRONTEND_URL` | Your Vercel address, e.g. `https://jalaops.vercel.app` (optional: the backend home page links to it, and the QR code on a request's PDF and HTML opens the request there) |

   That is all demo mode needs. `DEMO_MODE=true` is the image's default, and an app key is generated on start if `APP_KEY` is not set.

   Optional, for the **AI-tilannekatsaus** on the dashboard (see the [README](../README.md#ai-status-overview)):

   | Key | Value |
   |---|---|
   | `AI_INSIGHTS_ENABLED` | `true` |
   | `AI_INSIGHTS_PROVIDER` | `gemini` or `puter` |
   | `GEMINI_API_KEY` | Your Gemini API key (provider `gemini`) |
   | `PUTER_AUTH_TOKEN` | Your Puter auth token (provider `puter`; needs a Puter subscription) |

   The key is only needed on Render; Vercel never sees it.

5. Choose **Deploy Web Service**. The first build takes a few minutes.
6. Check that `https://jalaops-api.onrender.com/api/health` returns `{"status":"ok","database":"ok","demo":true}` and that the API documentation opens at `https://jalaops-api.onrender.com/docs`.

If the name `jalaops-api` was taken, Render gives the service a different address. Note it for the next step.

A Render service created before the image moved to `backend/` needs the new **Dockerfile Path** and **Docker Build Context Directory** under **Settings → Build & Deploy**, or its next build fails.

## 3. Frontend on Vercel

1. Point the frontend at your backend's address (Cloud Run's `https://jalaops-backend-….run.app` or Render's `https://jalaops-api.onrender.com`): change the `destination` in [`frontend/vercel.json`](../frontend/vercel.json) and `VITE_API_DOCS_URL` in [`frontend/.env.production`](../frontend/.env.production), and push the change.
   Also set `VITE_SITE_URL` in the same file to the frontend's public address (your Vercel or own domain, no trailing slash): link previews use it for the page address and the preview image.
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

## 4. Optional: a real database (DEMO_MODE=false)

Neither Cloud Run nor Render's free plan includes MySQL or MariaDB, so use a MySQL service elsewhere, for example [Aiven's free MySQL plan](https://aiven.io/free-mysql-database). The app works with MySQL as well as MariaDB. With a real database, Cloud Run's maximum instances can be raised, because the data no longer lives in the instance.

1. Create a free MySQL service at Aiven and open its **Overview** page for the connection details.
2. Download the service's **CA certificate**. Aiven requires SSL.
3. Give the backend the certificate as the file `/etc/secrets/ca.pem`:
   - **Render:** open the service, go to **Environment → Secret Files** and add a file named `ca.pem` with the certificate's contents.
   - **Cloud Run:** store it in Secret Manager and mount it (the service account needs `roles/secretmanager.secretAccessor` on the secret, as in "Settings and secrets" above):

     ```bash
     gcloud secrets create mysql-ca --data-file=ca.pem
     gcloud run services update jalaops-backend --region $REGION --update-secrets /etc/secrets/ca.pem=mysql-ca:latest
     ```

4. Add these environment variables (on Cloud Run, keep `DB_PASSWORD` and `APP_KEY` in Secret Manager as above):

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

5. The service restarts, and the start script runs the migrations against the database.
6. `/api/health` should now return `"demo":false`.

To return to demo mode, set `DEMO_MODE` back to `true`.

## Android app

The Android app opens the frontend's address (`host` in [`android/twa-manifest.json`](../android/twa-manifest.json), now `jalaops-demo.jussialanen.com`). If the frontend moves to another address, change `host` and the URLs in that file, rebuild the app (`./dev android`) and publish a new version. The frontend must keep serving `/.well-known/assetlinks.json` (from `frontend/public`), or the app shows an address bar. See "Android app" in the [README](../README.md#android-app).

## Environment variables

| Key | Default in the image | Purpose |
|---|---|---|
| `DEMO_MODE` | `true` | `true`: demo data in SQLite, reset on every start. `false`: use the `DB_*` database. |
| `SEED_DEMO_DATA` | (unset) | With `DEMO_MODE=false`: `true` seeds the demo requests once, into an empty table. |
| `APP_KEY` | random on each start | Laravel's encryption key. Set a fixed one when using a real database. |
| `APP_URL` | (unset) | The backend's public address, used e.g. in the API documentation. |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Don't enable debug on a public server. |
| `DB_*`, `MYSQL_ATTR_SSL_CA` | (unset) | Database connection when `DEMO_MODE=false`. |
| `PORT` | set by Cloud Run and Render | The port the server listens on (`10000` if unset). |
| `DEFAULT_VAT_RATE` / `DEFAULT_HOURLY_RATE` | `25.5` / `65` | Pre-filled VAT percent and hourly rate (euros, without VAT) for new rows of work and materials. Existing rows keep their own values. |
| `SELLER_NAME`, `SELLER_BUSINESS_ID`, `SELLER_STREET_ADDRESS`, `SELLER_POSTAL_CODE`, `SELLER_CITY`, `SELLER_EMAIL` | made-up demo values | The seller shown on invoices. |
| `SELLER_IBAN` / `SELLER_BIC` | (unset) | The bank account on invoices and in their payment QR code; without it the invoice says the account isn't set and has no payment QR code. |
| `FIRST_INVOICE_NUMBER` | `1001` | The number of the first invoice; later invoices continue from the highest number. |
| `DISPLAY_TIMEZONE` | `Europe/Helsinki` | Time zone of the times in downloaded PDF and HTML files (the app stores UTC). |
| `SHOW_SYSTEM_DETAILS` | `false` | `true` shows the PHP, Laravel and database versions on the "Tietoja" page (`/api/system`). Off on public servers so exact versions aren't advertised. |
| `NOMINATIM_USER_AGENT` | `JalaOps demo (https://github.com/jussipalanen/jalaops)` | Identifies the app to Nominatim and Photon (address lookup and suggestions), as their usage policies require. Set it to your own app name and contact URL or email. |

All keys are also listed in [`backend/.env.example`](../backend/.env.example).

## Updating

- **Cloud Run:** the deploy workflow deploys every push to `main` that changes `backend/`, after CI passes; `./dev deploy` deploys by hand.
- **Render and Vercel** deploy automatically when `main` changes.

In demo mode every deploy also resets the demo data.

## Troubleshooting

- **`Project '…' not found or permission denied` from gcloud:** use the project ID, not its name (`gcloud projects list`), and check that a billing account is linked (`gcloud billing projects describe $PROJECT_ID`). A project that was just restored or created can take a few minutes to work.
- **Cloud Run: "Build failed because the default service account is missing required IAM permissions":** give the build account the Cloud Run Builder role (see "One-time setup") and try again after a few minutes.
- **The deploy workflow fails at the Google sign-in:** check the three `GCP_*` repository variables and that the workflow ran on `main`.
- **The first request is slow or fails after a quiet period (Render):** the free Render service is starting up, which takes about a minute. Reload the page.
- **"API-yhteys ei toimi." on Vercel:** check the `destination` in `frontend/vercel.json` and that `/api/health` answers on the backend's address.
- **The backend fails with a database error in database mode:** check the `DB_*` values and that the certificate is at `/etc/secrets/ca.pem`.
