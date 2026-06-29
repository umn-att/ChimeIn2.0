<?php

use App\User;
use App\Http\Middleware\PreventRequestForgery;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

it('lists only the authenticated users api tokens', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $user->createToken('user-token-1');
    $user->createToken('user-token-2');
    $otherUser->createToken('other-user-token');

    $this->actingAs($user)
        ->getJson('/api/tokens')
        ->assertOk()
        ->assertJsonCount(2, 'tokens')
        ->assertJsonFragment(['name' => 'user-token-1'])
        ->assertJsonFragment(['name' => 'user-token-2'])
        ->assertJsonMissing(['name' => 'other-user-token']);
});

it('creates a token and validates required name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/tokens', ['name' => 'slides-addon'])
        ->assertCreated()
        ->assertJsonPath('name', 'slides-addon')
        ->assertJsonStructure(['token', 'name']);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
        'name' => 'slides-addon',
    ]);

    $this->actingAs($user)
        ->postJson('/api/tokens', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('deletes own token and blocks deleting another users token', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownToken = $user->createToken('mine')->accessToken;
    $otherToken = $otherUser->createToken('theirs')->accessToken;

    $this->actingAs($user)
        ->deleteJson('/api/tokens/' . $ownToken->id)
        ->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $ownToken->id,
    ]);

    $this->actingAs($user)
        ->deleteJson('/api/tokens/' . $otherToken->id)
        ->assertStatus(404);
});

it('revokes all tokens for the authenticated user only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $user->createToken('mine-1');
    $user->createToken('mine-2');
    $otherUser->createToken('theirs-1');

    $this->actingAs($user)
        ->postJson('/api/tokens/revoke-all')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
    expect($otherUser->tokens()->count())->toBe(1);
});
