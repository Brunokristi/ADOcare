@extends('emails.layout')

@section('content')
    <p>Dobrý deň,</p>

    <p>
        boli pre Vás pripravené nasledujúce dokumenty, ktoré si môžete zobraziť alebo stiahnuť prostredníctvom odkazov nižšie:
    </p>

    @php
        $documentTypeLabels = [
            'cp' => 'Cestovný príkaz',
            'dzc' => 'Denný záznam ciest',
            'proposal' => 'Návrh',
            'agreement' => 'Dohoda',
            'dekurz' => 'Dekurz',
            'leave' => 'Prepúšťacia správa',
            'record' => 'Ošetrovateľský záznam',
            'scan' => 'Lekársky nález',
            'kilometers_batch' => 'Dávka kilometrov',
            'points_batch' => 'Dávka bodov',
            'invoice' => 'Faktúra',
            'other' => 'Iné',
        ];
    @endphp

    @foreach ($documents as $document)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:8px;">
            <tr>
                <td
                    style="
                    background:#DEECEF;
                    border-radius:10px;
                    padding:10px 12px;
                    color:#575252;
                    font-size:12px;
                    line-height:1.4;
                ">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <!-- Icon -->
                            <td style="width:22px; vertical-align:middle; padding-right:8px;">
                                <img src="https://adocare.sk/file.svg" width="16" height="16" alt="doc" style="display:block;fill:#575252;">
                            </td>

                            <!-- Single-line content -->
                            <td style="vertical-align:middle; color:#575252;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <!-- LEFT: content -->
                                        <td style="vertical-align:middle; color:#575252; font-size:12px;">
                                            <strong>{{ $document['name'] ?? 'Dokument' }}</strong>
                                            &nbsp;•&nbsp;
                                            {{ $documentTypeLabels[$document['type'] ?? ''] ?? ($document['type'] ?? '-') }}
                                            &nbsp;•&nbsp;
                                            {{ $document['period'] ?? '-' }}
                                        </td>

                                        <!-- RIGHT: link -->
                                        <td style="vertical-align:middle; text-align:right; white-space:nowrap;">
                                            <a href="{{ $document['view_link'] }}" target="_blank" rel="noopener noreferrer" style="color:#575252; text-decoration:underline; font-size:12px;">
                                                Zobraziť
                                            </a>
                                            @if (!empty($document['download_link']))
                                                <span style="color:#575252; font-size:12px;">&nbsp;|&nbsp;</span>
                                                <a href="{{ $document['download_link'] }}" target="_blank" rel="noopener noreferrer" style="color:#575252; text-decoration:underline; font-size:12px;">
                                                    Stiahnuť
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    <p>S pozdravom,<br>{{ $senderName ?? '' }}</p>

    <p style="color:#575252; margin-top:20px; font-size:12px; line-height:1.5;">
        <span style="color:#575252;">{{ $companyName ?? '' }}</span><br>

        <span style="color:#575252;">
            {{ collect([$companyAddress ?? null, $companyCity ?? null])->filter()->implode(', ') }}
        </span><br>

        <a href="mailto:{{ $companyEmail }}" style="color:#575252 !important; text-decoration:none !important;">
            {{ $companyEmail ?? '' }}
        </a><br>

        <a href="tel:{{ $companyPhone }}" style="color:#575252 !important; text-decoration:none !important;">
            {{ $companyPhone ?? '' }}
        </a>
    </p>
@endsection
