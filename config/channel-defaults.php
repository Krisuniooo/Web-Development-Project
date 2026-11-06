<?php

declare(strict_types=1);

// The settings of the first channel when someone adds the bot, through the site or not.
// The language follows the server: Polish only when the server says it is Polish.
return [
    'model' => 'basal-mini',
    'rules' => [
        'racism' => ['level' => 'relaxed', 'action' => 'delete'],
        'harassment' => ['level' => 'relaxed', 'action' => 'delete'],
        // off, but delete is ready for when someone turns it on
        'profanity' => ['level' => 'off', 'action' => 'delete'],
    ],
];
