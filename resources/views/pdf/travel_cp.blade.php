<!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #000; }
        .title { text-align: center; font-weight: 700; font-size: 16px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .sign-wrap { margin-top: 48px; width: 45%; margin-left: 55%; text-align: center; }
        .signature-box { height: 70px; position: relative; margin-bottom: 6px; }
        .signature { max-width: 170px; max-height: 64px; }
        .line { border-top: 1px solid #000; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="title">CESTOVNY PRIKAZ</div>

    <table>
        <tbody>
            <tr>
                <td colspan="2">
                    Cestovny prikaz:
                    <strong>
                        {{ str_pad((string) ($cpData['user_id'] ?? ''), 3, '0', STR_PAD_LEFT) }}{{ $cpData['month'] ?? '' }}{{ $cpData['year'] ?? '' }}
                    </strong>
                </td>
            </tr>
            <tr>
                <td style="width: 50%;">
                    <strong>Zamestnavatel:</strong><br>
                    Nazov: {{ $cpData['company_name'] ?? '' }}<br>
                    ICO: {{ $cpData['ico'] ?? '' }}
                </td>
                <td style="width: 50%;">
                    <strong>Zamestnanec:</strong><br>
                    Meno: {{ $cpData['user_name'] ?? '' }}<br>
                    Funkcia: Terenna zdravotna sestra
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <strong>Ucel pracovnych ciest:</strong><br>
                    Zdravotna starostlivost o pacientov v domacom prostredi
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <strong>Miesto vykonu prace:</strong><br>
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
                    <strong>Dopravny prostriedok:</strong><br>
                    {{ $cpData['car_model'] ?? '' }}<br>
                    SPZ: {{ $cpData['car_license_plate'] ?? '' }}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <strong>Predpokladane naklady:</strong><br>
                    Podla skutocneho vykonu
                </td>
            </tr>
        </tbody>
    </table>

    <table>
        <tbody>
            <tr>
                <td style="width: 50%;">
                    Datum:<br>
                    <strong>{{ !empty($cpData['lastday_previous_month']) ? \Carbon\Carbon::parse($cpData['lastday_previous_month'])->format('d.m.Y') : '' }}</strong>
                </td>
                <td style="width: 50%;">
                    Schvalil:<br>
                    <strong>{{ $cpData['representative_name'] ?? '' }}</strong>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="sign-wrap">
        <div class="signature-box">
            @if(!empty($signatureDataUri))
                <img src="{{ $signatureDataUri }}" alt="Podpis" class="signature">
            @endif
        </div>
        <div class="line"></div>
        podpis schvalujuceho
    </div>
</body>
</html>
