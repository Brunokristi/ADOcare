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
            font-size: 14px;
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

        td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
            line-height: 1.2;
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
            position: relative;
            min-width: 150px;
        }

        .signature-area {
            height: 60px;
            margin-bottom: 8px;
            position: relative;
        }

        .stamp-image {
            max-width: 150px;
            max-height: 60px;
            object-fit: contain;
            opacity: 70%;
        }

        .signature-overlay {
            position: absolute;
            z-index: 2;
            max-width: 200px;
            max-height: 100px;
            object-fit: contain;
            top: 50%;
            left: 60%;
            transform: translate(-40%, -55%);
        }

        .line {
            border-top: 1px solid #000;
            margin-top: 4px;
            height: 0;
        }

        .text-justify {
            text-align: justify;
        }

        .problem-list {
            margin: 4px 0;
            padding-left: 20px;
        }

        .problem-list li {
            margin: 0 0 2px 0;
        }

        .fill-box {
            margin-top: 6px;
            white-space: pre-line;
            word-break: break-word;
        }
    </style>
</head>

<body>
    <div class="document-content">
        @php
            $problemLabels = [
                'nutrition' => 'výživy',
                'mobility' => 'mobility',
                'elimination' => 'vylučovania/vyprázdňovania',
                'injections' => 'aplikácie s. c. inj.',
                'hygiene' => 'hygieny',
                'wound_care' => 'starosti o ranu',
                'other_findings' => 'iné zistenia',
            ];

            $getProblemLabel = function (string $value) use ($problemLabels) {
                return $problemLabels[$value] ?? $value;
            };
        @endphp

        <div class="title">
            OŠETROVATEĽSKÁ PREPÚŠŤACIA SPRÁVA
        </div>

        <!-- PATIENT INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 50%;">
                        Meno, priezvisko, titul pacienta/pacientky:<br />
                        <strong>{{ $leaveData['patient_name'] ?? '' }}</strong>
                    </td>
                    <td style="width: 50%;">
                        Rodné číslo:<br />
                        <strong>{{ $leaveData['patient_birth_number'] ?? '' }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- NURSE INFO -->
        <table>
            <tbody>
                <tr>
                    <td style="width: 50%;">

                        Dátum:<br />
                        <strong>
                            @if (!empty($leaveData['date']))
                                {{ \Carbon\Carbon::parse($leaveData['date'])->format('d.m.Y') }}
                            @endif
                        </strong>
                    </td>
                    <td style="width: 50%;">

                        Zdravotný pracovník:<br />
                        <strong>{{ $leaveData['user_name'] ?? '' }}</strong>
                    </td>

                </tr>
            </tbody>
        </table>

        <!-- NURSING SECTION -->
        <table>
            <tbody>
                <tr>
                    <td colspan="2">
                        <strong>Pretrvávajúce problémy pri prepustení v oblasti sebaopatery:</strong>
                        @if (!empty($leaveData['problems']))
                            <ul class="problem-list">
                                @foreach ($leaveData['problems'] as $problem)
                                    <li>{{ $getProblemLabel($problem) }}</li>
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
                        <strong>Iné zistenia:</strong><br />
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
                        <strong>Vyhodnotenie výsledkov ošetrovateľskej starostlivosti:</strong><br />
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
                        <strong>Realizovaná edukácia o:</strong><br />
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
                        <strong>Pacient pri ukončení hospitalizácie prevzal:</strong><br />
                        {{ $leaveData['received'] ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- SIGNATURE -->
        <div class="signature-block">
            <div class="signature-box">
                <div class="signature-area">
                    @if (!empty($stampDataUri))
                        <img src="{{ $stampDataUri }}" alt="Pečiatka spoločnosti" class="stamp-image" />
                    @endif
                    @if (!empty($signatureDataUri))
                        <img src="{{ $signatureDataUri }}" alt="Podpis" class="signature-overlay">
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
