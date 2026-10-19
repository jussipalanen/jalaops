# Python modules

Modules add features to JalaOps in Python. A module is a folder here with a small Python program. Laravel runs the program as a separate process: a JSON object goes in on stdin, and a JSON object comes out on stdout. Each module gets a page in the app (**Työkalut → Moduulit → Avaa**): either a page drawn from its `module.json`, or its own HTML view.

Modules are part of the code. They are added and changed through pull requests, reviewed and tested like the rest of the backend, and built into the Docker image. They are never uploaded or edited from the app. A module can also come from a git repository of its own as a Composer package (see "Modules from Composer packages").

Modules are optional: users turn them on and off in the app (**Työkalut → Moduulit**), see "Turn a module on and off".

Complete examples:

- **`vat-calculator` (ALV-laskuri):** a form and results drawn by the app from `module.json`.
- **`hello` (Hei maailma):** a view of its own (`view.html`) with a bar chart, which runs the module when the page opens.
- **[`jalaops/news`](https://github.com/jussipalanen/jalaops-module-news) (Uutiset), a package:** a news feed with a data folder (SQLite), several pages in its view (a list, one item, a form), images, and a widget on the dashboard.

## Create a module

1. Make a folder whose name is the module's id: lowercase letters, numbers and hyphens, e.g. `modules/vat-calculator`.
2. Add `module.json` and `main.py` (below).
3. Try it on the command line:

   ```bash
   ./dev artisan modules:list
   ./dev artisan modules:run vat-calculator '{"amount": 100}'
   ```

4. Open **Työkalut** in the app. The module is listed there, and **Avaa** opens its page.
5. Add tests (see "Test a module") and open a pull request.

```text
modules/
  packages/            module packages installed by Composer (not in git)
  vat-calculator/
    module.json        name, settings, form and results
    main.py            the program: JSON in, JSON out
    view.html          optional: the module's own page
    assets/            optional: CSS and JS files for the view
```

## module.json

The `vat-calculator` module's:

```json
{
    "name": "ALV-laskuri",
    "description": "Laskee verottoman hinnan, arvonlisäveron ja verollisen hinnan.",
    "version": "1.0.0",
    "entry": "main.py",
    "timeout": 5,
    "enabled": true,
    "form": [
        { "name": "amount", "label": "Veroton hinta (€)", "type": "number", "required": true, "default": 100 },
        { "name": "rate", "label": "ALV-kanta (%)", "type": "select", "options": ["25.5", "14", "10", "0"] }
    ],
    "results": [
        { "name": "net", "label": "Veroton hinta (€)", "decimals": 2 },
        { "name": "vat", "label": "ALV (€)", "decimals": 2 },
        { "name": "total", "label": "Verollinen hinta (€)", "decimals": 2 }
    ]
}
```

| Field | Required | Meaning |
|---|---|---|
| `name` | yes | The name in the app, in Finnish |
| `description` | no | One sentence on what the module does, in Finnish |
| `version` | no | The module's own version, e.g. `1.0.0` |
| `entry` | no | The program to run, inside the module's folder (default `main.py`) |
| `timeout` | no | Seconds before the program is stopped (default 10, at most 30) |
| `enabled` | no | Whether the module is on before anyone changes it (default `false`) |
| `form` | no | The fields of the page the app draws (below) |
| `results` | no | Which output values the drawn page shows: `name`, `label` and optionally `decimals` (0–6, e.g. 2 for euros). Without it, the page shows every value |
| `view` | no | The module's own page, an HTML file in its folder (default `view.html` if there is one) |
| `assets` | no | CSS and JS files the view uses, in the module's folder, loaded in this order before the view (see "CSS, JS and libraries") |
| `storage` | no | `true` gives the module a data folder of its own (see "Data folder") |
| `max_input_kb` | no | The largest input the API sends the module, in kB (default 10, at most 4096), e.g. for images as data URLs |
| `widgets` | no | What the module shows elsewhere in the app, such as the dashboard (see "Widgets and hooks") |
| `routes` | no | Addresses of their own for the view's pages, such as `/news/:news` (see "Routes") |

Form fields (`form`):

| Key | Meaning |
|---|---|
| `name` | The key in the module's input: lowercase letters, numbers and `_` |
| `label` | The label in the app, in Finnish |
| `type` | `text`, `number`, `numbers` (a comma-separated list, sent as a list of numbers), `textarea` or `select` |
| `options` | The choices of a `select` field (texts) |
| `default` | The starting value (a list of numbers for `numbers`) |
| `help` | A hint under the field |
| `required` | `true` when the field can't be empty |

Empty fields are left out of the input, so the program can use its own defaults for them.

## main.py

A shortened version of `modules/vat-calculator/main.py`, which also rounds to cents and checks the VAT rate:

```python
import json
import sys


def fail(message):
    """Ends with an error; the app shows the message to the user."""
    print(message, file=sys.stderr)
    sys.exit(1)


data = json.load(sys.stdin)

amount = data.get("amount")
if not isinstance(amount, (int, float)) or isinstance(amount, bool):
    fail("Anna veroton hinta numerona.")

rate = float(data.get("rate", "25.5"))
vat = round(amount * rate / 100, 2)

json.dump({"vat": vat, "total": round(amount + vat, 2)}, sys.stdout, ensure_ascii=False)
```

The rules:

- **Input:** one JSON object on stdin, `{}` when there's nothing to send, exactly as the page sent it (strings aren't trimmed, and empty ones stay empty). Check it before using it: anything in the input comes from the user.
- **Output:** one JSON object on stdout and nothing else. Use stderr for debugging output.
- **Errors:** print a short Finnish message to stderr and exit with a non-zero code. The app shows the message's last line to the user, and the full error goes to the backend's log.
- **Environment:** the program gets only `PATH` and `LANG` (and `JALAOPS_DATA_DIR` with `"storage": true`), not the backend's variables, so it never sees API keys or database passwords. It runs in its own folder as the working directory.
- **Libraries:** the standard library works. A module that needs other libraries lists them with pinned versions in a `requirements.txt`, and they are installed when the Docker image is built (`backend/Dockerfile` and `docker/backend/Dockerfile`), never at runtime.
- **Text:** everything users see (names, labels, messages) is in Finnish; code and comments in English.

A program can be tried on its own too: `echo '{"amount": 100}' | python3 modules/vat-calculator/main.py`.

## Its own view (view.html)

Without a view, the app draws a page from `form` and `results`. For anything more (charts, a layout of its own, running the module on page load), add `view.html` to the module's folder. The app shows it on the module's page in a sandboxed frame.

The view is the inside of a page's `<body>`: HTML, a `<style>` and a `<script>`. It gets:

- **`jalaops.run(input)`**, which runs the module and returns a promise for its output object. Read the Python program's values from it directly: `output.vat`, `output.total`. If the module fails, the promise is rejected with an `Error` whose `message` is the Finnish reason.
- **Base styles** for headings, labels, inputs, selects and buttons, `.stack` (a column with gaps), `.error` and `[hidden]`.
- **The app's colours** as CSS variables that follow the light and dark theme: `--primary`, `--primary-text`, `--text`, `--muted`, `--border`, `--surface`, `--error`.
- **A height that fits its content:** the frame grows and shrinks with the page.
- **Pages with addresses of their own:** `jalaops.params` are the page address's parameters (`/tools/modules/news?news=3` gives `{ news: '3' }`, and with routes `/news/3` too), and `jalaops.navigate({ news: 4 })` changes them, so each page of the view has its own address and the back button works. `jalaops.navigate(params, { replace: true })` changes the address without a new history entry, e.g. from an old address to the current one. When they change, the view gets a `jalaops:params` event (`addEventListener('jalaops:params', (event) => draw(event.detail))`). Names are lowercase letters, numbers and `_`; values are texts.
- **A title and description for each page:** `jalaops.setPage({ title: 'Syyshuollot alkavat', description: '…' })` gives the page its browser tab title ("Syyshuollot alkavat – JalaOps") and description until the address changes. Without it, the tab has the module's name.
- **Links:** a link with `target="_blank"` opens in a new tab, outside the sandbox.

```html
<form id="form" class="stack">
  <label>Veroton hinta (€) <input id="amount" inputmode="decimal" value="100"></label>
  <div><button type="submit">Laske</button></div>
</form>
<p id="error" class="error" role="alert" hidden></p>
<p id="total"></p>

<script>
  document.getElementById('form').addEventListener('submit', async (event) => {
    event.preventDefault()
    const error = document.getElementById('error')
    error.hidden = true

    try {
      const output = await jalaops.run({ amount: Number(document.getElementById('amount').value) })
      document.getElementById('total').textContent = `Verollinen hinta: ${output.total} €`
    } catch (exception) {
      error.textContent = exception.message
      error.hidden = false
    }
  })
</script>
```

The frame is isolated from the app: it can't call the API or other addresses, load scripts or images from the network, or read the app's storage. Everything goes through `jalaops.run`. Images can be `data:` URLs. Write user-supplied values with `textContent`, not `innerHTML`; HTML that users write (like a news item's content) must be sanitized to an allowlist by the module, because scripts run in the frame. The frame can't open the browser's `alert()` or `confirm()` dialogs, so ask for confirmation in the page itself. The view can be at most 200 kB.

### CSS, JS and libraries

Longer styles and scripts, and libraries such as a chart library, go in files in the module's folder and are listed in `assets`:

```json
"assets": ["assets/hello.css", "assets/chart.js"]
```

The app puts them into the frame in this order, before the view, so the view's script can use what they define. `hello` does this: its styles are in `assets/hello.css`, and `assets/chart.js` is a small chart library that defines `barChart()`.

An external library is copied into the module's folder rather than loaded from a CDN, because the frame has no network access:

1. Download the library's browser build at a fixed version, e.g. Chart.js: `curl -o modules/my-module/assets/chart.umd.min.js https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js`
2. Add its licence next to it (`assets/chart.js-LICENSE.md`) and note the version in the module's README or `module.json` description.
3. List it in `assets` before the files that use it.

The copy is reviewed in the pull request like the rest of the module, and the app never loads code from elsewhere at runtime. Only `.css` and `.js` files are allowed, at most 1 MB together.

## Add and remove modules

`modules:add` checks a module and adds it, from a folder, an archive or a git repository. `modules:remove` takes one out.

```bash
./dev modules:add ~/code/jalaops-module-weather                        # a folder
./dev modules:add ~/Downloads/weather.zip                              # an archive: .zip, .tar, .tar.gz, .tgz or .gz
./dev modules:add https://example.com/releases/weather-1.2.0.tar.gz --id=weather
./dev modules:add https://github.com/owner/jalaops-module-weather.git  # a git repository
./dev modules:add https://github.com/owner/jalaops-module-weather.git --ref=v1.2.0
./dev modules:add https://github.com/owner/jalaops-module-weather.git --copy   # a package without Composer
./dev modules:add <source> --check                                     # only check it
./dev modules:remove weather                                           # asks first; --purge also deletes its data
```

`./dev modules:add` copies a folder or an archive on your machine into the backend container first, because the container sees only `backend/`. `./dev artisan modules:add` works the same for sources inside `backend/` and for URLs.

**What gets checked.** The module is fetched into a temporary folder and checked before anything changes:

- its `module.json`, with the same rules as an installed module: the fields, the entry script, the view and assets inside its folder, widgets, routes
- its id: lowercase letters, numbers and hyphens, and not taken (`--force` replaces a module in `backend/modules` with the same id)
- its routes: not one of the app's addresses, and not another module's
- its files: no symbolic links, at most 1000 files and 20 MB; an archive can't have paths outside its folder (`../`, `/…`) or links
- that its Python compiles
- its own tests, if it has a `tests/` folder (`python3 -m unittest`, without the backend's environment, like the module itself). They run the module's code, so check where it comes from; `--skip-tests` skips them.

A `requirements.txt` gives a warning: its libraries go in the Docker images (see "main.py").

**The id** comes from `--id`, from the package name in the module's `composer.json` (`jalaops/weather` is `weather`), or from the folder, repository or archive name without `jalaops-module-` (`jalaops-module-weather` is `weather`).

**Where it goes.**

- **A git repository that is a module package** (a `composer.json` of type `jalaops-module`, see "Modules from Composer packages") is added with Composer: `modules:add` adds the repository to `composer.json` and requires the package at the checked version, so `composer.lock` pins it. A package is installed at a version tag: its newest one, or `--ref=v1.2.0` (`^1.2`).
- **Anything else** (a folder, an archive, a git repository without a package) is copied into `backend/modules/<id>`, without `.git` and Python's caches. The files belong to you, not to the container's root user. `--copy` copies a package this way too, at the same version tag, without Composer: `composer.lock` doesn't pin it then, and updating it is another `modules:add --copy --force`.

A copied module works at once: the app reads the modules folder on every request, so nothing needs a restart. Turn it on in **Työkalut → Moduulit** or with `modules:enable <id>`.

`modules:remove` deletes a module's folder in `backend/modules`, or removes a package with Composer (and its repository `jalaops-module-<id>` from `composer.json`). `--purge` also deletes its data folder and its settings: whether it's on, and its widgets' places.

**Production.** Both commands change the code, so commit the changes and open a pull request: the module reaches production with the deploy, and is reviewed first. On Cloud Run, a module added on the server would be lost at the next restart or deploy (every instance has its own temporary files), so the commands ask before they change anything in production.

## Modules from Composer packages

A module can also live in a git repository of its own, for example on your own server, and be installed with Composer like a PHP library. The package is versioned by its git tags, and the backend's `composer.lock` pins the exact commit, so updating a module is a pull request in this repository too.

A module package is a git repository with a `composer.json` of type `jalaops-module`:

```json
{
    "name": "jalaops/worktime",
    "description": "JalaOps module: hours worked and labour cost from start and end times.",
    "type": "jalaops-module",
    "license": "MIT"
}
```

Next to it, either:

- **one module:** `module.json`, `main.py` and the rest at the repository's root. The module's id is the package name after the slash (`jalaops/worktime` is `worktime`), or
- **several modules:** one folder per module, like in `backend/modules` (`counter/module.json`, `timer/module.json`, …).

Release a version by tagging it (`git tag -a v1.0.0 -m 1.0.0 && git push --tags`), then install it in the backend. `modules:add` checks it first (see "Add and remove modules"); the two Composer commands do the same without the checks:

```bash
./dev modules:add https://git.example.com/jalaops/worktime.git
# or: ./dev composer config repositories.worktime vcs https://git.example.com/jalaops/worktime.git
#     ./dev composer require jalaops/worktime:^1.0
./dev artisan modules:list              # "Source" shows jalaops/worktime v1.0.0
```

Update to a newer tag with `./dev composer update jalaops/worktime`. Commit `composer.json` and `composer.lock` in a pull request, and read the module's changes first (`git diff v1.0.0 v1.1.0` in its repository): the module runs on the backend like the ones here, with the same rules and limits.

On GitHub, give a module package's repository the topic `jalaops-module` (**About → ⚙ → Topics**), and `./dev modules:search` lists them all, with whether each is installed and the commands to install it. It searches your own `gh` account, or another owner: `./dev modules:search some-org`.

Composer installs a module package into `backend/modules/packages/<name>/` (the package name after the slash), not `vendor/`, so you find it next to the other modules. The folder is ignored by git: only `composer.json` and `composer.lock` are committed, and `composer install` puts the locked version back. Don't edit the files there, because Composer replaces them; change the module in its own repository. A module of your own can't be called `packages`. The [`composer-plugins/module-installer`](../composer-plugins/module-installer) plugin in this repository does the installing.

Ids are unique. The modules in `backend/modules` come first, then the packages by name; a module whose id is already taken is skipped and logged. A package module that needs Python libraries lists them in a `requirements.txt` like the others.

The repository must be readable wherever `composer install` runs: your machine, CI and the Docker build. See "Module packages" in [docs/DEPLOYMENT.md](../../docs/DEPLOYMENT.md#module-packages-from-git).

## Routes

A view's pages are at `/tools/modules/<id>`, with their parameters in the query. With `routes` in `module.json`, they get addresses of their own:

```json
"routes": [
    { "path": "/news" },
    { "path": "/news/new", "params": { "view": "new" } },
    { "path": "/news/:news" },
    { "path": "/news/:news/edit", "params": { "view": "edit" } }
]
```

- **Paths:** parts of lowercase letters, numbers and hyphens, or `:name` for a parameter; the first part is fixed (`/news`, not `/:news`). At most 6 parts and 10 routes.
- **Parameters:** the view gets the path's parameters, the route's fixed `params` and the query together in `jalaops.params`: `/news/3/edit?tab=kuvat` gives `{ news: '3', view: 'edit', tab: 'kuvat' }`. A view doesn't need to change to get routes.
- **Addresses from parameters:** `jalaops.navigate(params)` and widget links go to the route whose fixed `params` match and whose path takes the most of the rest; what's left goes in the query (`{ tag: 'huolto' }` → `/news?tag=huolto`). If no route fits, the address is `/tools/modules/<id>`.
- **The app's addresses win:** a route can't start with the app's own addresses (`reserved_paths` in `config/modules.php`: `api`, `assets`, `docs`, `requests`, `customers`, `invoices`, `tools`, `map`, `about`, `mobile-apps`); such a module is skipped as broken. The frontend also skips any route it already has.
- **One module per first part:** if two modules use `/news`, the first by id keeps it, and the other works only at `/tools/modules/<id>` (logged as a warning).
- **Only modules that are on:** the routes are added when the module is turned on and removed when it's turned off; then its addresses show "Sivua ei löytynyt". An old `/tools/modules/<id>?…` address goes to the module's own.

The frontend adds the routes the first time an address matches no page of its own, so the app's pages don't wait for them.

## Data folder

A module with `"storage": true` gets a folder of its own for its data, in `JALAOPS_DATA_DIR`: `backend/storage/app/modules/<id>/` (`MODULES_STORAGE_PATH` changes the parent folder). The app makes it on the module's first run. Keep data there, for example in an SQLite database (`sqlite3` is in the standard library), never in the module's own folder, which Composer replaces when a package is updated.

The folder is the module's own: the module manages its format and upgrades it (`news` keeps a schema version in SQLite's `PRAGMA user_version`). Two runs can happen at the same time, so take a lock when writing (SQLite does this). On Cloud Run the folder resets when the instance restarts, like the rest of the demo data, so a module can fill a new database with demo content.

## Widgets and hooks

A module can show content elsewhere in the app with widgets. The app has places for them, hooks, listed in `config/modules.php`:

| Hook | Where |
|---|---|
| `dashboard.top` | Etusivun yläosa: after the welcome |
| `dashboard.after_counts` | Tilanteen jälkeen: after the request and invoice counts |
| `dashboard.after_overview` | Tilannekatsauksen jälkeen: after the AI overview |
| `dashboard.bottom` | Etusivun alaosa |

A widget in `module.json` names its default hook and the input the module gets for it:

```json
"widgets": [
    { "name": "latest", "title": "Uusimmat uutiset", "type": "feed", "hook": "dashboard.after_counts", "input": { "action": "feed", "limit": 5 } }
]
```

The widget's id is the module's id and its name: `news.latest`. Type `feed` is the only one so far: the app runs the module with `input` and draws a list from its output, so the module decides what is shown and the app how it looks:

```json
{ "items": [
    { "title": "Syyshuollot alkavat", "text": "Suodattimet tarkistetaan.", "date": "2026-10-19T12:00:00Z",
      "image": "data:image/jpeg;base64,…", "tags": ["huolto"], "params": { "news": "3" } }
] }
```

Only `title` is required. At most 10 items are shown; `image` must be a JPEG, PNG, GIF or WebP data URL of at most 300 kB, and `params` opens the module's page at that address (`/tools/modules/news?news=3`). A widget whose module fails shows an error instead.

Where a widget is shown, in order:

1. Its `hook` in `module.json`.
2. The server's choice for everyone: `./dev artisan modules:place news.latest dashboard.bottom` (or `hidden`, or `default` to go back). `./dev artisan modules:widgets` lists the widgets and where they are.
3. Each user's own choice in their browser: **Muokkaa etusivua** at the bottom of the dashboard moves or hides widgets.

Widgets of a module that is off aren't shown.

## Turn a module on and off

Modules are optional. A module is on when its `module.json` says `"enabled": true`; a module that adds content to the app (like `news`) should ship with `"enabled": false` and let users decide. Users turn modules on and off in the app (**Työkalut → Moduulit**), and the setting is stored in the database and is the same for everyone. The commands do the same on the server:

```bash
./dev artisan modules:enable hello
./dev artisan modules:disable hello
./dev artisan modules:list              # shows each module's status
```

A module that is off is listed in the app as **Pois käytöstä**. Its page, API and widgets don't run it. The app has no login, so anyone using it can switch modules, as they can change requests.

On Cloud Run, the demo data (and these settings) reset on every start, so production goes back to `"enabled"` from `module.json`.

## The API

| Method | Path | Does |
|---|---|---|
| GET | `/api/modules` | Lists the modules, whether each is on and its `routes` |
| GET | `/api/modules/{id}` | One module: its form, results and view (the view's HTML inside JSON) |
| PATCH | `/api/modules/{id}` | Turns the module on or off: `{"enabled": true}` |
| POST | `/api/modules/{id}/run` | Runs the module; the request body is the input, `output` the result |
| GET | `/api/dashboard/widgets` | The dashboard's hooks and the widgets in them, each with its items and its module's `routes` |

Running, switching and the widgets are allowed only from the app's own pages (`FRONTEND_URL`, `APP_URL` and `ALLOWED_ORIGINS`). Runs and widget loads are limited to 30 per minute per visitor each, switching to 20. The input can be at most 10 kB, or the module's `max_input_kb`. A module that is off answers 403; a failed run answers 422 with the reason in `reason`.

## Test a module

Feature tests live in `backend/tests/Feature`; `PythonModulesTest` and `ModuleApiTest` test `hello` and show how, `ModulePackagesTest` tests modules from packages, and `ModuleWidgetsTest` tests data folders, widgets and switching modules. A package module's own tests belong in its repository. A module's test runs the real program:

```php
$output = app(ModuleRunner::class)->run(app(ModuleRegistry::class)->find('vat-calculator'), ['amount' => 100]);

$this->assertSame(125.5, $output['total']);
```

Test both good input and the errors the program reports. CI runs the tests with Python, like the Docker images.
