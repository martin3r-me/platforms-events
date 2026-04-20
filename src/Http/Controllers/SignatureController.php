<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\DocumentSignature;
use Platform\Events\Models\Event;
use Platform\Events\Services\ActivityLogger;

class SignatureController extends Controller
{
    public function sign(Request $request, string $event)
    {
        $data = $request->validate([
            'role'      => 'required|in:left,right',
            'signature' => 'required|string', // data:image/png;base64,....
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;
        $eventModel = Event::resolveFromSlug($event, $team?->id);
        if (!$eventModel) abort(404);

        // Dokumenten-Hash aus dem relevanten Event-Snapshot (zum Zeitpunkt der Unterschrift).
        $hashData = json_encode([
            'event_id'     => $eventModel->id,
            'event_number' => $eventModel->event_number,
            'name'         => $eventModel->name,
            'sign_left'    => $eventModel->sign_left,
            'sign_right'   => $eventModel->sign_right,
        ]);
        $hash = hash('sha256', $hashData);

        $sig = DocumentSignature::updateOrCreate(
            ['event_id' => $eventModel->id, 'role' => $data['role']],
            [
                'team_id'          => $eventModel->team_id,
                'user_id'          => $user->id,
                'signature_image'  => $data['signature'],
                'document_hash'    => $hash,
                'ip_address'       => $request->ip(),
                'user_agent'       => $request->userAgent(),
                'signed_at'        => now(),
            ]
        );

        ActivityLogger::log($eventModel, 'signature', "Unterschrift {$data['role']} durch {$user->name}");

        return response()->json([
            'ok'        => true,
            'role'      => $sig->role,
            'user_name' => $user->name,
            'signed_at' => $sig->signed_at->format('d.m.Y H:i'),
        ]);
    }

    public function reset(Request $request, string $event, string $role)
    {
        if (!in_array($role, ['left', 'right'], true)) {
            abort(400);
        }

        $user = Auth::user();
        $team = $user->currentTeam;
        $eventModel = Event::resolveFromSlug($event, $team?->id);
        if (!$eventModel) abort(404);

        DocumentSignature::where('event_id', $eventModel->id)->where('role', $role)->delete();

        ActivityLogger::log($eventModel, 'signature', "Unterschrift {$role} zurueckgesetzt");

        return response()->json(['ok' => true]);
    }
}
