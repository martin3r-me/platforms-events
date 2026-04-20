<?php

namespace Platform\Events\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Events\Models\PickItem;
use Platform\Events\Models\PickList;

class PublicPickListController extends Controller
{
    public function show(Request $request, string $token)
    {
        $list = PickList::with('items')->where('token', $token)->firstOrFail();

        return view('events::public.picklist', [
            'list'  => $list,
            'event' => $list->event,
            'items' => $list->items,
        ]);
    }

    public function updateItem(Request $request, string $token, int $itemId)
    {
        $list = PickList::where('token', $token)->firstOrFail();
        $item = PickItem::where('pick_list_id', $list->id)->findOrFail($itemId);

        $status = $request->input('status');
        if (!in_array($status, ['open', 'picked', 'packed', 'loaded'], true)) {
            return response()->json(['ok' => false, 'error' => 'invalid status'], 422);
        }

        $item->update([
            'status'    => $status,
            'picked_by' => $request->input('picked_by') ?? $item->picked_by,
            'picked_at' => in_array($status, ['picked', 'packed', 'loaded']) ? now() : null,
        ]);

        return response()->json(['ok' => true, 'status' => $status]);
    }

    public function progress(Request $request, string $token)
    {
        $list = PickList::where('token', $token)->firstOrFail();
        $total = $list->items()->count();
        $done  = $list->items()->whereIn('status', ['picked', 'packed', 'loaded'])->count();

        return response()->json([
            'total'       => $total,
            'done'        => $done,
            'percent'     => $total > 0 ? round(($done / $total) * 100) : 0,
            'list_status' => $list->status,
        ]);
    }
}
