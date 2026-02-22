<?php

namespace Tests\Unit;

use App\Services\VisitsService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisitsServiceTest extends TestCase
{
    /**
     * Use in-memory sqlite or refresh real database depending on config.
     */
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_day_totals_on_empty_table_returns_defaults()
    {
        $service = app(VisitsService::class);

        $result = $service->getDayTotals('2021-01-01', 1, 1, true);

        // when the visits table is empty the DB query returns null for aggregates
        $this->assertEquals(0, $result['stops']);
        $this->assertEquals(0, $result['travel_seconds']);
        $this->assertEquals(0, $result['distance_m']);
        $this->assertEquals(0, $result['on_location_seconds']);
        $this->assertEquals(0, $result['total_seconds']);
        $this->assertEquals(0.0, $result['distance_km']);
        $this->assertArrayHasKey('first_arrival', $result);
        $this->assertArrayHasKey('last_arrival', $result);
    }

    public function test_month_totals_on_empty_table_returns_defaults()
    {
        $service = app(VisitsService::class);

        $result = $service->getMonthTotals('2021-01-15', 1, 1, false);

        $this->assertEquals(0, $result['stops']);
        $this->assertEquals(0, $result['travel_seconds']);
        $this->assertEquals(0, $result['distance_m']);
        $this->assertEquals(0, $result['on_location_seconds']);
        $this->assertEquals(0, $result['total_seconds']);
        $this->assertEquals(0.0, $result['distance_km']);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
    }
}
