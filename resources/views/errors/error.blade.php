<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }} — Chyba</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    @vite(['resources/js/app.ts', 'resources/css/app.css'])
</head>

<body class="font-sans antialiased bg-white text-gray-900">
    <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-4">
        <div class="max-w-3xl w-full text-center">
            <h1 class="text-6xl font-extrabold text-gray-900">{{ $code ?? ($status ?? 'Chyba') }}</h1>
            <p class="mt-4 text-xl text-gray-700">{{ $title ?? 'Nastala chyba' }}</p>
            <p class="mt-2 text-sm text-gray-500">{{ $message ?? 'Ospravedlňujeme sa, požadovanú stránku sa nám nepodarilo spracovať.' }}</p>

            <div class="mt-6">
                <a href="{{ url('/') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Späť na hlavnú stránku
                </a>
            </div>

            @if (config('app.debug') && isset($exception))
                <div class="mt-6 text-left p-4 bg-gray-50 rounded-md text-xs text-gray-700">
                    <strong>Informácie pre vývojára:</strong>
                    <pre class="whitespace-pre-wrap">{{ $exception->getMessage() }}</pre>
                    <pre class="whitespace-pre-wrap">{{ $exception->getTraceAsString() }}</pre>
                </div>
            @endif
        </div>
    </div>
</body>

</html>
