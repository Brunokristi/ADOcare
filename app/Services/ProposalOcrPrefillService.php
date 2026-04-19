<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Patient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Builds proposal prefill data from the latest scanned OCR document via Gemini.
 */
class ProposalOcrPrefillService
{
    private const MAX_OCR_TEXT_CHARS = 24000;

    /**
     * Returns latest scan availability for patient.
     *
     * @param Patient $patient
     * @return array<string, mixed>
     */
    public function getLatestScanAvailability(Patient $patient): array
    {
        $document = $this->getLatestScanDocument($patient);
        if (! $document) {
            return [
                'has_scan_document' => false,
                'has_extracted_text' => false,
                'can_prefill' => false,
                'latest_scan_document_id' => null,
                'scanned_at' => null,
            ];
        }

        $payload = $this->readScanPayload($document);
        $ocrText = $this->extractOcrText($payload);

        return [
            'has_scan_document' => true,
            'has_extracted_text' => trim($ocrText) !== '',
            'can_prefill' => trim($ocrText) !== '',
            'latest_scan_document_id' => $document->id,
            'scanned_at' => $payload['scanned_at'] ?? null,
        ];
    }

    /**
     * Build structured proposal data from latest scan OCR text using Gemini.
     *
     * @param Patient $patient
     * @return array<string, mixed>
     */
    public function buildPrefillFromLatestScan(Patient $patient): array
    {
        $document = $this->getLatestScanDocument($patient);
        if (! $document) {
            throw new \RuntimeException('Pacient nemá žiadny naskenovaný dokument.');
        }

        $payload = $this->readScanPayload($document);
        $ocrText = $this->extractOcrText($payload);
        if (trim($ocrText) === '') {
            throw new \RuntimeException('V poslednom skene nie je dostupný OCR text.');
        }

        $structured = $this->callGeminiAndParse($ocrText);

        return [
            'latest_scan_document_id' => $document->id,
            'scanned_at' => $payload['scanned_at'] ?? null,
            'prefill' => $structured,
        ];
    }

    private function getLatestScanDocument(Patient $patient): ?Document
    {
        return Document::query()
            ->where('patient_id', $patient->id)
            ->where('type', 'scan')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @param Document $document
     * @return array<string, mixed>
     */
    private function readScanPayload(Document $document): array
    {
        if (! $document->path || ! Storage::disk('local')->exists($document->path)) {
            return [];
        }

        $raw = Storage::disk('local')->get($document->path);
        $json = json_decode($raw, true);

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractOcrText(array $payload): string
    {
        $main = (string) ($payload['extracted_text'] ?? '');
        if (trim($main) !== '') {
            return mb_substr($main, 0, self::MAX_OCR_TEXT_CHARS);
        }

        $pages = $payload['extracted_pages'] ?? [];
        if (! is_array($pages)) {
            return '';
        }

        $combined = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $text = trim((string) ($page['text'] ?? ''));
            if ($text !== '') {
                $combined[] = $text;
            }
        }

        return mb_substr(implode("\n\n", $combined), 0, self::MAX_OCR_TEXT_CHARS);
    }

    /**
     * @return array<string, mixed>
     */
    private function callGeminiAndParse(string $ocrText): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        $configuredModel = (string) config('services.gemini.model', 'gemini-2.0-flash');

        if ($apiKey === '') {
            throw new \RuntimeException('Gemini API kľúč nie je nastavený.');
        }

        $models = array_values(array_unique(array_filter([
            $configuredModel,
            'gemini-2.0-flash',
            'gemini-flash-latest',
        ])));

        $lastStatus = null;
        $lastBody = null;

        foreach ($models as $model) {
            $response = Http::timeout(45)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [[
                        'parts' => [[
                            'text' => $this->buildPrompt($ocrText),
                        ]],
                    ]],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'temperature' => 0.1,
                    ],
                ]
            );

            if (! $response->successful()) {
                $lastStatus = $response->status();
                $lastBody = $response->body();
                continue;
            }

            $json = $response->json();
            $text = (string) data_get($json, 'candidates.0.content.parts.0.text', '');
            if (trim($text) === '') {
                throw new \RuntimeException('Gemini nevrátila použiteľné dáta.');
            }

            $decoded = json_decode($text, true);
            if (! is_array($decoded)) {
                throw new \RuntimeException('Gemini vrátila neplatný JSON.');
            }

            return $this->normalizeGeminiOutput($decoded);
        }

        $message = 'Gemini API vrátila chybu.';
        if ($lastStatus) {
            $message .= ' HTTP ' . $lastStatus . '.';
        }
        if (is_string($lastBody) && trim($lastBody) !== '') {
            $message .= ' ' . mb_substr($lastBody, 0, 300);
        }

        throw new \RuntimeException($message);
    }

    private function buildPrompt(string $ocrText): string
    {
        return "Si asistent pre zdravotnícky systém. Z OCR textu extrahuj údaje pre návrh ošetrovateľskej starostlivosti.\n"
            . "Vráť výlučne JSON bez markdownu a bez ďalšieho textu.\n"
            . "Použi presne tento formát:\n"
            . "{\n"
            . "  \"medical_diagnosis_codes\": string[],\n"
            . "  \"nurse_diagnosis_codes\": string[],\n"
            . "  \"epicrisis_description\": string,\n"
            . "  \"care_plan\": string,\n"
            . "  \"patient_mobility\": (\"H\"|\"I\"|\"F\")[],\n"
            . "  \"expected_duration\": (\"one_month\"|\"three_months\"|\"six_months\"|\"over_six_months\"|\"\"),\n"
            . "  \"procedures\": [{ \"code\": string, \"frequency\": (\"weekdays\"|\"weekends\"|\"daily\"|\"every_other_day\"|\"three_times_weekly\"|\"twice_weekly\"|\"once_weekly\"|\"twice_monthly\"|\"once_monthly\"|\"as_needed\"|\"\") }]\n"
            . "}\n"
            . "Ak niečo nie je možné spoľahlivo zistiť, použi prázdnu hodnotu.\n"
            . "Nedomýšľaj kódy diagnóz ani výkonov.\n\n"
            . "OCR TEXT:\n"
            . $ocrText;
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private function normalizeGeminiOutput(array $decoded): array
    {
        $medical = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            is_array($decoded['medical_diagnosis_codes'] ?? null) ? $decoded['medical_diagnosis_codes'] : []
        )));

        $nurse = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            is_array($decoded['nurse_diagnosis_codes'] ?? null) ? $decoded['nurse_diagnosis_codes'] : []
        )));

        $allowedMobility = ['H', 'I', 'F'];
        $mobility = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            is_array($decoded['patient_mobility'] ?? null) ? $decoded['patient_mobility'] : []
        ), fn ($v) => in_array($v, $allowedMobility, true)));

        $allowedDuration = ['one_month', 'three_months', 'six_months', 'over_six_months', ''];
        $duration = trim((string) ($decoded['expected_duration'] ?? ''));
        if (! in_array($duration, $allowedDuration, true)) {
            $duration = '';
        }

        $allowedFrequency = [
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
            '',
        ];

        $procedures = [];
        $rawProcedures = is_array($decoded['procedures'] ?? null) ? $decoded['procedures'] : [];
        foreach ($rawProcedures as $procedure) {
            if (! is_array($procedure)) {
                continue;
            }

            $code = trim((string) ($procedure['code'] ?? ''));
            $frequency = trim((string) ($procedure['frequency'] ?? ''));

            if ($frequency !== '' && ! in_array($frequency, $allowedFrequency, true)) {
                $frequency = '';
            }

            if ($code === '' && $frequency === '') {
                continue;
            }

            $procedures[] = [
                'code' => $code,
                'frequency' => $frequency,
            ];
        }

        return [
            'medical_diagnosis_codes' => array_values(array_unique($medical)),
            'nurse_diagnosis_codes' => array_values(array_unique($nurse)),
            'epicrisis_description' => trim((string) ($decoded['epicrisis_description'] ?? '')),
            'care_plan' => trim((string) ($decoded['care_plan'] ?? '')),
            'patient_mobility' => array_values(array_unique($mobility)),
            'expected_duration' => $duration,
            'procedures' => $procedures,
        ];
    }
}
