<?php

namespace Platform\Events\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Haengt zusaetzliche PDFs (z. B. Raumgrundrisse) an ein Basis-PDF an.
 * Nutzt Ghostscript (gs) via CLI. Wenn gs nicht verfuegbar oder der Merge
 * scheitert, wird das Basis-PDF unveraendert zurueckgegeben und eine
 * Warnung geloggt — das Angebot bleibt auslieferbar.
 */
class PdfFloorPlanMerger
{
    /**
     * Fuegt die gegebenen PDF-Anhaenge (Binaer-Inhalte) nach dem Basis-PDF ein.
     *
     * @param string        $baseBinary   Rohbinary des Basis-PDFs (DomPDF-Output)
     * @param list<string>  $appendBinaries Rohbinaries der anzuhaengenden PDFs (Reihenfolge = Reihenfolge im Ergebnis)
     */
    public static function append(string $baseBinary, array $appendBinaries): string
    {
        if (empty($appendBinaries)) {
            return $baseBinary;
        }

        $gs = self::ghostscriptBinary();
        if ($gs === null) {
            Log::warning('[Events/PDF] Ghostscript nicht gefunden; Grundriss-PDFs werden nicht angehaengt.');
            return $baseBinary;
        }

        $tmpDir  = sys_get_temp_dir();
        $created = [];

        $baseFile = self::writeTempPdf($tmpDir, 'pdfbase_', $baseBinary);
        $created[] = $baseFile;

        $inputs = [$baseFile];
        foreach ($appendBinaries as $i => $content) {
            if (!is_string($content) || $content === '') {
                continue;
            }
            $f = self::writeTempPdf($tmpDir, 'pdfapp_', $content);
            $created[] = $f;
            $inputs[] = $f;
        }

        // Nur Basis vorhanden (alle Anhaenge leer): keinen Merge-Lauf durchfuehren.
        if (count($inputs) === 1) {
            self::cleanup($created);
            return $baseBinary;
        }

        $outputFile = $tmpDir . DIRECTORY_SEPARATOR . 'pdfout_' . bin2hex(random_bytes(8)) . '.pdf';

        try {
            $cmd = array_merge(
                [
                    $gs,
                    '-dNOPAUSE',
                    '-dBATCH',
                    '-dQUIET',
                    '-sDEVICE=pdfwrite',
                    '-dPDFSETTINGS=/prepress',
                    '-sOutputFile=' . $outputFile,
                ],
                $inputs
            );

            $proc = new Process($cmd);
            $proc->setTimeout(45);
            $proc->run();

            if (!$proc->isSuccessful()) {
                Log::error('[Events/PDF] Ghostscript-Merge fehlgeschlagen', [
                    'exit'   => $proc->getExitCode(),
                    'stderr' => trim($proc->getErrorOutput()),
                ]);
                return $baseBinary;
            }

            if (!is_file($outputFile) || filesize($outputFile) === 0) {
                Log::error('[Events/PDF] Ghostscript-Merge lieferte leere Datei.');
                return $baseBinary;
            }

            $merged = @file_get_contents($outputFile);
            return $merged !== false && $merged !== '' ? $merged : $baseBinary;
        } catch (\Throwable $e) {
            Log::error('[Events/PDF] Exception beim Ghostscript-Merge', ['error' => $e->getMessage()]);
            return $baseBinary;
        } finally {
            @unlink($outputFile);
            self::cleanup($created);
        }
    }

    /**
     * Ermittelt den Ghostscript-Pfad. Erst PATH-lookup, danach gebraeuchliche
     * Installationspfade (Homebrew, Linux-Standards).
     */
    protected static function ghostscriptBinary(): ?string
    {
        // PATH-Lookup via "which"
        try {
            $which = new Process(['which', 'gs']);
            $which->setTimeout(3);
            $which->run();
            if ($which->isSuccessful()) {
                $path = trim($which->getOutput());
                if ($path !== '' && is_executable($path)) {
                    return $path;
                }
            }
        } catch (\Throwable $e) {
            // ignore, fall through to known paths
        }

        foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/opt/homebrew/bin/gs'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected static function writeTempPdf(string $dir, string $prefix, string $content): string
    {
        $path = $dir . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * @param list<string> $paths
     */
    protected static function cleanup(array $paths): void
    {
        foreach ($paths as $p) {
            @unlink($p);
        }
    }
}
