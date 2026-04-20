<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Events\Models\EmailLog;

class EmailTrackController extends Controller
{
    /**
     * Track Email-Open via 1x1-Pixel.
     */
    public function track(Request $request, string $token)
    {
        $email = EmailLog::where('tracking_token', $token)->first();
        if ($email && !$email->opened_at) {
            $email->update(['opened_at' => now(), 'status' => 'opened']);
        }

        // 1x1 transparent GIF
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return response($gif, 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
