@php
    // One template for both downloads. dompdf (the PDF) understands only part of
    // CSS, so the layout uses plain blocks and tables, no flexbox or grid.
    $timezone = config('app.display_timezone');
    $dateTime = fn ($time) => $time?->copy()->timezone($timezone)->format('j.n.Y \k\l\o H.i');
    $cityLine = trim(implode(' ', array_filter([$request->postal_code, $request->city])));
    $address = implode(', ', array_filter([$request->street_address, $cityLine]));
    $hasLocation = $request->latitude !== null && $request->longitude !== null;
    $priorityColours = ['low' => ['#f1f5f9', '#475569'], 'normal' => ['#e0f2fe', '#0369a1'], 'high' => ['#fee2e2', '#b91c1c']];
    $statusColours = ['open' => ['#fef3c7', '#92400e'], 'in_progress' => ['#e0f2fe', '#0369a1'], 'completed' => ['#d1fae5', '#047857']];
    [$priorityBg, $priorityFg] = $priorityColours[$request->priority->value];
    [$statusBg, $statusFg] = $statusColours[$request->status->value];
    $signedOff = $request->completion_signature || $request->signed_by;
    // Finnish number formats: "97,50 €", "1,5", "25,5 %".
    $euros = fn (int $cents) => number_format($cents / 100, 2, ',', "\u{00A0}")."\u{00A0}€";
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', "\u{00A0}"), '0'), ',');
    $totals = $request->totalsCents();
@endphp
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $request->title }} – Huoltopyyntö #{{ $request->id }}</title>
    <style>
        @page { margin: 22mm 20mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #0f172a;
            background: #ffffff;
            font-family: {!! $forPdf ? "'DejaVu Sans', sans-serif" : "system-ui, -apple-system, 'Segoe UI', Roboto, 'DejaVu Sans', sans-serif" !!};
            font-size: {{ $forPdf ? '10.5pt' : '16px' }};
            line-height: 1.55;
        }
        .page { max-width: 720px; margin: 0 auto; padding: {{ $forPdf ? '0' : '40px 24px' }}; }
        .brand { border-bottom: 3px solid #0284c7; padding-bottom: 8px; margin-bottom: 24px; color: #475569; font-size: 0.85em; }
        .brand strong { color: #0f172a; font-size: 1.15em; }
        .brand .ops { color: #0284c7; }
        h1 { font-size: 1.75em; line-height: 1.25; margin: 0 0 10px; }
        h2 { font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin: 24px 0 6px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 0.85em; font-weight: bold; margin-right: 6px; }
        .description { margin: 0; white-space: pre-line; }
        .muted { color: #64748b; }
        table.facts { width: 100%; border-collapse: collapse; }
        table.facts th, table.facts td { text-align: left; vertical-align: top; padding: 6px 0; border-bottom: 1px solid #e2e8f0; }
        table.facts th { width: 34%; color: #475569; font-weight: normal; }
        a { color: #0369a1; }
        .sign-off { page-break-inside: avoid; }
        table.signed { width: 100%; border-collapse: collapse; }
        table.signed td { vertical-align: top; padding: 0; }
        .signature { border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; }
        .signature img { display: block; width: 100%; height: auto; }
        table.items { width: 100%; border-collapse: collapse; font-size: 0.92em; table-layout: fixed; }
        table.items td.text { word-wrap: break-word; }
        table.items tr { page-break-inside: avoid; }
        table.items th, table.items td { padding: 6px 6px 6px 0; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        table.items th { color: #475569; font-weight: normal; font-size: 0.85em; }
        table.items .num { text-align: right; white-space: nowrap; }
        table.items tfoot td { border-bottom: 0; padding-top: 4px; }
        table.items tfoot tr.grand td { font-weight: bold; font-size: 1.05em; border-top: 2px solid #0f172a; padding-top: 6px; }
        table.sign-lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
        table.sign-lines td { padding: 30px 12px 4px 0; vertical-align: bottom; }
        table.sign-lines .line { border-bottom: 1px solid #0f172a; height: 1px; }
        table.sign-lines .label { padding-top: 4px; font-size: 0.8em; color: #64748b; }
        @if (! $forPdf)
        /* Narrow screens (the HTML file on a phone): the signature goes above the name and time. */
        @media (max-width: 560px) {
            table.signed td { display: block; width: 100% !important; padding: 0 0 10px !important; }
            table.facts th { width: 40%; }
        }
        @endif
        .footer { margin-top: 36px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 0.8em; color: #64748b; }
    </style>
</head>
<body>
<div class="page">
    <div class="brand"><strong>Jala<span class="ops">Ops</span></strong> &nbsp;·&nbsp; Huoltopyyntö #{{ $request->id }}</div>

    <h1>{{ $request->title }}</h1>
    <div>
        <span class="badge" style="background: {{ $priorityBg }}; color: {{ $priorityFg }};">Prioriteetti: {{ $request->priority->label() }}</span>
        <span class="badge" style="background: {{ $statusBg }}; color: {{ $statusFg }};">Tila: {{ $request->status->label() }}</span>
    </div>

    <h2>Kuvaus</h2>
    @if ($request->description)
        <p class="description">{{ $request->description }}</p>
    @else
        <p class="description muted">Ei kuvausta.</p>
    @endif

    <h2>Tiedot</h2>
    <table class="facts">
        <tr><th>Määräpäivä</th><td>{{ $request->due_date?->format('j.n.Y') ?? '–' }}</td></tr>
        <tr><th>Luotu</th><td>{{ $dateTime($request->created_at) ?? '–' }}</td></tr>
        <tr><th>Päivitetty</th><td>{{ $dateTime($request->updated_at) ?? '–' }}</td></tr>
    </table>

    <h2>Sijainti</h2>
    <table class="facts">
        <tr><th>Osoite</th><td>{{ $address !== '' ? $address : '–' }}</td></tr>
        @if ($hasLocation)
            <tr><th>Koordinaatit</th><td>{{ number_format($request->latitude, 5, '.', '') }}, {{ number_format($request->longitude, 5, '.', '') }}</td></tr>
            <tr>
                <th>Kartalla</th>
                <td>
                    <a href="https://www.openstreetmap.org/?mlat={{ $request->latitude }}&amp;mlon={{ $request->longitude }}#map=17/{{ $request->latitude }}/{{ $request->longitude }}">OpenStreetMap</a>
                    &nbsp;·&nbsp;
                    <a href="https://www.google.com/maps/@?api=1&amp;map_action=pano&amp;viewpoint={{ $request->latitude }},{{ $request->longitude }}">Street View</a>
                </td>
            </tr>
        @endif
    </table>

    @if ($request->items->isNotEmpty())
        <h2>Työt ja tarvikkeet</h2>
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 44%;">Kuvaus</th>
                    <th class="num" style="width: 13%;">Määrä</th>
                    <th class="num" style="width: 16%;">á-hinta (alv 0 %)</th>
                    <th class="num" style="width: 10%;">ALV</th>
                    <th class="num" style="width: 17%;">Yhteensä</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($request->items as $item)
                    <tr>
                        <td class="text">{{ $item->description }} <span class="muted">· {{ $item->type->label() }}</span></td>
                        <td class="num">{{ $number($item->quantity) }} {{ $item->unit }}</td>
                        <td class="num">{{ $euros($item->unit_price_cents) }}</td>
                        <td class="num">{{ $number($item->vat_rate_basis_points / 100) }} %</td>
                        <td class="num">{{ $euros($item->totalCents()) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="4" class="num">Veroton yhteensä</td><td class="num">{{ $euros($totals['net']) }}</td></tr>
                <tr><td colspan="4" class="num">ALV yhteensä</td><td class="num">{{ $euros($totals['vat']) }}</td></tr>
                <tr class="grand"><td colspan="4" class="num">Yhteensä</td><td class="num">{{ $euros($totals['total']) }}</td></tr>
            </tfoot>
        </table>
    @endif

    <div class="sign-off">
    <h2>Kuittaus</h2>
    @if ($signedOff)
        <table class="signed">
            <tr>
                @if ($request->completion_signature)
                    <td style="width: 46%; padding-right: 20px;">
                        <div class="signature"><img src="{{ $request->completion_signature }}" alt="Allekirjoitus"></div>
                    </td>
                @endif
                <td>
                    <table class="facts">
                        <tr><th>Kuittaaja</th><td>{{ $request->signed_by ?? '–' }}</td></tr>
                        <tr><th>Kuitattu</th><td>{{ $dateTime($request->signed_at) ?? '–' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    @else
        <p class="muted" style="margin: 0;">Pyyntöä ei ole kuitattu sovelluksessa. Tulostettuna sen voi kuitata alle.</p>
        <table class="sign-lines">
            <tr>
                <td style="width: 40%;"><div class="line"></div><div class="label">Allekirjoitus</div></td>
                <td style="width: 35%;"><div class="line"></div><div class="label">Nimenselvennys</div></td>
                <td style="width: 25%;"><div class="line"></div><div class="label">Päiväys</div></td>
            </tr>
        </table>
    @endif
    </div>

    <div class="footer">
        Ladattu JalaOpsista {{ now()->timezone($timezone)->format('j.n.Y \k\l\o H.i') }}.
        JalaOps on demosovellus; tiedot ovat esimerkkidataa.
    </div>
</div>
</body>
</html>
