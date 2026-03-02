<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use App\Services\ScanDocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ScanUploadController extends Controller
{
    public function __construct(private ScanDocumentService $service)
    {
    }

    /**
     * Upload scanned images for a session (public, token-based).
     */
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'session_token' => ['required', 'string'],
            // validate presence/shape; actual files are read via $request->file()
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'file', 'max:10240'], // 10MB each, in KB
        ]);

        $session = $this->service->getSessionByToken($validated['session_token']);
        if (!$session) {
            return $this->error('Invalid or expired session', 401);
        }

        /** @var UploadedFile[] $files */
        $files = $request->file('images', []);

        if (!is_array($files) || count($files) === 0) {
            // This is the classic "mobile sent something but PHP didn't create UploadedFile"
            Log::warning('scan upload: no files parsed', [
                'has_images_key' => $request->has('images'),
                'content_type' => $request->header('content-type'),
            ]);

            return $this->error('No files received (images)', 422);
        }

        $stored = [];

        foreach ($files as $i => $file) {
            if (!$file) {
                Log::warning("scan upload: images.$i is null");
                continue;
            }

            // Upload error diagnostics (CRITICAL for mobile issues)
            if ($file->getError() !== UPLOAD_ERR_OK) {
                Log::warning("scan upload: images.$i upload error", [
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage(),
                    'original' => $file->getClientOriginalName(),
                    'client_mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);

                return $this->error("Upload failed for images.$i: " . $file->getErrorMessage(), 422);
            }

            // Mime allowlist (more reliable than 'mimes' for HEIC/webp in some setups)
            $allowed = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/heic',
                'image/heif',
            ];

            $clientMime = $file->getClientMimeType() ?: '';
            if (!in_array($clientMime, $allowed, true)) {
                return $this->error("Unsupported image type for images.$i ({$clientMime})", 422);
            }

            // Optional: normalize to JPG to avoid HEIC/webp downstream pobems
            // If you already do this in storeScannedImage(), remove this conversion and just store.
            $normalizedFile = $this->normalizeToJpegIfNeeded($file);

            $stored[] = $this->service->storeScannedImage($session, $normalizedFile);
        }

        return $this->success([
            'success' => true,
            'image_paths' => $stored,
            'image_count' => count($stored),
        ], 'Images uploaded successfully', 200);
    }

    /**
     * Finalize the scan session and create document.
     */
    public function finalize(Request $request)
    {
        $validated = $request->validate([
            'session_token' => ['required', 'string'],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'file', 'max:10240'], // 10MB each, in KB
        ]);

        $session = $this->service->getSessionByToken($validated['session_token']);
        if (!$session) return $this->error('Invalid or expired session', 401);
        if ($session->status === 'completed') return $this->error('Session already finalized', 400);

        /** @var UploadedFile[] $files */
        $files = $request->file('images', []);
        if (!is_array($files) || count($files) === 0) {
            Log::warning('scan finalize: no files parsed', [
                'has_images_key' => $request->has('images'),
                'content_type' => $request->header('content-type'),
            ]);
            return $this->error('No files received (images)', 422);
        }

        $filePaths = [];

        foreach ($files as $i => $file) {
            if (!$file) {
                Log::warning("scan finalize: images.$i is null");
                continue;
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                Log::warning("scan finalize: images.$i upload error", [
                    'error' => $file->getError(),
                    'error_message' => $file->getErrorMessage(),
                    'original' => $file->getClientOriginalName(),
                    'client_mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]);

                return $this->error("Upload failed for images.$i: " . $file->getErrorMessage(), 422);
            }

            $allowed = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/heic',
                'image/heif',
            ];

            $clientMime = $file->getClientMimeType() ?: '';
            if (!in_array($clientMime, $allowed, true)) {
                return $this->error("Unsupported image type for images.$i ({$clientMime})", 422);
            }

            $normalizedFile = $this->normalizeToJpegIfNeeded($file);

            $filePaths[] = $this->service->storeScannedImage($session, $normalizedFile);
        }

        $document = $this->service->createDocumentFromScans($session, $filePaths);

        return $this->success([
            'document_id' => $document->id,
            'message' => 'Scan document created successfully',
        ], 'Document created', 200);
    }

    /**
     * Get session info.
     */
    public function getSessionInfo(Request $request)
    {
        $validated = $request->validate([
            'session_token' => 'required|string',
        ]);

        $session = $this->service->getSessionByToken($validated['session_token']);

        if (!$session) {
            $session = ScanSession::where('session_token', $validated['session_token'])->first();
            if ($session) {
                return response()->json([
                    'message' => 'Session expired',
                    'data' => [
                        'debug' => [
                            'expires_at' => $session->expires_at->toIso8601String(),
                            'now' => now()->toIso8601String(),
                            'difference_seconds' => $session->expires_at->diffInSeconds(now()),
                            'is_active' => $session->isActive(),
                            'status' => $session->status,
                        ],
                    ],
                ], 401);
            }
            return $this->error('Invalid or expired session', 401);
        }

        $session->load('patient');
        $expiresInSeconds = now()->diffInSeconds($session->expires_at);

        return $this->success([
            'valid' => true,
            'session_id' => $session->id,
            'patient_id' => $session->patient_id,
            'patient_name' => trim(($session->patient->first_name ?? '') . ' ' . ($session->patient->last_name ?? '')),
            'expires_in' => max(1, $expiresInSeconds),
            'is_expired' => !$session->isActive(),
            'debug' => [
                'expires_at' => $session->expires_at->toIso8601String(),
                'now' => now()->toIso8601String(),
                'difference_seconds' => $expiresInSeconds,
            ],
        ]);
    }

    /**
     * Normalize HEIC/WEBP/PNG to JPG when possible.
     * Returns an UploadedFile (original or converted temp file).
     *
     * If you already do conversion in ScanDocumentService::storeScannedImage(), you can delete this.
     */
    private function normalizeToJpegIfNeeded(UploadedFile $file): UploadedFile
    {
        $mime = $file->getClientMimeType() ?: '';

        // Already JPEG -> keep
        if ($mime === 'image/jpeg') {
            return $file;
        }

        // If Imagick exists, it can handle HEIC in many server setups (when delegates installed).
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->readImage($file->getRealPath());
                $imagick->setImageFormat('jpeg');

                $tmpPath = tempnam(sys_get_temp_dir(), 'scan_') . '.jpg';
                $imagick->writeImage($tmpPath);
                $imagick->clear();
                $imagick->destroy();

                return new UploadedFile(
                    $tmpPath,
                    pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg',
                    'image/jpeg',
                    null,
                    true // test mode: do not require HTTP upload
                );
            } catch (\Throwable $e) {
                // Fall through to return original
                Log::warning('normalizeToJpegIfNeeded: imagick failed, storing original', [
                    'error' => $e->getMessage(),
                    'mime' => $mime,
                ]);
                return $file;
            }
        }

        // If no imagick, we can still convert PNG -> JPG via GD (not HEIC).
        if (in_array($mime, ['image/png', 'image/webp'], true) && extension_loaded('gd')) {
            try {
                $src = null;
                if ($mime === 'image/png') $src = imagecreatefrompng($file->getRealPath());
                if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $src = imagecreatefromwebp($file->getRealPath());

                if ($src) {
                    $tmpPath = tempnam(sys_get_temp_dir(), 'scan_') . '.jpg';
                    imagejpeg($src, $tmpPath, 90);
                    imagedestroy($src);

                    return new UploadedFile(
                        $tmpPath,
                        pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg',
                        'image/jpeg',
                        null,
                        true
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('normalizeToJpegIfNeeded: gd failed, storing original', [
                    'error' => $e->getMessage(),
                    'mime' => $mime,
                ]);
                return $file;
            }
        }

        // If we can't convert (likely HEIC without Imagick delegates), store original.
        return $file;
    }
}