<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CarCollection;
use App\Http\Resources\CarResource;
use App\Http\Filters\ApiQuery;
use App\Http\Responses\ApiResponse;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CarController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $query = Car::query();

        $results = ApiQuery::apply(request(), $query, searchable: ['evc'], allowedFilters: ['company_id', 'user_id']);

        return $this->success(new CarCollection($results), 'Cars retrieved');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'evc' => 'required|string|max:255',
            'company_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ]);

        $car = Car::create($data);

        return $this->success(new CarResource($car), 'Created', 201);
    }

    public function show($id)
    {
        $car = Car::find($id);
        if (!$car) {
            return $this->error('Not found', 404);
        }

        return $this->success(new CarResource($car), 'Car retrieved');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'evc' => 'sometimes|required|string|max:255',
            'company_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ]);

        $car = Car::find($id);
        if (!$car) {
            return $this->error('Not found', 404);
        }

        $car->fill($data);
        $car->save();

        return $this->success(new CarResource($car), 'Updated');
    }

    public function destroy($id)
    {
        $car = Car::find($id);
        if (!$car) {
            return $this->error('Not found', 404);
        }

        $car->delete();

        return $this->success(null, 'Deleted');
    }
}
