@extends('emails.layout')

@section('content')
    <p>Hello {{ $recipientName ?? 'there' }},</p>

    <p>{{ $body ?? 'This is a sample email using the generic layout.' }}</p>

    <p>Regards,<br>{{ config('app.name') }}</p>
@endsection
