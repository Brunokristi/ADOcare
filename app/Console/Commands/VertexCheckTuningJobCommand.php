<?php

namespace App\Console\Commands;

use App\Services\Vertex\VertexTuningService;
use Illuminate\Console\Command;

class VertexCheckTuningJobCommand extends Command
{
    protected $signature = 'vertex:check-job {job_name : Celý Vertex tuning job resource name}';

    protected $description = 'Skontroluje aktuálny stav Vertex tuning jobu';

    public function handle(VertexTuningService $tuningService): int
    {
        $jobName = (string) $this->argument('job_name');

        try {
            $job = $tuningService->getTuningJob($jobName);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line('Job: ' . (string) ($job['name'] ?? '-'));
        $this->line('State: ' . (string) ($job['state'] ?? '-'));
        $this->line('Create time: ' . (string) ($job['createTime'] ?? '-'));
        $this->line('Update time: ' . (string) ($job['updateTime'] ?? '-'));

        return self::SUCCESS;
    }
}
