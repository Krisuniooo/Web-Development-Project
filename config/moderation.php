<?php

declare(strict_types=1);

// What the bot asks each model about a message, and from which answer the message breaks a rule.
// The questions and thresholds are measured together on the labelled messages in tests/models/ with bin/measure.php,
// so a changed question needs a new measurement.

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
        // the exceptions name what the model took for a violation: slang and colours as racism, beating someone
        // in a game as harassment; the swear words name the forms it missed
        'questions' => [
            'en' => [
                'racism' => 'Does this chat message attack or demean a racial, ethnic or national group, or a person because of their race or origin? Internet slang such as chad or sigma, colours of items in a game and talking about racism do not count.',
                'harassment' => 'Is this chat message an insult, a threat or bullying aimed at a person? Talking about a game, including saying you will beat someone in a game, and talking about oneself do not count.',
                'profanity' => 'Does this chat message contain a swear word or an obscene word, such as fuck, shit or bullshit, in any form? Words like kill or die and plain insults such as idiot do not count.',
            ],
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
        // shorter than those of mini: with the longer exceptions pico answered every harassment with about 0.5
        // and caught none of it on tests/models/en.php
        'questions' => [
            'en' => [
                'racism' => 'Does this chat message attack or demean a racial, ethnic or national group, or a person because of their race or origin?',
                'harassment' => 'Is this chat message an insult, a threat or bullying aimed at a person? Talking about a game or about oneself does not count.',
                'profanity' => 'Does this chat message contain a swear word or an obscene word, such as fuck or shit? Words like kill or die do not count.',
            ],
        ],
        // the thresholds of mini; with them pico acts on 18 of 22 violations in tests/models/en.php and blocks 3 of 36 clean,
        // too few messages to tune its own
        'thresholds' => ['en' => $englishThresholds],
    ],
];
