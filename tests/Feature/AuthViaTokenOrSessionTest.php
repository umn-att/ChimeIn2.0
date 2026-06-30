<?php

use App\User;

it('authenticates with a valid bearer token on an allowed route', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('slides-token', ['slides-addon'])->plainTextToken;

    $this
        ->withHeader('Authorization', 'Bearer ' . $plainTextToken)
        ->getJson('/api/chime')
        ->assertOk();
});

it('returns 403 when a token tries to access a route outside the slides-addon allowlist', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('slides-token', ['slides-addon'])->plainTextToken;

    $this
        ->withHeader('Authorization', 'Bearer ' . $plainTextToken)
        ->getJson('/api/tokens')
        ->assertStatus(403)
        ->assertJson(['message' => 'API tokens are restricted to read-only Google Slides integration endpoints.']);
});

it('returns 401 for an invalid bearer token', function () {
    $this
        ->withHeader('Authorization', 'Bearer invalid-token')
        ->getJson('/api/tokens')
        ->assertStatus(401)
        ->assertJson(['message' => 'Invalid API token.']);
});

it('returns 401 for a revoked bearer token', function () {
    $user = User::factory()->create();
    $newToken = $user->createToken('to-revoke');
    $plainTextToken = $newToken->plainTextToken;
    $newToken->accessToken->delete();

    $this
        ->withHeader('Authorization', 'Bearer ' . $plainTextToken)
        ->getJson('/api/tokens')
        ->assertStatus(401)
        ->assertJson(['message' => 'Invalid API token.']);
});

it('falls back to standard session authentication when no bearer token exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/tokens')
        ->assertOk();
});
