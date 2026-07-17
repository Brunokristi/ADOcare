<?php

namespace Tests\Feature;

use App\Jobs\StartMonthlyVertexRetrainingJob;
use App\Services\Vertex\VertexRetrainingEligibilityService;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class VertexMonthlyRetrainCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_skips_when_retraining_is_disabled(): void
    {
        Bus::fake();

        $service = Mockery::mock(VertexRetrainingEligibilityService::class);
        $service->shouldReceive('evaluate')
            ->once()
            ->with(false)
            ->andReturn([
                'can_start' => false,
                'message' => 'Automatické retrénovanie je vypnuté.',
            ]);

        $this->app->instance(VertexRetrainingEligibilityService::class, $service);

        $this->artisan('vertex:monthly-retrain')
            ->expectsOutput('Automatické retrénovanie je vypnuté.')
            ->assertSuccessful();

        Bus::assertNotDispatched(StartMonthlyVertexRetrainingJob::class);
    }

    public function test_command_skips_when_new_examples_are_insufficient(): void
    {
        Bus::fake();

        $service = Mockery::mock(VertexRetrainingEligibilityService::class);
        $service->shouldReceive('evaluate')
            ->once()
            ->with(false)
            ->andReturn([
                'can_start' => false,
                'message' => 'Nedostatok nových schválených príkladov pre retrénovanie.',
                'new_examples' => 3,
                'required_examples' => 25,
            ]);

        $this->app->instance(VertexRetrainingEligibilityService::class, $service);

        $this->artisan('vertex:monthly-retrain')
            ->expectsOutput('Nedostatok nových schválených príkladov pre retrénovanie.')
            ->expectsOutput('Nové príklady: 3')
            ->expectsOutput('Minimálny počet: 25')
            ->assertSuccessful();

        Bus::assertNotDispatched(StartMonthlyVertexRetrainingJob::class);
    }

    public function test_command_dispatches_queue_job_when_eligible(): void
    {
        Bus::fake();

        $service = Mockery::mock(VertexRetrainingEligibilityService::class);
        $service->shouldReceive('evaluate')
            ->once()
            ->with(false)
            ->andReturn([
                'can_start' => true,
                'message' => 'Retrénovanie môže byť spustené.',
                'new_examples' => 30,
                'required_examples' => 25,
                'pipeline' => 'dekurz',
            ]);

        $this->app->instance(VertexRetrainingEligibilityService::class, $service);

        $this->artisan('vertex:monthly-retrain')
            ->expectsOutput('Retrénovanie môže byť spustené.')
            ->expectsOutput('Nové príklady: 30')
            ->expectsOutput('Mesačný retraining job bol odoslaný do fronty.')
            ->assertSuccessful();

        Bus::assertDispatched(StartMonthlyVertexRetrainingJob::class);
    }

    public function test_command_dry_run_does_not_dispatch_queue_job(): void
    {
        Bus::fake();

        $service = Mockery::mock(VertexRetrainingEligibilityService::class);
        $service->shouldReceive('evaluate')
            ->once()
            ->with(false)
            ->andReturn([
                'can_start' => true,
                'message' => 'Retrénovanie môže byť spustené.',
                'new_examples' => 30,
                'required_examples' => 25,
                'pipeline' => 'dekurz',
            ]);

        $this->app->instance(VertexRetrainingEligibilityService::class, $service);

        $this->artisan('vertex:monthly-retrain --dry-run')
            ->expectsOutput('Retrénovanie môže byť spustené.')
            ->expectsOutput('Nové príklady: 30')
            ->expectsOutput('Dry-run dokončený. Tréning nebol spustený.')
            ->assertSuccessful();

        Bus::assertNotDispatched(StartMonthlyVertexRetrainingJob::class);
    }

    public function test_command_uses_force_mode_when_requested(): void
    {
        Bus::fake();

        $service = Mockery::mock(VertexRetrainingEligibilityService::class);
        $service->shouldReceive('evaluate')
            ->once()
            ->with(true)
            ->andReturn([
                'can_start' => true,
                'message' => 'Retrénovanie môže byť spustené.',
                'new_examples' => 5,
                'required_examples' => 25,
                'pipeline' => 'dekurz',
            ]);

        $this->app->instance(VertexRetrainingEligibilityService::class, $service);

        $this->artisan('vertex:monthly-retrain --force')
            ->expectsOutput('Retrénovanie môže byť spustené.')
            ->expectsOutput('Nové príklady: 5')
            ->expectsOutput('Mesačný retraining job bol odoslaný do fronty.')
            ->assertSuccessful();

        Bus::assertDispatched(StartMonthlyVertexRetrainingJob::class);
    }
}
