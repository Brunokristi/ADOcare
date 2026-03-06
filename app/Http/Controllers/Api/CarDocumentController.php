<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CarDocumentController extends Controller
{
    public function index(Car $car)
    {
        $documents = $car->documents()->get();

        return $this->success([
            'documents' => $documents,
        ]);
    }

    public function store(Request $request, Car $car)
    {
        $request->validate([
            'notes' => 'nullable|string',
            'file' => 'required|file|max:10240', // max 10MB
        ]);

        $file = $request->file('file');
        $path = $file->store("cars/{$car->id}/documents", 'local');

        $document = CarDocument::create([
            'car_id' => $car->id,
            'mime_type' => $file->getMimeType(),
            'path' => $path,
            'notes' => $request->input('notes'),
        ]);

        return $this->success([
            'document' => $document,
        ], 'Dokument bol úspešne nahraný', 201);
    }

    public function destroy(Car $car, CarDocument $document)
    {
        if ($document->car_id !== $car->id) {
            return $this->error('Document not found', 404);
        }

        if ($document->path && Storage::disk('local')->exists($document->path)) {
            Storage::disk('local')->delete($document->path);
        }

        $document->delete();

        return $this->success(null, 'Dokument bol vymazaný', 200);
    }

    public function download(Car $car, CarDocument $document)
    {
        if ($document->car_id !== $car->id) {
            return $this->error('Document not found', 404);
        }

        if (!Storage::disk('local')->exists($document->path)) {
            return $this->error('File not found', 404);
        }

        return Storage::disk('local')->download($document->path);
    }
}
