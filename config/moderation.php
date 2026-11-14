<?php

declare(strict_types=1);

// What the bot asks each model about a message, and from which answer the message breaks a rule.
// The questions and thresholds are measured together on the labelled messages in tests/models/ with bin/measure.php,
// so a changed question needs a new measurement.

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
            // the exceptions name what the model took for a violation: slang and colours as racism, beating someone
            // in a game as harassment; the swear words name the forms it missed
            'pl' => [
                'racism' => 'Czy ta wiadomość z czatu atakuje lub poniża grupę rasową, etniczną lub narodową albo osobę z powodu jej rasy lub pochodzenia? Slang internetowy, taki jak chad czy sigma, kolory przedmiotów w grze i rozmowa o rasizmie się nie liczą.',
                'harassment' => 'Czy ta wiadomość z czatu jest obelgą, groźbą lub nękaniem skierowanym do osoby? Mówienie o grze, w tym zapowiedzi pokonania kogoś w grze, oraz mówienie o sobie się nie liczy.',
                'profanity' => 'Czy ta wiadomość z czatu zawiera przekleństwo lub wulgarne słowo, takie jak kurwa, chuj, pierdolić albo gówno, także w odmienionej formie? Zwykłe obelgi, takie jak idiota, się nie liczą.',
            ],
        ],
        'thresholds' => [
            'en' => $englishThresholds,
            // measured on tests/models/pl.php: no clean message scores above 0.46 for profanity,
            // so its thresholds are lower than those of the other rules
            'pl' => [
                'racism' => ['relaxed' => 0.75, 'balanced' => 0.70, 'strict' => 0.65],
                'harassment' => ['relaxed' => 0.80, 'balanced' => 0.75, 'strict' => 0.70],
                'profanity' => ['relaxed' => 0.75, 'balanced' => 0.70, 'strict' => 0.65],
            ],
        ],
    ],
    // English only: in Polish pico missed too much (docs/models.md), and the database refuses that pair too
    'basal-pico' => [
        'url' => 'http://basal-pico:8000',
        'questions' => ['en' => $englishQuestions],
        // the thresholds of mini: on tests/models/en.php pico does about as well with them as mini
        // (it acts on 18 of 22 violations and blocks 3 of 36 clean, mini 20 and 4); too few messages to tune its own yet
        'thresholds' => ['en' => $englishThresholds],
    ],
];
