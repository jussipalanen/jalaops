# Changelog

All notable changes to JalaOps are listed here, newest first. Each version lists what was **Added**, **Changed**, **Fixed** and **Removed**.

Versions follow [semantic versioning](https://semver.org): until 1.0.0, a new minor version (0.**x**.0) is a feature milestone and a patch version (0.x.**y**) holds only fixes. 1.0.0 is the first complete release, as defined in [ROADMAP.md](ROADMAP.md). The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [1.10.0] - 2026-10-19

### Changed
- Composer installs module packages into `backend/modules/packages/<name>/`, next to the app's own modules, instead of `vendor/`. Git ignores the folder, so only `composer.json` and `composer.lock` are committed. A small Composer plugin in this repository (`backend/composer-plugins/module-installer`) does it, with no third-party dependency. After pulling, run `./dev composer install` to move installed packages there.

## [1.9.0] - 2026-10-19

### Added
- **Työaikalaskuri** (`worktime`), the first module from a package of its own: the hours worked, overtime past 8 hours and labour cost from a shift's start and end times and breaks, also over midnight. It's installed with Composer from [jalaops-module-worktime](https://github.com/jussipalanen/jalaops-module-worktime) at version 1.1.0.

## [1.8.0] - 2026-10-19

### Added
- Python modules from Composer packages: a module can live in a git repository of its own (on GitHub or your own server) as a package of type `jalaops-module`, required in `backend/composer.json`. Its git tags are its versions and `composer.lock` pins the commit, so installing or updating a module is still a reviewed pull request. A package holds one module at its root (named after the package, `jalaops/worktime` is `worktime`) or a folder of modules. How to make one is in [`backend/modules/README.md`](backend/modules/README.md#modules-from-composer-packages).
- `MODULES_PACKAGES` setting (default `true`) to leave installed module packages out; the backend's tests leave them out.
- `modules:list` shows where each module comes from: `modules`, or the package and its installed version.
- `./dev modules:search` lists the GitHub repositories with the topic `jalaops-module` and how to install each.
- `git` in the backend's production image, so Composer can install packages from plain git repositories.

## [1.7.0] - 2026-10-19

### Added
- Python modules (`backend/modules`): small Python programs that Laravel runs as separate processes, JSON in on stdin and JSON out on stdout. Modules come from the code (pull requests), never from uploads; each gets only `PATH` and `LANG`, not the backend's API keys, and is stopped after its timeout (at most 30 s). A guide for writing them is in [`backend/modules/README.md`](backend/modules/README.md).
- Two modules: **ALV-laskuri** (`vat-calculator`) calculates VAT and the gross price, rounded to cents, and **Hei maailma** (`hello`) greets and calculates the sum, average and median of numbers, with its own view and a bar chart.
- **Moduulit** on the Työkalut page: the modules and whether each is on (**Käytössä** / **Pois käytöstä**), with a page for each enabled one at `/tools/modules/{id}`. The page is drawn from the form and results in the module's `module.json`, or is the module's own `view.html` in a sandboxed frame that can't reach the app or the network and runs the module through `jalaops.run(input)`. A view can use CSS and JS files from the module's folder (`assets`), such as copies of libraries.
- Modules are on or off by `"enabled"` in `module.json`; `php artisan modules:enable` and `modules:disable` override it. `modules:list` and `modules:run` list and run them on the command line.
- API: `GET /api/modules`, `GET /api/modules/{id}` and `POST /api/modules/{id}/run`. Running is allowed only from the app's own pages, at most ten times per minute per visitor, with an input of at most 10 kB.
- `MODULES_PATH` and `PYTHON_BINARY` settings, and `python3` in the backend's Docker images.
- `./dev` commands for the Cloud Run backend: `gcloud:login`, `gcloud:status` (address, revision and the names of the environment variables), `gcloud:logs` and `gcloud:set-env`.
- `./dev` commands for the Vercel frontend: `vercel:login` (also links the folder), `vercel:deploy` (a preview, or production with `--prod`), `vercel:status`, `vercel:logs` and `vercel:env` (names only).
- Their settings (`GCP_PROJECT_ID`, `CLOUD_RUN_REGION`, `CLOUD_RUN_SERVICE`, `VERCEL_PROJECT`, `VERCEL_SCOPE`) come from the environment or `~/.jalaops/deploy.env`, outside the repository.

### Changed
- `./dev deploy` deploys to `GCP_PROJECT_ID` (or `PROJECT_ID`), which is now required, instead of gcloud's active project, so it can't go to the wrong project by accident. The `./dev` gcloud commands also make it gcloud's active project when another one is active.

## [1.6.2] - 2026-10-19

### Fixed
- The AI-tilannekatsaus answers only the app's own pages (`FRONTEND_URL`, `APP_URL` and the optional `ALLOWED_ORIGINS`), recognised by the browser's `Origin` or `Referer` header. Requests from other websites or without either header get 403, so they can't use up the hourly AI limit. Any page is allowed when `APP_ENV=local`.
- CORS allows only the same addresses instead of every website.
- The rate limits (AI refresh, address search, import) apply to each visitor again: behind Cloud Run's and Vercel's proxies, every visitor had the same address and shared one limit, so the AI overview's background refresh could fail with 429.

## [1.6.1] - 2026-10-16

### Changed
- The Mobiilisovellukset page is switched off for now, while Google Play Protect warns about the APK downloaded from GitHub: its links in the footer and on the About page are hidden, and `/mobile-apps` opens the home page. `VITE_MOBILE_APPS_PAGE=true` at build time turns it back on. The APK is still attached to each GitHub release.

## [1.6.0] - 2026-10-16

### Added
- QR codes for requests, easy to use on phones and tablets as well as computers, and a payment QR code for invoices that is off for now (#77).
- Payment QR code on invoices, off for now: `INVOICE_PAYMENT_QR=true` turns it on. An EPC QR code ("SEPA credit transfer") with the seller's IBAN, BIC and name, the amount and the reference as an RF creditor reference, which banking apps read to fill in the payment. It's on the invoice PDF and HTML next to the payment details, and shown only when `SELLER_IBAN` is set, the total is above zero and the invoice isn't paid.
- Maksutiedot on the invoice page: the recipient, IBAN, reference and amount, with **Kopioi** buttons for pasting into a banking app. With the payment QR code turned on, it shows the code too, and on a phone or tablet **Näytä maksukoodi** shows it on the whole screen, so the customer can scan it with their own phone.
- A QR code on the request PDF and HTML that opens the request in the app (`FRONTEND_URL/requests/{id}`).
- On the request page, **Avaa puhelimella** with the request's QR code on a computer; on a phone or tablet, **Jaa** opens the device's share menu (or **Kopioi linkki** copies the link) and **Näytä QR-koodi** shows the code on the whole screen.
- The full-screen QR code fits portrait and landscape, keeps the screen on where the browser allows it, and closes with **Sulje**, Escape or a tap outside the code. QR codes are always dark on white, also in the dark theme.
- `payment` in the invoice API: `recipient`, `iban`, `bic` and `qr`, the payment QR code's text (null when it isn't shown, as while it's off).
- `chillerlan/php-qrcode` backend dependency, which draws the QR codes in the PDF and HTML files.

### Changed
- The Mobiilisovellukset page uses the same QR code component and device check as the new QR codes.

## [1.5.0] - 2026-10-15

### Added
- Mobiilisovellukset page (`/mobile-apps`), linked from the footer and the About page. "Lataa Android-sovellus (APK)" downloads `jalaops.apk` from the newest GitHub release, with its version, size and date, and the iPhone and iPad section has the steps for adding JalaOps to the home screen in Safari. Both have the platform's icon; inside the installed app the download is hidden.
- On a phone, its own platform's card comes first and is marked "Tämä laite"; on a computer, a QR code opens the page on the phone.
- Turvallisuus on the same page in plain language: an official source, signed, no permissions. "Tekniset tiedot" has the build provenance, the signing key's SHA-256 fingerprint (from `assetlinks.json`) and the APK's SHA-256 checksum from GitHub. The READMEs tell how to check the file with `sha256sum` and `gh attestation verify`.
- `qrcode` frontend dependency, loaded only for the QR code.
- The [Android workflow](.github/workflows/android.yml) attaches a `SHA256SUMS` file to each release and creates a build provenance attestation for `jalaops.apk` and `jalaops.aab`, proving they were built from this repository.

### Changed
- The Android workflow removes the signing key from the runner even when the build fails.

## [1.4.1] - 2026-10-14

### Changed
- The Vercel frontend uses the backend on Cloud Run (`https://jalaops-backend-1097769575593.europe-north1.run.app`) instead of Render (#69): `/api/*` is forwarded there, and "API-dokumentaatio" links to its `/docs`.

## [1.4.0] - 2026-10-14

### Added
- The backend can run on Google Cloud Run (#69): the service `jalaops-backend` in `europe-north1`, always on with one instance and CPU billed only during requests, about 8–10 dollars a month. `docs/DEPLOYMENT.md` has the one-time setup, the first deploy, an image cleanup policy and a budget alert.
- The [deploy workflow](.github/workflows/deploy.yml) deploys the backend to Cloud Run on every push to `main` that changes `backend/`, after the CI checks pass. It signs in with Workload Identity Federation, so no Google key is stored in GitHub, and is skipped until the repository variables `GCP_PROJECT_ID`, `GCP_WORKLOAD_IDENTITY_PROVIDER` and `GCP_SERVICE_ACCOUNT` are set.
- `./dev deploy` deploys the backend to Cloud Run by hand with `gcloud`.

### Changed
- The production image moved into the backend: `backend/Dockerfile` with `backend/docker/start.sh`, built from the `backend` directory alone (`docker build backend`). On Render, set **Dockerfile Path** to `./backend/Dockerfile` and **Docker Build Context Directory** to `backend`.

## [1.3.0] - 2026-10-14

### Added
- Laskut, invoices from completed requests (#65), the third and last step of invoicing, linked from the main menu. "+ Uusi lasku" picks a customer and which of their billable requests (completed, with work or materials, not invoiced yet) go on the invoice; a completed request's details page has a "Luo lasku" shortcut.
- An invoice gets a running number (from 1001), a Finnish reference number (viitenumero) with its check digit, the invoice date and a due date from the customer's payment terms. It keeps copies of the customer's billing details and of the rows, with each request's delivery date, so later changes never alter it.
- Statuses Luonnos → Lähetetty → Maksettu (and back from paid to sent); a sent invoice past its due date shows as Erääntynyt. Only drafts can be deleted, which makes their requests billable again.
- The invoice page shows the rows by request, the VAT by rate and the amount to pay, and the invoice as a PDF or HTML page in Finnish invoice layout with the JalaOps logo: seller, customer, invoice number, dates, payment terms, reference number, VAT breakdown and payment details.
- "Vie kirjanpitoon": CSV (semicolons and decimal commas, for Excel) or JSON with one row per invoice and VAT rate, for accounting software.
- Laskutus on the dashboard: unpaid invoices with their sum, and overdue invoices.
- `GET/POST /api/invoices`, `GET/DELETE /api/invoices/{id}`, `PATCH /api/invoices/{id}/status`, `GET /api/invoices/{id}/export/pdf|html`, `GET /api/invoices/export`, `GET /api/customers/{id}/billable-requests`; the dashboard returns `invoices`.
- Seller and invoice settings: `SELLER_NAME`, `SELLER_BUSINESS_ID`, `SELLER_STREET_ADDRESS`, `SELLER_POSTAL_CODE`, `SELLER_CITY`, `SELLER_EMAIL`, `SELLER_IBAN`, `SELLER_BIC` and `FIRST_INVOICE_NUMBER` (made-up demo defaults).
- Two demo invoices: a sent one past its due date and a paid one.
- "Esikatsele PDF" and "Esikatsele HTML" open an invoice or a request in a new browser tab; "Lataa PDF" and "Lataa HTML" save it as before. In the request list the row's "Tiedosto" menu has the same four choices. The API takes `inline=1` on the export addresses.
- Page numbers on every page of invoice and request PDFs ("Lasku 1042 · Sivu 2/3").

### Changed
- An invoiced request's work and materials are locked; its details page links to the invoice.
- A customer with invoices can't be deleted.
- A row of work or materials can total at most 9 999 999,99 € (quantity × unit price), so it always fits on an invoice.
- The request list's "Lataa" menu is now "Tiedosto".

### Fixed
- Long descriptions or long words in a request PDF's work and materials table no longer push the price columns off the page; the columns have fixed widths and long words wrap.
- A frontend test could fail at random when an earlier test's address suggestion timer fired late; every test's components are now unmounted after it.

## [1.2.0] - 2026-10-13

### Added
- Työt ja tarvikkeet (#64): rows of work and materials on a request's details page, the second step of invoicing. Each row has a type (Työ or Tarvike), description, quantity and unit, unit price without VAT and VAT rate; the request shows the totals without VAT, the VAT and the total. A new work row starts at the default hourly rate and unit h, a material row with unit kpl; decimals can be typed with a comma.
- Amounts are stored as whole cents and VAT is rounded per row, as on a Finnish invoice, so totals never drift by a cent.
- The PDF and HTML download of a request lists the work and materials with their totals.
- `GET/POST /api/requests/{id}/items` and `PUT/DELETE /api/requests/{id}/items/{item}`; a request's details include `items` and `totals`.
- `DEFAULT_VAT_RATE` (25.5 %, Finland's general rate) and `DEFAULT_HOURLY_RATE` (65 €) settings for new rows.
- Work and materials on five completed or ongoing demo requests.

## [1.1.0] - 2026-10-13

### Added
- Asiakkaat, a customer register (#63), linked from the main menu: name, business ID (y-tunnus, with its check digit validated), contact person, email, phone, billing address, payment terms and notes. Search by name, business ID, contact person or city; each customer links to its requests. A customer with requests can't be deleted. The first step of invoicing.
- An "Asiakas" field on the request form, the customer on the details page, and an Asiakas filter on the request list (`?customer=3`).
- `GET/POST /api/customers`, `GET/PUT/DELETE /api/customers/{id}`; requests have `customer` (id and name) and accept `customer_id`; `GET /api/requests?customer=…`.
- Four made-up demo customers (example.com addresses, placeholder names) that the demo requests belong to by region.

### Fixed
- The Finnish validation messages had a misplaced rule and a few duplicate lines from earlier edits.

## [1.0.0] - 2026-10-13

The first complete release: every point of the Definition of Done in ROADMAP.md works.

### Added
- Dashboard counts on the home page (#8): how many requests are open, in progress, completed and overdue, under "Tilanne". Each count opens the request list filtered to the same requests, and the overdue count is highlighted when it isn't zero.
- `GET /api/dashboard`: the counts by status, overdue (not completed and due before today) and the total.

### Changed
- ROADMAP.md marks v1.0 as done and lists the planned features and ideas; AGENTS.md asks to keep the roadmap up to date and describes versioning from 1.0.0 on.
- The automated tests (#9) cover the API, the views and the components: about 100 backend and 100 frontend tests, run by CI on every pull request.

### Fixed
- The "Huoltopyyntöjen hallinta" heading on the home page had too little contrast in the light theme.
- The README listed `GET /api/dashboard` before it existed.

## [0.24.1] - 2026-10-12

### Fixed
- The Android workflow failed on GitHub's runners with Bubblewrap's "The provided androidSdk isn't correct" (#59), so releases didn't get `jalaops.apk` automatically. The workflow now prepares the runner's Android SDK the way Bubblewrap expects, like the `./dev android` image does.

## [0.24.0] - 2026-10-12

### Added
- Android app (#57): a Trusted Web Activity that opens JalaOps full screen without the browser's address bar, with shortcuts to a new request, the list and the map. `./dev android` builds `android/dist/jalaops.apk` (install on a phone) and `jalaops.aab` (Google Play) in Docker with Bubblewrap, so only Docker is needed; the app's version follows the frontend's. The first build creates an upload key in `~/.jalaops/android`, outside the repository.
- The [Android workflow](.github/workflows/android.yml) attaches `jalaops.apk` and `jalaops.aab` to every GitHub release and can be started by hand. `./dev android-secrets` stores the upload key in GitHub secrets for it.
- `/.well-known/assetlinks.json`, which lets the Android app open the site without an address bar.
- A service worker and an offline page ("Ei verkkoyhteyttä") when a page can't load without a connection. API calls, scripts and map tiles still always use the network.
- A maskable app icon and shortcuts in the web app manifest.
- "Install on Android" and "Build the app" sections in the READMEs.

## [0.23.0] - 2026-10-12

### Added
- A loading screen while the app starts (#55): the JalaOps logo, a spinner and "Ladataan JalaOpsia…". It's inline in `index.html`, so it appears before any JavaScript has loaded; it follows the light or dark theme, fades in only after a moment so fast loads don't flash it, keeps still when the user prefers reduced motion, and is replaced as soon as the app starts. Screen readers hear it as a status message.
- A Finnish message for browsers with JavaScript turned off.

## [0.22.0] - 2026-10-12

### Added
- Export and import (#51). The request list gets checkboxes and a "Vie" menu: CSV (opens in Excel: semicolons, UTF-8) or JSON (a full copy, including signatures), for the selected requests or all requests matching the filters. A new **Työkalut** page (`/tools`) in the main navigation exports all or filtered requests and imports a CSV or JSON file: it first checks the file and lists each invalid row with Finnish messages, and imports only when every row is valid. Imported requests are always new; Finnish labels ("Korkea"), dates (15.10.2026) and decimal commas are understood. Up to 1000 rows and 2 MB per file.
- `GET /api/requests/export?format=csv|json` (the list's filters, or `ids=1,2,3`) and `POST /api/requests/import` (`file`, `dry_run`; ten imports per minute per visitor). CSV cells starting with `=`, `+`, `-` or `@` get an apostrophe so Excel doesn't run them as formulas; import removes it again.
- A menu button (☰) for phones and tablets (below 1024 px): the navigation opens as a full-width panel under the header. It closes when a page opens, on Escape (focus returns to the button), on a click outside it and when keyboard focus leaves it.

### Fixed
- The open menu showed behind the map on the map, details and edit pages; maps now keep their layers inside themselves.
- Accessibility (WCAG AA, checked with axe-core): the primary buttons and the "Ops" of the logo had too little contrast in the light theme, the details page map was marked as an image although it has controls, and the file chooser's button followed the browser's language instead of Finnish.
- The download menu of the last row on the request list was cut off by the table's card.

## [0.21.0] - 2026-10-12

### Added
- Download a request as a PDF or an HTML file (#49): "Lataa PDF" and "Lataa HTML" on the details and edit pages, and a "Lataa" menu on each row of the request list. The document has the title, priority, status, description, due date, created and updated times, address, coordinates with OpenStreetMap and Street View links, and the sign-off with the signature image, name and time. Unsigned requests get empty lines for signing a printed copy by hand.
- The HTML file is self-contained (styles and signature built in) and opens offline; the PDF is A4, made with dompdf, a pure-PHP library.
- `GET /api/requests/{id}/export/pdf` and `/export/html` (downloads).
- `DISPLAY_TIMEZONE` setting (default `Europe/Helsinki`): times in downloaded files are shown in this time zone.

### Changed
- The backend Docker images (development and production) include PHP's GD extension, which dompdf needs to draw the signature. Rebuild the local backend image with `./dev rebuild`; Render rebuilds the production image on deploy.
- On phones, the actions of each request in the list form a 2×2 grid, so four buttons fit.

## [0.20.0] - 2026-10-12

### Added
- Sign-off when a request is completed (#47): with the status "Valmis", the form shows a "Kuittaus" section with the signer's name and a pad for drawing a signature with a finger, pen or mouse ("Tyhjennä" clears it). Both are optional, but a signature needs a name. The details page shows the signature, the signer and the time.
- The signature is stored as a PNG with the signer's name and time (`signed_by`, `signed_at`, `has_signature` in the API). The image is at `GET /api/requests/{id}/signature`, so request lists stay small. Saving without a new drawing keeps the signature, `completion_signature: null` removes it, and changing the status away from completed removes the sign-off. Only real PNG images are accepted.

## [0.19.0] - 2026-10-12

### Added
- "Tietoja sovelluksesta" page at `/about`, linked from the footer (#45): the frontend and API versions, environment, demo mode, time zone and server time, and a health check of the API, the database (with its response time) and the cache. With `SHOW_SYSTEM_DETAILS=true` it also shows the PHP, Laravel, database and Vue versions and the cache and queue drivers; the setting defaults to true only when `APP_ENV=local`, so the public demo doesn't show exact versions.
- `GET /api/system` with the same details.
- `SHOW_SYSTEM_DETAILS` in `backend/.env.example` and `docs/DEPLOYMENT.md`.

## [0.18.0] - 2026-10-12

### Added
- Sorting on the request list (#43): click a column header (title, priority, status, due date) to sort, and again to reverse it. Priority sorts high first on the first click. On phones a "Järjestys" select offers the same orders and "Uusimmat ensin"/"Vanhimmat ensin".
- Pagination on the request list: 10, 25, 50 or 100 requests per page (default 10), previous and next buttons and "Näytetään 1–10 / 15". Sort, page and page size are kept in the address; changing a filter goes back to the first page and keeps the sort.
- `GET /api/requests?sort=…&per_page=…&page=…`. `sort` takes `due_date`, `title`, `priority`, `status` or `created_at`, with `-` in front for descending; priority and status sort in their logical order. With `per_page` the response is paginated and has `links` and `meta`; without it, all matching requests are returned, as before.

### Changed
- Deleting a request on the list reloads the page, so it fills up from the next page.

## [0.17.0] - 2026-10-12

### Added
- Link preview and page metadata in the HTML source (`index.html`, #41): a Finnish title and description, application name, author and keywords, canonical address, Open Graph tags (title, description, locale, address and a 1200×627 preview image) and X (Twitter) card tags, so shared links show a card in Slack, Teams, LinkedIn, WhatsApp and similar services. Also structured data (schema.org `WebApplication`), a web app manifest with app icons, an Apple touch icon, `color-scheme` and `format-detection`.
- Each page has its own browser tab title, e.g. "Kartta – JalaOps"; the details page uses the request's title.
- A meta description on the backend home page.
- `VITE_SITE_URL` setting for the frontend's public address, used in the absolute preview URLs (set in `.env.production`).

### Changed
- The app stays out of search results: `noindex` is unchanged; the new tags only make shared links look better.

## [0.16.0] - 2026-10-12

### Added
- New requests on the map (#39): requests created in the last 7 days that aren't completed get a pulsing violet circle around their pin (a still circle when the user prefers reduced motion) and an "Uusi" badge in the map popup, the map's request list, the request list and the details page. The map panel shows how many of its requests are new and has a legend for the pin colours and the circle.

### Changed
- The demo requests get creation dates spread over the past weeks, so five of them are new instead of all.

## [0.15.0] - 2026-10-12

### Added
- Street View links for requests with a location (#37): "Street View ↗" in the map popup and "Avaa Street View" on the details page open Google Street View at the coordinates in a new tab. It uses a free Google Maps URL, so no API key is needed.

## [0.14.1] - 2026-10-12

### Fixed
- Search engines could index the hosted demo (#35). Every frontend and backend response now sends `X-Robots-Tag: noindex, nofollow` (Vercel headers and a Laravel middleware), and the frontend and the backend home page have a `noindex` meta tag. `robots.txt` still allows crawling, so crawlers can see the noindex.

## [0.14.0] - 2026-10-12

### Added
- Määräpäivä filter on the request list (#33): Myöhässä (not completed and due before today), Tänään, Seuraavat 7 päivää, Seuraavat 30 päivää, Ei määräpäivää, or Aikaväli… with Alkaen and Asti date fields. The choice is kept in the address like the other filters.
- `GET /api/requests?due=overdue|today|week|month|none` and `due_from`/`due_to` (YYYY-MM-DD, either end can be left open), with Finnish validation messages.

### Changed
- The search ("Haku", formerly "Osoitehaku") also matches the request title, on the list and the map.
- An invalid filter in the address shows the API's Finnish validation message on the request list.

## [0.13.0] - 2026-10-12

### Added
- Request locations (#31): street address, postal code, city and map coordinates on each request, all optional. Coordinates are given together or not at all.
- Kartta, a full-screen map view at `/map` with its own link in the main navigation. Requests with a location are shown as pins coloured by priority (completed requests are faded). Clicking a pin opens a popup with the title, priority, status, description, address, due date and a "Näytä tiedot" link. A side panel lists the requests on the map; on phones it opens from the bottom of the screen. `/map?request=12` focuses one request.
- The map uses OpenFreeMap's free vector basemap (built on OpenStreetMap data) with Google Maps-like colours, and a dark basemap in the dark theme. It needs no API key; MapLibre GL draws it inside Leaflet, and browsers without WebGL get OpenStreetMap's own tiles.
- Request details page at `/requests/:id` with all fields, a small map and Muokkaa/Poista buttons. Request titles in the list link to it.
- "Sijainti" section in the request form: address fields, a small map where clicking or dragging the pin sets the coordinates, and "Hae koordinaatit osoitteesta", which looks the address up with Nominatim (OpenStreetMap).
- Address suggestions while typing the street address. Choosing one fills in the address, postal code, city and coordinates. The suggestions come from Photon (OpenStreetMap), because Nominatim doesn't allow autocomplete.
- Osoitehaku on the request list and the map: search by street address, postal code or city. The search is kept in the address like the other filters (`?search=tampere`).
- `GET /api/requests?search=…`, `GET /api/geocode?q=…` and `GET /api/geocode/suggest?q=…`. Lookups and suggestions are cached and rate-limited per visitor, and Nominatim gets at most one call per second from the whole app.
- Finnish addresses with coordinates for the demo requests, around Finland from Helsinki to Rovaniemi. Seeding an existing database adds the locations to the demo requests that don't have one.
- "Kartalla" links in the request list for requests with a location.
- "API-etusivu" link in the footer to the backend home page.
- `NOMINATIM_USER_AGENT` setting in `backend/.env.example`.

### Changed
- The request list's filters are shared with the map (`useRequestFilters`).
- On phones the header shows only the logo, so the three navigation links and the theme button fit.

## [0.12.0] - 2026-10-09

### Added
- GitHub Actions CI (`.github/workflows/ci.yml`) for every pull request and push to `main` (#10). The backend job runs Pint, the Laravel tests and `composer audit`; the frontend job runs ESLint, the Vitest tests, the production build and `npm audit` (high and critical).
- Security workflow (`.github/workflows/security.yml`): CodeQL analysis of the JavaScript/Vue code and the workflows on every change and weekly, and dependency review that blocks pull requests adding dependencies with known high or critical vulnerabilities.
- Dependabot configuration for monthly grouped updates of Composer, npm, GitHub Actions and Docker images.
- `npm run lint:check` runs ESLint without fixing files, for CI.
- CI and Security badges and a "Continuous integration" section in the READMEs.
- `AGENTS.md` with the instructions for AI coding agents (project overview, check commands, language, branch, versioning and secret-handling rules). `CLAUDE.md` now imports it, so all tools share one set of rules.

### Fixed
- `php artisan test` failed because `phpunit.xml` listed a `tests/Unit` suite whose folder doesn't exist. The empty suite is removed.

## [0.11.1] - 2026-10-09

### Fixed
- The AI-tilannekatsaus no longer makes visitors wait for the AI after the requests change. The previous overview is shown right away with a "Päivitetään…" note, and the new one replaces it when ready. Gemini can take from a couple of seconds to over ten seconds per overview.
- The first visitor after a deploy or restart no longer waits for the first overview: the production image generates it in the background at startup (`php artisan ai-overview:warm`).

### Changed
- `GET /api/dashboard/ai-overview` returns the cached overview immediately with a new `outdated` flag instead of generating a new one. `POST …/refresh` generates it, and it reuses an overview generated for the same requests in the last minute, so simultaneous visitors cause only one AI call. AI calls run one at a time under a lock.
- The loading state says that the AI is writing the overview.
- Each AI call logs its duration, split into DNS lookup, connection, TLS and waiting for the reply.

## [0.11.0] - 2026-10-09

### Added
- AI-tilannekatsaus on the home page: a short Finnish summary of the requests with up to three suggested actions and things to watch, written by an AI model. Laravel computes the facts (counts, overdue requests, requests due within 7 days, open high-priority requests) and the AI only writes the text.
- Two AI providers, chosen with `AI_INSIGHTS_PROVIDER`: `gemini` (Google Gemini, `GEMINI_API_KEY`) and `puter` (Puter AI through its OpenAI-compatible API, `PUTER_AUTH_TOKEN`). The models can be changed with `GEMINI_MODEL` and `PUTER_MODEL`; the defaults are `gemini-3.1-flash-lite` (Gemini free tier) and `google/gemma-4-31b-it` (listed at $0 on Puter; server-side use of Puter needs a Puter subscription).
- `GET /api/dashboard/ai-overview` and `POST /api/dashboard/ai-overview/refresh`. Without `AI_INSIGHTS_ENABLED=true` and the provider's key, the endpoint returns `{"enabled": false}` and the card stays hidden.
- Overviews are cached until the requests, the provider or the model change (or after `AI_INSIGHTS_CACHE_MINUTES`). Refreshing is limited to three times per minute per visitor, and AI calls are capped per hour for the whole app (`AI_INSIGHTS_MAX_PER_HOUR`); past the cap the previous overview is shown.
- AI settings in `backend/.env.example`, the READMEs and `docs/DEPLOYMENT.md`.

## [0.10.0] - 2026-10-09

### Added
- Backend home page at `/`: a dashboard with the API version, database status, demo mode and the number of requests per status, links to the API documentation (`/docs`), the OpenAPI specification, the health check and the request list, and a "Siirry sovellukseen" link to the frontend. The footer tells it is a demo application powered by Laravel.
- `FRONTEND_URL` setting for the backend (`config('app.frontend_url')`), used by the home page link.

### Changed
- `/` returns HTML in the browser; API clients that ask for JSON still get the previous JSON with the API and docs links.

## [0.9.0] - 2026-10-09

### Added
- Dark mode. The app follows the system setting, and a button in the header switches between light and dark; the choice is remembered in the browser. The dark theme uses an emerald-green primary color, the light theme keeps sky blue.
- JalaOps logo: a gear with a check mark, used in the header, as the SVG favicon and as a wordmark in `docs/logo.svg` below the README title.
- Footer that tells this is a demo application, with a link to the API documentation and the app version. The docs link is set with `VITE_API_DOCS_URL`.
- Redesigned home page: a clearer introduction, shortcuts to the request list and a new request, and a short guide to priorities and statuses.
- A friendlier empty state on the request list.

### Changed
- The frontend is styled with Tailwind CSS (v4, through its Vite plugin) instead of hand-written CSS. Shared building blocks such as cards, buttons, badges and form fields are defined in `src/assets/main.css`.
- Refreshed look across the app with slate neutrals and the existing sky-blue primary color.
- On very narrow phones the header shows only the logo so the navigation fits.

## [0.8.0] - 2026-10-07

### Added
- Filter requests by status and/or priority: `GET /api/requests?status=open&priority=high` (#7). Invalid filter values return a Finnish validation error.
- Status and priority filters on the request list, with a button to clear them. The filters are kept in the address, so a filtered list survives a reload and can be shared as a link.

## [0.7.0] - 2026-10-07

### Added
- Production Docker image for the backend (`docker/production/Dockerfile`) with a start script, for hosting on Render.
- `DEMO_MODE` setting: `true` runs on demo data in SQLite that resets on every start, `false` uses the database configured with `DB_*`. `SEED_DEMO_DATA=true` seeds the demo requests once into an empty database.
- A notice in the app when it runs in demo mode, and a `demo` field in `/api/health`.
- `frontend/vercel.json` for hosting the frontend on Vercel: forwards `/api/*` to the backend and serves `index.html` for app routes.
- Deployment guide for Vercel and Render in `docs/DEPLOYMENT.md`.
- `DEMO_MODE`, `SEED_DEMO_DATA` and `MYSQL_ATTR_SSL_CA` in `backend/.env.example`.

### Changed
- Running the seeder again no longer duplicates the demo requests.

## [0.6.0] - 2026-10-07

### Added
- Create a request at `/requests/new` and edit one at `/requests/:id/edit`, including changing its status (#6).
- Reusable request form with title, description, priority, status and due date.
- The backend's Finnish validation errors are shown next to each field.
- "Uusi pyyntö" button on the request list.
- A "not found" message when editing a request that doesn't exist.

## [0.5.0] - 2026-10-07

### Added
- Request list at `/requests`: title, priority, status and due date in a table, with Finnish labels for priority and status (#5).
- Edit and delete actions for each request; deleting asks for confirmation first.
- Navigation between the home page and the request list.

### Changed
- New light, modern theme with a sky-blue primary color, cards and shared button and badge styles.
- Mobile and tablet friendly layout: on phones each request is shown as a card with large touch targets.

## [0.4.1] - 2026-10-07

### Fixed
- The backend container failed to start when new Composer packages had been added since `vendor/` was installed (for example Scramble in 0.4.0). It now runs `composer install` on every start, which does nothing when everything is already installed.

### Changed
- `./dev restart` recreates the containers instead of only restarting them, so startup steps and changes to `docker-compose.yml` take effect.

### Added
- `./dev rebuild` rebuilds the images and recreates the containers.

## [0.4.0] - 2026-10-07

### Added
- REST API for service requests: `GET /api/requests`, `GET /api/requests/{id}`, `POST /api/requests`, `PUT /api/requests/{id}` and `DELETE /api/requests/{id}` (#4).
- Validation for title, description, priority, status and due date, with Finnish error messages.
- Finnish 404 messages for missing requests and unknown API addresses.
- Interactive API documentation at `/docs`, and the OpenAPI specification at `/docs/api.json`.
- This changelog.

### Changed
- Requests are listed soonest due first; requests without a due date come last.
- The backend defaults to the Finnish locale.

## [0.3.0] - 2026-10-07

### Added
- `service_requests` table and `ServiceRequest` model with priority (`low`, `normal`, `high`) and status (`open`, `in_progress`, `completed`) (#3).
- Factory and a seeder with 15 Finnish demo requests, some of them always overdue.

### Removed
- Laravel's default test user from the database seeder.

## [0.2.0] - 2026-10-07

### Added
- Docker development environment with MariaDB, the Laravel backend and the Vue frontend: the whole app starts with `docker compose up` (#2).
- `./dev` helper script for common Docker commands.
- Database connection status in `/api/health`.

### Fixed
- Tests inside Docker always use an in-memory SQLite database instead of the MariaDB development database.

## [0.1.0] - 2026-10-07

### Added
- Laravel backend with a `/api/health` endpoint (#1).
- Vue 3 frontend with Vue Router that shows whether the API connection works.
- Vite dev proxy from the frontend to the backend API.
- README and environment configuration examples.

[Unreleased]: https://github.com/jussipalanen/jalaops/compare/v1.10.0...HEAD
[1.10.0]: https://github.com/jussipalanen/jalaops/compare/v1.9.0...v1.10.0
[1.9.0]: https://github.com/jussipalanen/jalaops/compare/v1.8.0...v1.9.0
[1.8.0]: https://github.com/jussipalanen/jalaops/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/jussipalanen/jalaops/compare/v1.6.2...v1.7.0
[1.6.2]: https://github.com/jussipalanen/jalaops/compare/v1.6.1...v1.6.2
[1.6.1]: https://github.com/jussipalanen/jalaops/compare/v1.6.0...v1.6.1
[1.6.0]: https://github.com/jussipalanen/jalaops/compare/v1.5.0...v1.6.0
[1.5.0]: https://github.com/jussipalanen/jalaops/compare/v1.4.1...v1.5.0
[1.4.1]: https://github.com/jussipalanen/jalaops/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/jussipalanen/jalaops/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/jussipalanen/jalaops/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/jussipalanen/jalaops/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/jussipalanen/jalaops/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/jussipalanen/jalaops/compare/v0.24.1...v1.0.0
[0.24.1]: https://github.com/jussipalanen/jalaops/compare/v0.24.0...v0.24.1
[0.24.0]: https://github.com/jussipalanen/jalaops/compare/v0.23.0...v0.24.0
[0.23.0]: https://github.com/jussipalanen/jalaops/compare/v0.22.0...v0.23.0
[0.22.0]: https://github.com/jussipalanen/jalaops/compare/v0.21.0...v0.22.0
[0.21.0]: https://github.com/jussipalanen/jalaops/compare/v0.20.0...v0.21.0
[0.20.0]: https://github.com/jussipalanen/jalaops/compare/v0.19.0...v0.20.0
[0.19.0]: https://github.com/jussipalanen/jalaops/compare/v0.18.0...v0.19.0
[0.18.0]: https://github.com/jussipalanen/jalaops/compare/v0.17.0...v0.18.0
[0.17.0]: https://github.com/jussipalanen/jalaops/compare/v0.16.0...v0.17.0
[0.16.0]: https://github.com/jussipalanen/jalaops/compare/v0.15.0...v0.16.0
[0.15.0]: https://github.com/jussipalanen/jalaops/compare/v0.14.1...v0.15.0
[0.14.1]: https://github.com/jussipalanen/jalaops/compare/v0.14.0...v0.14.1
[0.14.0]: https://github.com/jussipalanen/jalaops/compare/v0.13.0...v0.14.0
[0.13.0]: https://github.com/jussipalanen/jalaops/compare/v0.12.0...v0.13.0
[0.12.0]: https://github.com/jussipalanen/jalaops/compare/v0.11.1...v0.12.0
[0.11.1]: https://github.com/jussipalanen/jalaops/compare/v0.11.0...v0.11.1
[0.11.0]: https://github.com/jussipalanen/jalaops/compare/v0.10.0...v0.11.0
[0.10.0]: https://github.com/jussipalanen/jalaops/compare/v0.9.0...v0.10.0
[0.9.0]: https://github.com/jussipalanen/jalaops/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/jussipalanen/jalaops/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/jussipalanen/jalaops/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/jussipalanen/jalaops/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/jussipalanen/jalaops/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/jussipalanen/jalaops/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/jussipalanen/jalaops/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/jussipalanen/jalaops/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/jussipalanen/jalaops/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/jussipalanen/jalaops/releases/tag/v0.1.0
