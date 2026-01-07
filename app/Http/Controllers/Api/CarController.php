<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CarCollection;
use App\Http\Resources\CarResource;
use App\Http\Filters\ApiQuery;
use App\Http\Requests\StoreCarRequest;
use App\Http\Requests\UpdateCarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CarController extends Controller
{
    public function index()
    {
        $query = Car::query();

        $results = ApiQuery::apply(request(), $query, searchable: ['evc'], allowedFilters: ['company_id', 'user_id']);

        return $this->success(new CarCollection($results), 'Cars retrieved');
    }

    public function store(StoreCarRequest $request, Car $car)
   {
        $car = Car::create($request->validated());
        return $this->success(new CarResource($car), 'Created', 201);
    }

    public function show(Car $car)
    {
        return $this->success(new CarResource($car), 'Car retrieved');
    }

    public function update(UpdateCarRequest $request, Car $car)
    {
        $car->update($request->validated());
        return $this->success(new CarResource($car), 'Updated');
    }

    public function destroy(Car $car)
    {
        $car->delete();
        return $this->success(null, 'Deleted');
    }
}
