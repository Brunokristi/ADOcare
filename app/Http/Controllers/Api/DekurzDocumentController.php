<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\PatientPoint;
use App\Http\Requests\StoreDekurzRequest;
use App\Services\DekurzAiPrefillService;
use App\Services\DekurzDocumentService;
use App\Services\DocumentService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;


class DekurzDocumentController extends Controller
{
    public function __construct(private DekurzAiPrefillService $aiPrefillService)
    {
    }

    public function store(StoreDekurzRequest $request, DekurzDocumentService $service)
    {
        $document = $service->create($request->validated(), Auth::user());

        return response()->json([
            'success' => true,
            'document_id' => $document->id,
            'next_dekurz_number' => $document->next_dekurz_number,
            'message' => 'Dekurz bol úspešne vytvorený',
        ], 201);
    }

    public function show(Document $document, DekurzDocumentService $service)
    {
        $document->loadMissing(['user', 'patient']);

        $dekurzFile = $service->findDekurzFileForDocument($document);
        if (! $dekurzFile) {
            return response()->json(['message' => 'Dekurz data not found'], 404);
        }

        return response()->json(['document' => $document, 'dekurz_data' => $dekurzFile]);
    }

    public function last(Request $request, DekurzDocumentService $service)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
        ]);

        $doc = Document::query()
            ->where('type', 'dekurz')
            ->where('patient_id', (int) $data['patient_id'])
            ->orderByDesc('id')
            ->first();

        if (! $doc) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $dekurz = $service->findDekurzFileForDocument($doc);
        if (! $dekurz) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json(['success' => true, 'data' => ['document_id' => $doc->id, 'sections' => $dekurz['sections'] ?? []]]);
    }

    public function availableDates(Request $request, DekurzDocumentService $service)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'month' => 'required|date',
        ]);

        $result = $service->getAvailableDates((int) $data['patient_id'], $data['month']);

        return response()->json(['success' => true, 'message' => 'Available dates retrieved', 'data' => $result]);
    }

    public function prefillFromLatestProposal(Patient $patient)
    {
        try {
            $data = $this->aiPrefillService->buildFromLatestProposal($patient);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);
            return $this->error('Nepodarilo sa vygenerovať návrh textov dekurzu pomocou AI.', 500);
        }

        return $this->success($data, 'Návrh textov dekurzu bol úspešne vygenerovaný.');
    }

    public function improveText(Patient $patient, Request $request)
    {
        $request->validate([
            'text' => ['required', 'string', 'max:12000'],
        ]);

        try {
            $improved = $this->aiPrefillService->improveText((string) $request->input('text'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            report($e);
            return $this->error('Nepodarilo sa vylepšiť text pomocou AI.', 500);
        }

        return $this->success(['improved_text' => $improved], 'Text bol úspešne vylepšený pomocou AI.');
    }

    /**
     * Preview dekurz document as HTML via Blade template.
     */
    public function preview(Document $document, DekurzDocumentService $service)
    {
        $document->loadMissing('user');

        $dekurzData = $service->findDekurzFileForDocument($document);
        if (!$dekurzData) {
            return response()->json(['message' => 'Dekurz data not found'], 404);
        }

        $signatureDataUri = app(DocumentService::class)->getUserSignatureDataUri((int) ($dekurzData['user_id'] ?? 0));

        return response()->view('pdf.dekurz', [
            'dekurzData' => $dekurzData,
            'signatureDataUri' => $signatureDataUri,
        ])->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Download dekurz PDF generated from Blade template.
     */
    public function download(Document $document)
    {
        $document->loadMissing('user');

        $pdfPath = app(DocumentService::class)->getTravelDocumentPdfPath($document);
        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            return response()->json(['message' => 'Dekurz PDF not found'], 500);
        }

        $downloadName = pathinfo($document->name, PATHINFO_FILENAME) . '.pdf';

        return response()->download(Storage::disk('local')->path($pdfPath), $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get a signed public preview URL for dekurz document.
     */
    public function previewUrl(Document $document)
    {
        $this->authorize('view', $document);

        $url = URL::temporarySignedRoute(
            'documents.public',
            now()->addMinutes(15),
            ['document' => $document->id, 'format' => 'html']
        );

        return response()->json(['preview_url' => $url]);
    }

}
