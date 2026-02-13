<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Resources\BaseCollection;
use App\Models\Branch;
use App\Models\Document;
use App\Models\Patient;
use App\Services\CPDocumentService;
use App\Http\Requests\StoreCPRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CPDocumentController extends Controller
{
    public function __construct(private CPDocumentService $service)
    {
    }

    /**
     * List CP documents for current user.
     *
     * @group Documents
     * @queryParam branch_id int optional Filter by branch id.
     * @response 200 {"data": [{"id":1, "name":"cp_..."}]}
     */
    public function index(Request $request)
    {
        $query = Document::where('type', 'cp')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc');

        $documents = $query->get()->map(fn($doc) => [
            'id' => $doc->id,
            'name' => $doc->name,
            'type' => $doc->type,
            'mime_type' => $doc->mime_type,
            'created_at' => $doc->created_at,
            'path' => $doc->path,
        ]);

        return $this->success(['data' => $documents]);
    }

    /**
     * Create a CP (Cestovný príkaz) document for the current user.
     *
     * @group Documents
     * @bodyParam start date required Start date. Example: 2024-01-01
     * @bodyParam end date required End date. Example: 2024-01-31
     * @bodyParam branch_id integer required Branch ID. Example: 2
     * @response 201 {"document_id":123, "cp": {"document_id":123}}
     */
    public function store(StoreCPRequest $request)
    {
        [$document, $payload] = $this->service->createCp($request->validated(), $request->user());

        return $this->success([
            'document_id' => $document->id,
            'cp' => $payload,
        ], 'Cestovný príkaz bol úspešne vytvorený', 201);
    }

    /**
     * Show CP document payload.
     *
     * @group Documents
     * @urlParam document int required Document ID. Example: 123
     * @response 200 {"document": {...}, "cp_data": {...}}
     */
    public function show(Document $document)
    {
        $document->loadMissing('user');

        $payload = $this->service->getCpPayload($document);
        if (! $payload) {
            return $this->error('Cestovný príkaz data not found', 404);
        }

        return $this->success(['document' => $document, 'cp_data' => $payload]);
    }
}
