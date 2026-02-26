<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanSession;
use App\Services\ScanDocumentService;
use Illuminate\Http\Request;

class ScanUploadController extends Controller
{
    public function __construct(private ScanDocumentService $service)
    {
    }

    /**
     * Upload a scanned image for a session.
     * Called from mobile app - NO AUTH REQUIRED (uses session token).
     * Route is public/guest accessible.
     *
     * @group Documents
     * @bodyParam session_token string required Session token from QR code. Example: "abc..."
     * @bodyParam image file required Image file (jpg, png). Example: image.jpg
     * @response 200 {"success": true, "image_count": 1}
     */
    public function uploadImage(Request $request)
    {
        $validated = $request->validate([
            'session_token' => 'required|string',
            'image' => 'required|file|image|max:10240', // 10MB max
        ]);

        // Get and validate session
        $session = $this->service->getSessionByToken($validated['session_token']);
        if (!$session) {
            return $this->error('Invalid or expired session', 401);
        }

        // Store the image
        $imagePath = $this->service->storeScannedImage($session, $request->file('image'));

        return $this->success([
            'success' => true,
            'image_path' => $imagePath,
            'image_count' => count(\Illuminate\Support\Facades\Storage::disk('local')->files('scans/' . $session->id)),
        ], 'Image uploaded successfully', 200);
    }

    /**
     * Finalize the scan session and create document.
     * Called from mobile app when user finishes uploading.
     *
     * @group Documents
     * @bodyParam session_token string required Session token. Example: "abc..."
     * @response 200 {"document_id": 123, "message": "Document created"}
     */
    public function finalize(Request $request)
    {
        $validated = $request->validate([
            'session_token' => 'required|string',
        ]);

        // Get session
        $session = $this->service->getSessionByToken($validated['session_token']);
        if (!$session) {
            return $this->error('Invalid or expired session', 401);
        }

        if ($session->status === 'completed') {
            return $this->error('Session already finalized', 400);
        }

        // Get all uploaded images
        $scanDir = 'scans/' . $session->id;
        $files = \Illuminate\Support\Facades\Storage::disk('local')->files($scanDir);
        
        if (empty($files)) {
            return $this->error('No images uploaded', 400);
        }

        // Create document from scans
        $imagePaths = array_map(fn($file) => $scanDir . '/' . basename($file), $files);
        $document = $this->service->createDocumentFromScans($session, $imagePaths);

        return $this->success([
            'document_id' => $document->id,
            'message' => 'Scan document created successfully',
        ], 'Document created', 200);
    }

    /**
     * Get session info (for mobile app to verify it's valid).
     *
     * @group Documents
     * @bodyParam session_token string required Session token. Example: "abc..."
     * @response 200 {"valid": true, "patient_name": "John Doe", "expires_in": 3600}
     */
    public function getSessionInfo(Request $request)
    {
        $validated = $request->validate([
            'session_token' => 'required|string',
        ]);

        $session = $this->service->getSessionByToken($validated['session_token']);
        
        // If null, try to find it anyway for debugging
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
                        ]
                    ]
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
            ]
        ]);
    }
}
