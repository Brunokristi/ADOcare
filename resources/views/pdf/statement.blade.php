<!doctype html>
<html>

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

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
        }
    </style>
</head>

<body>
    <div class="title">SPRIEVODNÝ LIST | {{ $sheet['fileType'] ?? '' }}</div>

    <table>
        <tr>
            <td style="text-align:right">
                Sprievodný list k: <strong>{{ $sheet['fileName'] }}</strong>
            </td>
        </tr>
        <tr>
            <td>
                <strong>Vykázaná suma:</strong><br>
                {{ number_format((float) $sheet['amount'], 2, ',', ' ') }} €
            </td>
        </tr>
        @if (isset($sheet['kilometers']) && $sheet['kilometers'] !== '')
            <tr>
                <td>
                    <strong>Počet kilometrov:</strong><br>
                    {{ $sheet['kilometers'] }} km
                </td>
            </tr>
        @endif
        <tr>
            <td>
                <strong>Obdobie:</strong><br>
                {{ \Carbon\Carbon::parse($sheet['periodFrom'])->format('d.m.Y') }} - {{ \Carbon\Carbon::parse($sheet['periodTo'])->format('d.m.Y') }}
            </td>
        </tr>
        <tr>
            <td style="border-bottom: none;">
                <strong>Poisťovňa:</strong><br>
                {{ $sheet['insuranceName'] }}
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td>
                <strong>Vykázal:</strong><br>
                {{ $sheet['performedBy'] }}
            </td>
            <td>
                <strong>Vykázané dňa:</strong><br>
                {{ \Carbon\Carbon::parse($sheet['performedDate'])->format('d.m.Y') }}
            </td>
        </tr>
        <tr>
            <td>
                <strong>Spoločnosť:</strong><br>
                {{ $sheet['companyName'] }}
            </td>
            <td>
                <strong>Prevádzka:</strong><br>
                {{ $sheet['branchName'] }}
            </td>
        </tr>
    </table>
</body>

</html>
