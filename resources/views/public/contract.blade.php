<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Vertrag {{ $event->event_number }}</title>
    <style>
        body { margin: 0; font-family: 'DM Sans', -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 880px; margin: 20px auto; padding: 0 16px; }
        .card { background: white; border-radius: 8px; padding: 28px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        h1 { font-size: 1.4rem; margin: 0 0 8px 0; }
        .meta { color: #64748b; font-size: 0.85rem; margin-bottom: 20px; }
        .body { white-space: pre-wrap; font-size: 0.9rem; line-height: 1.6; border-top: 1px solid #e2e8f0; padding-top: 16px; margin-top: 10px; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; border: none; margin-right: 8px; }
        .btn-primary { background: #16a34a; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .status { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>
                @switch($contract->type)
                    @case('nutzungsvertrag') Nutzungsvertrag @break
                    @case('optionsbestaetigung') Optionsbestätigung @break
                    @default {{ ucfirst($contract->type) }}
                @endswitch
            </h1>
            <div class="meta">
                {{ $event->event_number }} · v{{ $contract->version }} ·
                @php
                    $map = ['draft' => ['#94a3b8', 'Entwurf'], 'sent' => ['#2563eb', 'Versandt'], 'signed' => ['#16a34a', 'Unterzeichnet'], 'rejected' => ['#ef4444', 'Abgelehnt']];
                    $st = $map[$contract->status] ?? ['#94a3b8', $contract->status];
                @endphp
                <span class="status" style="background: {{ $st[0] }}22; color: {{ $st[0] }};">{{ $st[1] }}</span>
            </div>

            @if(session('status'))
                <div style="padding: 12px; background: #dcfce7; border: 1px solid #86efac; border-radius: 6px; color: #166534; font-size: 0.85rem; margin-bottom: 16px;">
                    {{ session('status') }}
                </div>
            @endif

            <div class="body">{{ $contract->content['text'] ?? '' }}</div>

            @if($contract->status === 'sent')
                <form method="POST" action="{{ route('events.public.contract.respond', ['token' => $contract->token]) }}" style="margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                    @csrf
                    <button type="submit" name="action" value="sign" class="btn btn-primary">Unterschreiben</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger">Ablehnen</button>
                </form>
            @elseif($contract->status === 'signed')
                <div style="margin-top: 24px; padding: 16px; background: #dcfce7; border-radius: 6px; color: #166534;">
                    Vertrag unterzeichnet am {{ $contract->signed_at?->format('d.m.Y H:i') }}.
                </div>
            @elseif($contract->status === 'rejected')
                <div style="margin-top: 24px; padding: 16px; background: #fef2f2; border-radius: 6px; color: #991b1b;">
                    Vertrag abgelehnt.
                </div>
            @endif
        </div>
    </div>
</body>
</html>
