<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Platform\Events\Models\Contract;
use Platform\Events\Models\Event;
use Platform\Events\Services\PdfService;

class ContractPdfController extends Controller
{
    public function download(Request $request, string $event, int $contractId)
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $eventModel = Event::resolveFromSlug($event, $team?->id);
        if (!$eventModel) abort(404);

        $contract = Contract::where('event_id', $eventModel->id)->findOrFail($contractId);

        return PdfService::render('events::pdf.contract', [
            'event'    => $eventModel,
            'contract' => $contract,
        ], 'Vertrag-' . $eventModel->slug . '-v' . $contract->version . '.pdf');
    }
}
