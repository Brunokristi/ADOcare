@extends('emails.layout')

@section('content')
    <p>Dobrý deň,</p>

    <p>V prílohe odosielame odkazy na dokumenty</p>

    <ul>
        @foreach ($documents as $document)
            <li style="margin-bottom: 12px;">
                <strong>{{ $document['name'] ?? 'Dokument' }}</strong><br>
                Typ: {{ $document['type'] ?? '-' }}<br>
                Obdobie: {{ $document['period'] ?? '-' }}<br>
                <a href="{{ $document['view_link'] }}" target="_blank" rel="noopener noreferrer">Zobraziť dokument</a>
                &nbsp;|&nbsp;
                <a href="{{ $document['download_link'] }}" target="_blank" rel="noopener noreferrer">Stiahnuť dokument</a>
            </li>
        @endforeach
    </ul>

    <p>S pozdravom,<br>{{ $senderName ?? config('app.name') }}</p>
@endsection
