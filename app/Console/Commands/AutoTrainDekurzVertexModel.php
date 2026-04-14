<?php

namespace App\Console\Commands;

use App\Models\DekurzAiFeedback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Periodically export dekurz feedback and trigger a Vertex AI tuning job.
 */
class AutoTrainDekurzVertexModel extends Command
{
    private const VERTEX_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    protected $signature = 'ai:auto-train-dekurz
        {--dry-run : Validate pipeline and build/upload dataset without starting training job}
        {--force : Ignore minimum-new-feedback threshold}';

    protected $description = 'Automatically retrain dekurz Vertex model from captured user feedback';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $enabled = (bool) config('services.vertex_ai.auto_train.enabled', false);
        if (! $enabled) {
            $this->warn('Auto training is disabled. Set VERTEX_AUTOTRAIN_ENABLED=true to enable.');
            return self::SUCCESS;
        }

        $statePath = (string) config('services.vertex_ai.auto_train.state_path', 'ai/dekurz-autotrain/state.json');
        $datasetPath = (string) config('services.vertex_ai.auto_train.local_dataset_path', 'storage/app/private/ai-dataset-dekurz-feedback/train.jsonl');
        $datasetDisk = (string) config('services.vertex_ai.auto_train.dataset_disk', 'gcs');
        $datasetPrefix = trim((string) config('services.vertex_ai.auto_train.dataset_prefix', 'ai/dekurz-feedback'), '/');
        $minNewFeedback = (int) config('services.vertex_ai.auto_train.min_new_feedback', 25);
        $source = (string) config('services.vertex_ai.auto_train.source', 'proposal_ai_prefill');

        $state = $this->readState($statePath);

        if (($state['pending_job_name'] ?? '') !== '') {
            $this->warn('A previous training job is still pending promotion. Run ai:sync-dekurz-endpoint first.');
            return self::SUCCESS;
        }

        $lastFeedbackId = (int) ($state['last_feedback_id'] ?? 0);

        $latestFeedbackId = (int) DekurzAiFeedback::query()->max('id');
        if ($latestFeedbackId <= 0) {
            $this->warn('No dekurz feedback records found yet.');
            return self::SUCCESS;
        }

        $newFeedbackCount = DekurzAiFeedback::query()
            ->where('id', '>', $lastFeedbackId)
            ->when($source !== '', fn($query) => $query->where('source', $source))
            ->count();

        if (! $this->option('force') && $newFeedbackCount < $minNewFeedback) {
            $this->info('Skipping auto training. New feedback below threshold.');
            $this->line('New feedback: ' . $newFeedbackCount . ', required: ' . $minNewFeedback);
            return self::SUCCESS;
        }

        $buildExitCode = Artisan::call('ai:export-dekurz-feedback', [
            '--output' => $datasetPath,
            '--source' => $source,
        ]);

        if ($buildExitCode !== self::SUCCESS) {
            $this->error('Failed to build feedback dataset.');
            return self::FAILURE;
        }

        $absoluteDatasetPath = base_path($datasetPath);
        if (! is_file($absoluteDatasetPath)) {
            $this->error('Dataset file was not created: ' . $absoluteDatasetPath);
            return self::FAILURE;
        }

        $datasetSize = filesize($absoluteDatasetPath);
        if ($datasetSize === false || $datasetSize <= 0) {
            $this->error('Dataset is empty, training will not be started.');
            return self::FAILURE;
        }

        $timestamp = now()->format('Ymd_His');
        $remoteObjectPath = $datasetPrefix . '/train_' . $timestamp . '.jsonl';
        $datasetContent = file_get_contents($absoluteDatasetPath);

        if (! is_string($datasetContent)) {
            $this->error('Failed to read exported dataset file.');
            return self::FAILURE;
        }

        Storage::disk($datasetDisk)->put($remoteObjectPath, $datasetContent);

        $bucketName = (string) config('filesystems.disks.' . $datasetDisk . '.bucket');
        if ($bucketName === '') {
            $this->error('Configured dataset disk does not define bucket name.');
            return self::FAILURE;
        }

        $datasetUri = 'gs://' . $bucketName . '/' . $remoteObjectPath;
        $this->info('Dataset uploaded: ' . $datasetUri);

        if ($this->option('dry-run')) {
            $this->writeState($statePath, [
                ...$state,
                'last_dry_run_at' => now()->toIso8601String(),
                'last_dataset_uri' => $datasetUri,
                'last_feedback_seen_id' => $latestFeedbackId,
            ]);

            $this->info('Dry run completed. Training job not started.');
            return self::SUCCESS;
        }

        [$jobName, $rawResponse, $usedBaseModel] = $this->startVertexTrainingJob($datasetUri);

        $this->writeState($statePath, [
            ...$state,
            'pending_feedback_id' => $latestFeedbackId,
            'pending_job_name' => $jobName,
            'pending_job_started_at' => now()->toIso8601String(),
            'last_job_name' => $jobName,
            'last_job_response' => $rawResponse,
            'last_job_base_model' => $usedBaseModel,
            'last_dataset_uri' => $datasetUri,
            'last_trained_at' => now()->toIso8601String(),
        ]);

        $this->info('Vertex training job started: ' . $jobName);
        $this->line('Base model: ' . $usedBaseModel);

        return self::SUCCESS;
    }

    /**
      * Start Vertex AI tuning job and return [jobName, responseBody, usedBaseModel].
     *
      * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function startVertexTrainingJob(string $datasetUri): array
    {
        $projectId = (string) config('services.vertex_ai.project_id');
        $location = (string) config('services.vertex_ai.location', 'europe-west1');
        $credentialsPath = (string) config('services.vertex_ai.credentials_path');
        $baseModel = (string) config('services.vertex_ai.auto_train.base_model', 'gemini-2.0-flash-001');
          $configuredFallbackModels = (string) config('services.vertex_ai.auto_train.base_models', '');
        $endpoint = (string) config('services.vertex_ai.auto_train.training_endpoint', 'tuningJobs');

        if ($projectId === '' || $location === '' || $credentialsPath === '') {
            throw new \RuntimeException('Vertex AI configuration is incomplete for auto training.');
        }

        $displayName = 'dekurz-autotrain-' . now()->format('Ymd-His');

        $fallbackModels = array_filter(array_map('trim', explode(',', $configuredFallbackModels)));
        $models = array_values(array_unique(array_filter([
            $baseModel,
            ...$fallbackModels,
            'gemini-1.5-pro-002',
            'gemini-1.5-flash-002',
        ])));

        $accessToken = $this->getVertexAccessToken($credentialsPath);

        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/%s',
            $location,
            $projectId,
            $location,
            ltrim($endpoint, '/')
        );

        $lastError = null;

        foreach ($models as $candidateModel) {
            $payload = [
                'baseModel' => $candidateModel,
                'supervisedTuningSpec' => [
                    'trainingDatasetUri' => $datasetUri,
                ],
                'tunedModelDisplayName' => $displayName,
            ];

            $response = Http::timeout(90)
                ->retry(2, 1000, throw: false)
                ->withToken($accessToken)
                ->post($url, $payload);

            if (! $response->successful()) {
                $body = trim((string) $response->body());

                if ($response->status() === 400 && str_contains(mb_strtolower($body), 'not supported')) {
                    $lastError = 'Base model ' . $candidateModel . ' is not supported.';
                    continue;
                }

                $message = 'Failed to start Vertex training job. HTTP ' . $response->status();
                if ($body !== '') {
                    $message .= ' ' . mb_substr($body, 0, 400);
                }

                throw new \RuntimeException($message);
            }

            $decoded = $response->json();
            if (! is_array($decoded)) {
                throw new \RuntimeException('Vertex training response is invalid.');
            }

            $jobName = (string) ($decoded['name'] ?? 'unknown-job');

            return [$jobName, $decoded, $candidateModel];
        }

        throw new \RuntimeException($lastError ?? 'No supported base model available for Vertex tuning job.');
    }

    /**
     * Read persisted auto-training state from local disk.
     *
     * @return array<string, mixed>
     */
    private function readState(string $statePath): array
    {
        if (! Storage::disk('local')->exists($statePath)) {
            return [];
        }

        $raw = Storage::disk('local')->get($statePath);
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persist auto-training state to local disk.
     *
     * @param array<string, mixed> $state
     */
    private function writeState(string $statePath, array $state): void
    {
        Storage::disk('local')->put(
            $statePath,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Get OAuth access token for Vertex API from service account credentials.
     */
    private function getVertexAccessToken(string $credentialsPath): string
    {
        if (! is_file($credentialsPath)) {
            throw new \RuntimeException('Service account JSON file was not found.');
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);
        if (! is_array($json)) {
            throw new \RuntimeException('Service account JSON is invalid.');
        }

        $clientEmail = (string) ($json['client_email'] ?? '');
        $privateKey = (string) ($json['private_key'] ?? '');
        if ($clientEmail === '' || $privateKey === '') {
            throw new \RuntimeException('Service account JSON does not contain client_email or private_key.');
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
            throw new \RuntimeException('Failed to sign JWT for Vertex AI access token.');
        }

        $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Unable to retrieve Vertex access token.');
        }

        $accessToken = (string) data_get($response->json(), 'access_token', '');
        if ($accessToken === '') {
            throw new \RuntimeException('Vertex access token was not returned.');
        }

        return $accessToken;
    }

    /**
     * Encode value to base64url.
     */
    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
