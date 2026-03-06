<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class PointsBatchDocumentService
{
    public function getPointsBatchPayload(Document $document): ?array
    {
        if (! $document->path || ! Storage::disk('local')->exists($document->path)) {
            return null;
        }

        return json_decode(Storage::disk('local')->get($document->path), true);
    }


    public function createPointsBatch(array $data, $actor): array
    {
        $insuranceId = (int) data_get($data, 'insurance.id');
        $branchId  = (int) data_get($data, 'branch.id');
        $companyId = (int) data_get($data, 'company.id');
        $insuranceId = (int) data_get($data, 'insurance.id');

        $periodFromRaw = (string) data_get($data, 'period.0');
        $periodToRaw   = (string) data_get($data, 'period.1');

        $subtype = (string) data_get($data, 'batchType.code', 'N');

        $tz = 'Europe/Bratislava';
        $to = Carbon::parse($periodToRaw)->setTimezone($tz);

        $periodKey = $to->format('Y-m');

        return DB::transaction(function () use (
            $data, $actor, $branchId, $companyId, $periodFromRaw, $periodToRaw, $periodKey, $subtype, $insuranceId,
        ) {
            $type = 'points_batch';

            $existing = Document::query()
                ->where('type', $type)
                ->where('subtype', $subtype)
                ->where('user_id', $actor->id)
                ->where('branch_id', $branchId)
                ->where('period', $periodKey)
                ->where('insurance_company_id', $insuranceId)
                ->lockForUpdate()
                ->first();

            $newPath = 'points_batches/' . now()->timestamp . '_' . (int) data_get($data, 'batchNumber') . '.json';

            if ($existing) {
                if ($existing->path && Storage::disk('local')->exists($existing->path)) {
                    Storage::disk('local')->delete($existing->path);
                }

                $existing->update([
                    'company_id' => $companyId ?: $existing->company_id,
                    'mime_type'  => 'application/json',
                    'name'       => 'points_' . $subtype . '_davka_' . (int) data_get($data, 'batchNumber') . '_' . now()->format('d.m.Y'),
                    'path'       => $newPath,
                    'period'     => $periodKey,
                    'subtype'    => $subtype,
                    'insurance_company_id' => $insuranceId,
                ]);

                $document = $existing;
            } else {
                $document = Document::create([
                    'patient_id' => null,
                    'branch_id'  => $branchId,
                    'company_id' => $companyId ?: null,
                    'user_id'    => $actor->id,
                    'insurance_company_id' => $insuranceId,


                    'type'       => $type,
                    'subtype'    => $subtype,
                    'mime_type'  => 'application/json',

                    'name'       => 'points_' . $subtype . '_davka_' . (int) data_get($data, 'batchNumber') . '_' . now()->format('d.m.Y'),
                    'path'       => $newPath,
                    'period'     => $periodKey,
                ]);
            }

            $payload = [
                'document_id' => $document->id,
                'batchNumber' => (int) data_get($data, 'batchNumber'),
                'batchType'   => ['code' => $subtype],
                'insurance'   => ['id' => (int) data_get($data, 'insurance.id')],
                'period'      => [$periodFromRaw, $periodToRaw],
                'user'        => ['id' => $actor->id],
                'branch'      => ['id' => $branchId],
                'company'     => ['id' => $companyId ?: null],
                'patients'    => collect(data_get($data, 'patients', []))
                    ->map(fn ($p) => ['id' => (int) data_get($p, 'id')])
                    ->values()
                    ->all(),
                'meta' => data_get($data, 'meta', []),
                'saved_at' => now(),
            ];

            Storage::disk('local')->put(
                $document->path,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            return [$document, $payload];
        });
    }
}
