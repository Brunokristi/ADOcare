<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildProposalTrainingDataset extends Command
{
    protected $signature = 'ai:build-proposal-dataset
                            {--documents=storage/app/private/scans/documents : Path to OCR documents directory}
                            {--proposals=storage/app/private/proposals : Path to proposals directory}
                            {--output=storage/app/private/ai-dataset : Output directory}
                            {--min-score=11 : Minimum match score to accept pair}';

    protected $description = 'Build OCR -> proposal Gemini SFT training dataset from JSON files';

    private const SYSTEM_INSTRUCTION = 'You are a nursing documentation assistant. '
        . 'Suggest likely values for the target nursing form fields based on the OCR text and patterns commonly seen in historical completed forms. '
        . 'The output is only a draft suggestion for a nurse to review and edit. '
        . 'Use only valid codes and enum values for diagnosis, nurse_diagnosis, mobility, expected_duration, procedure codes, and procedure frequencies. '
        . 'Return JSON only.';

    private const ALLOWED_DIAGNOSIS_CODES = [
        'L97',
        'L89.15',
        'I70',
        'L89.25',
        'L02.8',
        'L02.2',
        'L02.4',
        'L08.8',
        'M54.4',
        'M54',
        'M54.0',
        'R26.3',
        'M54.05',
        'R63',
        'L89.17',
        'L89.27',
        'L89.14',
        'L89.16',
    ];

    private const ALLOWED_NURSE_DIAGNOSIS_CODES = [
        'K110',
        'D101',
        'D102',
        'A110',
    ];

    private const ALLOWED_MOBILITY = [
        'H',
        'I',
        'F',
    ];

    private const ALLOWED_EXPECTED_DURATION = [
        'one_month',
        'three_months',
        'six_months',
        'over_six_months',
    ];

    private const ALLOWED_PROCEDURE_CODES = [
        '3439',
        '3440',
        '3413',
        '3423A',
        '3423B',
        '3422A',
        '3422B',
        '3422C',
        '3416',
        '3419',
        '3393',
        '3390',
        '3424',
    ];

    private const ALLOWED_FREQUENCIES = [
        'weekdays',
        'weekends',
        'daily',
        'every_other_day',
        'three_times_weekly',
        'twice_weekly',
        'once_weekly',
        'twice_monthly',
        'once_monthly',
        'as_needed',
    ];

    public function handle(): int
    {
        $documentsDir = base_path($this->option('documents'));
        $proposalsDir = base_path($this->option('proposals'));
        $outputDir = base_path($this->option('output'));
        $minScore = (float) $this->option('min-score');

        if (!File::isDirectory($documentsDir)) {
            $this->error("Documents directory not found: {$documentsDir}");
            return self::FAILURE;
        }

        if (!File::isDirectory($proposalsDir)) {
            $this->error("Proposals directory not found: {$proposalsDir}");
            return self::FAILURE;
        }

        File::ensureDirectoryExists($outputDir);

        $this->info('Loading OCR documents...');
        $ocrDocs = $this->loadJsonFiles($documentsDir, 'ocr');

        $this->info('Loading proposals...');
        $proposalDocs = $this->loadJsonFiles($proposalsDir, 'proposal');

        $this->line('OCR docs loaded: ' . count($ocrDocs['items']));
        $this->line('Proposals loaded: ' . count($proposalDocs['items']));

        $skipped = array_merge($ocrDocs['skipped'], $proposalDocs['skipped']);

        $proposalsByBirthNumber = [];
        foreach ($proposalDocs['items'] as $proposal) {
            $birthNumber = $this->normalizeBirthNumber(data_get($proposal, 'patient_birth_number'));

            if ($birthNumber === '') {
                $skipped[] = [
                    'type' => 'proposal',
                    'path' => data_get($proposal, '__path'),
                    'reason' => 'missing_birth_number',
                ];
                continue;
            }

            $proposalsByBirthNumber[$birthNumber] ??= [];
            $proposalsByBirthNumber[$birthNumber][] = $proposal;
        }

        $trainRows = [];
        $pairedReport = [];

        $bar = $this->output->createProgressBar(count($ocrDocs['items']));
        $bar->start();

        foreach ($ocrDocs['items'] as $ocrDoc) {
            $bar->advance();

            $birthNumber = $this->normalizeBirthNumber(data_get($ocrDoc, 'patient_birth_number'));

            if ($birthNumber === '') {
                $skipped[] = [
                    'type' => 'ocr',
                    'path' => data_get($ocrDoc, '__path'),
                    'reason' => 'missing_birth_number',
                ];
                continue;
            }

            $candidates = $proposalsByBirthNumber[$birthNumber] ?? [];

            [$bestProposal, $matchMeta] = $this->pickBestProposal($ocrDoc, $candidates, $minScore);

            if (!$bestProposal) {
                $skipped[] = [
                    'type' => 'pair',
                    'ocr_path' => data_get($ocrDoc, '__path'),
                    'patient_name' => data_get($ocrDoc, 'patient_name'),
                    'patient_birth_number' => data_get($ocrDoc, 'patient_birth_number'),
                    'reason' => $matchMeta['reason'] ?? 'no_match',
                    'best_score' => $matchMeta['best_score'] ?? null,
                ];
                continue;
            }

            $cleanedOutput = $this->cleanProposalForTraining($bestProposal);

            $rejectReason = $this->shouldRejectTrainingRow($cleanedOutput, $ocrDoc);
            if ($rejectReason !== null) {
                $skipped[] = [
                    'type' => 'pair',
                    'ocr_path' => data_get($ocrDoc, '__path'),
                    'proposal_path' => data_get($bestProposal, '__path'),
                    'patient_name' => data_get($ocrDoc, 'patient_name'),
                    'patient_birth_number' => data_get($ocrDoc, 'patient_birth_number'),
                    'reason' => $rejectReason,
                ];
                continue;
            }

            $trainRows[] = $this->buildGeminiSftRow($ocrDoc, $cleanedOutput);

            $pairedReport[] = [
                'ocr_path' => data_get($ocrDoc, '__path'),
                'proposal_path' => data_get($bestProposal, '__path'),
                'patient_birth_number' => data_get($ocrDoc, 'patient_birth_number'),
                'patient_name_ocr' => data_get($ocrDoc, 'patient_name'),
                'patient_name_proposal' => data_get($bestProposal, 'patient_name'),
                'score' => $matchMeta['best_score'] ?? null,
                'details' => $matchMeta['details'] ?? [],
            ];
        }

        $bar->finish();
        $this->newLine(2);

        $trainPath = $outputDir . DIRECTORY_SEPARATOR . 'train.jsonl';
        $pairedReportPath = $outputDir . DIRECTORY_SEPARATOR . 'paired_report.json';
        $skippedReportPath = $outputDir . DIRECTORY_SEPARATOR . 'skipped_report.json';

        $this->writeJsonl($trainPath, $trainRows);

        File::put(
            $pairedReportPath,
            json_encode($pairedReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        File::put(
            $skippedReportPath,
            json_encode($skipped, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info('Dataset build finished.');
        $this->line('Train pairs: ' . count($trainRows));
        $this->line('Skipped: ' . count($skipped));
        $this->line("train.jsonl: {$trainPath}");
        $this->line("paired_report.json: {$pairedReportPath}");
        $this->line("skipped_report.json: {$skippedReportPath}");

        return self::SUCCESS;
    }

    protected function loadJsonFiles(string $directory, string $type): array
    {
        $items = [];
        $skipped = [];

        $files = File::files($directory);

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            try {
                $decoded = json_decode(File::get($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($decoded)) {
                    $skipped[] = [
                        'type' => $type,
                        'path' => $file->getPathname(),
                        'reason' => 'invalid_json_structure',
                    ];
                    continue;
                }

                $decoded['__path'] = $file->getPathname();
                $items[] = $decoded;
            } catch (\Throwable $e) {
                $skipped[] = [
                    'type' => $type,
                    'path' => $file->getPathname(),
                    'reason' => 'json_decode_failed',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'items' => $items,
            'skipped' => $skipped,
        ];
    }

    protected function pickBestProposal(array $ocrDoc, array $candidates, float $minScore): array
    {
        if (empty($candidates)) {
            return [null, [
                'reason' => 'no_candidates',
                'best_score' => null,
                'details' => [],
            ]];
        }

        $ocrBirthNumber = $this->normalizeBirthNumber(data_get($ocrDoc, 'patient_birth_number'));
        $ocrName = $this->normalizePersonName(data_get($ocrDoc, 'patient_name'));
        $ocrTime = $this->extractOcrTimestamp($ocrDoc);

        $scored = [];

        foreach ($candidates as $proposal) {
            $score = 0.0;
            $details = [];

            $proposalBirthNumber = $this->normalizeBirthNumber(data_get($proposal, 'patient_birth_number'));
            $proposalName = $this->normalizePersonName(data_get($proposal, 'patient_name'));
            $proposalTime = $this->extractProposalTimestamp($proposal);

            if ($ocrBirthNumber !== '' && $proposalBirthNumber !== '' && $ocrBirthNumber === $proposalBirthNumber) {
                $score += 10.0;
                $details[] = 'birth_number_match';
            } else {
                continue;
            }

            $nameSimilarity = $this->nameSimilarity($ocrName, $proposalName);
            $score += $nameSimilarity * 3.0;
            $details[] = 'name_similarity=' . number_format($nameSimilarity, 2, '.', '');

            if ($ocrTime && $proposalTime) {
                $deltaSeconds = $proposalTime->diffInSeconds($ocrTime, false);
                $hoursApart = abs($deltaSeconds) / 3600;

                if ($deltaSeconds >= 0) {
                    $score += 2.0;
                    $details[] = 'proposal_after_ocr';
                } else {
                    $score -= 1.0;
                    $details[] = 'proposal_before_ocr';
                }

                if ($hoursApart <= 24) {
                    $score += 2.0;
                    $details[] = 'within_24h';
                } elseif ($hoursApart <= 24 * 7) {
                    $score += 1.0;
                    $details[] = 'within_7d';
                } elseif ($hoursApart > 24 * 60) {
                    $score -= 2.0;
                    $details[] = 'too_far_in_time';
                }
            } else {
                $details[] = 'missing_time_data';
            }

            $scored[] = [
                'score' => $score,
                'proposal' => $proposal,
                'details' => $details,
            ];
        }

        if (empty($scored)) {
            return [null, [
                'reason' => 'no_scored_candidates',
                'best_score' => null,
                'details' => [],
            ]];
        }

        usort($scored, function (array $a, array $b) {
            return $b['score'] <=> $a['score'];
        });

        $best = $scored[0];

        if ($best['score'] < $minScore) {
            return [null, [
                'reason' => 'low_confidence_match',
                'best_score' => $best['score'],
                'details' => $best['details'],
            ]];
        }

        return [$best['proposal'], [
            'reason' => 'matched',
            'best_score' => $best['score'],
            'details' => $best['details'],
        ]];
    }

    protected function buildGeminiSftRow(array $ocrDoc, array $cleanedOutput): array
    {
        $inputText = $this->buildInputText($ocrDoc);
        $outputText = json_encode($cleanedOutput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'systemInstruction' => [
                'parts' => [
                    [
                        'text' => self::SYSTEM_INSTRUCTION,
                    ],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $inputText,
                        ],
                    ],
                ],
                [
                    'role' => 'model',
                    'parts' => [
                        [
                            'text' => $outputText,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function buildInputText(array $ocrDoc): string
    {
        $compactInput = [
            'patient_name' => $this->normalizeString(data_get($ocrDoc, 'patient_name')),
            'patient_birth_number' => $this->normalizeBirthNumber(data_get($ocrDoc, 'patient_birth_number')),
            'extracted_text' => $this->normalizeExtractedText(data_get($ocrDoc, 'extracted_text')),
            'allowed_values' => [
                'diagnosis_codes' => array_values(self::ALLOWED_DIAGNOSIS_CODES),
                'nurse_diagnosis_codes' => array_values(self::ALLOWED_NURSE_DIAGNOSIS_CODES),
                'mobility' => array_values(self::ALLOWED_MOBILITY),
                'expected_duration' => array_values(self::ALLOWED_EXPECTED_DURATION),
                'procedure_codes' => array_values(self::ALLOWED_PROCEDURE_CODES),
                'procedure_frequency' => array_values(self::ALLOWED_FREQUENCIES),
                'fixed_frequency_rules' => [
                    '3439' => 'weekdays',
                    '3440' => 'weekends',
                ],
            ],
        ];

        return implode("\n", [
            'Suggest likely values for the target nursing form fields based on the OCR text and historical patterns.',
            'Use only allowed codes and enum values.',
            'Return JSON only with this shape:',
            '{',
            '  "diagnosis": ["CODE"],',
            '  "nurse_diagnosis": ["CODE"],',
            '  "epicrisis": "TEXT",',
            '  "care_plan": "TEXT",',
            '  "mobility": ["H|I|F"],',
            '  "expected_duration": "one_month|three_months|six_months|over_six_months",',
            '  "procedures": [{"code":"CODE","frequency":"ENUM"}]',
            '}',
            '',
            'OCR JSON:',
            json_encode($compactInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    protected function cleanProposalForTraining(array $proposal): array
    {
        return [
            'diagnosis' => $this->normalizeDiagnosisCodes(data_get($proposal, 'diagnosis')),
            'nurse_diagnosis' => $this->normalizeNurseDiagnosisCodes(data_get($proposal, 'nurse_diagnosis')),
            'epicrisis' => $this->normalizeString(data_get($proposal, 'epicrisis')),
            'care_plan' => $this->normalizeString(data_get($proposal, 'care_plan')),
            'mobility' => $this->normalizeMobility(data_get($proposal, 'mobility')),
            'expected_duration' => $this->normalizeExpectedDuration(data_get($proposal, 'expected_duration')),
            'procedures' => $this->normalizeProcedures(data_get($proposal, 'procedures')),
        ];
    }

    protected function shouldRejectTrainingRow(array $cleanedOutput, array $ocrDoc): ?string
    {
        if (
            empty($cleanedOutput['diagnosis']) &&
            empty($cleanedOutput['nurse_diagnosis']) &&
            $cleanedOutput['epicrisis'] === '' &&
            $cleanedOutput['care_plan'] === '' &&
            empty($cleanedOutput['mobility']) &&
            $cleanedOutput['expected_duration'] === '' &&
            empty($cleanedOutput['procedures'])
        ) {
            return 'all_target_fields_empty';
        }

        $ocrText = $this->normalizeExtractedText(data_get($ocrDoc, 'extracted_text'));

        if (mb_strlen($ocrText) < 80) {
            return 'ocr_text_too_short';
        }

        if ($this->looksLikeGarbage($cleanedOutput['epicrisis'])) {
            return 'invalid_epicrisis';
        }

        if ($this->looksLikeGarbage($cleanedOutput['care_plan'])) {
            return 'invalid_care_plan';
        }

        if (empty($cleanedOutput['diagnosis'])) {
            return 'missing_diagnosis';
        }

        if (empty($cleanedOutput['nurse_diagnosis'])) {
            return 'missing_nurse_diagnosis';
        }

        if (empty($cleanedOutput['mobility'])) {
            return 'missing_mobility';
        }

        if ($cleanedOutput['expected_duration'] === '') {
            return 'missing_expected_duration';
        }

        if (empty($cleanedOutput['procedures'])) {
            return 'missing_procedures';
        }

        foreach ($cleanedOutput['diagnosis'] as $diagnosisCode) {
            if (!in_array($diagnosisCode, self::ALLOWED_DIAGNOSIS_CODES, true)) {
                return 'invalid_diagnosis_code';
            }
        }

        foreach ($cleanedOutput['nurse_diagnosis'] as $nurseDiagnosisCode) {
            if (!in_array($nurseDiagnosisCode, self::ALLOWED_NURSE_DIAGNOSIS_CODES, true)) {
                return 'invalid_nurse_diagnosis_code';
            }
        }

        foreach ($cleanedOutput['mobility'] as $mobility) {
            if (!in_array($mobility, self::ALLOWED_MOBILITY, true)) {
                return 'invalid_mobility';
            }
        }

        if (!in_array($cleanedOutput['expected_duration'], self::ALLOWED_EXPECTED_DURATION, true)) {
            return 'invalid_expected_duration';
        }

        foreach ($cleanedOutput['procedures'] as $procedure) {
            $code = data_get($procedure, 'code');
            $frequency = data_get($procedure, 'frequency');

            if (!in_array($code, self::ALLOWED_PROCEDURE_CODES, true)) {
                return 'invalid_procedure_code';
            }

            if (!in_array($frequency, self::ALLOWED_FREQUENCIES, true)) {
                return 'invalid_procedure_frequency';
            }

            if ($code === '3439' && $frequency !== 'weekdays') {
                return 'invalid_fixed_frequency_3439';
            }

            if ($code === '3440' && $frequency !== 'weekends') {
                return 'invalid_fixed_frequency_3440';
            }
        }

        return null;
    }

    protected function normalizeExtractedText(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = preg_replace('/\R+/u', "\n", $value) ?? $value;
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value;

        return trim($value);
    }

    protected function normalizeString(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    protected function normalizeStringArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim(preg_replace('/\s+/u', ' ', $item) ?? $item);

            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    protected function normalizeDiagnosisCodes(mixed $value): array
    {
        $items = $this->normalizeStringArray($value);
        $result = [];

        foreach ($items as $item) {
            $code = $this->extractLeadingCode($item);

            if ($code !== '' && in_array($code, self::ALLOWED_DIAGNOSIS_CODES, true)) {
                $result[] = $code;
            }
        }

        return array_values(array_unique($result));
    }

    protected function normalizeNurseDiagnosisCodes(mixed $value): array
    {
        $items = $this->normalizeStringArray($value);
        $result = [];

        foreach ($items as $item) {
            $code = $this->extractLeadingCode($item);

            if ($code !== '' && in_array($code, self::ALLOWED_NURSE_DIAGNOSIS_CODES, true)) {
                $result[] = $code;
            }
        }

        return array_values(array_unique($result));
    }

    protected function extractLeadingCode(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^([A-Za-z0-9\.]+)/u', $value, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return '';
    }

    protected function normalizeMobility(mixed $value): array
    {
        $items = $this->normalizeStringArray($value);

        $items = array_values(array_filter($items, function (string $item) {
            return in_array($item, self::ALLOWED_MOBILITY, true);
        }));

        return array_values(array_unique($items));
    }

    protected function normalizeExpectedDuration(mixed $value): string
    {
        $value = $this->normalizeString($value);

        return in_array($value, self::ALLOWED_EXPECTED_DURATION, true) ? $value : '';
    }

    protected function normalizeProcedures(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        $seen = [];

        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }

            $code = strtoupper(trim((string) data_get($item, 'code', '')));
            $frequency = trim((string) data_get($item, 'frequency', ''));

            if ($code === '' || $frequency === '') {
                continue;
            }

            if (!in_array($code, self::ALLOWED_PROCEDURE_CODES, true)) {
                continue;
            }

            if (!in_array($frequency, self::ALLOWED_FREQUENCIES, true)) {
                continue;
            }

            if ($code === '3439') {
                $frequency = 'weekdays';
            }

            if ($code === '3440') {
                $frequency = 'weekends';
            }

            $key = $code . '|' . $frequency;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $result[] = [
                'code' => $code,
                'frequency' => $frequency,
            ];
        }

        return array_values($result);
    }

    protected function looksLikeGarbage(string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $badTexts = [
            'HVHK',
            'JHJ',
            'dwdwwddw',
            'xxx',
            'test',
        ];

        if (in_array(mb_strtolower($value), array_map('mb_strtolower', $badTexts), true)) {
            return true;
        }

        if (mb_strlen($value) < 4) {
            return true;
        }

        return false;
    }

    protected function normalizeBirthNumber(?string $value): string
    {
        return preg_replace('/[^0-9]/', '', (string) $value) ?? '';
    }

    protected function normalizePersonName(?string $value): string
    {
        $value = (string) $value;
        $value = trim($value);
        $value = mb_strtolower($value);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function nameSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $aParts = array_values(array_filter(explode(' ', $a)));
        $bParts = array_values(array_filter(explode(' ', $b)));

        if (empty($aParts) || empty($bParts)) {
            return 0.0;
        }

        $intersection = array_intersect($aParts, $bParts);
        $union = array_unique(array_merge($aParts, $bParts));

        if (count($union) === 0) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    protected function extractOcrTimestamp(array $ocrDoc): ?Carbon
    {
        return $this->parseDateTime(
            data_get($ocrDoc, 'ocr_at') ?: data_get($ocrDoc, 'scanned_at')
        );
    }

    protected function extractProposalTimestamp(array $proposal): ?Carbon
    {
        return $this->parseDateTime(
            data_get($proposal, 'created_at') ?: data_get($proposal, 'date')
        );
    }

    protected function parseDateTime(?string $value): ?Carbon
    {
        if (!$value || !is_string($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function writeJsonl(string $path, array $rows): void
    {
        $lines = [];

        foreach ($rows as $row) {
            $lines[] = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        File::put($path, implode("\n", $lines) . "\n");
    }
}