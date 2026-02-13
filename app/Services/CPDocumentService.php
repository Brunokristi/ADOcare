<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Branch;
use Illuminate\Support\Facades\Storage;

class CPDocumentService
{
    /**
     * Create a CP document and persist its JSON payload.
     * Returns [Document, payload]
     */
    public function createCp(array $data, $actor): array
    {
        $branch = Branch::findOrFail($data['branch_id']);

        $document = Document::create([
            'patient_id' => null,
            'user_id' => $actor->id,
            'type' => 'cp',
            'mime_type' => 'application/json',
            'name' => 'cp_' . now()->format('d.m.Y'),
            'path' => 'cps/cp_' . now()->timestamp . '.json',
            'branch_id' => $branch->id,
        ]);

        $company = $branch->company;
        $car = $actor->cars()->first();
        $representative = $company?->representative;

        $startDate = $data['start'];
        $month = date('m', strtotime($startDate));
        $year = date('Y', strtotime($startDate));

        $lastdayPreviousMonth = date("Y-m-d", strtotime("last day of previous month", strtotime($startDate)));
        while (in_array(date('N', strtotime($lastdayPreviousMonth)), [6, 7])) {
            $lastdayPreviousMonth = date("Y-m-d", strtotime($lastdayPreviousMonth . " -1 day"));
        }

        $payload = [
            'company_name' => $company?->name ?? '',
            'user_id' => $actor->id,
            'ico' => $company?->ico ?? '',
            'city' => $company?->city ?? '',
            'user_name' => trim(($actor->title ?? '') . ' ' . ($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')),
            'start_date' => $startDate,
            'end_date' => $data['end'],
            'month' => $month,
            'year' => $year,
            'car_model' => $car?->model ?? '',
            'car_license_plate' => $car?->evc ?? '',
            'representative_name' => trim(($representative?->title ?? '') . ' ' . ($representative?->first_name ?? '') . ' ' . ($representative?->last_name ?? '')),
            'lastday_previous_month' => $lastdayPreviousMonth,
            'document_id' => $document->id,
            'created_at' => now(),
        ];

        Storage::disk('local')->put($document->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return [$document, $payload];
    }

    public function getCpPayload(Document $document): ?array
    {
        if (! $document->path || ! Storage::disk('local')->exists($document->path)) return null;
        $content = Storage::disk('local')->get($document->path);
        return json_decode($content, true);
    }
}
