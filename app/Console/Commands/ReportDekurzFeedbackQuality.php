<?php

namespace App\Console\Commands;

use App\Models\DekurzAiFeedback;
use Illuminate\Console\Command;

/**
 * Report quality metrics for dekurz AI feedback records.
 */
class ReportDekurzFeedbackQuality extends Command
{
    protected $signature = 'ai:report-dekurz-feedback
        {--from=}
        {--to=}
        {--source=proposal_ai_prefill}';

    protected $description = 'Show acceptance and edit metrics for dekurz AI suggestions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $source = trim((string) $this->option('source'));
        $from = $this->option('from');
        $to = $this->option('to');

        $query = DekurzAiFeedback::query()->orderBy('id');

        if ($source !== '') {
            $query->where('source', $source);
        }

        if (!empty($from)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if (!empty($to)) {
            $query->whereDate('created_at', '<=', $to);
        }

        $total = 0;
        $withoutEdits = 0;
        $sumSimilarity = 0.0;
        $sumDeltaChars = 0;

        $query->chunkById(300, function ($feedbackItems) use (&$total, &$withoutEdits, &$sumSimilarity, &$sumDeltaChars) {
            foreach ($feedbackItems as $feedback) {
                $total++;

                if (!$feedback->has_user_edits) {
                    $withoutEdits++;
                }

                $suggested = $this->joinSectionTexts($feedback->suggested_sections ?? []);
                $final = $this->joinSectionTexts($feedback->final_sections ?? []);

                $sumSimilarity += $this->calculateSimilarity($suggested, $final);
                $sumDeltaChars += abs(mb_strlen($final) - mb_strlen($suggested));
            }
        });

        if ($total === 0) {
            $this->warn('No feedback records found for selected filters.');
            return self::SUCCESS;
        }

        $acceptanceRate = ($withoutEdits / $total) * 100;
        $avgSimilarity = ($sumSimilarity / $total) * 100;
        $avgDeltaChars = $sumDeltaChars / $total;

        $this->info('Dekurz AI Feedback Quality Report');
        $this->line('Total records: ' . $total);
        $this->line('Accepted without edits: ' . $withoutEdits);
        $this->line('Acceptance rate: ' . number_format($acceptanceRate, 2) . '%');
        $this->line('Average text similarity: ' . number_format($avgSimilarity, 2) . '%');
        $this->line('Average absolute char delta: ' . number_format($avgDeltaChars, 2));

        return self::SUCCESS;
    }

    /**
     * Join section texts to a single normalized string.
     */
    protected function joinSectionTexts(array $sections): string
    {
        return collect($sections)
            ->map(fn($section) => trim((string) ($section['text'] ?? '')))
            ->filter(fn(string $text) => $text !== '')
            ->values()
            ->implode("\n\n");
    }

    /**
     * Calculate similarity ratio between two strings.
     */
    protected function calculateSimilarity(string $left, string $right): float
    {
        $leftNorm = $this->normalizeForComparison($left);
        $rightNorm = $this->normalizeForComparison($right);

        $maxLen = max(mb_strlen($leftNorm), mb_strlen($rightNorm));
        if ($maxLen === 0) {
            return 1.0;
        }

        $leftForDistance = mb_substr($leftNorm, 0, 6000);
        $rightForDistance = mb_substr($rightNorm, 0, 6000);

        $distance = levenshtein($leftForDistance, $rightForDistance);
        $effectiveMax = max(strlen($leftForDistance), strlen($rightForDistance));

        if ($effectiveMax === 0) {
            return 1.0;
        }

        return max(0.0, 1.0 - ($distance / $effectiveMax));
    }

    /**
     * Normalize text for stable comparison.
     */
    protected function normalizeForComparison(string $text): string
    {
        $normalized = mb_strtolower(trim($text));

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }
}
