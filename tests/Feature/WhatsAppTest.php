<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('sends whatsapp text via configured provider', function () {
    config([
        'services.whatsapp.base_url' => 'https://wa.test',
        'services.whatsapp.token' => 'test-token',
    ]);

    Http::fake([
        'https://wa.test/send-text' => Http::response(['ok' => true], 200),
    ]);

    $admin = User::create([
        'name' => 'Admin',
        'username' => 'admin-wa',
        'email' => 'adminwa@example.com',
        'password' => Hash::make('secret123'),
        'user_type' => 'admin',
        'active' => true,
    ]);

    Sanctum::actingAs($admin);

    // ini fitur untuk .... ngirim whatsapp text via api
    // postJson function to /api/wa/send-text
    $response = $this->postJson('/api/wa/send-text', [
        'to' => '6281234567890',
        'message' => 'Halo? apakah ini bisa?. Kalo bisa harusnya ini menampilkan apa gitu',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Sent']);

    // Verify that the HTTP request was sent with correct headers and payloadq
    Http::assertSent(function ($request) {
        return $request->url() === 'https://wa.test/send-text'
            && $request->hasHeaders([
                'Authorization' => 'Bearer test-token',
                'Accept' => 'application/json',
            ]);

            // postJson payload
            // $data = $request->da
    });
});