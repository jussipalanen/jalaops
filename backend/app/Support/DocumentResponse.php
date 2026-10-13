<?php

namespace App\Support;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * A downloadable document (PDF or HTML). With $inline the browser shows it in
 * the tab ("Esikatsele") instead of saving it.
 */
class DocumentResponse
{
    public static function make(string $content, string $format, string $filename, bool $inline): Response
    {
        $headers = [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'text/html; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $filename),
            'Cache-Control' => 'private, no-cache',
        ];

        if ($format === 'html') {
            // The page opens on the API's origin: it may show inline styles and
            // data: images, and nothing else (no scripts, no requests).
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; img-src data:";
        }

        return response($content, 200, $headers);
    }
}
