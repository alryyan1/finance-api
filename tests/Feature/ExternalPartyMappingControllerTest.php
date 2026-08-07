<?php

namespace Tests\Feature;

use App\Models\ExternalPartyMapping;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalPartyMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_mappings_scoped_to_source_system_and_type(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['name' => 'Ahmed Ali', 'type' => 'customer', 'is_active' => true]);
        ExternalPartyMapping::create([
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
            'party_id' => $party->id,
        ]);
        // Different source_type — must not leak into the client mapping list.
        ExternalPartyMapping::create([
            'source_system' => 'sales-api',
            'source_type' => 'supplier',
            'source_id' => '25',
            'party_id' => $party->id,
        ]);

        $this->actingAs($user)
            ->getJson('/api/party-mappings?source_system=sales-api&source_type=client')
            ->assertOk()
            ->assertExactJson(['25' => $party->id]);
    }

    public function test_it_upserts_a_mapping(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['name' => 'Ahmed Ali', 'type' => 'customer', 'is_active' => true]);

        $this->actingAs($user)
            ->putJson('/api/party-mappings/sales-api/client/25', ['party_id' => $party->id])
            ->assertOk();

        $this->assertDatabaseHas('external_party_mappings', [
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
            'party_id' => $party->id,
        ]);
    }

    public function test_it_deletes_a_mapping(): void
    {
        $user = User::factory()->create();
        $party = Party::create(['name' => 'Ahmed Ali', 'type' => 'customer', 'is_active' => true]);
        ExternalPartyMapping::create([
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
            'party_id' => $party->id,
        ]);

        $this->actingAs($user)
            ->deleteJson('/api/party-mappings/sales-api/client/25')
            ->assertNoContent();

        $this->assertDatabaseMissing('external_party_mappings', [
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
        ]);
    }
}
