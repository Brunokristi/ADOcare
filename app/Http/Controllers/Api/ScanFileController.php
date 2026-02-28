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
            ],
        ]);
    }

    public function image(int $sessionId, string $filename)
    {
        $path = "scans/{$sessionId}/{$filename}";

        abort_unless(\Storage::disk('local')->exists($path), 404);

        return \Storage::disk('local')->response($path);
    }
}
