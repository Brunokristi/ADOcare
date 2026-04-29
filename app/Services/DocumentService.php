<?php

namespace App\Services;

use App\Filters\ApiQuery;
use App\Mail\GenericEmail;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Document;
use App\Models\Patient;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DocumentService
{
    public function createCompanyTravelDocument(array $data, User $user): array
    {
        $branch = Branch::findOrFail((int) $data['branch_id']);

        if ($user->company_id && (int) $user->company_id !== (int) $branch->company_id) {
            throw new HttpException(403, 'Pobočka nepatrí do vašej spoločnosti.');
        }

        $period = Carbon::createFromFormat('Y-m', $data['period']);
        $start = $period->copy()->startOfMonth()->toDateString();
        $end = $period->copy()->endOfMonth()->toDateString();

        if ($data['type'] === 'cp') {
            [$document] = app(CPDocumentService::class)->createCp([
                'start' => $start,
                'end' => $end,
                'branch_id' => $branch->id,
                'job_title' => 'Manažér',
                'trip_purpose' => 'Pracovné stretnutia',
            ], $user);

            return ['document' => $document, 'type' => 'cp'];
        }

        [$document] = app(DZCDocumentService::class)->createManagerDzcFromVisitLocations([
            'period' => $period->format('Y-m'),
            'branch_id' => $branch->id,
        ], $user);

        return ['document' => $document, 'type' => 'dzc'];
    }

    public function getTravelDocuments(int $userId, ?int $branchId, ?string $period, int $perPage = 25): LengthAwarePaginator
    {
        $query = Document::query()
            ->whereIn('type', ['cp', 'dzc'])
            ->where('user_id', $userId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($period) {
            $query->where('period', Carbon::parse($period)->format('Y-m'));
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getTravelDocumentsForCompany(?array $branchIds, ?string $period, int $perPage = 25): LengthAwarePaginator
    {
        $query = Document::query()
            ->with([
                'user:id,title,first_name,last_name',
                'branch:id,address',
            ])
            ->whereIn('type', ['cp', 'dzc']);

        if (!empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        if ($period) {
            $query->where('period', Carbon::parse($period)->format('Y-m'));
        }

        $documents = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $documents->getCollection()->transform(function (Document $document) {
            $representative = $document->user;
            $branch = $document->branch;

            $userName = trim(implode(' ', array_filter([
                $representative?->title,
                $representative?->first_name,
                $representative?->last_name,
            ])));

            return [
                'id' => $document->id,
                'name' => $document->name,
                'type' => $document->type,
                'mime_type' => $document->mime_type,
                'path' => $document->path,
                'created_at' => $document->created_at?->toDateTimeString(),
                'updated_at' => $document->updated_at?->toDateTimeString(),
                'period' => $document->period,
                'created_by_user' => $userName ?: null,
                'created_by_branch' => $branch?->address ?: null,
            ];
        });

        return $documents;
    }

    public function buildTravelDocumentLinks(array $ids, User $user, int $ttlMinutes = 10080): array
    {
        $query = Document::query()
            ->whereIn('id', $ids)
            ->whereIn('type', ['cp', 'dzc']);

        if ($user->company_id) {
            $query->whereHas('user', fn($q) => $q->where('company_id', $user->company_id));
        }

        $documents = $query->get();
        if ($documents->isEmpty()) {
            return [];
        }

        return $documents->map(function (Document $document) use ($ttlMinutes) {
            return [
                'id' => $document->id,
                'name' => $document->name,
                'type' => $document->type,
                'period' => $document->period,
                'view_link' => $this->generatePublicDocumentLink($document, $ttlMinutes, false),
                'download_link' => $this->generatePublicDocumentLink($document, $ttlMinutes, true),
            ];
        })->toArray();
    }

    public function sendEmail(string $to, string $subject, string $view, array $viewData): void
    {
        Mail::to($to)->send(new GenericEmail($subject, $viewData, $view));
    }

    public function generatePublicDocumentLink(Document $document, int $ttlMinutes = 10080, bool $download = false): string
    {
        $parameters = ['document' => $document->id];
        if ($download) {
            $parameters['download'] = 1;
        }

        return URL::temporarySignedRoute(
            'documents.public',
            now()->addMinutes($ttlMinutes),
            $parameters
        );
    }

    public function deleteDocumentWithAssets(Document $document): bool
    {
        $this->deleteScanAssetsIfAny($document);
        $this->deleteTravelDocumentPdfCache($document);

        if (Storage::disk('local')->exists($document->path)) {
            return Storage::disk('local')->delete($document->path);
        }

        return true;
    }

    public function deleteManyDocumentsWithAssets(array $ids): void
    {
        $documents = Document::whereIn('id', $ids)->get();

        foreach ($documents as $document) {
            $this->deleteScanAssetsIfAny($document);

            if (Storage::disk('local')->exists($document->path)) {
                if (!Storage::disk('local')->delete($document->path)) {
                    \Log::error('Failed to delete document file (batch)', [
                        'document_id' => $document->id,
                        'path' => $document->path,
                    ]);
                }
            } else {
                \Log::warning('Document file not found on disk (batch)', [
                    'document_id' => $document->id,
                    'expected_path' => $document->path,
                ]);
            }
        }

        Document::whereIn('id', $ids)->delete();
    }

    public function documentExists(array $data, int $userId): array
    {
        $date = new \DateTime($data['date']);
        $period = $date->format('Y-m');
        $type = $data['type'];
        $patientId = $data['patient_id'] ?? null;
        $branchId = $data['branch_id'] ?? null;

        if ($patientId) {
            $document = Document::where('patient_id', $patientId)
                ->where('user_id', $userId)
                ->where('type', $type)
                ->where('period', $period)
                ->first();
        } else {
            $query = Document::where('user_id', $userId)
                ->where('type', $type)
                ->where('period', $period);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $document = $query->first();
        }

        return [
            'exists' => $document !== null,
            'document_id' => $document?->id,
        ];
    }

    public function getPatientDocuments(Request $request, int $patientId)
    {
        $query = Document::where('patient_id', $patientId);

        return ApiQuery::apply(
            $request,
            $query,
            ['name', 'type', 'created_at'],
            [],
            ['sort' => '-created_at']
        );
    }

    public function storePatientDocument(Request $request, int $patientId): Document
    {
        $file = $request->file('file');
        $path = $file->store("patients/{$patientId}/documents", 'local');

        return Document::create([
            'patient_id' => $patientId,
            'user_id' => auth()->id(),
            'type' => $request->input('type'),
            'mime_type' => $file->getMimeType(),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
        ]);
    }

    public function getTravelDocumentPdfPath(Document $document): ?string
    {
        $cachePath = $this->getTravelDocumentPdfCachePath($document);
        $disk = Storage::disk('local');


        if ($disk->exists($cachePath)) {
            $disk->delete($cachePath);
            // return $cachePath;
        }

        $pdfData = $this->buildTravelPdfAttachment($document);
        if (!$pdfData) {
            return null;
        }

        $disk->put($cachePath, $pdfData['data']);
        return $cachePath;
    }

    public function getUserSignatureDataUri(?int $userId): ?string
    {
        return $this->loadUserSignatureDataUri($userId);
    }

    public function getCompanyStampDataUri(?int $companyId): ?string
    {
        return $this->loadCompanyStampDataUri($companyId);
    }

    private function getTravelDocumentPdfCachePath(Document $document): string
    {
        return sprintf('documents/pdf/%s/%d.pdf', $document->type, $document->id);
    }

    private function deleteTravelDocumentPdfCache(Document $document): void
    {
        $cachePath = $this->getTravelDocumentPdfCachePath($document);
        if (Storage::disk('local')->exists($cachePath)) {
            Storage::disk('local')->delete($cachePath);
        }
    }

    private function buildTravelPdfAttachment(Document $document): ?array
    {
        if ($document->type === 'cp') {
            $payload = app(CPDocumentService::class)->getCpPayload($document);
            if (!$payload) {
                return null;
            }

            $signatureDataUri = $this->loadUserSignatureDataUri((int) ($payload['representative_id'] ?? 0));

            $pdf = Pdf::loadView('pdf.travel_cp', [
                'cpData' => $payload,
                'signatureDataUri' => $signatureDataUri,
            ])->setPaper('a4', 'portrait');

            return [
                'data' => $pdf->output(),
                'name' => $this->buildTravelPdfFilename('CP', $payload, $document),
                'mime' => 'application/pdf',
            ];
        }

        if ($document->type === 'dzc') {
            $payload = app(DZCDocumentService::class)->getDzcPayload($document);
            if (!$payload) {
                return null;
            }

            $signatureDataUri = $this->loadUserSignatureDataUri((int) ($payload['user_id'] ?? 0));

            $pdf = Pdf::loadView('pdf.travel_dzc', [
                'dzcData' => $payload,
                'signatureDataUri' => $signatureDataUri,
            ])->setPaper('a4', 'portrait');

            return [
                'data' => $pdf->output(),
                'name' => $this->buildTravelPdfFilename('DZP', $payload, $document),
                'mime' => 'application/pdf',
            ];
        }

        return null;
    }

    private function loadUserSignatureDataUri(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        $signaturePath = $user?->signature_path;

        if (!$signaturePath || !Storage::disk('local')->exists($signaturePath)) {
            return null;
        }

        $binary = Storage::disk('local')->get($signaturePath);
        if ($binary === null || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }

    private function loadCompanyStampDataUri(?int $companyId): ?string
    {
        if (!$companyId) {
            return null;
        }

        $company = Company::find($companyId);
        $stampPath = $company?->stamp_path;

        if (!$stampPath || !Storage::disk('local')->exists($stampPath)) {
            return null;
        }

        $binary = Storage::disk('local')->get($stampPath);
        if ($binary === null || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($binary);
    }

    private function buildTravelPdfFilename(string $prefix, array $payload, Document $document): string
    {
        $period = (string) ($payload['period'] ?? $document->period ?? 'unknown-period');

        if ($period === 'unknown-period') {
            $month = (string) ($payload['month'] ?? '');
            $year = (string) ($payload['year'] ?? '');
            if ($month !== '' && $year !== '') {
                $period = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT);
            }
        }

        $rawUserName = trim(implode(' ', array_filter([
            $document->user->last_name ?? null,
        ])));

        if ($rawUserName === '') {
            $rawUserName = 'unknown-user';
        }

        $normalizedUserName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $rawUserName);
        if ($normalizedUserName === false || trim($normalizedUserName) === '') {
            $normalizedUserName = $rawUserName;
        }

        return $this->sanitizeFilenamePart($prefix)
            . '_' . $this->sanitizeFilenamePart($period)
            . '_' . $this->sanitizeFilenamePart($normalizedUserName)
            . '.pdf';
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', '_', $value) ?? $value;
        $value = preg_replace('/[^A-Za-z0-9_\-.]/', '', $value) ?? $value;

        return $value === '' ? 'unknown' : $value;
    }

    private function deleteScanAssetsIfAny(Document $document): void
    {
        if ($document->type !== 'scan') {
            return;
        }

        $sessionId = null;

        if (Storage::disk('local')->exists($document->path)) {
            try {
                $raw = Storage::disk('local')->get($document->path);
                $json = json_decode($raw, true);
                $sessionId = (int) ($json['scan_session_id'] ?? 0);
            } catch (\Throwable $e) {
                \Log::warning('Failed to parse scan document JSON for cleanup', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$sessionId) {
            return;
        }

        $scanDir = "scans/{$sessionId}";
        if (Storage::disk('local')->exists($scanDir)) {
            Storage::disk('local')->deleteDirectory($scanDir);
        }
    }
}
