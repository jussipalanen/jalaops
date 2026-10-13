@php
    // An invoice as a PDF ($forPdf) or an HTML page. dompdf understands only
    // part of CSS, so the layout uses plain blocks and tables; the HTML page
    // adds a few screen styles on top.
    use App\Support\ReferenceNumber;

    $euros = fn (int $cents) => number_format($cents / 100, 2, ',', "\u{00A0}")."\u{00A0}€";
    $number = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', "\u{00A0}"), '0'), ',');
    $date = fn ($value) => $value?->format('j.n.Y');
    $totals = $invoice->totalsCents();
    $groups = $invoice->lines->groupBy(fn ($line) => $line->service_request_id.'|'.$line->request_title);
    $terms = $invoice->issue_date->diffInDays($invoice->due_date);
    $reference = ReferenceNumber::format($invoice->reference_number);
@endphp
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="utf-8">
    <title>Lasku {{ $invoice->number }}</title>
    <style>
        @page { margin: 18mm 18mm 20mm; }
        body { margin: 0; color: #0f172a; font-family: {!! $forPdf ? "'DejaVu Sans', sans-serif" : "system-ui, -apple-system, 'Segoe UI', Roboto, 'DejaVu Sans', sans-serif" !!}; font-size: 9.5pt; line-height: 1.45; }
        table { border-collapse: collapse; }
        .muted { color: #64748b; }
        .head td { vertical-align: top; padding: 0; }
        .brand { margin-bottom: 10px; }
        .brand img { width: 34px; height: 34px; vertical-align: middle; }
        .brand span { margin-left: 6px; font-size: 15pt; font-weight: bold; vertical-align: middle; }
        .brand .ops { margin: 0; color: #0369a1; }
        .seller strong { font-size: 13pt; }
        .title { font-size: 20pt; font-weight: bold; letter-spacing: 0.04em; margin: 0 0 6px; }
        .facts td { padding: 1px 0 1px 12px; }
        .facts td:first-child { color: #475569; padding-left: 0; }
        .stamp { display: inline-block; margin-bottom: 6px; padding: 2px 10px; border: 2px solid; border-radius: 4px; font-weight: bold; letter-spacing: 0.08em; }
        .stamp.draft { color: #92400e; border-color: #f59e0b; }
        .stamp.paid { color: #047857; border-color: #10b981; }
        .buyer { margin: 26px 0 22px; }
        /* Fixed column widths: the description gets most of the room, and long
           words wrap instead of widening the table past the page. */
        .lines { width: 100%; table-layout: fixed; }
        .lines th { text-align: left; font-weight: normal; color: #475569; font-size: 8.5pt; border-bottom: 1px solid #94a3b8; padding: 5px 6px 5px 0; }
        .lines td { padding: 4px 6px 4px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .lines td.text { word-wrap: break-word; }
        .lines tr { page-break-inside: avoid; }
        .lines .group td { padding-top: 10px; font-weight: bold; border-bottom: 0; }
        .num { text-align: right; white-space: nowrap; }
        /* The totals, payment details and footer always stay together. */
        .closing { page-break-inside: avoid; }
        .summary { width: 62%; margin: 14px 0 0 auto; }
        .summary td { padding: 3px 0 3px 10px; }
        .summary th { padding: 3px 0 3px 10px; text-align: right; font-weight: normal; color: #475569; font-size: 8.5pt; border-bottom: 1px solid #cbd5e1; }
        .summary .grand td { font-size: 12pt; font-weight: bold; border-top: 2px solid #0f172a; padding-top: 6px; }
        .payment { width: 100%; margin-top: 26px; border: 1px solid #94a3b8; }
        .payment td { padding: 6px 10px; border: 1px solid #cbd5e1; vertical-align: top; }
        .payment .label { display: block; font-size: 7.5pt; color: #475569; }
        .payment .value { font-size: 10.5pt; font-weight: bold; }
        .notes { margin-top: 16px; }
        .footer { margin-top: 26px; padding-top: 8px; border-top: 1px solid #e2e8f0; font-size: 8pt; color: #64748b; }
        @if (! $forPdf)
        /* The HTML page: an A4-wide sheet on a grey background, full width on a phone. */
        html { background: #e2e8f0; }
        body { max-width: 210mm; box-sizing: border-box; margin: 24px auto; padding: 18mm; background: #fff; font-size: 10.5pt; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.15); }
        .scroll { overflow-x: auto; }
        @media (max-width: 640px) {
            body { margin: 0; padding: 20px 16px; box-shadow: none; }
            .head > tbody > tr > td { display: block; width: auto !important; padding-bottom: 18px; }
            .lines { min-width: 620px; }
            .summary { width: 100%; }
            .payment, .payment tbody, .payment tr, .payment td { display: block; width: auto !important; }
            .payment td { border-width: 0 0 1px; }
            .payment tr:last-child td:last-child { border-bottom: 0; }
        }
        @media print {
            html { background: none; }
            body { max-width: none; margin: 0; padding: 0; box-shadow: none; }
        }
        @endif
    </style>
</head>
<body>
    <table class="head" style="width: 100%;">
        <tr>
            <td class="seller" style="width: 52%;">
                <div class="brand"><img src="{{ $logo }}" alt=""><span>Jala<span class="ops">Ops</span></span></div>
                <strong>{{ $seller['name'] }}</strong><br>
                {{ $seller['street_address'] }}<br>
                {{ $seller['postal_code'] }} {{ $seller['city'] }}<br>
                @if ($seller['business_id'])Y-tunnus {{ $seller['business_id'] }}<br>@endif
                <span class="muted">{{ $seller['email'] }}</span>
            </td>
            <td>
                @if ($invoice->status->value === 'draft')<span class="stamp draft">LUONNOS</span>@endif
                @if ($invoice->status->value === 'paid')<span class="stamp paid">MAKSETTU</span>@endif
                <p class="title">LASKU</p>
                <table class="facts">
                    <tr><td>Laskun numero</td><td>{{ $invoice->number }}</td></tr>
                    <tr><td>Laskun päivä</td><td>{{ $date($invoice->issue_date) }}</td></tr>
                    <tr><td>Eräpäivä</td><td><strong>{{ $date($invoice->due_date) }}</strong></td></tr>
                    <tr><td>Maksuehto</td><td>{{ $terms }} pv netto</td></tr>
                    <tr><td>Viitenumero</td><td>{{ $reference }}</td></tr>
                    @if ($invoice->customer_business_id)<tr><td>Asiakkaan y-tunnus</td><td>{{ $invoice->customer_business_id }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <div class="buyer">
        <span class="muted">Laskutettava</span><br>
        <strong>{{ $invoice->customer_name }}</strong><br>
        @if ($invoice->billing_street_address){{ $invoice->billing_street_address }}<br>@endif
        {{ trim($invoice->billing_postal_code.' '.$invoice->billing_city) }}
    </div>

    <div class="scroll">
    <table class="lines">
        <thead>
            <tr>
                <th style="width: 47%;">Kuvaus</th>
                <th class="num" style="width: 12%;">Määrä</th>
                <th class="num" style="width: 15%;">á-hinta (alv 0 %)</th>
                <th class="num" style="width: 9%;">ALV</th>
                <th class="num" style="width: 17%;">Yhteensä (alv 0 %)</th>
            </tr>
        </thead>
        @foreach ($groups as $lines)
            <tbody>
                <tr class="group">
                    <td colspan="5" class="text">{{ $lines->first()->request_title }}
                        @if ($lines->first()->delivered_on)<span class="muted" style="font-weight: normal;"> · toimitettu {{ $date($lines->first()->delivered_on) }}</span>@endif
                    </td>
                </tr>
                @foreach ($lines as $line)
                    <tr>
                        <td class="text">{{ $line->description }} <span class="muted">· {{ $line->type->label() }}</span></td>
                        <td class="num">{{ $number($line->quantity) }} {{ $line->unit }}</td>
                        <td class="num">{{ $euros($line->unit_price_cents) }}</td>
                        <td class="num">{{ $number($line->vat_rate_basis_points / 100) }} %</td>
                        <td class="num">{{ $euros($line->netCents()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>
    </div>

    <div class="closing">
    <div class="scroll">
    <table class="summary">
        <tr><th>ALV</th><th>Veroton</th><th>Vero</th><th>Yhteensä</th></tr>
        @foreach ($totals['by_rate'] as $rate => $sums)
            <tr>
                <td class="num">{{ $number($rate / 100) }} %</td>
                <td class="num">{{ $euros($sums['net']) }}</td>
                <td class="num">{{ $euros($sums['vat']) }}</td>
                <td class="num">{{ $euros($sums['net'] + $sums['vat']) }}</td>
            </tr>
        @endforeach
        <tr class="grand"><td colspan="3" class="num">Maksettava yhteensä</td><td class="num">{{ $euros($totals['total']) }}</td></tr>
    </table>
    </div>

    <table class="payment">
        <tr>
            <td style="width: 34%;"><span class="label">Saaja</span><span class="value">{{ $seller['name'] }}</span></td>
            <td style="width: 40%;"><span class="label">Tilinumero (IBAN){{ $seller['bic'] ? ' / BIC' : '' }}</span>
                @if ($seller['iban'])<span class="value">{{ $seller['iban'] }}</span>{{ $seller['bic'] ? ' / '.$seller['bic'] : '' }}
                @else<span class="muted">Ei asetettu (SELLER_IBAN)</span>@endif
            </td>
            <td><span class="label">Viitenumero</span><span class="value">{{ $reference }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Eräpäivä</span><span class="value">{{ $date($invoice->due_date) }}</span></td>
            <td colspan="2"><span class="label">Euroa</span><span class="value">{{ $euros($totals['total']) }}</span></td>
        </tr>
    </table>

    @if ($invoice->notes)
        <p class="notes"><span class="muted">Lisätiedot:</span> {{ $invoice->notes }}</p>
    @endif

    <div class="footer">
        Maksaessasi käytä viitenumeroa {{ $reference }}. JalaOps on demosovellus; laskun tiedot ovat esimerkkidataa.
    </div>
    </div>
</body>
</html>
