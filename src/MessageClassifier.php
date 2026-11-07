<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** Asks a moderation model how strongly a message breaks each rule, and compares the answers with the thresholds. */
final class MessageClassifier
{
    // from the fewest blocked messages to the most; off is not a level here, it skips the rule
    public const LEVELS = ['relaxed', 'balanced', 'strict'];
    // the model answers in 2 to 3 seconds, longer when other messages wait in its queue
    private const TIMEOUT_SECONDS = 60;
    // a model that is up answers at once
    private const STATUS_TIMEOUT_SECONDS = 3;
    // a model that is up takes the connection at once, even when busy; a stopped one is found out sooner this way
    private const CONNECT_TIMEOUT_SECONDS = 3;

    private array $model;

    public function __construct(private string $modelName)
    {
        $models = require dirname(__DIR__) . '/config/moderation.php';

        if (!isset($models[$modelName])) {
            throw new RuntimeException("config/moderation.php has no model $modelName");
        }

        $this->model = $models[$modelName];
    }

    /** @return list<string> the languages the model has questions in */
    public function languages(): array
    {
        return array_keys($this->model['questions']);
    }

    /** Whether the model is running and can take messages; while it starts, it does not answer yet. */
    public function isReady(): bool
    {
        try {
            return ($this->request('/health', null, self::STATUS_TIMEOUT_SECONDS)['status'] ?? null) === 'ok';
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * The model's answer for each category, from 0 (no) to 1 (yes).
     *
     * @return array<string, float>
     */
    public function scores(string $text, string $language): array
    {
        $questions = $this->model['questions'][$language] ?? null;

        if ($questions === null) {
            throw new RuntimeException("The model $this->modelName has no questions in the language $language");
        }

        $response = $this->request('/v1/systemone', [
            'state' => $text,
            // one question per category, each answered with a number
            'questions' => array_map(
                fn(string $question): array => ['type' => 'noul', 'instructions' => $question],
                $questions,
            ),
        ], self::TIMEOUT_SECONDS);

        $scores = [];

        foreach (array_keys($questions) as $category) {
            $score = $response['answers'][$category]['noul'] ?? null;

            if (!is_int($score) && !is_float($score)) {
                throw new RuntimeException("The model $this->modelName gave no answer for $category");
            }

            $scores[$category] = (float) $score;
        }

        return $scores;
    }

    /**
     * The categories whose rule the message breaks at the levels the channel has.
     *
     * @param array<string, float> $scores from scores()
     * @param array<string, string> $levels by category: off, relaxed, balanced or strict
     * @return list<string>
     */
    public function brokenRules(array $scores, string $language, array $levels): array
    {
        $broken = [];

        foreach ($scores as $category => $score) {
            $level = $levels[$category] ?? 'off';

            if ($level === 'off') {
                continue;
            }

            $threshold = $this->model['thresholds'][$language][$category][$level] ?? null;

            if ($threshold === null) {
                throw new RuntimeException("The model $this->modelName has no $level threshold for $category in $language");
            }

            if ($score >= $threshold) {
                $broken[] = $category;
            }
        }

        return $broken;
    }

    /** Sends the body as JSON by POST, or asks by GET when there is no body. */
    private function request(string $path, ?array $body, int $timeoutSeconds): array
    {
        $curl = curl_init($this->model['url'] . $path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);

        if ($body !== null) {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                // broken bytes become a replacement character, so the rest of the text is still checked
                CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
        }

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        // the errors name only the model and the status, never the text of the message
        if ($response === false) {
            throw new RuntimeException("The model $this->modelName did not answer: " . curl_error($curl));
        }

        $data = json_decode($response, true);

        if ($status !== 200 || !is_array($data)) {
            throw new RuntimeException("The model $this->modelName answered with HTTP $status");
        }

        return $data;
    }
}
