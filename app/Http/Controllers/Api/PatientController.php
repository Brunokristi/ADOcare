<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PatientCollection;
use App\Http\Resources\PatientResource;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PatientController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $patients = Patient::query()->get();

        return $this->success(new PatientCollection($patients), 'Patients retrieved');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'personal_number' => 'nullable|string',
            'sex' => 'nullable|in:M,F',
        ]);

        $patient = Patient::create($data);

        return $this->success(new PatientResource($patient), 'Created', 201);
    }

    public function show($id)
    {
        $patient = Patient::find($id);
        if (! $patient) {
            return $this->error('Not found', 404);
        }

        return $this->success(new PatientResource($patient), 'Patient retrieved');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'personal_number' => 'nullable|string',
            'sex' => 'nullable|in:M,F',
        ]);

        $patient = Patient::find($id);
        if (! $patient) {
            return $this->error('Not found', 404);
        }

        $patient->fill($data);
        $patient->save();

        return $this->success(new PatientResource($patient), 'Updated');
    }

    public function destroy($id)
    {
        $patient = Patient::find($id);
        if (! $patient) {
            return $this->error('Not found', 404);
        }

        $patient->delete();

        return $this->success(null, 'Deleted');
    }
}
