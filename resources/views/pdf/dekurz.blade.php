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
            font-size: 15px;
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

        .dekurz-page {
            background: white;
            margin: 0;
            box-sizing: border-box;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .dekurz-page+.dekurz-page {
            page-break-before: always;
            break-before: page;
        }

        .page-inner {
            width: 100%;
            box-sizing: border-box;
        }

        .page-title {
            text-align: center;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .dekurz-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 2px;
        }

        .dekurz-table td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
            line-height: 1.4;
            font-size: 13px;
        }

        .text-normal {
            font-size: 14px;
            margin: 3px 0;
        }

        .font-normal {
            font-weight: normal;
            font-size: 14px;
        }

        .align-top {
            vertical-align: top;
        }

        .row-signature {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            min-height: 40px;
            margin-top: 12px;
        }

        .signature-image {
            margin-left: auto;
            height: 40px;
            max-width: 150px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .text-sm {
            font-size: 12px;
        }

        .whitespace-pre-line {
            white-space: pre-line;
        }

        .leading-snug {
            line-height: 1.5;
        }

        .mt-2 {
            margin-top: 8px;
        }

        .header-table {
            margin-bottom: 2px;
        }

        .company-info-table td:nth-child(1),
        .company-info-table td:nth-child(2),
        .company-info-table td:nth-child(3),
        .company-info-table td:nth-child(4) {
            width: 25%;
        }

        .entries-table-header {
            font-weight: 700;
            font-size: 10px;
        }

        .entries-table td:nth-child(1) {
            width: 25%;
        }

        .entries-table td:nth-child(2) {
            width: 75%;
        }

        .empty-state-cell {
            text-align: center;
            padding: 16px;
        }
    </style>
</head>

<body>
    @php
        $days = $dekurzData['days'] ?? [];
        $sortedDays = collect($days)->sortBy('date')->values()->toArray();

        $estimateWrappedLines = static function (?string $text): int {
            $normalized = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text)));

            if ($normalized === '') {
                return 1;
            }

            $paragraphs = preg_split('/\r\n|\r|\n/u', $normalized) ?: [];
            $lines = 0;

            foreach ($paragraphs as $paragraph) {
                $length = mb_strlen(trim($paragraph));
                $lines += max(1, (int) ceil($length / 85));
            }

            return max(1, $lines);
        };

        $estimateRowLines = static function (array $row) use ($estimateWrappedLines): int {
            $descriptionLines = $estimateWrappedLines($row['text'] ?? '');

            // Date/time line, description line(s), and signature block.
            return max(3, $descriptionLines + 3);
        };

        $formatVisitTime = static function ($value): string {
            $value = trim((string) $value);

            if ($value === '') {
                return '00:00';
            }

            try {
                return \Carbon\Carbon::parse($value)->format('H:i');
            } catch (\Throwable $e) {
                return strlen($value) >= 5 ? substr($value, -8, 5) : $value;
            }
        };
        $rowsPerPageLines = 22;
        $pages = [];
        $currentPageRows = [];
        $currentPageLines = 0;

        foreach ($sortedDays as $row) {
            $rowLines = $estimateRowLines($row);

            if (!empty($currentPageRows) && $currentPageLines + $rowLines > $rowsPerPageLines) {
                $pages[] = $currentPageRows;
                $currentPageRows = [];
                $currentPageLines = 0;
            }

            $currentPageRows[] = $row;
            $currentPageLines += $rowLines;
        }

        if (!empty($currentPageRows)) {
            $pages[] = $currentPageRows;
        }

        if (empty($pages)) {
            $pages = [[]];
        }

        $totalPages = count($pages);
    @endphp

    @foreach ($pages as $pageIdx => $pageRows)
        @php
            $dekurzNumber = (int) ($dekurzData['dekurz_number'] ?? 1) + $pageIdx;
        @endphp

        <div class="dekurz-page">
            <div class="page-inner">
                <div class="page-title">
                    DEKURZ OŠETROVATEĽSKEJ STAROSTLIVOSTI
                </div>

                <!-- COMPANY HEADER TABLE -->
                <table class="dekurz-table company-info-table header-table">
                    <colgroup>
                        <col />
                        <col />
                        <col />
                        <col />
                    </colgroup>
                    <tbody>
                        <tr>
                            <td colspan="4">
                                <div class="text-normal"><strong>{{ $dekurzData['company_name'] ?? '' }}</strong></div>
                                <div class="text-normal">{{ $dekurzData['company_address'] ?? '' }}</div>
                                <div class="text-normal">Agentúra domácej ošetrovateľskej starostlivosti</div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="align-top">
                                <div class="text-normal">Meno, priezvisko, titul pacienta/pacientky:</div>
                                <div class="font-normal"><strong>{{ $dekurzData['patient_name'] ?? '' }}</strong></div>
                            </td>
                            <td class="align-top">
                                <div class="text-normal">Rodné číslo:</div>
                                <div class="font-normal"><strong>{{ $dekurzData['patient_personal_number'] ?? '—' }}</strong></div>
                            </td>
                            <td class="align-top">
                                <div class="text-normal">Poisťovňa:</div>
                                <div class="font-normal"><strong>{{ $dekurzData['insurance_code'] ?? '—' }}</strong></div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="align-top">
                                <div class="text-normal">Adresa pacienta/pacientky:</div>
                                <div class="font-normal"><strong>{{ $dekurzData['patient_address'] ?? '' }}</strong></div>
                            </td>
                            <td class="align-top">
                                <div class="text-normal">Poradové číslo dekurzu:</div>
                                <div class="font-normal"><strong>{{ $dekurzNumber }}</strong></div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- ENTRIES TABLE -->
                <table class="dekurz-table entries-table">
                    <colgroup>
                        <col />
                        <col />
                    </colgroup>
                    <tbody>
                        <tr>
                            <td class="align-top entries-table-header">
                                Dátum a<br />čas zápisu:
                            </td>
                            <td class="align-top entries-table-header">
                                Rozsah poskytnutej ZS a služieb súvisiacich s poskytnutím ZS, identifikácia ošetrujúceho zdravotného pracovníka (meno, priezvisko, odtlačok pečiatky a podpis)
                            </td>
                        </tr>

                        @forelse($pageRows as $row)
                            @php
                                $dateStr = \Carbon\Carbon::parse($row['date'])->format('d.m.Y');
                                $timeStr = !empty($row['administrative_time']) ? $formatVisitTime($row['administrative_time']) : (!empty($row['terrain_time']) ? $formatVisitTime($row['terrain_time']) : '00:00');
                                $text = trim($row['text'] ?? '');
                            @endphp
                            <tr>
                                <td class="align-top">
                                    <div class="whitespace-pre-line text-normal">{{ $dateStr }}<br />{{ $timeStr }}</div>
                                </td>
                                <td class="align-top">
                                    <div class="leading-snug text-normal">
                                        <span class="font-normal">{{ $timeStr }}: </span>
                                        <span class="whitespace-pre-line">{{ $text }}</span>
                                    </div>

                                    <div class="row-signature">
                                        <div class="text-sm"><strong>{{ $dekurzData['user_name'] ?? '' }}</strong></div>
                                        @if (!empty($signatureDataUri))
                                            <img src="{{ $signatureDataUri }}" alt="Podpis" class="signature-image" />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="empty-state-cell">Žiadne záznamy.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</body>

</html>
