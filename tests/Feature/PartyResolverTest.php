<?php

namespace Tests\Feature;

use App\Services\PartyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_party_and_mapping_on_first_resolve(): void
    {
        $party = (new PartyResolver)->resolve('sales-api', 'client', '25', [
            'name' => 'Ahmed Ali',
            'phone' => '99887766',
            'email' => 'ahmed@test.com',
        ]);

        $this->assertDatabaseCount('parties', 1);
        $this->assertSame('Ahmed Ali', $party->name);
        $this->assertSame('customer', $party->type);
        $this->assertSame('99887766', $party->phone);

        $this->assertDatabaseHas('external_party_mappings', [
            'source_system' => 'sales-api',
            'source_type' => 'client',
            'source_id' => '25',
            'party_id' => $party->id,
        ]);
    }

    public function test_it_reuses_the_existing_party_on_subsequent_resolves(): void
    {
        $resolver = new PartyResolver;

        $first = $resolver->resolve('sales-api', 'client', '25', ['name' => 'Ahmed Ali']);
        $second = $resolver->resolve('sales-api', 'client', '25', ['name' => 'A different name that must be ignored']);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('parties', 1);
        $this->assertDatabaseCount('external_party_mappings', 1);
    }

    public function test_it_defaults_supplier_source_type_to_supplier_party_type(): void
    {
        $party = (new PartyResolver)->resolve('sales-api', 'supplier', '7', ['name' => 'ABC Co']);

        $this->assertSame('supplier', $party->type);
    }

    public function test_it_keeps_mappings_distinct_per_source_type(): void
    {
        $resolver = new PartyResolver;

        $client = $resolver->resolve('sales-api', 'client', '1', ['name' => 'Same Id Client']);
        $supplier = $resolver->resolve('sales-api', 'supplier', '1', ['name' => 'Same Id Supplier']);

        $this->assertNotSame($client->id, $supplier->id);
        $this->assertDatabaseCount('parties', 2);
    }

    public function test_it_honors_an_explicit_party_type_override(): void
    {
        $party = (new PartyResolver)->resolve('sales-api', 'client', '99', [
            'name' => 'Special Case',
            'type' => 'other',
        ]);

        $this->assertSame('other', $party->type);
    }
}
