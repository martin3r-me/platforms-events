<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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

    /**
     * TinyMCE-Upload-Endpoint: speichert das Bild auf der bevorzugten Disk
     * (S3 wenn konfiguriert, sonst public) und liefert eine signierte URL
     * zurueck, die sowohl im Editor sofort funktioniert als auch spaeter zur
     * events-asset://-Form normalisiert werden kann.
     */
    public function upload(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'unauthenticated'], 401);

        $request->validate([
            'file' => 'required|file|image|max:10240',
        ]);

        $teamId = $user->currentTeam?->id ?? 0;
        $disk = config('filesystems.disks.s3.bucket') ? 's3' : 'public';

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
            $ext = 'png';
        }

        $filename = Str::random(16) . '.' . $ext;
        $dir = "events/contract-assets/team-{$teamId}";
        $storedPath = $file->storeAs($dir, $filename, $disk);

        if (!$storedPath || !Storage::disk($disk)->exists($storedPath)) {
            return response()->json(['error' => 'upload failed'], 500);
        }

        // Signierte URL: funktioniert sofort im Editor UND im Public-View
        $url = URL::temporarySignedRoute(
            'events.public.asset',
            now()->addHours(24),
            ['disk' => $disk, 'path' => $storedPath]
        );

        return response()->json([
            'location'   => $url,
            'disk'       => $disk,
            'path'       => $storedPath,
            'asset_ref'  => "events-asset://{$disk}/{$storedPath}",
        ]);
    }
}
