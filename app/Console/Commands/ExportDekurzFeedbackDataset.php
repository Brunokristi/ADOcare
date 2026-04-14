<?php

namespace App\Console\Commands;

use App\Models\DekurzAiFeedback;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Export final dekurz texts with proposal inputs captured from AI feedback.
 */
class ExportDekurzFeedbackDataset extends Command
{
    protected $signature = 'ai:export-dekurz-feedback
        {--output=storage/app/private/ai-dataset-dekurz-feedback/train.jsonl}
        {--from=}
        {--to=}
        {--source=proposal_ai_prefill}';

    protected $description = 'Export dekurz continuous-learning dataset from AI feedback records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputPath = base_path((string) $this->option('output'));
        File::ensureDirectoryExists(dirname($outputPath));

        $source = trim((string) $this->option('source'));
        $from = $this->option('from');
        $to = $this->option('to');

        $rows = [];

        $query = DekurzAiFeedback::query()
            ->whereNotNull('proposal_document_id')
            ->orderBy('id');

        if ($source !== '') {
            $query->where('source', $source);
        }

        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        $query->chunkById(200, function ($feedbackItems) use (&$rows) {
            foreach ($feedbackItems as $feedback) {
                $proposalPayload = $this->loadProposalPayload((int) $feedback->proposal_document_id);
                if ($proposalPayload === null) {
                    continue;
                }

                $finalSections = collect($feedback->final_sections ?? [])
                    ->map(function ($section) {
                        return [
                            'text' => trim((string) ($section['text'] ?? '')),
                        ];
                    })
                    ->filter(fn(array $section) => $section['text'] !== '')
                    ->values()
                    ->all();

                if (!count($finalSections)) {
                    continue;
                }

                $rows[] = [
                    'input_text' => $this->buildInput($proposalPayload),
                    'output_text' => json_encode(['sections' => $finalSections], JSON_UNESCAPED_UNICODE),
                ];
            }
        });

        $lines = array_map(
            fn(array $row) => json_encode($row, JSON_UNESCAPED_UNICODE),
            $rows
        );

        File::put($outputPath, implode("\n", $lines));

        $this->info('Export complete. Rows: ' . count($rows));
        $this->info('Output: ' . $outputPath);

        return self::SUCCESS;
    }

    /**
     * Load proposal payload from linked proposal document.
     */
    protected function loadProposalPayload(int $proposalDocumentId): ?array
    {
        $document = Document::find($proposalDocumentId);
        if (!$document || !$document->path) {
            return null;
        }

        if (!Storage::disk('local')->exists($document->path)) {
            return null;
        }

        $payload = json_decode((string) Storage::disk('local')->get($document->path), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Build training input from proposal payload.
     */
    protected function buildInput(array $proposal): string
    {
        $cleanedProposal = [
            'diagnosis' => $proposal['diagnosis'] ?? [],
            'nurse_diagnosis' => $proposal['nurse_diagnosis'] ?? [],
            'epicrisis' => $proposal['epicrisis'] ?? '',
            'care_plan' => $proposal['care_plan'] ?? '',
            'mobility' => $proposal['mobility'] ?? [],
            'expected_duration' => $proposal['expected_duration'] ?? '',
            'procedures' => $proposal['procedures'] ?? [],
        ];

        return implode("\n", [
            'You are given a structured nursing proposal.',
            'Generate likely dekurz section texts based on it.',
            'Return JSON only.',
            '',
            'INPUT JSON:',
            json_encode($cleanedProposal, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
