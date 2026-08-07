<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyResolveExternalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_party_and_mapping_on_first_call(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/parties/resolve-external', [
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
            'name' => 'Ahmed Ali',
            'phone' => '99887766',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Ahmed Ali')
            ->assertJsonPath('type', 'customer');

        $this->assertDatabaseCount('parties', 1);
        $this->assertDatabaseHas('external_party_mappings', [
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
        ]);
    }

    public function test_it_reuses_the_existing_party_on_a_second_call(): void
    {
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/api/parties/resolve-external', [
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
            'name' => 'Ahmed Ali',
        ])->json();

        $second = $this->actingAs($user)->postJson('/api/parties/resolve-external', [
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
            'name' => 'Ahmed Ali',
        ])->json();

        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseCount('parties', 1);
    }

    public function test_it_requires_the_core_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/parties/resolve-external', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_system', 'source_type', 'source_id', 'name']);
    }
}
