<?php

namespace App\Services;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * An invoice as an A4 PDF or a self-contained HTML page, both made from the
 * same template (resources/views/exports/invoice.blade.php).
 */
class InvoiceExport
{
    public function html(Invoice $invoice): string
    {
        return $this->view($invoice, false);
    }

    public function pdf(Invoice $invoice): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->view($invoice, true), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        PdfPageNumbers::add($dompdf, "Lasku {$invoice->number}");

        return (string) $dompdf->output();
    }

    /**
     * "lasku-1042.pdf"
     */
    public function filename(Invoice $invoice, string $extension): string
    {
        return "lasku-{$invoice->number}.{$extension}";
    }

    private function view(Invoice $invoice, bool $forPdf): string
    {
        return view('exports.invoice', [
            'invoice' => $invoice->loadMissing('lines'),
            'seller' => config('billing.seller'),
            // The logo is inline, so the HTML file works offline and dompdf
            // needs no remote files.
            'logo' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('images/logo.png'))),
            'forPdf' => $forPdf,
        ])->render();
    }
}
