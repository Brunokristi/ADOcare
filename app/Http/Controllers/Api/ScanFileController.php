<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Support\Facades\URL;

class ScanFileController extends Controller
{
    /**
     * Show scan metadata for a document.
     *
     * @group Documents
     * @urlParam document int required Document ID. Example: 1
     */
    public function show(Document $document)
    {
        $this->authorize('view', $document);

        // document->path points to JSON like: scans/documents/132_1772264811.json
        if (!$document->path || !Storage::disk('local')->exists($document->path)) {
            return $this->error('Scan JSON súbor neexistuje.', 404, ['data' => null]);
        }

        $raw = Storage::disk('local')->get($document->path);

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return $this->error('Scan JSON je neplatný.', 422, ['data' => null]);
        }

        $sessionId = $json['scan_session_id'] ?? null;
        $paths = $json['image_paths'] ?? [];

        if (!$sessionId || !is_array($paths) || count($paths) === 0) {
            return $this->error('V scan JSON chýbajú obrázky alebo session.', 404, ['data' => null]);
        }

        // Convert stored paths like "scans/132/file.jpg" -> filename -> public URL via ScanFileController
        $images = [];
        foreach ($paths as $p) {
            if (!is_string($p) || $p === '')
                continue;
            $filename = basename(str_replace('\\', '/', $p));
            $images[] = [
                'name' => $filename,
                'url' => "/api/v1/scans/{$sessionId}/" . rawurlencode($filename),
            ];
        }

        return $this->success([
            'document_id' => $json['document_id'] ?? $document->id,
            'scan_session_id' => (int) $sessionId,
            'image_count' => $json['image_count'] ?? count($images),
            'scanned_at' => $json['scanned_at'] ?? null,
            'patient_name' => $json['patient_name'] ?? null,
            'patient_birth_number' => $json['patient_birth_number'] ?? null,
            'images' => $images,
            'extracted_text' => $json['extracted_text'] ?? null,
            'extracted_pages' => $json['extracted_pages'] ?? null,
            'ocr_engine' => $json['ocr_engine'] ?? null,
            'ocr_at' => $json['ocr_at'] ?? null,
        ], 'Skenovaný dokument bol načítaný');
    }

    /**
     * Serve a scan image by session and filename.
     *
     * @group Documents
     * @urlParam sessionId int required Scan session ID. Example: 12
     * @urlParam filename string required Image filename. Example: image.jpg
     */
    public function image(int $sessionId, string $filename)
    {
        $path = "scans/{$sessionId}/{$filename}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        $absolutePath = Storage::disk('local')->path($path);

        return response()->file($absolutePath);
    }

    /**
     * Update extracted OCR text for a scan document.
     *
     * @group Documents
     * @urlParam document int required Document ID. Example: 1
     * @bodyParam page_index int optional Page index. Example: 0
     * @bodyParam text string required Text content.
     */
    public function updateText(Document $document)
    {
        $this->authorize('update', $document);

        $validated = request()->validate([
            'page_index' => 'nullable|integer|min:0',
            'text' => 'required|string',
        ]);

        if (!$document->path || !Storage::disk('local')->exists($document->path)) {
            return $this->error('Scan JSON súbor neexistuje.', 404);
        }

        $raw = Storage::disk('local')->get($document->path);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            return $this->error('Scan JSON je neplatný.', 422);
        }

        // Update the text based on page_index
        if (isset($validated['page_index']) && isset($json['extracted_pages'])) {
            $pageIndex = $validated['page_index'];
            if (isset($json['extracted_pages'][$pageIndex])) {
                $json['extracted_pages'][$pageIndex]['text'] = $validated['text'];
            }
        } else {
            // Update the main extracted_text
            $json['extracted_text'] = $validated['text'];
        }

        // Save back to JSON
        Storage::disk('local')->put($document->path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->success([
            'extracted_text' => $json['extracted_text'] ?? null,
            'extracted_pages' => $json['extracted_pages'] ?? null,
        ], 'Text bol úspešne aktualizovaný.');
    }

    /**
     * Preview scan document as HTML via Blade template.
     *
     * @group Documents
     * @urlParam document int required Document ID. Example: 1
     */
    public function preview(Document $document)
    {
        $this->authorize('view', $document);

        $preview = app(DocumentService::class)->getDocumentPreviewData($document);
        if (!$preview) {
            return $this->error('Náhľad skenu nie je dostupný', 404);
        }

        return response()
            ->view($preview['view'], $preview['data'])
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Download scan PDF generated from Blade template.
     *
     * @group Documents
     * @urlParam document int required Document ID. Example: 1
     */
    public function download(Document $document)
    {
        $this->authorize('view', $document);

        $pdfPath = app(DocumentService::class)->getTravelDocumentPdfPath($document);
        if (!$pdfPath || !Storage::disk('local')->exists($pdfPath)) {
            return $this->error('PDF skenu sa nenašlo', 500);
        }

        $downloadName = pathinfo($document->name, PATHINFO_FILENAME) . '.pdf';

        return response()->download(Storage::disk('local')->path($pdfPath), $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get a signed public preview URL for scan document.
     *
     * @group Documents
     * @urlParam document int required Document ID. Example: 1
     */
    public function previewUrl(Document $document)
    {
        $this->authorize('view', $document);

        $url = URL::temporarySignedRoute(
            'documents.public',
            now()->addMinutes(15),
            ['document' => $document->id, 'format' => 'html']
        );

        return $this->success(['preview_url' => $url]);
    }
}
