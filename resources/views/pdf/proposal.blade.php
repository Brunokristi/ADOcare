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
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
            text-align: left;
        }

        td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .no-border {
            border: none;
            padding: 0;
        }

        .signature-block {
            margin-top: 16px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            text-align: center;
            font-size: 10px;
        }

        .signature-box {
            display: flex;
            flex-direction: column;
        }

        .signature-area {
            height: 50px;
            margin-bottom: 6px;
            position: relative;
        }

        .signature {
            max-width: 140px;
            max-height: 50px;
        }

        .line {
            border-top: 1px solid #000;
            margin-top: 2px;
            height: 0;
        }

        .text-justify {
            text-align: justify;
        }

        .compact-list {
            margin: 0;
            padding-left: 16px;
        }

        .compact-list li {
            margin: 1px 0;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="title">NÁVRH NA PRIJATIE DO OŠETROVATEĽSKEJ STAROSTLIVOSTI</div>

        <!-- PATIENT INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 60%;">
                        Meno a priezvisko:<br/>
                        <strong>{{ $proposalData['patient_name'] ?? '' }}</strong>
                    </td>
                    <td style="width: 40%;">
                        Rodné číslo:<br/>
                        <strong>{{ $proposalData['patient_birth_number'] ?? '' }}</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Adresa:<br/>
                        <strong>{{ $proposalData['patient_address'] ?? '' }}</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        P.č. poistenca:<br/>
                        <strong>{{ $proposalData['insurance_code'] ?? '' }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- PROVIDER INFO -->
        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                        Poskytovateľ:<br/>
                        <strong>{{ $proposalData['company_name'] ?? '' }}<br/>{{ $proposalData['company_address'] ?? '' }}</strong>
                    </td>
                </tr>
                <tr>
                    <td style="width: 50%;">
                        Ordinácia:<br/>
                        <strong>{{ $proposalData['user_name'] ?? '' }}</strong>
                    </td>
                    <td style="width: 50%;">
                        Lekár:<br/>
                        <strong>{{ $proposalData['doctor_name'] ?? '' }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- DIAGNOSIS -->
        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                        <strong>Diagnózy lekára:</strong>
                        @if(!empty($proposalData['diagnosis']))
                            <ul class="compact-list">
                                @foreach($proposalData['diagnosis'] as $diagnosis)
                                    <li>{{ $diagnosis }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Zdravotné diagnózy sestry:</strong>
                        @if(!empty($proposalData['nurse_diagnosis']))
                            <ul class="compact-list">
                                @foreach($proposalData['nurse_diagnosis'] as $diagnosis)
                                    <li>{{ $diagnosis }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- PLAN -->
        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                        <strong>Indikácia:</strong><br/>
                        {{ $proposalData['epicrisis'] ?? '' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Plán ošetrovateľskej starostlivosti:</strong><br/>
                        {{ $proposalData['care_plan'] ?? '' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Očakávaná dĺžka starostlivosti:</strong><br/>
                        {{ $proposalData['expected_duration'] ?? '' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Plánované výkony:</strong>
                        @if(!empty($proposalData['procedures']))
                            <ul class="compact-list">
                                @foreach($proposalData['procedures'] as $proc)
                                    <li>{{ $proc['code'] ?? '' }} – {{ $proc['frequency'] ?? '' }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- DATE -->
        <table>
            <tbody>
                <tr>
                    <td>
                        Dátum:<br/>
                        <strong>
                            @if(!empty($proposalData['date']))
                                {{ \Carbon\Carbon::parse($proposalData['date'])->format('d.m.Y') }}
                            @endif
                        </strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SIGNATURES -->
        <div class="signature-block">
            <div class="signature-box">
                <div class="signature-area">
                    @if(!empty($stampDataUri))
                        <img src="{{ $stampDataUri }}" alt="Pečiatka" class="signature">
                    @endif
                </div>
                <div class="line"></div>
                <div>{{ $proposalData['doctor_name'] ?? '' }}</div>
                <div style="font-size: 9px;">Lekár</div>
            </div>
            <div class="signature-box">
                <div class="signature-area">
                    @if(!empty($signatureDataUri))
                        <img src="{{ $signatureDataUri }}" alt="Podpis" class="signature">
                    @endif
                </div>
                <div class="line"></div>
                <div>{{ $proposalData['representative_name'] ?? '' }}</div>
                <div style="font-size: 9px;">Poskytovateľ</div>
            </div>
        </div>
    </div>
</body>

</html>
