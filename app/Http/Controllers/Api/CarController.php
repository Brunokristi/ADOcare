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
use \App\Http\Controllers\Controller;

class CarController extends Controller
{


    /**
     * List cars
     *
     * @group Cars
     * @queryParam per_page int The number of items per page. Example: 15
     * @queryParam q string Search query across `evc`. Example: "abc123"
     * @queryParam filter[company_id] int Filter by company id. Example: 4
     * @queryParam filter[user_id] int Filter by user id. Example: 2
     * @response 200 {
     *  "data": [
     *    {"id":1, "evc":"ABC", "company_id":4, "user_id":2}
     *  ],
     *  "meta": {"total":1}
     * }
     */
    public function index()
    {
        $query = Car::query();

        $results = ApiQuery::apply(request(), $query, searchable: ['evc'], allowedFilters: ['company_id', 'user_id']);

        return $this->success(new CarCollection($results), 'Cars retrieved');
    }

    /**
     * Create a car
     *
     * @group Cars
     * @bodyParam evc string required The car EVC. Example: "ABC123"
     * @bodyParam company_id integer required Company id. Example: 4
     * @bodyParam user_id integer required Owner user id. Example: 2
     * @response 201 {
     *   "data": {"id":1, "evc":"ABC123", "company_id":4, "user_id":2}
     * }
     */
    public function store(StoreCarRequest $request, Car $car)
    {
        $car = Car::create($request->validated());
        return $this->success(new CarResource($car), 'Created', 201);
    }

    /**
     * Get a car
     *
     * @group Cars
     * @urlParam car int required The ID of the car. Example: 1
     * @response 200 {
     *   "data": {"id":1, "evc":"ABC123", "company_id":4, "user_id":2}
     * }
     */
    public function show(Car $car)
    {
        return $this->success(new CarResource($car), 'Car retrieved');
    }

    /**
     * Update a car
     *
     * @group Cars
     * @urlParam car int required The ID of the car. Example: 1
     * @bodyParam evc string The car EVC. Example: "NEW123"
     * @bodyParam company_id integer Company id. Example: 4
     * @bodyParam user_id integer Owner user id. Example: 2
     * @response 200 {
     *   "data": {"id":1, "evc":"NEW123", "company_id":4, "user_id":2}
     * }
     */
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

    public function destroyMany(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['message' => 'No IDs provided'], 400);
        }
        Car::whereIn('id', $ids)->delete();
        return $this->success(null, 'Cars deleted successfully');
    }
}
