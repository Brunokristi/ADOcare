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
            font-size: 12px;
            line-height: 1.35;
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
            font-size: 17px;
            line-height: 1.25;
            margin-bottom: 18px;
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
            padding: 10px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
            line-height: 1.45;
        }

        .signature-block {
            margin-top: 32px;
            display: table;
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            text-align: center;
            font-size: 14px;
            line-height: 1.35;
        }

        .signature-box {
            display: table-cell;
            vertical-align: top;
            padding-right: 28px;
        }

        .signature-box+.signature-box {
            padding-left: 28px;
            padding-right: 0;
        }

        .signature-area {
            height: 84px;
            margin-bottom: 12px;
            position: relative;
        }

        .signature {
            max-width: 170px;
            max-height: 64px;
        }

        .line {
            border-top: 1px solid #000;
            margin-top: 8px;
            height: 0;
        }

        .text-justify {
            text-align: justify;
            line-height: 1.5;
        }

        .signature-caption {
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="title">
            DOHODA O POSKYTOVANÍ ZDRAVOTNEJ STAROSTLIVOSTI V ROZSAHU<br />
            OŠETROVATEĽSKEJ STAROSTLIVOSTI
        </div>

        <!-- PATIENT INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 75%;">
                        Meno, priezvisko, titul poistenca:<br />
                        <strong>{{ $agreementData['patient_name'] ?? '' }}</strong>
                    </td>
                    <td style="width: 25%;">
                        Rodné číslo:<br />
                        <strong>{{ $agreementData['patient_birth_number'] ?? '' }}</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Miesto trvalého pobytu:<br />
                        <strong>{{ $agreementData['patient_address'] ?? '' }}</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Prechodný pobyt:
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Kontaktná osoba, zákonný zástupca:
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- PROVIDER INFO -->
        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                        Poskytovateľom ošetrovateľskej starostlivosti:<br />
                        <strong>ADOS</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        Názov a adresa:<br />
                        <strong>
                            {{ $agreementData['company_name'] ?? '' }},
                            {{ $agreementData['company_address'] ?? '' }},
                            {{ $agreementData['company_city'] ?? '' }}
                        </strong>
                    </td>
                </tr>
                <tr>
                    <td style="width: 50%;">
                        Meno, priezvisko, titul odborného zástupcu:<br />
                        <strong>{{ $agreementData['user_name'] ?? '' }}</strong>
                    </td>
                    <td style="width: 50%;">
                        Telefón:<br />
                        <strong>{{ $agreementData['user_contact'] ?? '' }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- AGREEMENT TEXT -->
        <table>
            <tbody>
                <tr>
                    <td class="text-justify">
                        Dohodu o poskytovaní zdravotnej starostlivosti v rozsahu
                        ošetrovateľskej starostlivosti uzatváram v zmysle § 12 zákona
                        č. 576/2004 Z. z. o zdravotnej starostlivosti, službách súvisiacich
                        s poskytovaním zdravotnej starostlivosti a o zmene a doplnení
                        niektorých zákonov v znení neskorších predpisov.
                        <br><br>
                        Vyhlasujem na svoju česť, že nemám súbežne uzavretú žiadnu dohodu
                        o poskytovaní zdravotnej starostlivosti v rozsahu ošetrovateľskej
                        starostlivosti s iným poskytovateľom ošetrovateľskej starostlivosti.
                        <br><br>
                        Svojím podpisom potvrdzujem, že som bol(a) riadne poučený(á) podľa
                        zákona č. 576/2004 Z. z. § 6 a dávam týmto informovaný súhlas na
                        poskytovanie zdravotnej starostlivosti uhrádzanej na základe
                        verejného zdravotného poistenia v rozsahu ošetrovateľskej
                        starostlivosti v ZSS v súvislosti s platnou legislatívou.
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- DATE AND LOCATION -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 75%;">
                        V:<br />
                        <strong>{{ $agreementData['branch_city'] ?? '' }}</strong>
                    </td>
                    <td style="width: 25%;">
                        Dátum:<br />
                        <strong>
                            @if (!empty($agreementData['date']))
                                {{ \Carbon\Carbon::parse($agreementData['date'])->format('d. m. Y') }}
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
                    @if (!empty($stampDataUri))
                        <img src="{{ $stampDataUri }}" alt="Pečiatka" class="signature">
                    @endif
                    @if (!empty($signatureDataUri))
                        <img src="{{ $signatureDataUri }}" alt="Podpis" class="signature">
                    @endif
                </div>
                <div class="line"></div>
                <div>{{ $agreementData['user_name'] ?? '' }}</div>
                <div class="signature-caption">odborný zástupca poskytovateľa ošetrovateľskej starostlivosti</div>
            </div>
            <div class="signature-box">
                <div class="signature-area"></div>
                <div class="line"></div>
                <div>podpis poistenca / zákonného zástupcu</div>
            </div>
        </div>
    </div>
</body>

</html>
