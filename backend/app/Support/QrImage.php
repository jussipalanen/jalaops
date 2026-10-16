<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * A QR code as a PNG data URI for the PDF and HTML documents. It's inline, so
 * dompdf needs no remote files and the HTML page works offline. PNG, because
 * dompdf draws SVG images only in part.
 */
class QrImage
{
    public static function dataUri(string $text): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            // Medium error correction, as payment QR codes require.
            'eccLevel' => EccLevel::M,
            'scale' => 8,
            'quietzoneSize' => 4,
        ])))->render($text);
    }
}
