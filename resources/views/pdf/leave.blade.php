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
            font-size: 11px;
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
            font-size: 14px;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
            text-align: left;
        }

        td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .signature-block {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
            gap: 80px;
            text-align: center;
            font-size: 11px;
        }

        .signature-box {
            min-width: 150px;
        }

        .signature-area {
            height: 60px;
            margin-bottom: 8px;
            position: relative;
        }

        .signature {
            max-width: 170px;
            max-height: 60px;
        }

        .line {
            border-top: 1px solid #000;
            margin-top: 4px;
            height: 0;
        }

        .text-justify {
            text-align: justify;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="title">
            OŠETROVATEĽSKÁ PREPÚŠŤACIA SPRÁVA
        </div>

        <!-- PATIENT INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 60%;">
                        Pacient:<br/>
                        <strong>{{ $leaveData['patient_name'] ?? '' }}</strong>
                    </td>
                    <td style="width: 40%;">
                        Rodné číslo:<br/>
                        <strong>{{ $leaveData['patient_birth_number'] ?? '' }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- NURSE INFO -->
        <table>
            <tbody>
                <tr>
                    <td>
                        Sestra:<br/>
                        <strong>{{ $leaveData['user_name'] ?? '' }}</strong>
                    </td>
                    <td>
                        Dátum:<br/>
                        <strong>
                            @if(!empty($leaveData['date']))
                                {{ \Carbon\Carbon::parse($leaveData['date'])->format('d.m.Y') }}
                            @endif
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- NURSING SECTION -->
        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                        <strong>Problematika pacientov v domácej péči:</strong>
                        @if(!empty($leaveData['problems']))
                            <ul style="margin: 4px 0; padding-left: 20px;">
                                @foreach($leaveData['problems'] as $problem)
                                    <li>{{ $problem }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div>&nbsp;</div>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- OTHER FINDINGS -->
        <table>
            <tbody>
                <tr>
                    <td>
                        <strong>Iné zistenia:</strong><br/>
                        {{ $leaveData['other_findings'] ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- RESULTS -->
        <table>
            <tbody>
                <tr>
                    <td class="text-justify">
                        <strong>Výsledok domácej zdravotnej starostlivosti:</strong><br/>
                        {{ $leaveData['results'] ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- EDUCATION -->
        <table>
            <tbody>
                <tr>
                    <td class="text-justify">
                        <strong>Edukácia pacienta/rodiny:</strong><br/>
                        {{ $leaveData['education'] ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- RECEIVED ITEMS -->
        <table>
            <tbody>
                <tr>
                    <td>
                        <strong>Vybrané položky:</strong><br/>
                        {{ $leaveData['received'] ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SIGNATURE -->
        <div class="signature-block">
            <div class="signature-box">
                <div class="signature-area">
                    @if(!empty($signatureDataUri))
                        <img src="{{ $signatureDataUri }}" alt="Podpis" class="signature">
                    @endif
                </div>
                <div class="line"></div>
                <div>{{ $leaveData['user_name'] ?? '' }}</div>
                <div style="font-size: 10px;">podpis sestry</div>
            </div>
        </div>
    </div>
</body>

</html>
