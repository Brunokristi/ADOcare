@extends('emails.layout')

@section('title', $subject ?? 'Upozornenie na predplatné')

@section('content')
    <p>Dobrý deň,</p>

    <p>
        zasielame upozornenie na predplatné spoločnosti
        <strong>{{ $companyName ?? 'Neznáma spoločnosť' }}</strong>
    </p>

    @if(!empty($items) && is_array($items))
        <ul>
            @foreach($items as $item)
                <li>
                    <strong>{{ $item['title'] ?? 'Upozornenie' }}:</strong>
                    {{ $item['message'] ?? '' }}
                </li>
            @endforeach
        </ul>
    @endif

    <p>S pozdravom,</p>
    <p>ADOcare</p>
@endsection
