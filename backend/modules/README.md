# Python modules

Modules add features to JalaOps in Python. A module is a folder here with a small Python program. Laravel runs the program as a separate process: a JSON object goes in on stdin, and a JSON object comes out on stdout. Each module gets a page in the app (**Työkalut → Moduulit → Avaa**): either a page drawn from its `module.json`, or its own HTML view.

Modules are part of the code. They are added and changed through pull requests, reviewed and tested like the rest of the backend, and built into the Docker image. They are never uploaded or edited from the app.

Two complete examples:

- **`vat-calculator` (ALV-laskuri):** a form and results drawn by the app from `module.json`.
- **`hello` (Hei maailma):** a view of its own (`view.html`) with a bar chart, which runs the module when the page opens.

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

- **Input:** one JSON object on stdin, `{}` when there's nothing to send. Check it before using it: anything in the input comes from the user.
- **Output:** one JSON object on stdout and nothing else. Use stderr for debugging output.
- **Errors:** print a short Finnish message to stderr and exit with a non-zero code. The app shows the message's last line to the user, and the full error goes to the backend's log.
- **Environment:** the program gets only `PATH` and `LANG`, not the backend's variables, so it never sees API keys or database passwords. It runs in its own folder as the working directory.
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

The frame is isolated from the app: it can't call the API or other addresses, load scripts or images from the network, or read the app's storage. Everything goes through `jalaops.run`. Images can be `data:` URLs. Write user-supplied values with `textContent`, not `innerHTML`. The view can be at most 200 kB.

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

## Turn a module on and off

A module is on when its `module.json` says `"enabled": true`. The commands below override that and store the setting in the database:

```bash
./dev artisan modules:enable hello
./dev artisan modules:disable hello
./dev artisan modules:list              # shows each module's status
```

A module that is off is listed in the app as **Pois käytöstä**. Its page and API don't run it. Modules are switched on the server, not in the app, because the app has no login.

On Cloud Run, the demo data (and these settings) reset on every start, so production uses `"enabled"` from `module.json`.

## The API

| Method | Path | Does |
|---|---|---|
| GET | `/api/modules` | Lists the modules and whether each is on |
| GET | `/api/modules/{id}` | One module: its form, results and view (the view's HTML inside JSON) |
| POST | `/api/modules/{id}/run` | Runs the module; the request body is the input, `output` the result |

Running is allowed only from the app's own pages (`FRONTEND_URL`, `APP_URL` and `ALLOWED_ORIGINS`), at most ten times per minute per visitor. The input can be at most 10 kB. A module that is off answers 403; a failed run answers 422 with the reason in `reason`.

## Test a module

Feature tests live in `backend/tests/Feature`; `PythonModulesTest` and `ModuleApiTest` test `hello` and show how. A module's test runs the real program:

```php
$output = app(ModuleRunner::class)->run(app(ModuleRegistry::class)->find('vat-calculator'), ['amount' => 100]);

$this->assertSame(125.5, $output['total']);
```

Test both good input and the errors the program reports. CI runs the tests with Python, like the Docker images.
