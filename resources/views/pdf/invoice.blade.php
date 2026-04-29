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
            font-size: 16px;
            margin-bottom: 12px;
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
            padding: 6px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            font-weight: 700;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="title">FAKTÚRA</div>

        <!-- INVOICE INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        <strong>Číslo faktúry:</strong><br/>
                        {{ $invoiceData['invoice_number'] ?? '' }}
                    </td>
                    <td style="width: 50%;">
                        <strong>Dátum faktúry:</strong><br/>
                        @if(!empty($invoiceData['invoice_date']))
                            {{ \Carbon\Carbon::parse($invoiceData['invoice_date'])->format('d.m.Y') }}
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- PROVIDER AND CUSTOMER INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        <strong>Dodávateľ:</strong><br/>
                        {{ $invoiceData['company_name'] ?? '' }}<br/>
                        {{ $invoiceData['company_address'] ?? '' }}<br/>
                        <strong>IČO:</strong> {{ $invoiceData['ico'] ?? '' }}<br/>
                        <strong>DIČ:</strong> {{ $invoiceData['dic'] ?? '' }}
                    </td>
                    <td style="width: 50%;">
                        <strong>Klient:</strong><br/>
                        {{ $invoiceData['customer_name'] ?? '' }}<br/>
                        {{ $invoiceData['customer_address'] ?? '' }}<br/>
                        <strong>IČO:</strong> {{ $invoiceData['customer_ico'] ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ITEMS TABLE -->
        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Popis</th>
                    <th style="width: 12.5%; text-align: right;">Počet</th>
                    <th style="width: 12.5%; text-align: right;">Jednotková cena</th>
                    <th style="width: 25%; text-align: right;">Spolu (€)</th>
                </tr>
            </thead>
            <tbody>
                @if(!empty($invoiceData['items']))
                    @php
                        $totalAmount = 0;
                    @endphp
                    @foreach($invoiceData['items'] as $item)
                        @php
                            $itemTotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                            $totalAmount += $itemTotal;
                        @endphp
                        <tr>
                            <td>{{ $item['description'] ?? '' }}</td>
                            <td class="text-right">{{ $item['quantity'] ?? 0 }}</td>
                            <td class="text-right">{{ number_format($item['unit_price'] ?? 0, 2, ',', ' ') }}</td>
                            <td class="text-right">{{ number_format($itemTotal, 2, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>

        <!-- TOTALS -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 75%; text-align: right; border-top: 2px solid #000;">
                        <strong>Celková suma:</strong>
                    </td>
                    <td style="width: 25%; text-align: right; border-top: 2px solid #000;" class="total-row">
                        @if(!empty($invoiceData['total_amount']))
                            {{ number_format($invoiceData['total_amount'], 2, ',', ' ') }} €
                        @else
                            0,00 €
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- NOTES -->
        @if(!empty($invoiceData['notes']))
            <table>
                <tbody>
                    <tr>
                        <td>
                            <strong>Poznámky:</strong><br/>
                            {{ $invoiceData['notes'] ?? '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
</body>

</html>
