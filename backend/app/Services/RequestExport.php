<?php

namespace App\Services;

use App\Models\ServiceRequest;
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
        return view('exports.request', ['request' => $request, 'forPdf' => false])->render();
    }

    public function pdf(ServiceRequest $request): string
    {
        $options = new Options;
        // Everything the document needs is inline (the signature is a data URI),
        // so dompdf never fetches remote files.
        $options->setIsRemoteEnabled(false);
        // DejaVu Sans ships with dompdf and has the Finnish characters.
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.request', ['request' => $request, 'forPdf' => true])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * "pyynto-12-vaihda-ilmansuodatin.pdf"
     */
    public function filename(ServiceRequest $request, string $extension): string
    {
        $slug = Str::limit(Str::slug($request->title), 60, '');

        return trim("pyynto-{$request->id}-{$slug}", '-').".{$extension}";
    }
}
