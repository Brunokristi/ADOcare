<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BuildProposalTrainingDataset extends Command
{
    protected $signature = 'ai:build-proposal-training-dataset
        {--proposals=storage/app/private/proposals : Path to proposals directory}
        {--dekurz=storage/app/private/dekurz : Path to dekurz directory}
        {--output=storage/app/private/ai-dataset-dekurz : Output directory}';

    protected $description = 'Build proposal -> dekurz JSONL dataset';

    public function handle(): int
    {
        $proposalsDir = base_path($this->option('proposals'));
        $dekurzDir = base_path($this->option('dekurz'));
        $outputDir = base_path($this->option('output'));

        if (!File::isDirectory($proposalsDir)) {
            $this->error("Proposals directory not found: {$proposalsDir}");
            return self::FAILURE;
        }

        if (!File::isDirectory($dekurzDir)) {
            $this->error("Dekurz directory not found: {$dekurzDir}");
            return self::FAILURE;
        }

        File::ensureDirectoryExists($outputDir);

        $proposals = $this->loadJsonFiles($proposalsDir);
        $dekurzDocs = $this->loadJsonFiles($dekurzDir);

        $this->info('Proposals: ' . count($proposals));
        $this->info('Dekurz: ' . count($dekurzDocs));

        $dekurzByBirthNumber = [];
        foreach ($dekurzDocs as $dekurz) {
            $birthNumber = $this->normalizeBirthNumber(
                data_get($dekurz, 'patient_personal_number')
            );

            if ($birthNumber === '') {
                continue;
            }

            $dekurzByBirthNumber[$birthNumber] ??= [];
            $dekurzByBirthNumber[$birthNumber][] = $dekurz;
        }

        $rows = [];
        $paired = 0;
        $skipped = 0;

        foreach ($proposals as $proposal) {
            $birthNumber = $this->normalizeBirthNumber(
                data_get($proposal, 'patient_birth_number')
            );

            if ($birthNumber === '') {
                $skipped++;
                continue;
            }

            $matches = $dekurzByBirthNumber[$birthNumber] ?? [];

            if (empty($matches)) {
                $skipped++;
                continue;
            }

            $dekurz = $this->pickBestDekurz($proposal, $matches);

            if ($dekurz === null) {
                $skipped++;
                continue;
            }

            $cleanProposal = $this->cleanProposal($proposal);
            $cleanDekurz = $this->cleanDekurz($dekurz);

            if ($this->shouldSkipRow($cleanProposal, $cleanDekurz)) {
                $skipped++;
                continue;
            }

            $rows[] = [
                'input_text' => $this->buildInputText($cleanProposal),
                'output_text' => json_encode(
                    $cleanDekurz,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ];

            $paired++;
        }

        $outputPath = $outputDir . DIRECTORY_SEPARATOR . 'train.jsonl';
        $this->writeJsonl($outputPath, $rows);

        $this->info('Done. Rows: ' . count($rows));
        $this->line('Paired: ' . $paired);
        $this->line('Skipped: ' . $skipped);
        $this->line('Output: ' . $outputPath);

        return self::SUCCESS;
    }

    protected function loadJsonFiles(string $directory): array
    {
        $items = [];

        foreach (File::files($directory) as $file) {
            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            try {
                $decoded = json_decode(
                    File::get($file->getPathname()),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if (is_array($decoded)) {
                    $decoded['__path'] = $file->getPathname();
                    $items[] = $decoded;
                }
            } catch (\Throwable $e) {
                // skip bad JSON files silently for dataset building
            }
        }

        return $items;
    }

    protected function pickBestDekurz(array $proposal, array $matches): ?array
    {
        if (count($matches) === 1) {
            return $matches[0];
        }

        $proposalTime = $this->parseDateTime(
            data_get($proposal, 'created_at') ?: data_get($proposal, 'date')
        );

        if ($proposalTime === null) {
            return $matches[0];
        }

        $best = null;
        $bestDiff = null;

        foreach ($matches as $candidate) {
            $candidateTime = $this->parseDateTime(
                data_get($candidate, 'created_at') ?: data_get($candidate, 'month')
            );

            if ($candidateTime === null) {
                continue;
            }

            $diff = abs($candidateTime->getTimestamp() - $proposalTime->getTimestamp());

            if ($best === null || $diff < $bestDiff) {
                $best = $candidate;
                $bestDiff = $diff;
            }
        }

        return $best ?? $matches[0];
    }

    protected function cleanProposal(array $proposal): array
    {
        return [
            'diagnosis' => $this->normalizeStringArray(data_get($proposal, 'diagnosis')),
            'nurse_diagnosis' => $this->normalizeStringArray(data_get($proposal, 'nurse_diagnosis')),
            'epicrisis' => $this->normalizeString(data_get($proposal, 'epicrisis')),
            'care_plan' => $this->normalizeString(data_get($proposal, 'care_plan')),
            'mobility' => $this->normalizeStringArray(data_get($proposal, 'mobility')),
            'expected_duration' => $this->normalizeString(data_get($proposal, 'expected_duration')),
            'procedures' => $this->normalizeProcedures(data_get($proposal, 'procedures')),
        ];
    }

    protected function cleanDekurz(array $dekurz): array
    {
        $sections = [];

        foreach ((array) data_get($dekurz, 'sections', []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            $text = $this->normalizeMultilineText(data_get($section, 'text'));

            if ($text === '') {
                continue;
            }

            $sections[] = [
                'text' => $text,
            ];
        }

        return [
            'sections' => $sections,
        ];
    }

    protected function shouldSkipRow(array $proposal, array $dekurz): bool
    {
        $hasProposalSignal =
            !empty($proposal['diagnosis']) ||
            !empty($proposal['nurse_diagnosis']) ||
            $proposal['epicrisis'] !== '' ||
            $proposal['care_plan'] !== '' ||
            !empty($proposal['procedures']);

        if (!$hasProposalSignal) {
            return true;
        }

        if (empty($dekurz['sections'])) {
            return true;
        }

        return false;
    }

    protected function buildInputText(array $proposal): string
    {
        return implode("\n", [
            'You are given a structured nursing proposal.',
            'Generate likely dekurz section texts based on the proposal and patterns commonly used in historical nursing documentation.',
            'The output is only a draft suggestion for a nurse to review and edit.',
            'Return JSON only.',
            '',
            'TARGET FIELD:',
            '- sections',
            '',
            'Each section must contain only:',
            '- text',
            '',
            'INPUT JSON:',
            json_encode($proposal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    protected function normalizeBirthNumber(?string $value): string
    {
        return preg_replace('/[^0-9]/', '', (string) $value) ?? '';
    }

    protected function normalizeString(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    protected function normalizeMultilineText(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = str_replace("\r\n", "\n", $value);
        $value = str_replace("\r", "\n", $value);
        $value = preg_replace("/[ \t]+/u", ' ', $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;

        $lines = array_map(
            fn (string $line) => trim($line),
            explode("\n", $value)
        );

        return trim(implode("\n", $lines));
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

            $item = $this->normalizeString($item);

            if ($item !== '') {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
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

    protected function parseDateTime(?string $value): ?\DateTimeImmutable
    {
        if (!$value || !is_string($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function writeJsonl(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open file for writing: {$path}");
        }

        try {
            foreach ($rows as $row) {
                $json = json_encode(
                    $row,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );

                if ($json === false) {
                    continue;
                }

                fwrite($handle, $json . "\n");
            }
        } finally {
            fclose($handle);
        }
    }
}