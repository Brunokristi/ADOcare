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

        .subtitle {
            text-align: center;
            font-weight: 700;
            font-size: 11px;
            margin-bottom: 6px;
            margin-top: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 4px;
            text-align: left;
        }

        td {
            border: 1px solid #000;
            padding: 3px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
            line-height: 1.2;
        }

        .label {
            font-weight: 700;
            width: 30%;
        }

        .value {
            font-family: monospace;
            white-space: pre-wrap;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="title">MEDICÍNSKY ZÁZNAM</div>

        <!-- PATIENT INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        <strong>Pacient:</strong> {{ $recordData['patient_name'] ?? '' }}
                    </td>
                    <td style="width: 50%;">
                        <strong>RČ:</strong> {{ $recordData['patient_birth_number'] ?? '' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Dátum:</strong> {{ $recordData['date'] ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- VITALS AND OBSERVATIONS -->
        <div class="subtitle">Pozorovaní a zistenia</div>
        <table>
            <tbody>
                @if(!empty($recordData['form_fields']) && is_array($recordData['form_fields']))
                    @foreach($recordData['form_fields'] as $field)
                        <tr>
                            <td class="label">{{ $field['label'] ?? $field['name'] ?? '' }}</td>
                            <td class="value">{{ $field['value'] ?? '' }}</td>
                        </tr>
                    @endforeach
                @elseif(!empty($recordData['observations']))
                    <tr>
                        <td colspan="2">
                            {{ $recordData['observations'] ?? '' }}
                        </td>
                    </tr>
                @else
                    <tr>
                        <td colspan="2">Žiadne záznamy</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <!-- NOTES -->
        @if(!empty($recordData['notes']))
            <div class="subtitle">Poznámky</div>
            <table>
                <tbody>
                    <tr>
                        <td>{{ $recordData['notes'] }}</td>
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
                        <div style="border-top: 1px solid #000;">{{ $recordData['user_name'] ?? '' }}</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>
