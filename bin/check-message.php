<?php

declare(strict_types=1);

use App\Config;
use App\Moderation\MessageClassifier;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Checks one message with the model new channels get and shows what each level would decide,
// for trying the questions and thresholds:
// docker compose exec php php bin/check-message.php pl "zamknij się"

$language = $argv[1] ?? '';
$text = $argv[2] ?? '';

if ($language === '' || $text === '') {
    fwrite(STDERR, "Usage: php bin/check-message.php en|pl \"message\"\n");
    exit(1);
}

$model = Config::load('channel-defaults')['model'];

try {
    $classifier = new MessageClassifier($model);
    $scores = $classifier->scores($text, $language);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

foreach ($scores as $category => $score) {
    printf("%-11s %.4f\n", $category, $score);
}

foreach ($classifier->brokenRulesByLevel($scores, $language) as $level => $broken) {
    printf("%-9s %s\n", $level . ':', $broken === [] ? 'allowed' : 'breaks ' . implode(', ', $broken));
}
