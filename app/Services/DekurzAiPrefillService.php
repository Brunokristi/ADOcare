<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Patient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DekurzAiPrefillService
{
    private const VERTEX_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /**
     * Build dekurz section draft texts from latest patient proposal via Gemini.
     *
     * @return array<string, mixed>
     */
    public function buildFromLatestProposal(Patient $patient): array
    {
        $document = Document::query()
            ->where('patient_id', $patient->id)
            ->where('type', 'proposal')
            ->orderByDesc('created_at')
            ->first();

        if (! $document) {
            throw new \RuntimeException('Pacient nemá žiadny návrh ošetrovateľskej starostlivosti.');
        }

        $proposal = $this->readProposalPayload($document);
        if (empty($proposal)) {
            throw new \RuntimeException('Posledný návrh ošetrovateľskej starostlivosti neobsahuje údaje.');
        }

        return [
            'proposal_document_id' => $document->id,
            'sections' => $this->callVertexAndParse($proposal),
        ];
    }

    /**
     * Improve user-written dekurz text while preserving medical meaning.
     */
    public function improveText(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            throw new \RuntimeException('Text na vylepšenie je prázdny.');
        }

        $response = $this->callVertexEndpoint($this->buildImprovePrompt($trimmed));
        $output = $this->extractPredictionPayload($response);

        if (is_array($output)) {
            $candidate = trim((string) ($output['text'] ?? $output['improved_text'] ?? ''));
            if ($candidate !== '') {
                return $this->extractImprovedText($candidate);
            }

            if (isset($output['sections']) && is_array($output['sections'])) {
                $parts = [];
                foreach ($output['sections'] as $section) {
                    if (! is_array($section)) {
                        continue;
                    }
                    $sectionText = trim((string) ($section['text'] ?? ''));
                    if ($sectionText !== '') {
                        $parts[] = $sectionText;
                    }
                }

                if (! empty($parts)) {
                    return implode("\n\n", $parts);
                }
            }
        }

        $raw = is_string($output) ? trim($output) : '';
        if ($raw === '') {
            throw new \RuntimeException('AI nevrátila použiteľný text.');
        }

        return $this->extractImprovedText($raw);
    }

    /**
     * @return array<string, mixed>
     */
    private function readProposalPayload(Document $document): array
    {
        if (! $document->path || ! Storage::disk('local')->exists($document->path)) {
            return [];
        }

        $raw = Storage::disk('local')->get($document->path);
        $json = json_decode($raw, true);

        return is_array($json) ? $json : [];
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<int, array{text: string}>
     */
    private function callVertexAndParse(array $proposal): array
    {
        $attempts = 2;
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $output = $this->extractPredictionPayload(
                    $this->callVertexEndpoint($this->buildPrompt($proposal, $attempt > 1))
                );
            } catch (\Throwable $e) {
                $lastError = $e;
                continue;
            }

            $parsed = $this->parseSectionsFromOutput($output);
            if ($parsed !== null && ! empty($parsed)) {
                return $parsed;
            }
        }

        if ($lastError !== null) {
            throw new \RuntimeException('AI služba je momentálne nedostupná. Skúste to prosím znova o chvíľu.');
        }

        throw new \RuntimeException('AI nevrátila použiteľné texty dekurzu. Skúste to prosím znova.');
    }

    /**
     * @return array<string, mixed>
     */
    private function callVertexEndpoint(string $userPrompt): array
    {
        $projectId = (string) config('services.vertex_ai.project_id');
        $location = (string) config('services.vertex_ai.location', 'europe-west1');
        $endpointId = $this->resolveActiveEndpointId();
        $credentialsPath = (string) config('services.vertex_ai.credentials_path');

        if ($projectId === '' || $location === '' || $endpointId === '' || $credentialsPath === '') {
            throw new \RuntimeException('Vertex AI konfigurácia nie je úplná.');
        }

        $accessToken = $this->getVertexAccessToken($credentialsPath);
        $response = Http::timeout(75)
            ->retry(2, 700)
            ->withToken($accessToken)
            ->post(
                sprintf(
                    'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/endpoints/%s:generateContent',
                    $location,
                    $projectId,
                    $location,
                    $endpointId
                ),
                [
                    'systemInstruction' => [
                        'parts' => [[
                            'text' => 'You are a nursing documentation assistant. Generate likely draft outputs based on the provided input. The output is only a draft suggestion for a nurse to review and edit. Return JSON only.',
                        ]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $userPrompt,
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.05,
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

        if (! $response->successful()) {
            $message = 'Vertex AI vrátila chybu.';
            $message .= ' HTTP ' . $response->status() . '.';
            if (trim($response->body()) !== '') {
                $message .= ' ' . mb_substr($response->body(), 0, 300);
            }

            throw new \RuntimeException($message);
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new \RuntimeException('Vertex AI vrátila neplatnú odpoveď.');
        }

        return $decoded;
    }

    /**
     * Resolve active endpoint id from auto-train state, fallback to static config.
     */
    private function resolveActiveEndpointId(): string
    {
        $defaultEndpointId = (string) config('services.vertex_ai.endpoint_id');
        $statePath = (string) config('services.vertex_ai.auto_train.state_path', 'ai/dekurz-autotrain/state.json');

        if ($statePath === '' || !Storage::disk('local')->exists($statePath)) {
            return $defaultEndpointId;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($statePath), true);
        if (!is_array($decoded)) {
            return $defaultEndpointId;
        }

        $activeEndpointId = trim((string) ($decoded['active_endpoint_id'] ?? ''));
        return $activeEndpointId !== '' ? $activeEndpointId : $defaultEndpointId;
    }

    /**
     * @return array<string, mixed>
     */
    private function callVertexModel(string $userPrompt): array
    {
        $projectId = (string) config('services.vertex_ai.project_id');
        $location = (string) config('services.vertex_ai.general_location', 'global');
        $generalModel = (string) config('services.vertex_ai.general_model', 'gemini-2.0-flash');
        $configuredFallbacks = (string) config('services.vertex_ai.general_models', '');
        $credentialsPath = (string) config('services.vertex_ai.credentials_path');

        if ($projectId === '' || $location === '' || $generalModel === '' || $credentialsPath === '') {
            throw new \RuntimeException('Vertex AI konfigurácia pre všeobecný model nie je úplná.');
        }

        $accessToken = $this->getVertexAccessToken($credentialsPath);
        $fallbackModels = array_filter(array_map('trim', explode(',', $configuredFallbacks)));
        $models = array_values(array_unique(array_filter([
            $generalModel,
            ...$fallbackModels,
            'gemini-2.0-flash',
            'gemini-2.0-flash-001',
            'gemini-1.5-flash-002',
            'gemini-1.5-pro-002',
        ])));

        $lastStatus = null;
        $lastBody = null;

        foreach ($models as $model) {
            $response = Http::timeout(60)
                ->withToken($accessToken)
                ->post(
                    sprintf(
                        'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/publishers/google/models/%s:generateContent',
                        $location,
                        $projectId,
                        $location,
                        $model
                    ),
                    [
                        'systemInstruction' => [
                            'parts' => [[
                                'text' => 'You are a nursing documentation assistant. Generate likely draft outputs based on the provided input. The output is only a draft suggestion for a nurse to review and edit. Return JSON only.',
                            ]],
                        ],
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [[
                                'text' => $userPrompt,
                            ]],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 2048,
                            'responseMimeType' => 'application/json',
                        ],
                    ]
                );

            if (! $response->successful()) {
                $lastStatus = $response->status();
                $lastBody = $response->body();

                if (in_array($lastStatus, [403, 404], true)) {
                    continue;
                }

                $message = 'Vertex AI (všeobecný model) vrátila chybu.';
                $message .= ' HTTP ' . $lastStatus . '.';
                if (trim((string) $lastBody) !== '') {
                    $message .= ' ' . mb_substr((string) $lastBody, 0, 300);
                }

                throw new \RuntimeException($message);
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new \RuntimeException('Vertex AI (všeobecný model) vrátila neplatnú odpoveď.');
            }

            return $decoded;
        }

        $message = 'Vertex AI (všeobecný model) nie je dostupná pre zvolené modely/lokáciu.';
        $message .= ' Skúšané modely: ' . implode(', ', $models) . '.';
        if ($lastStatus) {
            $message .= ' Posledné HTTP: ' . $lastStatus . '.';
        }
        if (is_string($lastBody) && trim($lastBody) !== '') {
            $message .= ' ' . mb_substr($lastBody, 0, 300);
        }

        throw new \RuntimeException($message);
    }

    /**
     * @param array<string, mixed> $proposal
     */
    private function buildPrompt(array $proposal, bool $strict = false): string
    {
        $input = [
            'diagnosis' => is_array($proposal['diagnosis'] ?? null) ? $proposal['diagnosis'] : [],
            'nurse_diagnosis' => is_array($proposal['nurse_diagnosis'] ?? null) ? $proposal['nurse_diagnosis'] : [],
            'epicrisis' => (string) ($proposal['epicrisis'] ?? ''),
            'care_plan' => (string) ($proposal['care_plan'] ?? ''),
            'mobility' => is_array($proposal['mobility'] ?? null) ? $proposal['mobility'] : [],
            'expected_duration' => (string) ($proposal['expected_duration'] ?? ''),
            'procedures' => is_array($proposal['procedures'] ?? null) ? $proposal['procedures'] : [],
        ];

        $base = "You are a nursing documentation assistant. Generate likely draft outputs based on the provided input. "
            . "The output is only a draft suggestion for a nurse to review and edit. Return JSON only.\n\n"
            . "You are given a structured nursing proposal. Generate likely dekurz section texts based on it.\n"
            . "Return only JSON in this exact shape: {\"sections\":[{\"text\":\"...\"}]}.\n"
            . "Use Slovak language. Keep medical terminology from input.\n"
            . "Do not output markdown, code fences, or explanations.\n\n"
            . "INPUT JSON:\n"
            . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (! $strict) {
            return $base;
        }

        return $base
            . "\n\nIMPORTANT: Return valid parseable JSON object only, starting with '{' and ending with '}'. "
            . "Do not truncate output.";
    }

    /**
     * @param array<string, mixed>|string|null $output
     * @return array<int, array{text: string}>|null
     */
    private function parseSectionsFromOutput(array|string|null $output): ?array
    {
        if (is_array($output)) {
            if (isset($output['sections']) && is_array($output['sections'])) {
                try {
                    return $this->normalizeSections($output);
                } catch (\Throwable) {
                    return null;
                }
            }

            if (isset($output['text'])) {
                $single = trim((string) $output['text']);
                return $single !== '' ? [['text' => $single]] : null;
            }

            return null;
        }

        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        $decodedJson = json_decode($output, true);
        if (! is_array($decodedJson)) {
            preg_match('/\{(?:[^{}]|(?R))*\}/s', $output, $match);
            $decodedJson = isset($match[0]) ? json_decode($match[0], true) : null;
        }

        if (is_array($decodedJson) && isset($decodedJson['sections']) && is_array($decodedJson['sections'])) {
            try {
                return $this->normalizeSections($decodedJson);
            } catch (\Throwable) {
                return null;
            }
        }

        $chunks = preg_split('/\n{2,}/', trim($output)) ?: [];
        $sections = [];
        foreach ($chunks as $chunk) {
            $text = trim(preg_replace('/^[\-\*\d\.\)\s]+/u', '', (string) $chunk) ?? '');
            if ($text !== '') {
                $sections[] = ['text' => $text];
            }
        }

        return ! empty($sections) ? $sections : null;
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<int, array{text: string}>
     */
    private function normalizeSections(array $decoded): array
    {
        $sections = [];
        $raw = is_array($decoded['sections'] ?? null) ? $decoded['sections'] : [];

        foreach ($raw as $section) {
            if (! is_array($section)) {
                continue;
            }

            $text = trim((string) ($section['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $sections[] = ['text' => $text];
        }

        if (empty($sections)) {
            throw new \RuntimeException('AI nevrátila žiadne použiteľné texty dekurzu.');
        }

        return $sections;
    }

    private function buildImprovePrompt(string $text): string
    {
        return "Improve the following nursing dekurz text in Slovak language.\n"
            . "Keep all medical facts, medications, procedures, and meaning unchanged.\n"
            . "Only improve readability, grammar, structure, and professional tone.\n"
            . "Do not add new clinical claims.\n"
            . "Return JSON only in this shape: {\"improved_text\":\"...\"}.\n\n"
            . "INPUT TEXT:\n"
            . $text;
    }

    private function extractImprovedText(string $raw): string
    {
        $text = trim($raw);
        if ($text === '') {
            throw new \RuntimeException('AI nevrátila použiteľný text.');
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $candidate = trim((string) ($decoded['improved_text'] ?? $decoded['text'] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $match) === 1) {
            $embedded = json_decode($match[0], true);
            if (is_array($embedded)) {
                $candidate = trim((string) ($embedded['improved_text'] ?? $embedded['text'] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $loose = $this->extractLooseImprovedText($text);
        if ($loose !== null && $loose !== '') {
            return $loose;
        }

        return $text;
    }

    private function extractLooseImprovedText(string $text): ?string
    {
        if (preg_match('/"improved_text"\s*:\s*"((?:\\\\.|[^"\\])*)"/s', $text, $m) === 1) {
            $candidate = trim(stripcslashes((string) $m[1]));
            return $candidate !== '' ? $candidate : null;
        }

        if (preg_match('/"improved_text"\s*:\s*"(.*)$/s', $text, $m) === 1) {
            $candidate = (string) $m[1];
            $candidate = preg_replace('/"\s*}\s*$/s', '', $candidate) ?? $candidate;
            $candidate = trim(stripcslashes($candidate));
            return $candidate !== '' ? $candidate : null;
        }

        return null;
    }

    private function getVertexAccessToken(string $credentialsPath): string
    {
        if (! is_file($credentialsPath)) {
            throw new \RuntimeException('Súbor so service account JSON sa nenašiel.');
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);
        if (! is_array($json)) {
            throw new \RuntimeException('Service account JSON je neplatný.');
        }

        $clientEmail = (string) ($json['client_email'] ?? '');
        $privateKey = (string) ($json['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            throw new \RuntimeException('Service account JSON neobsahuje client_email alebo private_key.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $claimSet = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'scope' => self::VERTEX_SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES));

        $unsignedJwt = $header . '.' . $claimSet;
        $signature = '';
        $signed = openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            throw new \RuntimeException('Nepodarilo sa podpísať JWT pre Vertex AI.');
        }

        $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Nepodarilo sa získať Vertex access token.');
        }

        $accessToken = (string) data_get($response->json(), 'access_token', '');
        if ($accessToken === '') {
            throw new \RuntimeException('Vertex access token nebol vrátený.');
        }

        return $accessToken;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<int, array{text: string}>
     */
    private function extractPredictionPayload(array $decoded): array|string|null
    {
        $candidates = [
            data_get($decoded, 'candidates.0.content.parts.0.text'),
            data_get($decoded, 'candidates.0.output'),
            data_get($decoded, 'predictions.0.text'),
            data_get($decoded, 'predictions.0.content.parts.0.text'),
            data_get($decoded, 'predictions.0.output'),
            data_get($decoded, 'predictions.0.response.text'),
            data_get($decoded, 'predictions.0'),
            data_get($decoded, 'deployedModelId') ? data_get($decoded, 'predictions.0') : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }

            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
