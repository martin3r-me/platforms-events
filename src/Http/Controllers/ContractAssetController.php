<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liefert Vertrags-Assets (Bilder) ueber signierte URLs aus beliebigen Disks.
 * Die URL wird vom ContractRenderer::resolveAssetUrls() fuer mode='web' erzeugt
 * und ist durch Laravel's signed-Middleware gegen Manipulation geschuetzt.
 * Damit funktionieren Bilder in der oeffentlichen Vertrags-Ansicht auch dann,
 * wenn die Disk keine nativen URLs anbietet (z.B. 'local' oder 'public' ohne
 * storage:link).
 */
class ContractAssetController extends Controller
{
    protected const ALLOWED_DISKS = ['public', 'local', 's3'];
    protected const ALLOWED_PATH_PREFIX = 'events/contract-assets/';

    public function show(Request $request, string $disk)
    {
        abort_unless(in_array($disk, self::ALLOWED_DISKS, true), 404);

        $path = (string) $request->query('path', '');
        abort_if($path === '' || !str_starts_with($path, self::ALLOWED_PATH_PREFIX), 404);

        $storage = Storage::disk($disk);
        abort_unless($storage->exists($path), 404);

        $mime = $storage->mimeType($path) ?: 'application/octet-stream';

        return new StreamedResponse(function () use ($storage, $path) {
            $stream = $storage->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            } else {
                echo $storage->get($path);
            }
        }, 200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
