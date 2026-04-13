<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuildProposalTrainingDataset extends Command
{
    protected $signature = 'ai:build-proposal-dataset
                            {--documents=storage/app/private/scans/documents : Path to OCR documents directory}
                            {--proposals=storage/app/private/proposals : Path to proposals directory}
                            {--output=storage/app/private/ai-dataset : Output directory}
                            {--min-score=11 : Minimum match score to accept pair}';

    protected $description = 'Build OCR -> proposal training dataset from JSON files';

    public function handle(): int
    {
        $documentsDir = base_path($this->option('documents'));
        $proposalsDir = base_path($this->option('proposals'));
        $outputDir = base_path($this->option('output'));
        $minScore = (float) $this->option('min-score');

        if (! File::isDirectory($documentsDir)) {
            $this->error("Documents directory not found: {$documentsDir}");
            return self::FAILURE;
        }

        if (! File::isDirectory($proposalsDir)) {
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

            if (! $bestProposal) {
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

            $trainRows[] = [
                'input_text' => $this->buildInputText($ocrDoc),
                'output_text' => json_encode(
                    $cleanedOutput,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ];

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

                if (! is_array($decoded)) {
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

    protected function buildInputText(array $ocrDoc): string
    {
        return implode("\n", [
            'You are given OCR output from a medical referral, examination, or related healthcare document.',
            'Convert the OCR JSON into the target proposal JSON structure.',
            'Use the OCR text as the main source of truth.',
            'Do not invent values.',
            'Return JSON only.',
            '',
            'OCR JSON:',
            json_encode($ocrDoc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    protected function cleanProposalForTraining(array $proposal): array
    {
        unset(
            $proposal['document_id'],
            $proposal['created_at'],
            $proposal['updated_at'],
            $proposal['__path']
        );

        return $proposal;
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

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

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
        if (! $value || ! is_string($value)) {
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