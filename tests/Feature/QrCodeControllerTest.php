<?php

use App\Chime;
use App\User;

it('allows a presenter to fetch an svg qr code with cache headers', function () {
    $presenter = User::factory()->create();
    $chime = Chime::factory()->withPresenter($presenter)->create();

    $response = $this->actingAs($presenter)
        ->get('/api/chime/' . $chime->id . '/qrcode?format=svg&size=300');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/svg+xml');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=3600');
    expect($response->headers->get('Cache-Control'))->toContain('public');
});

it('allows a participant to fetch an svg qr code', function () {
    $presenter = User::factory()->create();
    $participant = User::factory()->create();

    $chime = Chime::factory()
        ->withPresenter($presenter)
        ->hasAttached($participant, ['permission_number' => 100])
        ->create();

    $response = $this->actingAs($participant)
        ->get('/api/chime/' . $chime->id . '/qrcode?format=svg');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/svg+xml');
});

it('returns 404 for a non-member due to presenter lookup', function () {
    $presenter = User::factory()->create();
    $nonMember = User::factory()->create();

    $chime = Chime::factory()->withPresenter($presenter)->create();

    $this->actingAs($nonMember)
        ->get('/api/chime/' . $chime->id . '/qrcode?format=svg')
        ->assertStatus(404);
});

it('returns png by default when imagick is available', function () {
    if (!extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick extension is not available in this environment.');
    }

    $presenter = User::factory()->create();
    $chime = Chime::factory()->withPresenter($presenter)->create();

    $response = $this->actingAs($presenter)
        ->get('/api/chime/' . $chime->id . '/qrcode');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/png');
});
