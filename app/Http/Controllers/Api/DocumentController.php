<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents for a patient.
     */
    public function index($patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $this->authorize('view', $patient);

        $documents = Document::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($documents);
    }

    /**
     * Store a newly created document.
     */
    public function store(Request $request, $patientId)
    {
        $patient = Patient::findOrFail($patientId);
        $this->authorize('update', $patient);

        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'type' => 'required|string',
        ]);

        $file = $request->file('file');
        $path = $file->store("patients/{$patientId}/documents", 'local');

        $document = Document::create([
            'patient_id' => $patientId,
            'user_id' => Auth::id(),
            'type' => $validated['type'],
            'mime_type' => $file->getMimeType(),
            'name' => $file->getClientOriginalName(),
            'path' => $path,
        ]);

        return response()->json($document, 201);
    }

    /**
     * Display the specified document.
     */
    public function show(Document $document)
    {
        $this->authorize('view', $document);

        return response()->json($document);
    }

    /**
     * Download the specified document.
     */
    public function download(Document $document)
    {
        $this->authorize('view', $document);

        return Storage::disk('local')->download($document->path, $document->name);
    }

    /**
     * Update the specified document.
     */
    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        $validated = $request->validate([
            'type' => 'sometimes|string',
            'name' => 'sometimes|string',
        ]);

        $document->update($validated);

        return response()->json($document);
    }

    /**
     * Delete the specified document.
     */
    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        // Delete file from storage using unlink
        $fullPath = storage_path('app/' . $document->path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    /**
     * Delete multiple documents.
     */
    public function destroyMany(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            return response()->json(['message' => 'No IDs provided'], 400);
        }
        
        $documents = Document::whereIn('id', $ids)->get();
        
        // Delete files from storage using unlink
        foreach ($documents as $document) {
            $fullPath = storage_path('app/' . $document->path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        
        // Delete documents from database
        Document::whereIn('id', $ids)->delete();
        
        return response()->json(['message' => 'Documents deleted successfully']);
    }

    /**
     * Get documents by type for a patient.
     */
    public function getByType($patientId, $type)
    {
        $patient = Patient::findOrFail($patientId);
        $this->authorize('view', $patient);

        $documents = Document::where('patient_id', $patientId)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($documents);
    }

    /**
     * Generate PDF from document data
     */
    public function generatePdf(Request $request)
    {
        $validated = $request->validate([
            'documentData' => 'required|array',
            'user' => 'required|array',
            'user.id' => 'nullable|integer',
            'branch' => 'required|array',
            'branch.id' => 'nullable|integer',
        ]);

        try {
            $documentData = $validated['documentData'];

            // Build the HTML from the document data
            $html = $this->buildProposalHtml($documentData);

            // Generate PDF using DomPDF
            $pdf = Pdf::loadHTML($html)
                ->setOption('isPhpEnabled', true)
                ->setOption('margin-top', 5)
                ->setOption('margin-right', 5)
                ->setOption('margin-bottom', 5)
                ->setOption('margin-left', 5)
                ->setPageSize('A4');

            return $pdf->stream('proposal.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate PDF',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build HTML for the proposal document
     */
    private function buildProposalHtml($data)
    {
        $date = !empty($data['documentDate']) 
            ? \Carbon\Carbon::parse($data['documentDate'])->format('d.m.Y')
            : date('d.m.Y');

        // Extract data with defaults
        $facilityName = $data['facilityName'] ?? '';
        $facilityAddress = $data['facilityAddress'] ?? '';
        $patientName = $data['patientName'] ?? '';
        $patientIdNumber = $data['patientIdNumber'] ?? '';
        $patientHealthCode = $data['patientHealthCode'] ?? '';
        $patientCurrentAddress = $data['patientCurrentAddress'] ?? '';
        $patientPreviousAddress = $data['patientPreviousAddress'] ?? '';
        $prescriptionNote = $data['prescriptionNote'] ?? '';
        $doctorDiagnosis = $data['doctorDiagnosis'] ?? '';
        $diagnosisCode = $data['diagnosisCode'] ?? '';
        $sistersHygieneExpectedDays = $data['sistersHygieneExpectedDays'] ?? '';
        $patientCategory = $data['patientCategory'] ?? '';
        $carePlan = $data['carePlan'] ?? '';
        $treatmentOutcomes = $data['treatmentOutcomes'] ?? '';
        $expectedDuration = $data['expectedDuration'] ?? '';
        $doctorName = $data['doctorName'] ?? '';

        // Checkbox checks
        $checkH = ($patientCategory === 'H') ? 'checked' : '';
        $checkI = ($patientCategory === 'I') ? 'checked' : '';
        $checkF = ($patientCategory === 'F') ? 'checked' : '';

        // Radio checks
        $radio1m = ($expectedDuration === 'do1mesiac') ? 'checked' : '';
        $radio3m = ($expectedDuration === 'do3mesiacov') ? 'checked' : '';
        $radio6m = ($expectedDuration === 'do6mesiacov') ? 'checked' : '';
        $radioOver6m = ($expectedDuration === 'nad6mesiacov') ? 'checked' : '';

        // Show previous address only if present
        $prevAddressRow = !empty($patientPreviousAddress) 
            ? '<div class="field-row"><div class="field-label">Dočasná adresa:</div><div class="field-value">' . htmlspecialchars($patientPreviousAddress) . '</div></div>'
            : '';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Návrh na poskytovanie ošetrovateľskej starostlivosti</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
            background: white;
        }
        .document-wrapper {
            max-width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 15px;
            box-sizing: border-box;
        }
        h1 {
            text-align: center;
            font-size: 14px;
            margin: 0 0 15px 0;
            font-weight: bold;
        }
        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .section-title {
            font-weight: bold;
            font-size: 11px;
            margin: 8px 0 4px 0;
            border-bottom: 1px solid #333;
        }
        .field-row {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px;
            margin-bottom: 4px;
            align-items: start;
        }
        .field-label {
            font-weight: bold;
            padding: 2px 0;
        }
        .field-value {
            border: 1px solid #ccc;
            padding: 3px 4px;
            background: #f9f9f9;
            word-break: break-word;
        }
        .text-box {
            border: 1px solid #ccc;
            padding: 4px;
            background: #f9f9f9;
            min-height: 20px;
            margin-bottom: 4px;
        }
        .text-box.large {
            min-height: 40px;
        }
        .checkbox-group, .radio-group {
            margin: 4px 0;
        }
        .checkbox-item, .radio-item {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
            font-size: 10px;
        }
        .checkbox-item input, .radio-item input {
            margin-right: 4px;
            width: 14px;
            height: 14px;
        }
        .two-col {
            grid-template-columns: 1fr 1fr;
        }
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        .signature-block {
            text-align: center;
            border-top: 1px solid #333;
            padding-top: 40px;
            min-height: 50px;
        }
        .signature-label {
            font-size: 9px;
            font-weight: bold;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .document-wrapper {
                margin: 0;
                padding: 10px;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="document-wrapper">
        <h1>Návrh na poskytovanie ošetrovateľskej starostlivosti</h1>

        <!-- Healthcare Facility Section -->
        <div class="section">
            <div class="field-row">
                <div class="field-label">Zdravotnícke zariadenie:</div>
                <div class="field-value">$facilityName</div>
            </div>
            <div class="field-row">
                <div class="field-label">Adresa:</div>
                <div class="field-value">$facilityAddress</div>
            </div>
        </div>

        <!-- Patient Information Section -->
        <div class="section">
            <div class="section-title">PACIENT</div>
            <div class="field-row">
                <div class="field-label">Meno pacienta:</div>
                <div class="field-value">$patientName</div>
            </div>
            <div class="field-row">
                <div class="field-label">Rodné číslo:</div>
                <div class="field-value">$patientIdNumber</div>
            </div>
            <div class="field-row">
                <div class="field-label">Kód poistenca:</div>
                <div class="field-value">$patientHealthCode</div>
            </div>
            <div class="field-row">
                <div class="field-label">Trvalá adresa:</div>
                <div class="field-value">$patientCurrentAddress</div>
            </div>
            $prevAddressRow
        </div>

        <!-- Prescription Note Section -->
        <div class="section">
            <div class="section-title">POSUDOK, LEKÁRSKY PREDPIS</div>
            <div class="text-box large">$prescriptionNote</div>
        </div>

        <!-- Medical Diagnosis Section -->
        <div class="section">
            <div class="section-title">DIAGNÓZA</div>
            <div class="field-row">
                <div class="field-label">Lekárska diagnóza:</div>
                <div class="field-value">$doctorDiagnosis</div>
            </div>
            <div class="field-row">
                <div class="field-label">Kód diagnózy:</div>
                <div class="field-value">$diagnosisCode</div>
            </div>
            <div class="field-row">
                <div class="field-label">Sesterská diagnóza:</div>
                <div class="field-value">$sistersHygieneExpectedDays</div>
            </div>
        </div>

        <!-- Patient Category Section -->
        <div class="section">
            <div class="section-title">KATEGÓRIA PACIENTA</div>
            <div class="checkbox-group">
                <div class="checkbox-item">
                    <input type="checkbox" $checkH disabled>
                    <span>H - Pacient s obmedzenou pohyblivosťou (50%)</span>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" $checkI disabled>
                    <span>I - Pacient úplne nepohyblivý (75%)</span>
                </div>
                <div class="checkbox-item">
                    <input type="checkbox" $checkF disabled>
                    <span>F - Pacient s psychickou diagnózou/duševnou retardáciou (75%)</span>
                </div>
            </div>
        </div>

        <!-- Care Plan Section -->
        <div class="section">
            <div class="section-title">PLÁN OŠETROVATEĽSKEJ STAROSTLIVOSTI</div>
            <div class="text-box large">$carePlan</div>
        </div>

        <!-- Treatment Methods Section -->
        <div class="section">
            <div class="section-title">METÓDY LIEČBY - KÓDY PROCEDÚR A FREKVENCIA</div>
            <div class="text-box large">$treatmentOutcomes</div>
        </div>

        <!-- Expected Duration Section -->
        <div class="section">
            <div class="section-title">PREDPOKLADANÁ DĹŽKA POSKYTOVANIA STAROSTLIVOSTI</div>
            <div class="radio-group">
                <div class="radio-item">
                    <input type="radio" $radio1m disabled>
                    <span>Do 1 mesiaca</span>
                </div>
                <div class="radio-item">
                    <input type="radio" $radio3m disabled>
                    <span>Do 3 mesiacov</span>
                </div>
                <div class="radio-item">
                    <input type="radio" $radio6m disabled>
                    <span>Do 6 mesiacov</span>
                </div>
                <div class="radio-item">
                    <input type="radio" $radioOver6m disabled>
                    <span>Viac ako 6 mesiacov</span>
                </div>
            </div>
        </div>

        <!-- Doctor Information Section -->
        <div class="section">
            <div class="section-title">PODPIS LEKÁRA</div>
            <div class="field-row">
                <div class="field-label">Lekár:</div>
                <div class="field-value">$doctorName</div>
            </div>
            <div class="field-row">
                <div class="field-label">Dátum:</div>
                <div class="field-value">$date</div>
            </div>
        </div>

        <!-- Signature Blocks -->
        <div class="signature-section">
            <div class="signature-block">
                <div style="font-size: 9px;">Podpis lekára a pečiatka</div>
            </div>
            <div class="signature-block">
                <div style="font-size: 9px;">Podpis zástupcu zdravotníckeho zariadenia a pečiatka</div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}
