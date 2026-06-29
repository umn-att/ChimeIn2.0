<?php

use App\Chime;
use App\Folder;
use App\Question;
use App\Session;
use App\User;

it('includes question text_preview with html stripped and long text truncated', function () {
    $presenter = User::factory()->create();
    $chime = Chime::factory()->withPresenter($presenter)->create();
    $folder = Folder::factory()->create(['chime_id' => $chime->id, 'order' => 1]);

    $firstParagraph = 'This is a long first paragraph with bold emphasis and enough words to exceed the eighty character limit easily for preview generation.';
    $htmlText = '<p>' . $firstParagraph . '</p><p>Second paragraph should be ignored.</p>';

    $question = Question::factory()->create([
        'folder_id' => $folder->id,
        'text' => $htmlText,
        'order' => 1,
    ]);

    $session = Session::factory()->create([
        'question_id' => $question->id,
    ]);

    $question->current_session_id = $session->id;
    $question->save();

    $clean = trim(preg_replace('/\s+/', ' ', strip_tags($firstParagraph)));
    $expectedPreview = mb_strlen($clean) > 80
        ? mb_substr($clean, 0, 77) . '...'
        : $clean;

    $this->actingAs($presenter)
        ->getJson('/api/chime/' . $chime->id . '/openQuestions')
        ->assertOk()
        ->assertJsonCount(1, 'sessions')
        ->assertJsonPath('sessions.0.question.text_preview', $expectedPreview);
});
