<?php

namespace Tests\Feature;

use Tests\TestCase;

class VertexSchedulerRegistrationTest extends TestCase
{
    public function test_monthly_retraining_command_is_registered_in_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('php artisan vertex:monthly-retrain')
            ->assertSuccessful();
    }
}
