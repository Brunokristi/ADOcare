<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use Illuminate\Support\Facades\Storage;
use App\Models\Document;

class ScanFileController extends Controller
{
    public function show(Document $document)
    {
        $this->authorize('view', $document);

        // document->path points to JSON like: scans/documents/132_1772264811.json
        if (!$document->path || !Storage::disk('local')->exists($document->path)) {
            return response()->json([
                'message' => 'Scan JSON súbor neexistuje.',
                'data' => null,
            ], 404);
        }

        $raw = Storage::disk('local')->get($document->path);

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return response()->json([
                'message' => 'Scan JSON je neplatný.',
                'data' => null,
            ], 422);
        }

        $sessionId = $json['scan_session_id'] ?? null;
        $paths = $json['image_paths'] ?? [];

        if (!$sessionId || !is_array($paths) || count($paths) === 0) {
            return response()->json([
                'message' => 'V scan JSON chýbajú obrázky alebo session.',
                'data' => null,
            ], 404);
        }

        // Convert stored paths like "scans/132/file.jpg" -> filename -> public URL via ScanFileController
        $images = [];
        foreach ($paths as $p) {
            if (!is_string($p) || $p === '') continue;
            $filename = basename(str_replace('\\', '/', $p));
            $images[] = [
                'name' => $filename,
                'url' => "/api/v1/scans/{$sessionId}/" . rawurlencode($filename),
            ];
        }

        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    public function image(int $sessionId, string $filename)
    {
        $path = "scans/{$sessionId}/{$filename}";

        abort_unless(\Storage::disk('local')->exists($path), 404);

        return \Storage::disk('local')->response($path);
    }

    public function updateText(Document $document)
    {
        $this->authorize('update', $document);

        $validated = request()->validate([
            'page_index' => 'nullable|integer|min:0',
            'text' => 'required|string',
        ]);

        if (!$document->path || !Storage::disk('local')->exists($document->path)) {
            return response()->json([
                'message' => 'Scan JSON súbor neexistuje.',
            ], 404);
        }

        $raw = Storage::disk('local')->get($document->path);
        $json = json_decode($raw, true);
        
        if (!is_array($json)) {
            return response()->json([
                'message' => 'Scan JSON je neplatný.',
            ], 422);
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

        return response()->json([
            'message' => 'Text bol úspešne aktualizovaný.',
            'data' => [
                'extracted_text' => $json['extracted_text'] ?? null,
                'extracted_pages' => $json['extracted_pages'] ?? null,
            ],
        ]);
    }
}
