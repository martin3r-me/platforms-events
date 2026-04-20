<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packliste: {{ $list->title }}</title>
    <style>
        body { margin: 0; font-family: 'DM Sans', -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; font-size: 0.95rem; }
        .wrap { max-width: 640px; margin: 12px auto; padding: 0 12px; }
        .card { background: white; border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        h1 { font-size: 1.2rem; margin: 0 0 4px 0; }
        .meta { color: #64748b; font-size: 0.8rem; }
        .item { display: flex; align-items: center; padding: 10px 4px; border-bottom: 1px solid #f1f5f9; gap: 12px; }
        .item.done { opacity: 0.5; text-decoration: line-through; }
        .status-btn { padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.75rem; cursor: pointer; background: white; font-weight: 600; }
        .status-btn.open { background: #f8fafc; }
        .status-btn.picked { background: #dbeafe; color: #1e40af; }
        .status-btn.packed { background: #cffafe; color: #155e75; }
        .status-btn.loaded { background: #dcfce7; color: #14532d; }
        .progress { height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; margin-top: 8px; }
        .progress-bar { height: 100%; background: #16a34a; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ $list->title }}</h1>
            <div class="meta">Event {{ $event->event_number }} · {{ $list->items->count() }} Positionen</div>
            <div class="progress"><div class="progress-bar" id="progress-bar" style="width: 0%;"></div></div>
        </div>

        <div class="card">
            @foreach($items as $item)
                <div class="item" id="item-{{ $item->id }}">
                    <div style="flex: 1;">
                        <div style="font-weight: 600;">{{ $item->name }}</div>
                        <div class="meta">{{ $item->gruppe }} · {{ $item->quantity }}× {{ $item->gebinde }}</div>
                    </div>
                    <button class="status-btn {{ $item->status }}" onclick="toggleStatus({{ $item->id }}, '{{ $item->status }}')">
                        {{ match ($item->status) {
                            'open'   => '○ Offen',
                            'picked' => '✓ Gepickt',
                            'packed' => '▣ Gepackt',
                            'loaded' => '⇨ Verladen',
                            default  => $item->status,
                        } }}
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        const token = @json($list->token);

        function nextStatus(current) {
            return { open: 'picked', picked: 'packed', packed: 'loaded', loaded: 'open' }[current] ?? 'open';
        }

        function toggleStatus(itemId, current) {
            const next = nextStatus(current);
            fetch(`/picking/${token}/items/${itemId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ status: next }),
            }).then(r => r.json()).then(res => {
                if (res.ok) {
                    window.location.reload();
                }
            });
        }

        function refreshProgress() {
            fetch(`/picking/${token}/progress`).then(r => r.json()).then(res => {
                document.getElementById('progress-bar').style.width = res.percent + '%';
            });
        }

        refreshProgress();
        setInterval(refreshProgress, 5000);
    </script>
</body>
</html>
