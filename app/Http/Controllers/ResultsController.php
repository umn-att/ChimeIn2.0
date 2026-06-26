<?php

namespace App\Http\Controllers;

use App\Chime;
use App\Question;
use App\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ResultsController extends Controller
{
    /**
     * Get aggregated results for a session
     */
    public function show(Chime $chime, Session $session)
    {
        // Authorization: only presenters or global admins
        if (!Auth::user()->isPresenter($chime->id) && !Auth::user()->global_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $question = $session->question()->with('folder')->first();

        // Validate session belongs to chime via question → folder → chime
        if (!$question || $question->folder->chime_id !== $chime->id) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        // Cache key: results-{session_id}
        $cacheKey = "results-session-{$session->id}";
        $cachedResults = Cache::get($cacheKey);

        if ($cachedResults) {
            return response()->json($cachedResults);
        }

        // Fetch all responses for this session
        $responses = $session->responses()
            ->with('user')
            ->get();

        // Aggregate based on question type
        $results = $this->aggregateResults($question, $responses);

        // Build response envelope — strip HTML from text for label use
        $response = [
            'question' => [
                'id' => $question->id,
                'text' => strip_tags($question->text),
                'type' => $question->getQuestionType(),
                'anonymous' => $question->anonymous,
                'question_info' => $question->question_info,
            ],
            'session' => [
                'id' => $session->id,
                'created_at' => $session->created_at,
                'in_progress' => (bool) $question->current_session_id === $session->id,
            ],
            'total_responses' => $responses->count(),
            'results' => $results,
            'updated_at' => now()->toIso8601String(),
        ];

        // Cache for 5 seconds
        Cache::put($cacheKey, $response, now()->addSeconds(5));

        return response()->json($response);
    }

    /**
     * Aggregate results by question type
     */
    private function aggregateResults(Question $question, $responses)
    {
        $questionType = $question->getQuestionType();

        switch ($questionType) {
            case Question::MULTIPLE_CHOICE_TYPE:
                return $this->aggregateMultipleChoice($question, $responses);
            case Question::SLIDER_TYPE:
                return $this->aggregateSlider($responses);
            case Question::FREE_RESPONSE_TYPE:
                return $this->aggregateFreeResponse($responses);
            case Question::IMAGE_RESPONSE_TYPE:
                return $this->aggregateImageResponse($responses);
            case Question::HEATMAP_RESPONSE_TYPE:
                return $this->aggregateHeatmap($responses);
            case Question::TEXT_HEATMAP_RESPONSE_TYPE:
                return $this->aggregateTextHeatmap($responses);
            case 'numeric_response':
                return $this->aggregateNumericResponse($question, $responses);
            default:
                return [];
        }
    }

    /**
     * Aggregate multiple choice responses
     */
    private function aggregateMultipleChoice(Question $question, $responses)
    {
        $choices = $question->getResponseChoices() ?? [];
        $totalResponses = $responses->count();
        $results = [];

        foreach ($choices as $choice) {
            $choiceText = $choice['text'] ?? $choice;
            $isCorrect = $choice['correct'] ?? false;

            // Count responses that selected this choice
            $count = $responses->filter(function ($response) use ($choiceText) {
                $userChoice = $response->response_info['choice'] ?? null;
                if (is_array($userChoice)) {
                    return in_array($choiceText, $userChoice);
                }
                return $userChoice === $choiceText;
            })->count();

            $results[] = [
                'choice' => $choiceText,
                'count' => $count,
                'percentage' => $totalResponses > 0 ? round(($count / $totalResponses) * 100, 2) : 0,
                'correct' => $isCorrect,
            ];
        }

        return [
            'type' => 'multiple_choice',
            'choices' => $results,
        ];
    }

    /**
     * Aggregate slider responses
     */
    private function aggregateSlider($responses)
    {
        if ($responses->isEmpty()) {
            return [
                'type' => 'slider',
                'min' => null,
                'max' => null,
                'average' => null,
                'median' => null,
                'count' => 0,
            ];
        }

        $values = $responses->map(function ($response) {
            return $response->response_info['choice'] ?? null;
        })->filter(fn($v) => $v !== null)->values()->toArray();

        if (empty($values)) {
            return [
                'type' => 'slider',
                'min' => null,
                'max' => null,
                'average' => null,
                'median' => null,
                'count' => 0,
            ];
        }

        sort($values);
        $count = count($values);
        $average = array_sum($values) / $count;

        // Calculate median
        if ($count % 2 === 0) {
            $median = ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
        } else {
            $median = $values[floor($count / 2)];
        }

        return [
            'type' => 'slider',
            'min' => min($values),
            'max' => max($values),
            'average' => round($average, 2),
            'median' => round($median, 2),
            'count' => $count,
            'values' => $values,
        ];
    }

    /**
     * Aggregate free response text responses
     */
    private function aggregateFreeResponse($responses)
    {
        $textResponses = [];

        $responses->each(function ($response) use (&$textResponses) {
            $text = $response->response_info['text'] ?? '';
            if (!empty($text)) {
                $textResponses[] = [
                    'text' => $text,
                    'user' => $response->user ? $response->user->name : 'Anonymous',
                    'submitted_at' => $response->created_at,
                ];
            }
        });

        // Sort by most recent
        usort($textResponses, function ($a, $b) {
            return strtotime($b['submitted_at']) - strtotime($a['submitted_at']);
        });

        // Extract top terms (word frequency)
        $wordCounts = [];
        foreach ($textResponses as $response) {
            $words = preg_split('/\s+/', strtolower($response['text']), -1, PREG_SPLIT_NO_EMPTY);
            // Filter out common stop words
            $words = array_filter($words, function ($word) {
                $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
                return !in_array($word, $stopWords) && strlen($word) > 2;
            });

            foreach ($words as $word) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }

        arsort($wordCounts);
        $topTerms = array_slice($wordCounts, 0, 10, true);

        return [
            'type' => 'free_response',
            'responses' => $textResponses,
            'top_terms' => array_map(function ($word, $count) {
                return ['term' => $word, 'count' => $count];
            }, array_keys($topTerms), $topTerms),
            'total_responses' => count($textResponses),
        ];
    }

    /**
     * Aggregate image response data
     */
    private function aggregateImageResponse($responses)
    {
        $images = [];

        $responses->each(function ($response) use (&$images) {
            $imageName = $response->response_info['image_name'] ?? null;
            if ($imageName) {
                $images[] = [
                    'image_name' => $imageName,
                    'user' => $response->user ? $response->user->name : 'Anonymous',
                    'submitted_at' => $response->created_at,
                ];
            }
        });

        return [
            'type' => 'image_response',
            'images' => $images,
            'total_responses' => count($images),
        ];
    }

    /**
     * Aggregate heatmap coordinate responses
     */
    private function aggregateHeatmap($responses)
    {
        $coordinates = [];

        $responses->each(function ($response) use (&$coordinates) {
            $coord = $response->response_info['image_coordinates'] ?? null;
            if ($coord) {
                $coordinates[] = [
                    'x' => $coord['coordinate_x'] ?? 0,
                    'y' => $coord['coordinate_y'] ?? 0,
                    'submitted_at' => $response->created_at,
                ];
            }
        });

        // Simple clustering: group nearby coordinates
        $clusters = $this->clusterCoordinates($coordinates, 50);

        return [
            'type' => 'heatmap',
            'coordinates' => $coordinates,
            'clusters' => $clusters,
            'total_responses' => count($coordinates),
        ];
    }

    /**
     * Simple coordinate clustering for heatmap
     */
    private function clusterCoordinates($coordinates, $threshold = 50)
    {
        if (empty($coordinates)) {
            return [];
        }

        $clusters = [];
        $assigned = [];

        foreach ($coordinates as $index => $coord) {
            if (isset($assigned[$index])) {
                continue;
            }

            $cluster = [$coord];
            $assigned[$index] = true;

            foreach ($coordinates as $otherIndex => $otherCoord) {
                if (isset($assigned[$otherIndex])) {
                    continue;
                }

                $distance = sqrt(
                    pow($coord['x'] - $otherCoord['x'], 2) +
                    pow($coord['y'] - $otherCoord['y'], 2)
                );

                if ($distance <= $threshold) {
                    $cluster[] = $otherCoord;
                    $assigned[$otherIndex] = true;
                }
            }

            $centerX = array_sum(array_column($cluster, 'x')) / count($cluster);
            $centerY = array_sum(array_column($cluster, 'y')) / count($cluster);

            $clusters[] = [
                'center' => ['x' => round($centerX, 2), 'y' => round($centerY, 2)],
                'count' => count($cluster),
                'radius' => round($threshold / 2, 2),
            ];
        }

        return $clusters;
    }

    /**
     * Aggregate text heatmap responses
     */
    private function aggregateNumericResponse(Question $question, $responses)
    {
        $chartType = $question->question_info['question_responses']['chart_type'] ?? 'bar';

        if ($responses->isEmpty()) {
            return ['type' => 'numeric_response', 'chart_type' => $chartType, 'count' => 0, 'frequency' => []];
        }

        $xValues = $responses->map(fn($r) => $r->response_info['x'] ?? null)
            ->filter(fn($v) => $v !== null)
            ->map(fn($v) => floatval($v))
            ->values()
            ->toArray();

        if (empty($xValues)) {
            return ['type' => 'numeric_response', 'chart_type' => $chartType, 'count' => 0, 'frequency' => []];
        }

        sort($xValues);
        $count = count($xValues);
        $average = array_sum($xValues) / $count;

        // Build frequency table per unique value
        $freq = [];
        foreach ($xValues as $v) {
            $key = (string) $v;
            $freq[$key] = ($freq[$key] ?? 0) + 1;
        }
        ksort($freq, SORT_NUMERIC);
        $frequency = array_map(
            fn($val, $cnt) => ['value' => (float) $val, 'count' => $cnt],
            array_keys($freq), $freq
        );

        return [
            'type'       => 'numeric_response',
            'chart_type' => $chartType,
            'count'      => $count,
            'min'        => min($xValues),
            'max'        => max($xValues),
            'average'    => round($average, 2),
            'frequency'  => $frequency,
        ];
    }

    /**
     * Aggregate text heatmap responses
     */
    private function aggregateTextHeatmap($responses)
    {
        $ranges = [];

        $responses->each(function ($response) use (&$ranges) {
            $range = $response->response_info ?? null;
            if ($range && isset($range['startOffset'], $range['endOffset'])) {
                $key = "{$range['startOffset']}-{$range['endOffset']}";
                if (!isset($ranges[$key])) {
                    $ranges[$key] = [
                        'start_offset' => $range['startOffset'],
                        'end_offset' => $range['endOffset'],
                        'count' => 0,
                    ];
                }
                $ranges[$key]['count']++;
            }
        });

        // Sort by count descending
        usort($ranges, fn($a, $b) => $b['count'] <=> $a['count']);

        return [
            'type' => 'text_heatmap',
            'ranges' => $ranges,
            'total_responses' => $responses->count(),
        ];
    }
}
