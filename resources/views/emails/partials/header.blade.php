<div style=";color:#fff;padding:16px 20px; align-items:center; display:flex; flex-direction:column;">
    <img 
        src="https://adocare.sk/logo_dark.svg" 
        alt="{{ config('app.name') }}" 
        style="height:28px; display:block;"
    >

    @if (!empty($headerSubtitle))
        <p style="margin:6px 0 0;font-size:12px;opacity:.85;">
            {{ $headerSubtitle }}
        </p>
    @endif
</div>