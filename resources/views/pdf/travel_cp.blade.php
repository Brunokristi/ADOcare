<!doctype html>
<html lang="sk">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            /* margin: 14mm; */
        }



        html,
        body {
            margin: 0;
            padding: 0;
            width: auto;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff;
            box-sizing: border-box;
        }

        html{
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
            font-size: 16px;
            margin-bottom: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }

        td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .sign-wrap {
            margin-top: 48px;
            width: 45%;
            margin-left: 55%;
            text-align: center;
        }

        .signature-box {
            height: 70px;
            position: relative;
            margin-bottom: 6px;
        }

        .signature {
            max-width: 170px;
            max-height: 64px;
        }

        .line {
            border-top: 1px solid #000;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="title">CESTOVNÝ PRÍKAZ</div>

        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                        Cestovný príkaz:
                        <strong>
                            {{ str_pad((string) ($cpData['user_id'] ?? ''), 3, '0', STR_PAD_LEFT) }}{{ $cpData['month'] ?? '' }}{{ $cpData['year'] ?? '' }}
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td style="width: 50%;">
                        <strong>Zamestnávateľ:</strong><br>
                        Názov: {{ $cpData['company_name'] ?? '' }}<br>
                        IČO: {{ $cpData['ico'] ?? '' }}
                    </td>
                    <td style="width: 50%;">
                        <strong>Zamestnanec:</strong><br>
                        Meno: {{ $cpData['user_name'] ?? '' }}<br>
                        Funkcia: {{ $cpData['job_title'] ?? 'Terénna zdravotná sestra' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Účel pracovných ciest:</strong><br>
                        {{ $cpData['trip_purpose'] ?? 'Zdravotná starostlivosť o pacientov v domácom prostredí' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Miesto výkonu práce:</strong><br>
                        {{ $cpData['city'] ?? '' }} a okolie
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Obdobie platnosti:</strong><br>
                        od {{ !empty($cpData['start_date']) ? \Carbon\Carbon::parse($cpData['start_date'])->format('d.m.Y') : '' }}<br>
                        do {{ !empty($cpData['end_date']) ? \Carbon\Carbon::parse($cpData['end_date'])->format('d.m.Y') : '' }}
                    </td>
                    <td>
                        <strong>Dopravný prostriedok:</strong><br>
                        {{ $cpData['car_model'] ?? '' }}<br>
                        ŠPZ: {{ $cpData['car_license_plate'] ?? '' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Predpokladané náklady:</strong><br>
                        Podľa skutočného výkonu
                    </td>
                </tr>
            </tbody>
        </table>

        <table>
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        Dátum:<br>
                        <strong>{{ !empty($cpData['lastday_previous_month']) ? \Carbon\Carbon::parse($cpData['lastday_previous_month'])->format('d.m.Y') : '' }}</strong>
                    </td>
                    <td style="width: 50%;">
                        Schválil:<br>
                        <strong>{{ $cpData['representative_name'] ?? '' }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="sign-wrap">
            <div class="signature-box">
                @if (!empty($signatureDataUri))
                    <img src="{{ $signatureDataUri }}" alt="Podpis" class="signature">
                @endif
            </div>
            <div class="line"></div>
            podpis schvalujúceho
        </div>
    </div>
</body>

</html>
