<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_whatsapp_not_configured(): void
    {
        config(['services.whatsapp.token' => null, 'services.whatsapp.phone_number_id' => null]);

        $user = User::factory()->create();

        // Symfony's JsonResponse turns a null body into "{}", not the literal "null".
        $this->actingAs($user)->getJson('/api/whatsapp/phone-number')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_returns_display_phone_number_when_configured(): void
    {
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.api_version' => 'v20.0',
        ]);

        Http::fake([
            'graph.facebook.com/v20.0/1234567890*' => Http::response([
                'display_phone_number' => '+249 99 196 1111',
                'verified_name' => 'Jawda Medical',
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/whatsapp/phone-number')
            ->assertOk()
            ->assertJson([
                'display_phone_number' => '+249 99 196 1111',
                'verified_name' => 'Jawda Medical',
            ]);
    }

    public function test_returns_null_when_meta_request_fails(): void
    {
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);

        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad']], 401)]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/whatsapp/phone-number')
            ->assertOk()
            ->assertExactJson([]);
    }
}
