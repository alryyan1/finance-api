<?php

namespace App\Services;

use App\Models\ExternalPartyMapping;
use App\Models\Party;

/**
 * Resolves a Party for a record owned by an external system (e.g. sales-api),
 * creating the Party and its mapping on first contact and reusing it afterwards.
 */
class PartyResolver
{
    /**
     * @param  string  $sourceSystem  e.g. "sales-api"
     * @param  string  $sourceType  e.g. "client" or "supplier"
     * @param  string  $sourceId  the external record's id, as a string
     * @param  array{name: string, phone?: string|null, email?: string|null, address?: string|null, type?: string}  $attributes  used only when the Party doesn't exist yet
     */
    public function resolve(string $sourceSystem, string $sourceType, string $sourceId, array $attributes): Party
    {
        $mapping = ExternalPartyMapping::where('source_system', $sourceSystem)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($mapping) {
            return $mapping->party;
        }

        $party = Party::create([
            'name' => $attributes['name'],
            'type' => $attributes['type'] ?? $this->defaultPartyType($sourceType),
            'phone' => $attributes['phone'] ?? null,
            'email' => $attributes['email'] ?? null,
            'address' => $attributes['address'] ?? null,
            'is_active' => true,
        ]);

        ExternalPartyMapping::create([
            'source_system' => $sourceSystem,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'party_id' => $party->id,
        ]);

        return $party;
    }

    private function defaultPartyType(string $sourceType): string
    {
        return match ($sourceType) {
            'client' => 'customer',
            'supplier' => 'supplier',
            default => 'other',
        };
    }
}
