<!doctype html>
<html lang="sk">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: auto;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
            box-sizing: border-box;
        }

        html {
            margin: 14mm;
        }

        * {
            box-sizing: border-box;
        }

        .document-content {
            width: auto;
            max-width: none;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .title {
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .header-info {
            margin-bottom: 8px;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
            text-align: left;
        }

        td,
        th {
            border: 1px solid #000;
            padding: 3px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
            line-height: 1.2;
        }

        th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: left;
        }

        .entry {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="title">DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI</div>
        <div class="title">(Záznamy o poskytnutej starostlivosti)</div>

        <!-- HEADER -->
        <div class="header-info">
            <strong>Pacient:</strong> {{ $dekurzData['patient_name'] ?? '' }} |
            <strong>RČ:</strong> {{ $dekurzData['patient_birth_number'] ?? '' }} |
            <strong>Sestra:</strong> {{ $dekurzData['user_name'] ?? '' }}
        </div>

        <!-- VISITS TABLE -->
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Dátum</th>
                    <th style="width: 12%;">Čas príchodu</th>
                    <th style="width: 12%;">Čas odchodu</th>
                    <th style="width: 30%;">Správa</th>
                    <th style="width: 17%;">Terénny čas</th>
                    <th style="width: 17%;">Admin. čas</th>
                </tr>
            </thead>
            <tbody>
                @if(!empty($dekurzData['visits']) && is_array($dekurzData['visits']))
                    @foreach($dekurzData['visits'] as $visit)
                        <tr class="entry">
                            <td>
                                @if(!empty($visit['date']))
                                    {{ \Carbon\Carbon::parse($visit['date'])->format('d.m.Y') }}
                                @endif
                            </td>
                            <td>{{ $visit['time_from'] ?? '' }}</td>
                            <td>{{ $visit['time_to'] ?? '' }}</td>
                            <td>{{ $visit['note'] ?? $visit['description'] ?? '' }}</td>
                            <td>{{ $visit['terrain_time'] ?? '0' }} h</td>
                            <td>{{ $visit['administrative_time'] ?? '0' }} h</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="6" style="text-align: center;">Žiadne záznamy</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <!-- SUMMARY -->
        @if(!empty($dekurzData['summary']))
            <table>
                <tbody>
                    <tr>
                        <td>
                            <strong>Zhrnutie:</strong><br/>
                            {{ $dekurzData['summary'] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif

        <!-- SIGNATURE -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 100%; text-align: center;">
                        <br/>
                        @if(!empty($signatureDataUri))
                            <img src="{{ $signatureDataUri }}" alt="Podpis" style="max-width: 150px; max-height: 50px;">
                        @endif
                        <div style="border-top: 1px solid #000; margin-top: 4px;">{{ $dekurzData['user_name'] ?? '' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>
