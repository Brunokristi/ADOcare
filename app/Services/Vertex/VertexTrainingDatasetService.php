<?php

namespace App\Services\Vertex;

use App\Models\DekurzAiFeedback;
use App\Models\Document;
use App\Models\VertexTrainingRun;
use App\Models\VertexTrainingRunExample;
use Illuminate\Support\Facades\Storage;

class VertexTrainingDatasetService
{
    public function __construct(
        private readonly DekurzPromptBuilder $promptBuilder
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForRun(VertexTrainingRun $run, bool $force = false): array
    {
        $version = $run->version ?: now()->format('Y-m-d-His');
        $basePath = 'ai/dekurz-autotrain/datasets';

        $trainPath = $basePath . '/train-' . $version . '.jsonl';
        $validationPath = $basePath . '/validation.jsonl';
        $holdoutPath = $basePath . '/holdout.jsonl';

        $existingValidation = $this->readRows($validationPath);
        $existingHoldout = $this->readRows($holdoutPath);

        $knownRoleFeedbackIds = VertexTrainingRunExample::query()
            ->whereIn('dataset_role', ['validation', 'holdout'])
            ->pluck('feedback_id')
            ->all();

        if (empty($existingValidation) || empty($existingHoldout)) {
            $seedRows = $this->loadApprovedFeedbackRows();
            $seedRows = array_values(array_filter($seedRows, fn (array $row) => ! in_array((int) $row['feedback_id'], $knownRoleFeedbackIds, true)));

            foreach ($seedRows as $index => $seed) {
                $bucket = $index % 10;

                if ($bucket === 0) {
                    $existingHoldout[] = $seed;
                } elseif ($bucket === 1) {
                    $existingValidation[] = $seed;
                }
            }

            if (empty($existingValidation) && ! empty($seedRows)) {
                $existingValidation[] = $seedRows[0];
            }

            if (empty($existingHoldout) && count($seedRows) > 1) {
                $existingHoldout[] = $seedRows[1];
            }

            $this->writeRows($validationPath, $existingValidation);
            $this->writeRows($holdoutPath, $existingHoldout);
        }

        $usedForTraining = VertexTrainingRunExample::query()
            ->where('dataset_role', 'training')
            ->pluck('feedback_id')
            ->all();

        $blockedIds = array_values(array_unique(array_merge(
            $usedForTraining,
            array_map(fn (array $row) => (int) ($row['feedback_id'] ?? 0), $existingValidation),
            array_map(fn (array $row) => (int) ($row['feedback_id'] ?? 0), $existingHoldout)
        )));

        $newRows = $this->loadApprovedFeedbackRows($blockedIds);
        $minimum = (int) config('services.vertex_ai.auto_train.min_new_feedback', 25);

        if (! $force && count($newRows) < $minimum) {
            throw new \RuntimeException('Nedostatok nových schválených príkladov pre retrénovanie.');
        }

        $this->validateRows($newRows, 'training');
        $this->validateRows($existingValidation, 'validation');
        $this->validateRows($existingHoldout, 'holdout');

        $this->writeRows($trainPath, $newRows);

        foreach ($newRows as $row) {
            VertexTrainingRunExample::query()->create([
                'training_run_id' => $run->id,
                'feedback_id' => (int) $row['feedback_id'],
                'dataset_role' => 'training',
                'created_at' => now(),
            ]);
        }

        foreach ($existingValidation as $row) {
            VertexTrainingRunExample::query()->firstOrCreate([
                'training_run_id' => $run->id,
                'feedback_id' => (int) $row['feedback_id'],
                'dataset_role' => 'validation',
            ], ['created_at' => now()]);
        }

        foreach ($existingHoldout as $row) {
            VertexTrainingRunExample::query()->firstOrCreate([
                'training_run_id' => $run->id,
                'feedback_id' => (int) $row['feedback_id'],
                'dataset_role' => 'holdout',
            ], ['created_at' => now()]);
        }

        $datasetHash = hash('sha256', implode("\n", array_map(
            fn (array $row) => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $newRows
        )));

        $datasetDisk = (string) config('services.vertex_ai.auto_train.dataset_disk', 'gcs');
        $datasetPrefix = trim((string) config('services.vertex_ai.auto_train.dataset_prefix', 'ai/dekurz-feedback'), '/');
        $bucket = trim((string) config('filesystems.disks.' . $datasetDisk . '.bucket'));

        if ($bucket === '') {
            throw new \RuntimeException('Dataset disk nemá nakonfigurovaný bucket.');
        }

        $remoteTrainPath = $datasetPrefix . '/' . $version . '/train.jsonl';
        $remoteValidationPath = $datasetPrefix . '/' . $version . '/validation.jsonl';

        Storage::disk($datasetDisk)->put($remoteTrainPath, Storage::disk('local')->get($trainPath));
        Storage::disk($datasetDisk)->put($remoteValidationPath, Storage::disk('local')->get($validationPath));

        return [
            'version' => $version,
            'train_path' => $trainPath,
            'validation_path' => $validationPath,
            'holdout_path' => $holdoutPath,
            'training_examples_count' => count($newRows),
            'validation_examples_count' => count($existingValidation),
            'holdout_examples_count' => count($existingHoldout),
            'training_dataset_uri' => 'gs://' . $bucket . '/' . $remoteTrainPath,
            'validation_dataset_uri' => 'gs://' . $bucket . '/' . $remoteValidationPath,
            'dataset_hash' => $datasetHash,
        ];
    }

    /**
     * @param array<int, int> $excludeFeedbackIds
     * @return array<int, array<string, mixed>>
     */
    private function loadApprovedFeedbackRows(array $excludeFeedbackIds = []): array
    {
        $source = trim((string) config('services.vertex_ai.auto_train.source', 'proposal_ai_prefill'));

        $query = DekurzAiFeedback::query()
            ->whereNotNull('proposal_document_id')
            ->orderBy('id');

        if ($source !== '') {
            $query->where('source', $source);
        }

        if (! empty($excludeFeedbackIds)) {
            $query->whereNotIn('id', $excludeFeedbackIds);
        }

        $rows = [];

        $query->chunkById(250, function ($items) use (&$rows): void {
            foreach ($items as $feedback) {
                $proposal = $this->loadProposalPayload((int) $feedback->proposal_document_id);
                if ($proposal === null) {
                    continue;
                }

                $sections = collect($feedback->final_sections ?? [])
                    ->map(fn ($section) => ['text' => trim((string) ($section['text'] ?? ''))])
                    ->filter(fn (array $section) => $section['text'] !== '')
                    ->values()
                    ->all();

                if (empty($sections)) {
                    continue;
                }

                $rows[] = [
                    'feedback_id' => (int) $feedback->id,
                    'proposal' => $this->normalizeProposal($proposal),
                    'expected' => ['sections' => $sections],
                    'input_prompt' => $this->promptBuilder->build($proposal),
                ];
            }
        });

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadProposalPayload(int $proposalDocumentId): ?array
    {
        $document = Document::query()->find($proposalDocumentId);

        if (! $document || ! $document->path || ! Storage::disk('local')->exists($document->path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($document->path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    private function normalizeProposal(array $proposal): array
    {
        return [
            'diagnosis' => is_array($proposal['diagnosis'] ?? null) ? $proposal['diagnosis'] : [],
            'nurse_diagnosis' => is_array($proposal['nurse_diagnosis'] ?? null) ? $proposal['nurse_diagnosis'] : [],
            'epicrisis' => (string) ($proposal['epicrisis'] ?? ''),
            'care_plan' => (string) ($proposal['care_plan'] ?? ''),
            'mobility' => is_array($proposal['mobility'] ?? null) ? $proposal['mobility'] : [],
            'expected_duration' => (string) ($proposal['expected_duration'] ?? ''),
            'procedures' => is_array($proposal['procedures'] ?? null) ? $proposal['procedures'] : [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function validateRows(array $rows, string $role): void
    {
        if (empty($rows)) {
            throw new \RuntimeException('Dataset (' . $role . ') je prázdny.');
        }

        $seen = [];

        foreach ($rows as $row) {
            $feedbackId = (int) ($row['feedback_id'] ?? 0);
            $prompt = trim((string) ($row['input_prompt'] ?? ''));
            $sections = data_get($row, 'expected.sections', []);

            if ($feedbackId <= 0 || $prompt === '' || ! is_array($sections) || empty($sections)) {
                throw new \RuntimeException('Dataset (' . $role . ') obsahuje neplatné záznamy.');
            }

            $hash = hash('sha256', $prompt . '|' . json_encode($sections, JSON_UNESCAPED_UNICODE));
            if (isset($seen[$hash])) {
                throw new \RuntimeException('Dataset (' . $role . ') obsahuje duplicitné vstup/výstup páry.');
            }
            $seen[$hash] = true;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(string $path): array
    {
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $raw = (string) Storage::disk('local')->get($path);
        $lines = preg_split('/\R/', $raw) ?: [];

        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeRows(string $path, array $rows): void
    {
        $dir = dirname($path);
        if (! Storage::disk('local')->exists($dir)) {
            Storage::disk('local')->makeDirectory($dir);
        }

        $lines = array_map(
            fn (array $row) => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $rows
        );

        Storage::disk('local')->put($path, implode("\n", $lines));
    }
}
