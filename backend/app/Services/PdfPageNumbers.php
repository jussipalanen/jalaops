<?php

namespace App\Services;

use Dompdf\Dompdf;

/**
 * Writes "Lasku 1042 · Sivu 2/3" at the bottom of every page of a rendered
 * PDF, so each page of a long document says what it belongs to.
 */
class PdfPageNumbers
{
    public static function add(Dompdf $dompdf, string $document): void
    {
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');

        // Bottom left, inside the 18 mm side margin and below the content.
        $canvas->page_text(51, $canvas->get_height() - 34, "{$document} · Sivu {PAGE_NUM}/{PAGE_COUNT}", $font, 7.5, [0.39, 0.45, 0.55]);
    }
}
