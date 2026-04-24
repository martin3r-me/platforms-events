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

    /**
     * Wie render(), haengt aber zusaetzliche PDF-Binaries (z. B. Raumgrundrisse)
     * per Ghostscript als Seiten ans Ende an. Wenn der Merge scheitert, wird
     * das Basis-PDF unveraendert ausgeliefert (siehe PdfFloorPlanMerger).
     *
     * @param list<string> $pdfAppendBinaries
     */
    public static function renderWithAppendedPdfs(string $view, array $data, string $filename, array $pdfAppendBinaries = []): Response
    {
        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'sans-serif');

        $binary = $pdf->output();
        if (!empty($pdfAppendBinaries)) {
            $binary = PdfFloorPlanMerger::append($binary, $pdfAppendBinaries);
        }

        return response($binary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($binary),
        ]);
    }
}
