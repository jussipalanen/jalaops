@php
    // An invoice for the PDF. dompdf understands only part of CSS, so the
    // layout uses plain blocks and tables.
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
        body { margin: 0; color: #0f172a; font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; line-height: 1.45; }
        table { border-collapse: collapse; }
        .muted { color: #64748b; }
        .head td { vertical-align: top; padding: 0; }
        .seller strong { font-size: 13pt; }
        .title { font-size: 20pt; font-weight: bold; letter-spacing: 0.04em; margin: 0 0 6px; }
        .facts td { padding: 1px 0 1px 12px; }
        .facts td:first-child { color: #475569; padding-left: 0; }
        .stamp { display: inline-block; margin-bottom: 6px; padding: 2px 10px; border: 2px solid; border-radius: 4px; font-weight: bold; letter-spacing: 0.08em; }
        .stamp.draft { color: #92400e; border-color: #f59e0b; }
        .stamp.paid { color: #047857; border-color: #10b981; }
        .buyer { margin: 26px 0 22px; }
        .lines { width: 100%; }
        .lines th { text-align: left; font-weight: normal; color: #475569; font-size: 8.5pt; border-bottom: 1px solid #94a3b8; padding: 5px 6px 5px 0; }
        .lines td { padding: 4px 6px 4px 0; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .lines .group td { padding-top: 10px; font-weight: bold; border-bottom: 0; }
        .num { text-align: right; white-space: nowrap; }
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
    </style>
</head>
<body>
    <table class="head" style="width: 100%;">
        <tr>
            <td class="seller" style="width: 52%;">
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

    <table class="lines">
        <thead>
            <tr>
                <th>Kuvaus</th>
                <th class="num">Määrä</th>
                <th class="num">á-hinta (alv 0 %)</th>
                <th class="num">ALV</th>
                <th class="num">Yhteensä (alv 0 %)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($groups as $lines)
                <tr class="group">
                    <td colspan="5">{{ $lines->first()->request_title }}
                        @if ($lines->first()->delivered_on)<span class="muted" style="font-weight: normal;"> · toimitettu {{ $date($lines->first()->delivered_on) }}</span>@endif
                    </td>
                </tr>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line->description }} <span class="muted">· {{ $line->type->label() }}</span></td>
                        <td class="num">{{ $number($line->quantity) }} {{ $line->unit }}</td>
                        <td class="num">{{ $euros($line->unit_price_cents) }}</td>
                        <td class="num">{{ $number($line->vat_rate_basis_points / 100) }} %</td>
                        <td class="num">{{ $euros($line->netCents()) }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

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
</body>
</html>
