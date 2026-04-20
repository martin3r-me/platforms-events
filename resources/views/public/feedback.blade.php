<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback: {{ $event->name }}</title>
    <style>
        body { margin: 0; font-family: 'DM Sans', -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
        .wrap { max-width: 520px; margin: 20px auto; padding: 0 16px; }
        .card { background: white; border-radius: 8px; padding: 28px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        h1 { font-size: 1.3rem; margin: 0 0 4px 0; }
        .meta { color: #64748b; font-size: 0.85rem; margin-bottom: 20px; }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: #475569; margin: 16px 0 6px; }
        input[type=text], textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 0.9rem; box-sizing: border-box; }
        .stars { display: flex; gap: 4px; }
        .stars input { display: none; }
        .stars label { margin: 0; font-size: 1.5rem; color: #cbd5e1; cursor: pointer; padding: 0; }
        .stars input:checked ~ label,
        .stars label:hover,
        .stars label:hover ~ label { color: #fbbf24; }
        .stars { direction: rtl; justify-content: flex-end; }
        .btn { display: inline-block; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #16a34a; color: white; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>{{ $link->label }}</h1>
            <div class="meta">Feedback zu: {{ $event->name }}</div>

            @if(session('status'))
                <div style="padding: 12px; background: #dcfce7; border: 1px solid #86efac; border-radius: 6px; color: #166534; font-size: 0.9rem; margin-bottom: 16px;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('events.public.feedback.submit', ['token' => $link->token]) }}">
                @csrf

                <label>Name (optional)</label>
                <input type="text" name="name" placeholder="Dein Name">

                @foreach([
                    'rating_overall' => 'Gesamteindruck',
                    'rating_location' => 'Location',
                    'rating_catering' => 'Catering',
                    'rating_organization' => 'Organisation',
                ] as $field => $label)
                    <label>{{ $label }}</label>
                    <div class="stars">
                        @for($i = 5; $i >= 1; $i--)
                            <input type="radio" name="{{ $field }}" id="{{ $field }}_{{ $i }}" value="{{ $i }}">
                            <label for="{{ $field }}_{{ $i }}">★</label>
                        @endfor
                    </div>
                @endforeach

                <label>Kommentar (optional)</label>
                <textarea name="comment" rows="4" placeholder="Was war gut? Was können wir verbessern?"></textarea>

                <button type="submit" class="btn">Feedback absenden</button>
            </form>
        </div>
    </div>
</body>
</html>
