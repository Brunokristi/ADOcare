<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\CarService;
use Illuminate\Http\Request;

class CarServiceController extends Controller
{
    public function index(Car $car)
    {
        $services = CarService::where('car_id', $car->id)
            ->orderBy('date', 'desc')
            ->get();

        return $this->success([
            'services' => $services,
        ]);
    }

    public function store(Request $request, Car $car)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'interval_days' => 'required|integer|min:1',
            'date' => 'required|date',
        ]);

        $service = CarService::create([
            'car_id' => $car->id,
            'name' => $request->string('name'),
            'interval_days' => $request->integer('interval_days'),
            'date' => $request->input('date'),
            'active' => true,
        ]);

        return $this->success([
            'service' => $service,
        ], 'Služba bola vytvorená', 201);
    }

    public function update(Request $request, Car $car, CarService $service)
    {
        if ($service->car_id !== $car->id) {
            return $this->error('Service not found', 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'interval_days' => 'sometimes|integer|min:1',
            'date' => 'sometimes|date',
            'active' => 'sometimes|boolean',
        ]);

        $service->update($request->only([
            'name',
            'interval_days',
            'date',
            'active',
        ]));

        return $this->success([
            'service' => $service,
        ], 'Služba bola aktualizovaná', 200);
    }

    public function destroy(Car $car, CarService $service)
    {
        if ($service->car_id !== $car->id) {
            return $this->error('Service not found', 404);
        }

        $service->delete();

        return $this->success(null, 'Služba bola vymazaná', 200);
    }

    /**
     * Get all car services due this month
     */
    public function dueThisMonth(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return $this->success([
                'services' => [],
            ]);
        }

        // Get all cars for this company's users
        $services = CarService::query()
            ->whereHas('car', fn ($q) => $q->where('company_id', $company->id))
            ->where('active', true)
            ->whereNotNull('date')
            ->with('car.user')
            ->get()
            ->filter(fn ($s) => $s->isDueThisMonth())
            ->values();

        return $this->success([
            'services' => $services,
        ]);
    }

    /**
     * Get car services due this month for the logged-in user
     */
    public function dueThisMonthForUser(Request $request)
    {
        $user = $request->user();

        // Get all cars assigned to this user
        $services = CarService::query()
            ->whereHas('car', fn ($q) => $q->where('user_id', $user->id))
            ->where('active', true)
            ->whereNotNull('date')
            ->with('car.user')
            ->get()
            ->filter(fn ($s) => $s->isDueThisMonth())
            ->values();

        return $this->success([
            'services' => $services,
        ]);
    }
}
