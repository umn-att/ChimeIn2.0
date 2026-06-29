<?php

use App\User;

it('authenticates with a valid bearer token', function () {
    $user = User::factory()->create();
    $plainTextToken = $user->createToken('slides-token')->plainTextToken;

    $response = $this
        ->withHeader('Authorization', 'Bearer ' . $plainTextToken)
        ->getJson('/api/tokens');

    $response
        ->assertOk()
        ->assertJsonFragment(['name' => 'slides-token']);
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
