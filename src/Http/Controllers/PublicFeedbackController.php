<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Events\Models\FeedbackEntry;
use Platform\Events\Models\FeedbackLink;

class PublicFeedbackController extends Controller
{
    public function show(Request $request, string $token)
    {
        $link = FeedbackLink::with('event')->where('token', $token)->firstOrFail();
        if (!$link->is_active) {
            abort(404);
        }
        $link->incrementViews();

        return view('events::public.feedback', [
            'link'  => $link,
            'event' => $link->event,
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $link = FeedbackLink::where('token', $token)->firstOrFail();
        if (!$link->is_active) {
            abort(404);
        }

        $data = $request->validate([
            'name'                => 'nullable|string|max:255',
            'rating_overall'      => 'nullable|integer|min:1|max:5',
            'rating_location'     => 'nullable|integer|min:1|max:5',
            'rating_catering'     => 'nullable|integer|min:1|max:5',
            'rating_organization' => 'nullable|integer|min:1|max:5',
            'comment'             => 'nullable|string',
        ]);

        FeedbackEntry::create(array_merge($data, [
            'team_id'          => $link->team_id,
            'feedback_link_id' => $link->id,
            'event_id'         => $link->event_id,
            'ip_address'       => $request->ip(),
            'user_agent'       => $request->userAgent(),
        ]));

        return redirect()->route('events.public.feedback', ['token' => $token])
            ->with('status', 'Vielen Dank für dein Feedback!');
    }
}
