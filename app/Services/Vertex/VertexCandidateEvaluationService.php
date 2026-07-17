<?php

namespace App\Services\Vertex;

use App\Models\VertexTrainingRun;
use Illuminate\Support\Facades\Storage;

class VertexCandidateEvaluationService
{
    public function __construct(
        private readonly DekurzPromptBuilder $promptBuilder,
        private readonly VertexInferenceClient $inferenceClient,
        private readonly VertexAutotrainStateService $stateService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluate(VertexTrainingRun $run): array
    {
        $datasetBase = 'ai/dekurz-autotrain/datasets';
        $validationRows = $this->readRows($datasetBase . '/validation.jsonl');
        $holdoutRows = $this->readRows($datasetBase . '/holdout.jsonl');
        $rows = array_values(array_merge($validationRows, $holdoutRows));

        if (empty($rows)) {
            throw new \RuntimeException('Chýbajú validačné dáta pre vyhodnotenie kandidáta.');
        }

        $state = $this->stateService->read();
        $currentLocation = trim((string) ($state['active_location'] ?? config('services.vertex_ai.dekurz.location')));
        $currentEndpointId = trim((string) ($state['active_endpoint_id'] ?? config('services.vertex_ai.dekurz.endpoint_id')));

        if ($currentLocation === '' || $currentEndpointId === '') {
            throw new \RuntimeException('Nie je dostupný aktuálny produkčný endpoint na porovnanie.');
        }

        $candidateMetrics = $this->runMetrics(
            $rows,
            (string) $run->new_location,
            (string) $run->new_endpoint_id
        );

        $currentMetrics = $this->runMetrics(
            $rows,
            $currentLocation,
            $currentEndpointId
        );

        $candidateScore = $this->computeScore($candidateMetrics);
        $currentScore = $this->computeScore($currentMetrics);

        $maxRegression = (float) config('services.vertex_ai.auto_train.max_score_regression', 0.01);

        return [
            'candidate' => $candidateMetrics,
            'current' => $currentMetrics,
            'candidate_score' => $candidateScore,
            'current_score' => $currentScore,
            'score_regression' => $currentScore - $candidateScore,
            'passes' => $this->passesThresholds($candidateMetrics, $candidateScore, $currentScore, $maxRegression),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function runMetrics(array $rows, string $location, string $endpointId): array
    {
        $validJson = 0;
        $requiredSchema = 0;
        $emptyOutputs = 0;
        $httpFailures = 0;
        $criticalErrors = 0;
        $latencySum = 0.0;

        $diagnosisPreserved = 0;
        $procedurePreserved = 0;
        $medicationPreserved = 0;
        $importantFactsPreserved = 0;
        $inventedDiagnosis = 0;
        $inventedMedication = 0;
        $inventedProcedure = 0;

        foreach ($rows as $row) {
            $prompt = (string) ($row['input_prompt'] ?? '');
            $expected = $row['expected'] ?? [];

            if ($prompt === '' || ! is_array($expected)) {
                $httpFailures++;
                continue;
            }

            $start = microtime(true);

            try {
                $response = $this->inferenceClient->invokeEndpoint($location, $endpointId, $prompt);
                $latencySum += (microtime(true) - $start) * 1000;
            } catch (\Throwable) {
                $httpFailures++;
                continue;
            }

            $text = trim((string) data_get($response, 'candidates.0.content.parts.0.text', ''));
            if ($text === '') {
                $text = trim((string) data_get($response, 'predictions.0.content.parts.0.text', ''));
            }

            if ($text === '') {
                $emptyOutputs++;
                continue;
            }

            if (str_contains($text, '```')) {
                $criticalErrors++;
            }

            $decoded = json_decode($text, true);

            if (! is_array($decoded)) {
                continue;
            }

            $validJson++;

            $sections = data_get($decoded, 'sections', []);
            if (! is_array($sections) || empty($sections)) {
                continue;
            }

            $allSectionsValid = collect($sections)->every(fn ($s) => is_array($s) && trim((string) ($s['text'] ?? '')) !== '');
            if (! $allSectionsValid) {
                continue;
            }

            $requiredSchema++;

            $expectedText = $this->joinSections(data_get($expected, 'sections', []));
            $actualText = $this->joinSections($sections);

            $diagnosisExpected = $this->extractDiagnosisCodes($expectedText);
            $diagnosisActual = $this->extractDiagnosisCodes($actualText);

            $procedureExpected = $this->extractProcedureCodes($expectedText);
            $procedureActual = $this->extractProcedureCodes($actualText);

            $medsExpected = $this->extractMedicationLikeTokens($expectedText);
            $medsActual = $this->extractMedicationLikeTokens($actualText);

            $diagnosisPreserved += $this->isSubset($diagnosisExpected, $diagnosisActual) ? 1 : 0;
            $procedurePreserved += $this->isSubset($procedureExpected, $procedureActual) ? 1 : 0;
            $medicationPreserved += $this->isSubset($medsExpected, $medsActual) ? 1 : 0;

            $importantFactsPreserved += $this->factPreservation($expectedText, $actualText);

            $inventedDiagnosis += count(array_diff($diagnosisActual, $diagnosisExpected));
            $inventedProcedure += count(array_diff($procedureActual, $procedureExpected));
            $inventedMedication += count(array_diff($medsActual, $medsExpected));

            if (
                count(array_diff($diagnosisActual, $diagnosisExpected)) > 0
                || count(array_diff($procedureActual, $procedureExpected)) > 0
            ) {
                $criticalErrors++;
            }
        }

        $total = max(count($rows), 1);

        return [
            'total' => count($rows),
            'valid_json_rate' => $validJson / $total,
            'required_fields_rate' => $requiredSchema / $total,
            'empty_output_rate' => $emptyOutputs / $total,
            'http_failures' => $httpFailures,
            'average_latency_ms' => $latencySum / $total,
            'diagnosis_preservation' => $diagnosisPreserved / $total,
            'procedure_preservation' => $procedurePreserved / $total,
            'medication_preservation' => $medicationPreserved / $total,
            'important_facts_preservation' => $importantFactsPreserved / $total,
            'invented_diagnosis_count' => $inventedDiagnosis,
            'invented_medication_count' => $inventedMedication,
            'invented_procedure_count' => $inventedProcedure,
            'critical_error_count' => $criticalErrors,
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     */
    private function computeScore(array $metrics): float
    {
        $score = 0.0;

        $score += ((float) ($metrics['valid_json_rate'] ?? 0.0)) * 0.30;
        $score += ((float) ($metrics['required_fields_rate'] ?? 0.0)) * 0.30;
        $score += ((float) ($metrics['diagnosis_preservation'] ?? 0.0)) * 0.10;
        $score += ((float) ($metrics['procedure_preservation'] ?? 0.0)) * 0.10;
        $score += ((float) ($metrics['medication_preservation'] ?? 0.0)) * 0.10;
        $score += ((float) ($metrics['important_facts_preservation'] ?? 0.0)) * 0.10;

        $score -= min(0.50, ((int) ($metrics['critical_error_count'] ?? 0)) * 0.02);
        $score -= min(0.20, ((int) ($metrics['http_failures'] ?? 0)) * 0.01);

        return max(0.0, min(1.0, $score));
    }

    /**
     * @param array<string, mixed> $candidateMetrics
     */
    private function passesThresholds(array $candidateMetrics, float $candidateScore, float $currentScore, float $maxRegression): bool
    {
        $minJson = (float) config('services.vertex_ai.auto_train.min_json_validity', 1.00);
        $minRequired = (float) config('services.vertex_ai.auto_train.min_required_fields_rate', 1.00);
        $maxHttpFailures = (int) config('services.vertex_ai.auto_train.max_http_failures', 0);
        $maxCritical = (int) config('services.vertex_ai.auto_train.max_critical_errors', 0);

        if ((float) ($candidateMetrics['valid_json_rate'] ?? 0.0) < $minJson) {
            return false;
        }

        if ((float) ($candidateMetrics['required_fields_rate'] ?? 0.0) < $minRequired) {
            return false;
        }

        if ((int) ($candidateMetrics['http_failures'] ?? 0) > $maxHttpFailures) {
            return false;
        }

        if ((int) ($candidateMetrics['critical_error_count'] ?? 0) > $maxCritical) {
            return false;
        }

        return ($currentScore - $candidateScore) <= $maxRegression;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     */
    private function joinSections(array $sections): string
    {
        return collect($sections)
            ->map(fn ($section) => trim((string) ($section['text'] ?? '')))
            ->filter(fn (string $text) => $text !== '')
            ->values()
            ->implode("\n\n");
    }

    /**
     * @return array<int, string>
     */
    private function extractDiagnosisCodes(string $text): array
    {
        preg_match_all('/\b[A-Z][0-9]{2,4}\b/u', strtoupper($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * @return array<int, string>
     */
    private function extractProcedureCodes(string $text): array
    {
        preg_match_all('/\b[0-9]{4}[A-Z]?\b/u', strtoupper($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * @return array<int, string>
     */
    private function extractMedicationLikeTokens(string $text): array
    {
        preg_match_all('/\b[A-Z][A-Z0-9\-]{3,}\b/u', strtoupper($text), $matches);

        return array_values(array_unique(array_filter($matches[0] ?? [], fn (string $token) => ! is_numeric($token))));
    }

    /**
     * @param array<int, string> $expected
     * @param array<int, string> $actual
     */
    private function isSubset(array $expected, array $actual): bool
    {
        if (empty($expected)) {
            return true;
        }

        return empty(array_diff($expected, $actual));
    }

    private function factPreservation(string $expectedText, string $actualText): float
    {
        $expectedWords = collect(preg_split('/\s+/u', mb_strtolower(trim($expectedText))) ?: [])
            ->filter(fn ($word) => mb_strlen($word) >= 4)
            ->values()
            ->all();

        $actualWords = collect(preg_split('/\s+/u', mb_strtolower(trim($actualText))) ?: [])
            ->filter(fn ($word) => mb_strlen($word) >= 4)
            ->values()
            ->all();

        if (empty($expectedWords)) {
            return 1.0;
        }

        $overlap = count(array_intersect($expectedWords, $actualWords));

        return $overlap / max(count($expectedWords), 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(string $path): array
    {
        if (! Storage::disk('local')->exists($path)) {
            return [];
        }

        $lines = preg_split('/\R/', (string) Storage::disk('local')->get($path)) ?: [];
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
}
