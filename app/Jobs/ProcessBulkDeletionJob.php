<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\BulkDeletionService;
use Illuminate\Support\Facades\Log;

class ProcessBulkDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $modelClass;
    public array $ids;
    public ?int $userId;

    public function __construct(string $modelClass, array $ids, ?int $userId = null)
    {
        $this->modelClass = $modelClass;
        $this->ids = $ids;
        $this->userId = $userId;
    }

    public function handle(BulkDeletionService $service)
    {
        // Require the original user to still exist and be authorized. If the user
        // cannot be rehydrated, abort — queued deletes must not run anonymously.
        if (!$this->userId) {
            Log::warning('ProcessBulkDeletionJob: no user id provided, aborting queued deletion', ['model' => $this->modelClass]);
            return;
        }

        $user = \App\Models\User::find($this->userId);
        if (!$user) {
            Log::warning('ProcessBulkDeletionJob: user no longer exists, aborting queued deletion', ['model' => $this->modelClass, 'user_id' => $this->userId]);
            return;
        }

        try {
            $service->authorizeIds($user, $this->modelClass, $this->ids);
        } catch (\Throwable $e) {
            Log::warning('ProcessBulkDeletionJob: authorization failed for queued deletion', ['model' => $this->modelClass, 'user_id' => $this->userId, 'error' => $e->getMessage()]);
            return;
        }

        $result = $service->processQueued($this->modelClass, $this->ids);
        Log::info('ProcessBulkDeletionJob completed', ['model' => $this->modelClass, 'processed' => $result['processed'], 'failed' => $result['failed'], 'user_id' => $this->userId]);
    }
}
