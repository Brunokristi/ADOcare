<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Company;
use App\Models\User;
use App\Models\Plan;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_company_plans()
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Plan::factory()->create(['company_id' => $company->id, 'name' => 'A']);
        Plan::factory()->create(['company_id' => $company->id, 'name' => 'B']);
        Plan::factory()->create(['company_id' => $other->id, 'name' => 'C']);

        // sanity: verify created plans exist in database with correct company
        $this->assertDatabaseHas('plans', ['name' => 'A', 'company_id' => $company->id]);
        $this->assertDatabaseHas('plans', ['name' => 'B', 'company_id' => $company->id]);
        $this->assertDatabaseHas('plans', ['name' => 'C', 'company_id' => $other->id]);

        $this->assertEquals($company->id, $user->company_id);
        $response = $this->actingAs($user)->getJson('/api/v1/plans');
        $response->assertStatus(200);
        // ensure the authenticated user available during request matches
        $this->assertEquals($company->id, auth()->user()->company_id);
        $data = $response->json('data.items');
        // make sure other company's plan doesn't appear
        $names = array_column($data, 'name');
        $this->assertNotContains('C', $names);
        // ensure at least one of ours is there (A or B)
        $this->assertTrue(
            in_array('A', $names, true) || in_array('B', $names, true),
            'Expected at least one of our created plans to appear, got: ' . implode(',', $names)
        );
    }

    public function test_store_update_and_delete_work()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)
            ->postJson('/api/v1/plans', [
                'name' => 'Foo',
                'text' => 'Some text',
                'sort_order' => 5,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Foo');

        $plan = Plan::first();
        $this->assertNotNull($plan);
        $this->assertEquals($company->id, $plan->company_id);

        $this->actingAs($user)
            ->putJson("/api/v1/plans/{$plan->id}", ['name' => 'Foo2'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Foo2');

        $this->actingAs($user)
            ->deleteJson("/api/v1/plans/{$plan->id}")
            ->assertStatus(204);

        // plan uses soft deletes
        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
    }
}
