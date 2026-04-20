<?php

namespace Platform\Events\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Zentraler PDF-Generator (DomPDF). Rendert eine Blade-View mit
 * Daten ins PDF und liefert eine Download-Response.
 *
 * Pragmatic: keine erweiterten Styling-Customizations; DomPDF mit
 * A4-Standard und deutscher Sprache.
 */
class PdfService
{
    public static function render(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'sans-serif');

        return $pdf->download($filename);
    }

    public static function stream(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'sans-serif');

        return $pdf->stream($filename);
    }
}
