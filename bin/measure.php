<?php

declare(strict_types=1);

use App\Moderation\Evaluation;
use App\Moderation\MessageClassifier;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Measures a model on the labelled messages in tests/models/<language>.php: for every rule and level,
// how many violations it catches and how many clean messages it blocks, and which messages it gets wrong.
// docker compose exec -T php php bin/measure.php basal-mini pl
// A new question is tried without touching the config, for one rule at a time:
// docker compose exec -T php php bin/measure.php basal-mini pl --racism="Czy ta wiadomość ..."
//
// The model's answers are kept in the temporary folder of the container, so a second run with the same
// questions takes a moment. The thresholds are suggested from every other message and checked on the rest,
// so a suggestion is not tuned to the very messages that judge it.

$model = $argv[1] ?? '';
$language = $argv[2] ?? '';
$datasetPath = dirname(__DIR__) . "/tests/models/$language.php";

if ($model === '' || !preg_match('/^[a-z]{2}$/', $language) || !is_file($datasetPath)) {
    fwrite(STDERR, "Usage: php bin/measure.php <model> <language> [--<category>=\"question\"]\n");
    fwrite(STDERR, "The language needs labelled messages in tests/models/<language>.php\n");
    exit(1);
}

$questions = [];

foreach (array_slice($argv, 3) as $option) {
    if (!preg_match('/^--([a-z]+)=(.+)$/s', $option, $match)) {
        fwrite(STDERR, "Not an option: $option\n");
        exit(1);
    }

    $questions[$match[1]] = $match[2];
}

$messages = require $datasetPath;
$config = require dirname(__DIR__) . '/config/moderation.php';

try {
    $classifier = new MessageClassifier($model, $questions === [] ? [] : [$language => $questions]);
    $asked = $classifier->questions($language);
    $thresholds = $config[$model]['thresholds'][$language];
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

// the answers by model, questions and text: a changed question is asked again, the rest is not;
// one file for each model and language, so two measurements at once do not overwrite each other
$cachePath = sys_get_temp_dir() . "/chatwarden-measure-$model-$language.json";
$cache = is_file($cachePath) ? (json_decode((string) file_get_contents($cachePath), true) ?: []) : [];
$scores = [];

foreach ($messages as $index => $message) {
    $key = sha1(json_encode([$model, $language, $asked, $message['text']], JSON_THROW_ON_ERROR));

    if (!isset($cache[$key])) {
        fwrite(STDERR, sprintf("\rAsking the model: %d of %d", $index + 1, count($messages)));

        try {
            $cache[$key] = $classifier->scores($message['text'], $language);
        } catch (RuntimeException $error) {
            fwrite(STDERR, "\n" . $error->getMessage() . "\n");
            exit(1);
        }

        // saved after every answer, so a stopped run keeps what it has
        file_put_contents($cachePath, json_encode($cache, JSON_THROW_ON_ERROR));
    }

    $scores[$index] = $cache[$key];
}

fwrite(STDERR, "\r" . str_repeat(' ', 40) . "\r");

$clean = array_filter($messages, fn(array $message): bool => $message['breaks'] === []);
printf("%s, %s: %d messages, %d of them clean\n", $model, $language, count($messages), count($clean));

if ($questions !== []) {
    foreach ($questions as $category => $question) {
        printf("Trying a new %s question: %s\n", $category, $question);
    }
}

$mistakes = [];

foreach ($asked as $category => $question) {
    // the messages about this rule: those that break it, and those that break nothing;
    // a message that breaks only another rule is removed for that one, so it is no mistake here
    $cases = [];

    foreach ($messages as $index => $message) {
        $breaks = in_array($category, $message['breaks'], true);

        if ($breaks || $message['breaks'] === []) {
            $cases[$index] = ['score' => $scores[$index][$category], 'breaks' => $breaks];
        }
    }

    printf("\n%s\n", strtoupper($category));
    printf("  %-9s %-9s %-16s %s\n", 'level', 'threshold', 'caught', 'blocked clean');

    foreach (MessageClassifier::LEVELS as $level) {
        $count = Evaluation::count(array_values($cases), $thresholds[$category][$level]);
        printf(
            "  %-9s %-9.2f %-16s %s\n",
            $level,
            $thresholds[$category][$level],
            sprintf('%d of %d', $count['caught'], $count['violations']),
            sprintf('%d of %d', $count['blocked'], $count['clean']),
        );
    }

    // suggested from the even messages, judged on the odd ones
    $tuning = array_values(array_filter($cases, fn(int $index): bool => $index % 2 === 0, ARRAY_FILTER_USE_KEY));
    $checking = array_values(array_filter($cases, fn(int $index): bool => $index % 2 === 1, ARRAY_FILTER_USE_KEY));
    $suggested = Evaluation::bestThreshold($tuning);
    $check = Evaluation::count($checking, $suggested);
    printf(
        "  suggested for balanced: %.2f (on the other half: caught %d of %d, blocked %d of %d clean)\n",
        $suggested,
        $check['caught'],
        $check['violations'],
        $check['blocked'],
        $check['clean'],
    );

    foreach ($cases as $index => $case) {
        $isAbove = MessageClassifier::breaks($case['score'], $thresholds[$category]['balanced']);

        if ($isAbove !== $case['breaks']) {
            $mistakes[] = sprintf(
                '  %-7s %-10s %.2f  %s',
                $case['breaks'] ? 'missed' : 'blocked',
                $category,
                $case['score'],
                $messages[$index]['text'],
            );
        }
    }
}

// what a channel with every rule at balanced would do with each message: act on it or let it through
$should = 0;
$caught = 0;
$blocked = 0;

foreach ($messages as $index => $message) {
    $isActed = $classifier->brokenRules($scores[$index], $language, array_fill_keys(array_keys($asked), 'balanced')) !== [];

    if ($message['breaks'] !== []) {
        $should++;
        $caught += $isActed ? 1 : 0;
    } else {
        $blocked += $isActed ? 1 : 0;
    }
}

printf("\nEVERY RULE AT BALANCED\n  acted on %d of %d messages that break a rule, blocked %d of %d clean\n", $caught, $should, $blocked, count($clean));

if ($mistakes !== []) {
    echo "\nMISTAKES AT BALANCED\n" . implode("\n", $mistakes) . "\n";
}
