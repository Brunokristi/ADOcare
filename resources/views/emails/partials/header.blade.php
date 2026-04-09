<div style="background:#1a202c;color:#fff;padding:16px 20px;">
    <h1 style="margin:0;font-size:20px;">{{ $headerTitle ?? config('app.name') }}</h1>
    @if (!empty($headerSubtitle))
        <p style="margin:0;font-size:12px;opacity:.85;">{{ $headerSubtitle }}</p>
    @endif
</div>
