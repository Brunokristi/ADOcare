<!doctype html>
<html lang="sk">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            font-family: DejaVu Sans, sans-serif;
            color: #000;
            background: #fff;
            box-sizing: border-box;
        }

        * {
            box-sizing: border-box;
        }

        .document-content {
            width: 100%;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .page {
            width: 210mm;
            height: 297mm;
            display: flex;
            align-items: center;
            justify-content: center;
            page-break-after: always;
            break-after: always;
            background: white;
        }

        .image-container {
            max-width: 100%;
            max-height: 100%;
            overflow: contain;
        }

        .scan-image {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .metadata {
            margin: 14mm;
            font-size: 11px;
            margin-bottom: 8px;
        }

        .metadata strong {
            display: block;
            font-weight: 700;
            margin-bottom: 4px;
        }
    </style>
</head>

<body>
    <div class="document-content">
        <div class="metadata">
            <strong>Skeny pacienta:</strong>
            {{ $scanData['patient_name'] ?? '' }}
            @if(!empty($scanData['patient_birth_number']))
                ({{ $scanData['patient_birth_number'] }})
            @endif
            <br/>
            <strong>Dátum vytvorenia:</strong>
            @if(!empty($scanData['date']))
                {{ \Carbon\Carbon::parse($scanData['date'])->format('d.m.Y H:i') }}
            @endif
        </div>

        @if(!empty($scanData['images']))
            @foreach($scanData['images'] as $index => $image)
                <div class="page">
                    <div class="image-container">
                        @if(strpos($image, 'data:') === 0)
                            <img src="{{ $image }}" alt="Skeny strana {{ $index + 1 }}" class="scan-image">
                        @else
                            <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(storage_path('app/' . $image))) }}" alt="Skeny strana {{ $index + 1 }}" class="scan-image">
                        @endif
                    </div>
                </div>
            @endforeach
        @else
            <div class="page">
                <div style="text-align: center; padding: 14mm;">
                    <p>Žiadne obrázky skanov nie sú dostupné</p>
                </div>
            </div>
        @endif
    </div>
</body>

</html>
