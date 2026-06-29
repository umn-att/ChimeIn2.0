<?php

use App\Chime;
use App\Folder;
use App\Question;
use App\Response;
use App\Session;
use App\User;

function createQuestionAndSession(Folder $folder, array $questionInfo): array
{
    $question = Question::factory()->create([
        'folder_id' => $folder->id,
        'question_info' => $questionInfo,
    ]);

    $session = Session::factory()->create([
        'question_id' => $question->id,
    ]);

    return [$question, $session];
}

it('allows presenters and denies participants', function () {
    $presenter = User::factory()->create();
    $participant = User::factory()->create();

    $chime = Chime::factory()
        ->withPresenter($presenter)
        ->hasAttached($participant, ['permission_number' => 100])
        ->create();

    $folder = Folder::factory()->create(['chime_id' => $chime->id]);

    [, $session] = createQuestionAndSession($folder, [
        'question_type' => Question::MULTIPLE_CHOICE_TYPE,
        'question_responses' => [
            ['text' => 'A', 'correct' => true],
            ['text' => 'B', 'correct' => false],
        ],
    ]);

    $this->actingAs($presenter)
        ->getJson('/api/chime/' . $chime->id . '/session/' . $session->id . '/results')
        ->assertOk();

    $this->actingAs($participant)
        ->getJson('/api/chime/' . $chime->id . '/session/' . $session->id . '/results')
        ->assertStatus(403);
});

it('returns 404 when session does not belong to the requested chime', function () {
    $presenter = User::factory()->create();

    $realChime = Chime::factory()->withPresenter($presenter)->create();
    $wrongChime = Chime::factory()->withPresenter($presenter)->create();

    $folder = Folder::factory()->create(['chime_id' => $realChime->id]);
    [, $session] = createQuestionAndSession($folder, [
        'question_type' => Question::MULTIPLE_CHOICE_TYPE,
        'question_responses' => [
            ['text' => 'A', 'correct' => true],
        ],
    ]);

    $this->actingAs($presenter)
        ->getJson('/api/chime/' . $wrongChime->id . '/session/' . $session->id . '/results')
        ->assertStatus(404)
        ->assertJson(['message' => 'Session not found']);
});

it('aggregates multiple-choice responses with correct counts and percentages', function () {
    $presenter = User::factory()->create();
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $chime = Chime::factory()
        ->withPresenter($presenter)
        ->hasAttached($userA, ['permission_number' => 100])
        ->hasAttached($userB, ['permission_number' => 100])
        ->create();

    $folder = Folder::factory()->create(['chime_id' => $chime->id]);

    [, $session] = createQuestionAndSession($folder, [
        'question_type' => Question::MULTIPLE_CHOICE_TYPE,
        'question_responses' => [
            ['text' => 'A', 'correct' => true],
            ['text' => 'B', 'correct' => false],
        ],
    ]);

    Response::factory()->create([
        'session_id' => $session->id,
        'user_id' => $userA->id,
        'response_info' => [
            'question_type' => Question::MULTIPLE_CHOICE_TYPE,
            'choice' => 'A',
        ],
    ]);

    Response::factory()->create([
        'session_id' => $session->id,
        'user_id' => $userB->id,
        'response_info' => [
            'question_type' => Question::MULTIPLE_CHOICE_TYPE,
            'choice' => 'B',
        ],
    ]);

    $this->actingAs($presenter)
        ->getJson('/api/chime/' . $chime->id . '/session/' . $session->id . '/results')
        ->assertOk()
        ->assertJsonPath('results.type', 'multiple_choice')
        ->assertJsonPath('total_responses', 2)
        ->assertJsonFragment(['choice' => 'A', 'count' => 1, 'percentage' => 50.0, 'correct' => true])
        ->assertJsonFragment(['choice' => 'B', 'count' => 1, 'percentage' => 50.0, 'correct' => false]);
});

it('aggregates slider responses with summary statistics', function () {
    $presenter = User::factory()->create();
    $participant = User::factory()->create();

    $chime = Chime::factory()
        ->withPresenter($presenter)
        ->hasAttached($participant, ['permission_number' => 100])
        ->create();

    $folder = Folder::factory()->create(['chime_id' => $chime->id]);

    [, $session] = createQuestionAndSession($folder, [
        'question_type' => 'slider_response',
        'question_responses' => [],
    ]);

    foreach ([10, 20, 30] as $value) {
        Response::factory()->create([
            'session_id' => $session->id,
            'user_id' => $participant->id,
            'response_info' => [
                'question_type' => 'slider_response',
                'choice' => $value,
            ],
        ]);
    }

    $this->actingAs($presenter)
        ->getJson('/api/chime/' . $chime->id . '/session/' . $session->id . '/results')
        ->assertOk()
        ->assertJsonPath('results.type', 'slider')
        ->assertJsonPath('results.min', 10)
        ->assertJsonPath('results.max', 30)
        ->assertJsonPath('results.average', 20)
        ->assertJsonPath('results.median', 20)
        ->assertJsonPath('results.count', 3);
});

it('aggregates free-response text and top terms', function () {
    $presenter = User::factory()->create();
    $participantA = User::factory()->create(['name' => 'Alice']);
    $participantB = User::factory()->create(['name' => 'Bob']);

    $chime = Chime::factory()
        ->withPresenter($presenter)
        ->hasAttached($participantA, ['permission_number' => 100])
        ->hasAttached($participantB, ['permission_number' => 100])
        ->create();

    $folder = Folder::factory()->create(['chime_id' => $chime->id]);

    [, $session] = createQuestionAndSession($folder, [
        'question_type' => Question::FREE_RESPONSE_TYPE,
        'question_responses' => [],
    ]);

    Response::factory()->create([
        'session_id' => $session->id,
        'user_id' => $participantA->id,
        'response_info' => [
            'question_type' => Question::FREE_RESPONSE_TYPE,
            'text' => 'Hello world from slides',
        ],
    ]);

    Response::factory()->create([
        'session_id' => $session->id,
        'user_id' => $participantB->id,
        'response_info' => [
            'question_type' => Question::FREE_RESPONSE_TYPE,
            'text' => 'Hello testing world',
        ],
    ]);

    $this->actingAs($presenter)
        ->getJson('/api/chime/' . $chime->id . '/session/' . $session->id . '/results')
        ->assertOk()
        ->assertJsonPath('results.type', 'free_response')
        ->assertJsonPath('results.total_responses', 2)
        ->assertJsonFragment(['term' => 'hello', 'count' => 2])
        ->assertJsonFragment(['term' => 'world', 'count' => 2]);
});

it('aggregates numeric responses with frequency and summary values', function () {
    $presenter = User::factory()->create();
    $participant = User::factory()->create();

    $chime = Chime::factory()
        ->withPresenter($presenter)
        ->hasAttached($participant, ['permission_number' => 100])
        ->create();

    $folder = Folder::factory()->create(['chime_id' => $chime->id]);

    [, $session] = createQuestionAndSession($folder, [
        'question_type' => 'numeric_response',
        'question_responses' => ['chart_type' => 'line'],
    ]);

    foreach ([1, 2, 2] as $xValue) {
        Response::factory()->create([
            'session_id' => $session->id,
            'user_id' => $participant->id,
            'response_info' => [
                'question_type' => 'numeric_response',
                'x' => $xValue,
            ],
        ]);
    }

    $this->actingAs($presenter)
        ->getJson('/api/chime/' . $chime->id . '/session/' . $session->id . '/results')
        ->assertOk()
        ->assertJsonPath('results.type', 'numeric_response')
        ->assertJsonPath('results.chart_type', 'line')
        ->assertJsonPath('results.count', 3)
        ->assertJsonPath('results.min', 1)
        ->assertJsonPath('results.max', 2)
        ->assertJsonPath('results.average', 1.67)
        ->assertJsonFragment(['value' => 1.0, 'count' => 1])
        ->assertJsonFragment(['value' => 2.0, 'count' => 2]);
});
