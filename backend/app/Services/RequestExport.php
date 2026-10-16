<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Support\QrImage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;

/**
 * A request as a downloadable document: a self-contained HTML file or a PDF
 * made from the same template (resources/views/exports/request.blade.php).
 */
class RequestExport
{
    public function html(ServiceRequest $request): string
    {
        return $this->view($request, false);
    }

    public function pdf(ServiceRequest $request): string
    {
        $options = new Options;
        // Everything the document needs is inline (the signature and the QR code
        // are data URIs), so dompdf never fetches remote files.
        $options->setIsRemoteEnabled(false);
        // DejaVu Sans ships with dompdf and has the Finnish characters.
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->view($request, true), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        PdfPageNumbers::add($dompdf, "Huoltopyyntö #{$request->id}");

        return (string) $dompdf->output();
    }

    /**
     * The request's address in the app, e.g. https://jalaops.example/requests/12,
     * or null when FRONTEND_URL isn't set.
     */
    public function appUrl(ServiceRequest $request): ?string
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return $frontend !== '' ? "{$frontend}/requests/{$request->id}" : null;
    }

    /**
     * "pyynto-12-vaihda-ilmansuodatin.pdf"
     */
    public function filename(ServiceRequest $request, string $extension): string
    {
        $slug = Str::limit(Str::slug($request->title), 60, '');

        return trim("pyynto-{$request->id}-{$slug}", '-').".{$extension}";
    }

    private function view(ServiceRequest $request, bool $forPdf): string
    {
        $appUrl = $this->appUrl($request);

        return view('exports.request', [
            'request' => $request->loadMissing('items'),
            'appUrl' => $appUrl,
            // A QR code that opens the request in the app on a phone.
            'appQr' => $appUrl !== null ? QrImage::dataUri($appUrl) : null,
            'forPdf' => $forPdf,
        ])->render();
    }
}
