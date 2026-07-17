<?php

namespace App\Services\Vertex;

use App\Models\VertexTrainingRun;
use Illuminate\Support\Facades\Http;

class VertexTuningService
{
    public function __construct(
        private readonly VertexAutotrainStateService $stateService,
        private readonly VertexAuthService $authService
    ) {
    }

    public function createTuningJob(VertexTrainingRun $run): string
    {
        $projectId = trim((string) config('services.vertex_ai.project_id'));
        $location = trim((string) config('services.vertex_ai.dekurz.location', 'europe-southwest1'));
        $endpoint = trim((string) config('services.vertex_ai.auto_train.training_endpoint', 'tuningJobs'));
        $credentialsPath = trim((string) config('services.vertex_ai.credentials_path'));

        if ($projectId === '' || $location === '' || $credentialsPath === '') {
            throw new \RuntimeException('Vertex AI konfigurácia pre retrénovanie je neúplná.');
        }

        $baseModel = $this->resolveBaseModel();
        $accessToken = $this->authService->getAccessToken($credentialsPath);

        $url = sprintf(
            'https://%s-aiplatform.googleapis.com/v1/projects/%s/locations/%s/%s',
            $location,
            $projectId,
            $location,
            ltrim($endpoint, '/')
        );

        $response = Http::timeout(90)
            ->retry(2, 1000, throw: false)
            ->withToken($accessToken)
            ->acceptJson()
            ->post($url, [
                'baseModel' => $baseModel,
                'supervisedTuningSpec' => [
                    'trainingDatasetUri' => (string) $run->training_dataset_uri,
                    'validationDatasetUri' => (string) $run->validation_dataset_uri,
                ],
                'tunedModelDisplayName' => 'dekurz-autotrain-' . ($run->version ?: now()->format('Ymd-His')),
            ]);

        if (! $response->successful()) {
            $message = 'Nepodarilo sa spustiť Vertex tuning job.';
            $message .= ' HTTP ' . $response->status() . '.';
            $body = trim((string) $response->body());
            if ($body !== '') {
                $message .= ' ' . mb_substr($body, 0, 300);
            }

            throw new \RuntimeException($message);
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new \RuntimeException('Vertex tuning odpoveď je neplatná.');
        }

        $jobName = trim((string) ($decoded['name'] ?? ''));
        if ($jobName === '') {
            throw new \RuntimeException('Vertex tuning odpoveď neobsahuje názov jobu.');
        }

        return $jobName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTuningJob(string $jobName): array
    {
        $location = trim((string) config('services.vertex_ai.dekurz.location', 'europe-southwest1'));
        $credentialsPath = trim((string) config('services.vertex_ai.credentials_path'));

        if ($location === '' || $credentialsPath === '') {
            throw new \RuntimeException('Vertex AI konfigurácia je neúplná pre polling.');
        }

        $accessToken = $this->authService->getAccessToken($credentialsPath);

        $response = Http::timeout(60)
            ->retry(2, 1000, throw: false)
            ->withToken($accessToken)
            ->acceptJson()
            ->get(sprintf('https://%s-aiplatform.googleapis.com/v1/%s', $location, ltrim($jobName, '/')));

        if (! $response->successful()) {
            throw new \RuntimeException('Nepodarilo sa načítať Vertex tuning job. HTTP ' . $response->status() . '.');
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new \RuntimeException('Vertex tuning job odpoveď je neplatná.');
        }

        return $decoded;
    }

    public function cancelTuningJob(string $jobName): void
    {
        $location = trim((string) config('services.vertex_ai.dekurz.location', 'europe-southwest1'));
        $credentialsPath = trim((string) config('services.vertex_ai.credentials_path'));

        if ($location === '' || $credentialsPath === '') {
            return;
        }

        $accessToken = $this->authService->getAccessToken($credentialsPath);

        Http::timeout(30)
            ->retry(1, 500, throw: false)
            ->withToken($accessToken)
            ->post(sprintf('https://%s-aiplatform.googleapis.com/v1/%s:cancel', $location, ltrim($jobName, '/')));
    }

    /**
     * @param array<string, mixed> $completedJob
     * @return array<string, string>
     */
    public function resolveCompletedDeployment(array $completedJob): array
    {
        $modelName = trim((string) data_get($completedJob, 'tunedModel.model', ''));
        $endpointName = trim((string) data_get($completedJob, 'tunedModel.endpoint', ''));

        if ($modelName === '' || $endpointName === '') {
            throw new \RuntimeException('Vertex tuning job neobsahuje výsledný model alebo endpoint.');
        }

        preg_match('/endpoints\/([^\/]+)/', $endpointName, $endpointMatch);
        $endpointId = trim((string) ($endpointMatch[1] ?? ''));

        preg_match('/locations\/([^\/]+)/', $endpointName, $locationMatch);
        $location = trim((string) ($locationMatch[1] ?? config('services.vertex_ai.dekurz.location', '')));

        if ($endpointId === '' || $location === '') {
            throw new \RuntimeException('Vertex endpoint deployment údaje sú neplatné.');
        }

        return [
            'model_name' => $modelName,
            'endpoint_name' => $endpointName,
            'endpoint_id' => $endpointId,
            'location' => $location,
        ];
    }

    private function resolveBaseModel(): string
    {
        $state = $this->stateService->read();
        $activeModel = trim((string) ($state['active_model_name'] ?? ''));

        if ($activeModel !== '') {
            return $activeModel;
        }

        return trim((string) config('services.vertex_ai.auto_train.base_model', 'publishers/google/models/gemini-2.5-flash'));
    }

}
