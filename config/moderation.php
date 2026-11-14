<?php

declare(strict_types=1);

// What the bot asks each model about a message, and from which answer the message breaks a rule.
// The questions and thresholds were measured together on 80 test messages per language,
// so a changed question needs new thresholds.

$englishQuestions = [
    'racism' => 'Does this chat message attack or demean a racial, ethnic or national group, or a person because of their race or origin?',
    'harassment' => 'Is this chat message an insult, a threat or bullying aimed at a person? Talking about a game or about oneself does not count.',
    'profanity' => 'Does this chat message contain a swear word or an obscene word, such as fuck or shit? Words like kill or die do not count.',
];

// balanced makes the fewest mistakes on the test messages, relaxed is one step (0.05) higher and strict one lower;
// a message breaks the rule when the model's answer is at least the threshold
$englishThresholds = [
    'racism' => ['relaxed' => 0.90, 'balanced' => 0.85, 'strict' => 0.80],
    'harassment' => ['relaxed' => 0.80, 'balanced' => 0.75, 'strict' => 0.70],
    'profanity' => ['relaxed' => 0.80, 'balanced' => 0.75, 'strict' => 0.70],
];

return [
    'basal-mini' => [
        // the model's container; it has no port outside Docker
        'url' => 'http://basal-mini:8000',
        'questions' => [
            'en' => $englishQuestions,
            'pl' => [
                'racism' => 'Czy ta wiadomość z czatu atakuje lub poniża grupę rasową, etniczną lub narodową albo osobę z powodu jej rasy lub pochodzenia?',
                'harassment' => 'Czy ta wiadomość z czatu jest obelgą, groźbą lub nękaniem skierowanym do osoby? Mówienie o grze lub o sobie się nie liczy.',
                'profanity' => 'Czy ta wiadomość z czatu zawiera przekleństwo lub wulgarne słowo, takie jak kurwa albo chuj? Zwykłe obelgi, takie jak idiota, się nie liczą.',
            ],
        ],
        'thresholds' => [
            'en' => $englishThresholds,
            // racism and harassment are 0.10 above what the test messages gave: on real chats the measured values
            // took vulgar insults for racism (0.67) and mild digs for harassment (0.73)
            'pl' => [
                'racism' => ['relaxed' => 0.75, 'balanced' => 0.70, 'strict' => 0.65],
                'harassment' => ['relaxed' => 0.80, 'balanced' => 0.75, 'strict' => 0.70],
                'profanity' => ['relaxed' => 0.85, 'balanced' => 0.80, 'strict' => 0.75],
            ],
        ],
    ],
    // English only: in Polish pico missed too much (docs/models.md), and the database refuses that pair too
    'basal-pico' => [
        'url' => 'http://basal-pico:8000',
        'questions' => ['en' => $englishQuestions],
        // TEMPORARY: the thresholds of mini, not measured for pico. The two models answer with different numbers,
        // so these need their own measurement
        'thresholds' => ['en' => $englishThresholds],
    ],
];
