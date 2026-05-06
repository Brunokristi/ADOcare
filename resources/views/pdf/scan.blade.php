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
            color: #000;
            background: #fff;
            box-sizing: border-box;
            font-size: 14px;
        }

        html {
            margin: 14mm;
            padding: 0;
        }

        * {
            box-sizing: border-box;
        }

        .document-content {
            width: auto;
            margin: 0;
            box-sizing: border-box;
        }

        .page {
            width: 100%;
            height: calc(297mm - 30mm);
            page-break-after: always;
            break-after: page;
            background: white;
            page-break-inside: avoid;
            /* display: flex;
            flex-direction: column; */
        }

        .page:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .page-inner {
            width: 100%;
        }

        .title {
            text-align: center;
            font-weight: 700;
            font-size: 16px;
            margin: 0 0 8px 0;
        }

        .image-container {
            /* flex: 1; */
        }

        .info-table,
        .image-container {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            page-break-inside: avoid;
            margin-bottom: 8px;
        }

        .info-table td,
        .image-container {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
            word-break: break-word;
        }

        .label {
            /* font-weight: 700; */
            display: block;
            margin-bottom: 3px;
        }

        .page-number {
            text-align: left;
        }


        .image-container {
            width: 100%;
            text-align: center;
        }

        .scan-image {
            max-width: 100%;
            max-height: 180mm;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        .empty-state {
            text-align: center;
            padding: 14mm;
        }

        .page-title {
            text-align: center;
            font-weight: 700;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <div class="document-content">
        @if (!empty($scanData['images']))
            @foreach ($scanData['images'] as $index => $image)
                <div class="page">
                    <div class="title">Lekársky nález</div>

                    <table class="info-table">
                        <tbody>
                            <tr>
                                <td style="width: 75%;">
                                    <span class="label">Meno, priezvisko poistenca:</span>
                                    <strong>{{ $scanData['patient_name'] ?? '' }}</strong>
                                </td>
                                <td style="width: 25%;">
                                    <span class="label">Rodné číslo:</span>
                                    <strong>{{ $scanData['patient_birth_number'] ?? '' }}</strong>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="label">Dátum nahratia:</span>
                                    <strong>
                                        @if (!empty($scanData['date']))
                                            {{ \Carbon\Carbon::parse($scanData['date'])->format('d.m.Y H:i') }}
                                        @endif
                                    </strong>
                                </td>
                                <td class="page-number">
                                    <span class="label">Strana:</span>
                                    <strong>{{ $index + 1 }} / {{ count($scanData['images']) }}</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="image-container">
                        @if (strpos($image, 'data:') === 0)
                            <img src="{{ $image }}" alt="Skeny strana {{ $index + 1 }}" class="scan-image">
                        @else
                            <img src="data:image/jpeg;base64,{{ base64_encode(Storage::disk('local')->get($image)) }}" alt="Skeny strana {{ $index + 1 }}" class="scan-image">
                        @endif
                    </div>
                </div>
            @endforeach
        @else
            <div class="page">
                <div class="page-inner">
                    <div class="title">Lekársky nález</div>
                    <div class="empty-state">
                        <p>Žiadne obrázky skenov nie sú dostupné</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</body>

</html>
