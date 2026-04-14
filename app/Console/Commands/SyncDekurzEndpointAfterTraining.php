<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Promote newly trained Vertex endpoint into active runtime state.
 */
class SyncDekurzEndpointAfterTraining extends Command
{
    private const VERTEX_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    protected $signature = 'ai:sync-dekurz-endpoint
        {--endpoint-id= : Manually set endpoint id if training job response does not contain it}';

    protected $description = 'Check latest Vertex training job and activate new endpoint when available';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $statePath = (string) config('services.vertex_ai.auto_train.state_path', 'ai/dekurz-autotrain/state.json');
        $state = $this->readState($statePath);

        $pendingJobName = (string) ($state['pending_job_name'] ?? '');
        if ($pendingJobName === '') {
            $this->info('No pending training job found.');
            return self::SUCCESS;
        }

        $job = $this->fetchTrainingJob($pendingJobName);
        $jobState = strtoupper((string) data_get($job, 'state', ''));

        if (!in_array($jobState, ['JOB_STATE_SUCCEEDED', 'SUCCEEDED'], true)) {
            if (in_array($jobState, ['JOB_STATE_FAILED', 'FAILED', 'JOB_STATE_CANCELLED', 'CANCELLED'], true)) {
                $this->writeState($statePath, [
                    ...$state,
                    'last_failed_job_name' => $pendingJobName,
                    'last_failed_job_state' => $jobState,
                    'last_failed_job_response' => $job,
                    'pending_job_name' => null,
                    'pending_feedback_id' => null,
                ]);

                $this->error('Training job failed or was cancelled: ' . $jobState);
                return self::FAILURE;
            }

            $this->info('Training job is not finished yet: ' . ($jobState !== '' ? $jobState : 'UNKNOWN'));
            return self::SUCCESS;
        }

        $endpointId = trim((string) $this->option('endpoint-id'));
        if ($endpointId === '') {
            $endpointId = $this->extractEndpointId($job) ?? '';
        }

        if ($endpointId === '') {
            $this->writeState($statePath, [
                ...$state,
                'last_successful_job_name' => $pendingJobName,
                'last_successful_job_response' => $job,
                'last_successful_job_at' => now()->toIso8601String(),
            ]);

            $this->warn('Training succeeded but endpoint id was not found in the job response.');
            $this->warn('Run again with --endpoint-id=<new-endpoint-id> to promote manually.');
            return self::SUCCESS;
        }

        $this->writeState($statePath, [
            ...$state,
            'active_endpoint_id' => $endpointId,
            'active_endpoint_switched_at' => now()->toIso8601String(),
            'last_successful_job_name' => $pendingJobName,
            'last_successful_job_response' => $job,
            'last_successful_job_at' => now()->toIso8601String(),
            'last_feedback_id' => (int) ($state['pending_feedback_id'] ?? ($state['last_feedback_id'] ?? 0)),
            'pending_job_name' => null,
            'pending_feedback_id' => null,
        ]);

        $this->info('New endpoint promoted: ' . $endpointId);

        return self::SUCCESS;
    }

    /**
     * Fetch a Vertex training job resource.
     *
     * @return array<string, mixed>
     */
    private function fetchTrainingJob(string $jobName): array
    {
        $location = (string) config('services.vertex_ai.location', 'europe-west1');
        $credentialsPath = (string) config('services.vertex_ai.credentials_path');

        if ($credentialsPath === '') {
            throw new \RuntimeException('Vertex AI credentials path is missing.');
        }

        $accessToken = $this->getVertexAccessToken($credentialsPath);

        $response = Http::timeout(60)
            ->retry(2, 1000)
            ->withToken($accessToken)
            ->get(sprintf('https://%s-aiplatform.googleapis.com/v1/%s', $location, ltrim($jobName, '/')));

        if (!$response->successful()) {
            throw new \RuntimeException('Unable to fetch Vertex training job. HTTP ' . $response->status());
        }

        $decoded = $response->json();
        if (!is_array($decoded)) {
            throw new \RuntimeException('Vertex training job response is invalid.');
        }

        return $decoded;
    }

    /**
     * Extract endpoint id from Vertex training job payload when available.
     */
    private function extractEndpointId(array $job): ?string
    {
        $candidates = [
            data_get($job, 'endpoint'),
            data_get($job, 'endpointName'),
            data_get($job, 'tunedModel.endpoint'),
            data_get($job, 'tunedModel.endpointName'),
            data_get($job, 'tunedModel.inferenceEndpoint'),
            data_get($job, 'output.endpoint'),
            data_get($job, 'output.endpointName'),
            data_get($job, 'deployedModel.endpoint'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            if (preg_match('/endpoints\/([^\/]+)/', $candidate, $matches) === 1) {
                return (string) $matches[1];
            }

            return trim($candidate);
        }

        return null;
    }

    /**
     * Read persisted auto-training state from local disk.
     *
     * @return array<string, mixed>
     */
    private function readState(string $statePath): array
    {
        if (!Storage::disk('local')->exists($statePath)) {
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
        if (!is_file($credentialsPath)) {
            throw new \RuntimeException('Service account JSON file was not found.');
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);
        if (!is_array($json)) {
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
        if (!$signed) {
            throw new \RuntimeException('Failed to sign JWT for Vertex AI access token.');
        }

        $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
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
