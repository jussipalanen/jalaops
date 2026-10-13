<?php

namespace App\Services;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * An invoice as an A4 PDF (resources/views/exports/invoice.blade.php).
 */
class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('exports.invoice', ['invoice' => $invoice, 'seller' => config('billing.seller')])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
