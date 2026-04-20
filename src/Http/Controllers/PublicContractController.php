<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Events\Models\Contract;

class PublicContractController extends Controller
{
    public function show(Request $request, string $token)
    {
        $contract = Contract::with('event')->where('token', $token)->firstOrFail();
        $contract->increment('view_count');
        $contract->update(['last_viewed_at' => now()]);

        return view('events::public.contract', [
            'contract' => $contract,
            'event'    => $contract->event,
        ]);
    }

    public function respond(Request $request, string $token)
    {
        $contract = Contract::where('token', $token)->firstOrFail();
        $action = $request->input('action');

        if ($action === 'sign') {
            $contract->update(['status' => 'signed', 'signed_at' => now()]);
        } elseif ($action === 'reject') {
            $contract->update(['status' => 'rejected']);
        }

        return redirect()->route('events.public.contract', ['token' => $token])->with('status', 'Vielen Dank.');
    }
}
