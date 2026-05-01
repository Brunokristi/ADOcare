<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Requests\DestroyManyInvoiceRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Company;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$actor->company_id) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        $query = Invoice::query()
            ->with([
                'insuranceCompany:id,name',
                'user:id,title,first_name,last_name,company_id',
                'relatedInvoice:id,invoice_number',
            ])
            ->whereHas('user', fn($q) => $q->where('company_id', $actor->company_id))
            ->when($request->filled('period'), fn($q) => $q->where('period', $request->string('period')->toString()));

        $results = ApiQuery::apply(
            $request,
            $query,
            ['name', 'invoice_number', 'period', 'total', 'insuranceCompany' => ['name'], 'user' => ['title', 'first_name', 'last_name']],
            ['insurance_company_id', 'period', 'user_id', 'invoice_number'],
            ['sort' => '-updated_at']
        );

        if ($results instanceof LengthAwarePaginator) {
            $results->setCollection($results->getCollection()->map(fn(Invoice $invoice) => $this->toRow($invoice)));
        } elseif ($results instanceof Collection) {
            $results = $results->map(fn(Invoice $invoice) => $this->toRow($invoice));
        }

        return $this->success($results, 'Faktúry boli načítané');
    }

    public function store(StoreInvoiceRequest $request)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$actor->company_id) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validated();

        try {
            $invoice = DB::transaction(function () use ($actor, $validated) {
                $company = Company::query()->lockForUpdate()->find($actor->company_id);
                if (!$company) {
                    throw new \RuntimeException('Spoločnosť pre vystavenie faktúry nebola nájdená.');
                }

                // Guard: keep only one invoice per (type, period, insurance company) within actor's company.
                $this->deletePreviousScopedInvoices($actor, $validated);

                $nextInvoiceNumber = ((int) ($company->invoice_number ?? 0)) + 1;
                $company->invoice_number = $nextInvoiceNumber;
                $company->save();

                $jsonPath = sprintf(
                    'invoices/invoice_%s_%s_%s.json',
                    $actor->id,
                    str_replace('-', '', (string) $validated['period']),
                    now()->format('YmdHisv')
                );

                $invoice = Invoice::create([
                    'user_id' => $actor->id,
                    'name' => 'invoice_' . $nextInvoiceNumber . '.json',
                    'path' => $jsonPath,
                    'insurance_company_id' => $validated['insurance_company_id'] ?? null,
                    'period' => $validated['period'],
                    'type' => $validated['type'],
                    'total' => 0,
                    'invoice_number' => (string) $nextInvoiceNumber,
                    'related_invoice_id' => $validated['related_invoice_id'] ?? null,
                    'mime_type' => 'application/json',
                ]);

                if ($this->isNoteType((string) $invoice->type)) {
                    $relatedInvoice = isset($validated['related_invoice_id'])
                        ? Invoice::query()->find($validated['related_invoice_id'])
                        : null;

                    if (!$relatedInvoice || !$this->canAccess($actor, $relatedInvoice)) {
                        throw new \RuntimeException('Súvisiaca faktúra nebola nájdená alebo k nej nemáte prístup.');
                    }

                    $invoice->update([
                        'total' => (float) ($validated['amount'] ?? 0),
                        'insurance_company_id' => $validated['insurance_company_id'] ?? $relatedInvoice->insurance_company_id,
                    ]);
                } else {
                    $association = $this->getAssociatedDocumentsAndTotal($invoice, (int) $actor->company_id);
                    $invoice->update(['total' => $association['total']]);
                }

                $payload = $this->buildInvoicePayload($invoice, $actor);
                dump($payload, $invoice->path);
                Storage::disk('local')->put($invoice->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return $invoice;
            });
        } catch (\Throwable $e) {
            return $this->error('Nepodarilo sa vytvoriť faktúru', Response::HTTP_INTERNAL_SERVER_ERROR, [
                'message' => $e->getMessage(),
            ]);
        }

        $invoice->load(['insuranceCompany:id,name', 'user:id,title,first_name,last_name,company_id']);

        return $this->success($this->toRow($invoice), 'Faktúra bola vytvorená', Response::HTTP_CREATED);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$this->canAccess($actor, $invoice)) {
            return $this->forbidden('Nemáte oprávnenie na prístup k tejto faktúre');
        }

        $payload = $this->readDocumentJson($invoice->path);

        if (!is_array($payload) || !array_key_exists('company_register', $payload)) {
            $payload = $this->buildInvoicePayload($invoice, $actor);
            Storage::disk('local')->put($invoice->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $this->success($payload, 'Faktúra bola načítaná');
    }

    /**
     * Preview invoice document as HTML via Blade template.
     */
    public function preview(Request $request, Invoice $invoice)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$this->canAccess($actor, $invoice)) {
            return $this->forbidden('Nemáte oprávnenie na prístup k tejto faktúre');
        }

        $payload = $this->readDocumentJson($invoice->path);

        if (!is_array($payload) || !array_key_exists('company_register', $payload)) {
            $payload = $this->buildInvoicePayload($invoice, $actor);
            Storage::disk('local')->put($invoice->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return response()->view('pdf.invoice', [
            'invoiceData' => $payload,
        ])->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Download invoice PDF generated from Blade template.
     */
    public function download(Request $request, Invoice $invoice)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$this->canAccess($actor, $invoice)) {
            return $this->forbidden('Nemáte oprávnenie na prístup k tejto faktúre');
        }

        $pdfPath = app(DocumentService::class)->getInvoicePdfPath($invoice);
        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            return $this->error('PDF faktúry sa nenašlo', 500);
        }

        $downloadName = 'faktúra_' . $invoice->invoice_number . '.pdf';

        return response()->download(Storage::disk('local')->path($pdfPath), $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get a signed public preview URL for invoice.
     */
    public function previewUrl(Request $request, Invoice $invoice)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$this->canAccess($actor, $invoice)) {
            return $this->forbidden('Nemáte oprávnenie na prístup k tejto faktúre');
        }

        $url = URL::temporarySignedRoute(
            'invoices.public',
            now()->addMinutes(15),
            ['invoice' => $invoice->id, 'format' => 'html']
        );

        return $this->success(['preview_url' => $url]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$this->canAccess($actor, $invoice)) {
            return $this->forbidden('Nemáte oprávnenie upraviť túto faktúru');
        }

        $validated = $request->validated();
        $validated['mime_type'] = 'application/json';
        $manualAmount = null;

        if (array_key_exists('amount', $validated)) {
            $manualAmount = (float) $validated['amount'];
            unset($validated['amount']);
        }

        $invoice->update($validated);

        if ($this->isNoteType((string) $invoice->type)) {
            if (!$invoice->related_invoice_id) {
                return $this->error('Pre dobropis/ťarchopis je povinná súvisiaca faktúra.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($invoice->related_invoice_id) {
                $relatedInvoice = Invoice::query()->find($invoice->related_invoice_id);
                if (!$relatedInvoice || !$this->canAccess($actor, $relatedInvoice)) {
                    return $this->forbidden('Nemáte oprávnenie použiť zvolenú súvisiacu faktúru');
                }
            }

            $invoice->update([
                'total' => $manualAmount ?? (float) $invoice->total,
                'insurance_company_id' => $invoice->insurance_company_id ?? $invoice->relatedInvoice?->insurance_company_id,
            ]);
        } else {
            $association = $this->getAssociatedDocumentsAndTotal($invoice, (int) $actor->company_id);
            $invoice->update(['total' => $association['total']]);
        }

        $payload = $this->buildInvoicePayload($invoice, $actor);
        Storage::disk('local')->put($invoice->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $invoice->load(['insuranceCompany:id,name', 'user:id,title,first_name,last_name,company_id']);

        return $this->success($this->toRow($invoice), 'Faktúra bola aktualizovaná');
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$this->canAccess($actor, $invoice)) {
            return $this->forbidden('Nemáte oprávnenie odstrániť túto faktúru');
        }

        $this->deleteStoredFile($invoice->path);
        $invoice->delete();

        return $this->success(null, 'Faktúra bola odstránená');
    }

    public function destroyMany(DestroyManyInvoiceRequest $request)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$actor->company_id) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        $ids = $request->validated()['ids'];
        $invoices = Invoice::query()
            ->whereIn('id', $ids)
            ->whereHas('user', fn($q) => $q->where('company_id', $actor->company_id))
            ->get();

        foreach ($invoices as $invoice) {
            $this->deleteStoredFile($invoice->path);
            $invoice->delete();
        }

        return $this->success(null, 'Faktúry boli odstránené');
    }

    public function file(Request $request, Invoice $invoice)
    {
        $actor = $request->user();
        if (!$actor instanceof User || !$this->canAccess($actor, $invoice)) {
            return $this->forbidden('Nemáte oprávnenie na prístup k tejto faktúre');
        }

        if (!Storage::disk('local')->exists($invoice->path)) {
            return $this->notFound('Súbor faktúry neexistuje');
        }

        $absolutePath = Storage::disk('local')->path($invoice->path);
        $mimeType = $invoice->mime_type ?: Storage::disk('local')->mimeType($invoice->path);

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes($invoice->name) . '"',
        ]);
    }

    private function canAccess(User $actor, Invoice $invoice): bool
    {
        if (!$actor->company_id) {
            return false;
        }

        return (bool) $invoice->user()->where('company_id', $actor->company_id)->exists();
    }

    private function deleteStoredFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInvoicePayload(Invoice $invoice, User $actor): array
    {
        $invoice->loadMissing([
            'insuranceCompany:id,name,address,city,psc,ico,dic,ic_dph,register',
            'user:id,title,first_name,last_name,company_id',
            'user.company:id,name,address,city,psc,ico,dic,ic_dph,iban,bic,register',
            'relatedInvoice:id,invoice_number',
        ]);
        $association = $this->getAssociatedDocumentsAndTotal($invoice, (int) ($actor->company_id ?? 0));

        return [
            'company_name' => $invoice->user?->company?->name,
            'company_address' => $invoice->user?->company?->address,
            'company_city' => $invoice->user?->company?->city,
            'company_zip' => $invoice->user?->company?->psc,
            'company_ico' => $invoice->user?->company?->ico,
            'company_dic' => $invoice->user?->company?->dic,
            'company_ic_dph' => $invoice->user?->company?->ic_dph,
            'company_iban' => $invoice->user?->company?->iban,
            'company_bic' => $invoice->user?->company?->bic,
            'company_register' => $invoice->user?->company?->register,

            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'constant_symbol' => '0308',
            'due_date' => 'v zmysle zmluvy s poisťovňou',
            'payment_method' => 'bankový prevod',
            'invoice_created_at' => $invoice->created_at?->toDateTimeString(),
            'invoice_sent_at' => $invoice->created_at?->toDateTimeString(),
            'services_delivered_at' => $invoice->created_at?->toDateTimeString(),

            'insurance_company_id' => $invoice->insurance_company_id,
            'insurance_company_name' => $invoice->insuranceCompany?->name,
            'insurance_company_address' => $invoice->insuranceCompany?->address,
            'insurance_company_city' => $invoice->insuranceCompany?->city,
            'insurance_company_zip' => $invoice->insuranceCompany?->psc,
            'insurance_company_ico' => $invoice->insuranceCompany?->ico,
            'insurance_company_dic' => $invoice->insuranceCompany?->dic,
            'insurance_company_ic_dph' => $invoice->insuranceCompany?->ic_dph,
            'insurance_company_register' => $invoice->insuranceCompany?->register,

            'period' => $invoice->period,
            'type' => $invoice->type,
            'total' => (float) $association['total'],
            'related_invoice_id' => $invoice->related_invoice_id,
            'related_invoice_number' => $invoice->relatedInvoice?->invoice_number,
            'associated_documents' => $association['documents'],
            'created_by_user_id' => $actor->id,
            'created_by_user' => trim(($actor->title ?? '') . ' ' . ($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array{total: float, documents: array<int, array<string, mixed>>}
     */
    private function getAssociatedDocumentsAndTotal(Invoice $invoice, int $companyId): array
    {
        if ($this->isNoteType((string) $invoice->type)) {
            return [
                'total' => (float) $invoice->total,
                'documents' => [],
            ];
        }

        $targetType = $invoice->type === 'transport' ? 'kilometers_batch' : 'points_batch';

        $documentsQuery = Document::query()
            ->where('type', $targetType)
            ->where('period', $invoice->period)
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->with([
                'user:id,code,initials',
                'branch:id,code',
            ]);

        if ($invoice->insurance_company_id) {
            $documentsQuery->where('insurance_company_id', $invoice->insurance_company_id);
        }

        $documents = $documentsQuery->orderBy('created_at')->get();

        $total = 0.0;
        $rows = [];

        foreach ($documents as $document) {
            $json = $this->readDocumentJson($document->path);
            $amount = (float) data_get($json, 'meta.amount', 0);
            $total += $amount;

            $rows[] = [
                'branch_code' => $document->branch?->code,
                'user_code' => $document->user?->code,
                'user_initials' => $document->user?->initials,
                'amount' => $amount,
            ];
        }


        return [
            'total' => round($total, 2),
            'documents' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readDocumentJson(?string $path): ?array
    {
        if (!$path || !Storage::disk('local')->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function deletePreviousScopedInvoices(User $actor, array $validated): void
    {
        $query = Invoice::query()
            ->where('type', (string) $validated['type'])
            ->where('period', (string) $validated['period'])
            ->whereHas('user', fn($q) => $q->where('company_id', $actor->company_id));

        if (array_key_exists('insurance_company_id', $validated) && $validated['insurance_company_id']) {
            $query->where('insurance_company_id', (int) $validated['insurance_company_id']);
        } else {
            $query->whereNull('insurance_company_id');
        }

        $existing = $query->get();

        foreach ($existing as $invoice) {
            $this->deleteStoredFile($invoice->path);
            $invoice->delete();
        }
    }

    private function isNoteType(string $type): bool
    {
        return in_array($type, ['credit_note', 'debit_note'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Invoice $invoice): array
    {
        $nameParts = array_filter([
            $invoice->user?->title,
            $invoice->user?->first_name,
            $invoice->user?->last_name,
        ]);

        return [
            'id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'name' => $invoice->name,
            'path' => $invoice->path,
            'mime_type' => $invoice->mime_type,
            'insurance_company_id' => $invoice->insurance_company_id,
            'insurance_company_name' => $invoice->insuranceCompany?->name,
            'period' => $invoice->period,
            'type' => $invoice->type,
            'total' => (float) $invoice->total,
            'invoice_number' => $invoice->invoice_number,
            'related_invoice_id' => $invoice->related_invoice_id,
            'related_invoice_number' => $invoice->relatedInvoice?->invoice_number,
            'created_at' => $invoice->created_at?->toDateTimeString(),
            'updated_at' => $invoice->updated_at?->toDateTimeString(),
            'created_by_user' => implode(' ', $nameParts) ?: 'Neznámy',
        ];
    }
}
