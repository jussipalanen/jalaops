@php
    $statusLabels = ['open' => 'Avoin', 'in_progress' => 'Työn alla', 'completed' => 'Valmis'];
    $links = [
        ['href' => url('/docs'), 'title' => 'API-dokumentaatio', 'text' => 'Kaikki rajapinnan polut, parametrit ja vastaukset. Kokeile kutsuja suoraan selaimessa.', 'primary' => true],
        ['href' => url('/docs/api.json'), 'title' => 'OpenAPI-määrittely', 'text' => 'Koneluettava kuvaus rajapinnasta (JSON), esimerkiksi Postmaniin tai koodin generointiin.'],
        ['href' => url('/api/health'), 'title' => 'Terveystarkistus', 'text' => 'Kertoo, toimivatko rajapinta ja tietokantayhteys.'],
        ['href' => url('/api/requests'), 'title' => 'Pyynnöt (JSON)', 'text' => 'Kaikki huoltopyynnöt. Suodata esimerkiksi:', 'code' => '?status=open&priority=high'],
    ];
@endphp
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JalaOps API</title>
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><rect width="64" height="64" rx="15" fill="#0284c7"/><g fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round"><circle cx="32" cy="32" r="19" stroke-width="6" stroke-linecap="butt" stroke-dasharray="6.5 8.42"/><circle cx="32" cy="32" r="14" stroke-width="5"/><path d="M25 32.5l5 5 9-10" stroke-width="4.5"/></g></svg>') }}">
    <style>
        /* Same look as the Vue app: sky blue in the light theme, emerald green in the dark theme. */
        :root {
            color-scheme: light dark;
            --bg: #f8fafc;
            --surface: #fff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #0284c7;
            --primary-strong: #0369a1;
            --primary-light: #38bdf8;
            --primary-soft: #e0f2fe;
            --on-primary: #fff;
            --ok: #047857;
            --ok-soft: #d1fae5;
            --error: #b91c1c;
            --error-soft: #fee2e2;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #020617;
                --surface: #0f172a;
                --border: #1e293b;
                --text: #f1f5f9;
                --muted: #94a3b8;
                --primary: #10b981;
                --primary-strong: #34d399;
                --primary-light: #34d399;
                --primary-soft: rgb(16 185 129 / 0.15);
                --on-primary: #020617;
                --ok: #6ee7b7;
                --ok-soft: rgb(16 185 129 / 0.15);
                --error: #fca5a5;
                --error-soft: rgb(239 68 68 / 0.15);
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg);
            color: var(--text);
            font: 16px/1.5 ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: inherit; }

        :focus-visible { outline: 3px solid var(--primary); outline-offset: 2px; }

        .wrap { width: 100%; max-width: 64rem; margin: 0 auto; padding: 0 1rem; }

        header { border-bottom: 1px solid var(--border); background: var(--surface); }
        header .wrap { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 4rem; }
        .brand { display: inline-flex; align-items: center; gap: 0.625rem; font-size: 1.125rem; font-weight: 700; text-decoration: none; }
        .brand svg { width: 2rem; height: 2rem; flex-shrink: 0; }
        .brand .ops { color: var(--primary); }
        .tag { border-radius: 999px; background: var(--primary-soft); color: var(--primary-strong); padding: 0.125rem 0.625rem; font-size: 0.8125rem; font-weight: 600; white-space: nowrap; }

        main { flex: 1; padding: 2rem 0 3rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 1rem; }
        .hero { padding: 1.5rem; margin-bottom: 1.5rem; }
        .eyebrow { margin: 0 0 0.5rem; color: var(--primary); font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 1.875rem; line-height: 1.2; letter-spacing: -0.02em; }
        .lead { margin: 0.75rem 0 1.25rem; max-width: 40rem; color: var(--muted); font-size: 1.0625rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 2.75rem; padding: 0 1rem; border-radius: 0.5rem; font-weight: 600; text-decoration: none; }
        .btn-primary { background: var(--primary); color: var(--on-primary); }
        .btn-primary:hover { background: var(--primary-strong); }
        .btn-secondary { background: var(--primary-soft); color: var(--primary-strong); }

        h2 { margin: 0 0 0.75rem; font-size: 1.125rem; }
        section { margin-bottom: 1.5rem; }
        .grid { display: grid; gap: 1rem; }
        .stats { grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); }
        .stat { padding: 1rem 1.25rem; }
        .stat dt { color: var(--muted); font-size: 0.875rem; }
        .stat dd { margin: 0.25rem 0 0; font-size: 1.5rem; font-weight: 700; }
        .pill { display: inline-flex; align-items: center; gap: 0.375rem; border-radius: 999px; padding: 0.125rem 0.625rem; font-size: 0.875rem; font-weight: 600; }
        .pill::before { content: ''; width: 0.375rem; height: 0.375rem; border-radius: 50%; background: currentColor; }
        .pill-ok { background: var(--ok-soft); color: var(--ok); }
        .pill-error { background: var(--error-soft); color: var(--error); }
        .links { grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
        .link { display: block; padding: 1.25rem; text-decoration: none; transition: border-color 0.15s; }
        .link:hover { border-color: var(--primary); }
        .link strong { display: block; margin-bottom: 0.25rem; color: var(--primary-strong); }
        .link span { color: var(--muted); font-size: 0.9375rem; }
        .link.featured { border-color: var(--primary); }
        code { white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.875em; }

        footer { border-top: 1px solid var(--border); color: var(--muted); font-size: 0.875rem; }
        footer .wrap { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        footer .wrap { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem 1.5rem; }
        footer p { margin: 0; }
        footer a { color: var(--primary-strong); font-weight: 600; text-decoration: none; }
        footer a:hover { text-decoration: underline; }

        @media (min-width: 40rem) {
            .wrap { padding: 0 1.5rem; }
            .hero { padding: 2rem; }
            h1 { font-size: 2.25rem; }
        }
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            <a class="brand" href="{{ url('/') }}">
                <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <defs>
                        <linearGradient id="tile" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="var(--primary-light)" />
                            <stop offset="1" stop-color="var(--primary-strong)" />
                        </linearGradient>
                    </defs>
                    <rect width="64" height="64" rx="15" fill="url(#tile)" />
                    <g fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="32" cy="32" r="19" stroke-width="6" stroke-linecap="butt" stroke-dasharray="6.5 8.42" />
                        <circle cx="32" cy="32" r="14" stroke-width="5" />
                        <path d="M25 32.5l5 5 9-10" stroke-width="4.5" />
                    </g>
                </svg>
                <span>Jala<span class="ops">Ops</span> API</span>
            </a>
            <span class="tag">Versio {{ $version }}</span>
        </div>
    </header>

    <main>
        <div class="wrap">
            <div class="card hero">
                <p class="eyebrow">Huoltopyyntöjen hallinta</p>
                <h1>JalaOps-rajapinta</h1>
                <p class="lead">
                    Tämä on JalaOps-demosovelluksen Laravel-taustapalvelu. Se tarjoaa REST-rajapinnan
                    huoltopyyntöjen listaamiseen, luomiseen, muokkaamiseen ja poistamiseen.
                </p>
                <div class="actions">
                    <a class="btn btn-primary" href="{{ url('/docs') }}">Avaa API-dokumentaatio</a>
                    @if ($frontendUrl)
                        <a class="btn btn-secondary" href="{{ $frontendUrl }}">Siirry sovellukseen</a>
                    @endif
                </div>
            </div>

            <section aria-labelledby="status-title">
                <h2 id="status-title">Tilanne</h2>
                <dl class="grid stats">
                    <div class="card stat">
                        <dt>Tietokanta</dt>
                        <dd>
                            @if ($statusCounts !== null)
                                <span class="pill pill-ok">Toimii</span>
                            @else
                                <span class="pill pill-error">Ei yhteyttä</span>
                            @endif
                        </dd>
                    </div>
                    <div class="card stat">
                        <dt>Tila</dt>
                        <dd><span class="pill pill-ok">{{ $demo ? 'Demotila' : (app()->isProduction() ? 'Tuotanto' : 'Kehitys') }}</span></dd>
                    </div>
                    @if ($statusCounts !== null)
                        <div class="card stat">
                            <dt>Pyyntöjä yhteensä</dt>
                            <dd>{{ array_sum($statusCounts) }}</dd>
                        </div>
                        @foreach ($statusCounts as $status => $count)
                            <div class="card stat">
                                <dt>{{ $statusLabels[$status] ?? $status }}</dt>
                                <dd>{{ $count }}</dd>
                            </div>
                        @endforeach
                    @endif
                </dl>
            </section>

            <section aria-labelledby="links-title">
                <h2 id="links-title">Rajapinta</h2>
                <div class="grid links">
                    @foreach ($links as $link)
                        <a class="card link{{ ! empty($link['primary']) ? ' featured' : '' }}" href="{{ $link['href'] }}">
                            <strong>{{ $link['title'] }}</strong>
                            <span>
                                {{ $link['text'] }}
                                @isset($link['code'])
                                    <code>{{ $link['code'] }}</code>
                                @endisset
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="wrap">
            <p>
                JalaOps on demosovellus huoltopyyntöjen hallintaan.
                @if ($demo)
                    Demotilassa tiedot palautuvat esimerkkidataksi, kun palvelin käynnistyy uudelleen.
                @endif
            </p>
            <p>
                Powered by <a href="https://laravel.com" rel="noopener">Laravel</a> {{ app()->version() }}
                · PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }}
            </p>
        </div>
    </footer>
</body>
</html>
