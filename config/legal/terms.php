<?php

declare(strict_types=1);

// The terms of service, by language, in the same shape as config/legal/privacy.php.
return [
    'updated' => '2026-11-15',
    'pl' => [
        [
            'heading' => 'Czym jest {app}',
            'blocks' => [
                '{app} to bezpłatny bot, który moderuje czat na Discordzie, oraz panel do jego ustawień. Usługę prowadzi {operator}.',
                'Korzystając z bota albo z panelu, akceptujesz ten regulamin. Obowiązują też regulamin i zasady Discorda.',
            ],
        ],
        [
            'heading' => 'Kto może dodać bota',
            'blocks' => [
                'Bota może dodać i ustawiać tylko osoba, która może zarządzać danym serwerem na Discordzie. Dostęp do ustawień serwera w panelu kończy się, gdy tracisz to uprawnienie.',
            ],
        ],
        [
            'heading' => 'Obowiązki osób zarządzających serwerem',
            'blocks' => [
                [
                    'Poinformuj członków serwera, że wiadomości na wybranych kanałach sprawdza bot, na przykład w regulaminie serwera.',
                    'Ustawiaj zasady zgodnie z prawem i zasadami Discorda.',
                    'Nie wykorzystuj bota ani panelu do nękania, śledzenia ludzi ani innych działań niezgodnych z prawem.',
                ],
            ],
        ],
        [
            'heading' => 'Decyzje modelu',
            'blocks' => [
                'Bot ocenia wiadomości automatycznie, za pomocą modelu językowego. Model może się mylić: przepuścić wiadomość, która łamie zasady, albo usunąć poprawną. Bot nie zastępuje moderatorów, a historię jego działań można sprawdzić w panelu.',
            ],
        ],
        [
            'heading' => 'Dostępność usługi',
            'blocks' => [
                'Usługa jest w wersji testowej i udostępniamy ją bez gwarancji działania. Może mieć przerwy, a gdy model albo bot nie działa, wiadomości przechodzą bez sprawdzenia.',
                'Możemy zmienić albo zakończyć usługę, a bota usunąć z serwera, na którym narusza się ten regulamin.',
            ],
        ],
        [
            'heading' => 'Odpowiedzialność',
            'blocks' => [
                'W granicach, na które pozwala prawo, nie odpowiadamy za szkody wynikające z decyzji modelu, z przerw w działaniu usługi ani z tego, jak osoby zarządzające serwerem ustawiły bota.',
            ],
        ],
        [
            'heading' => 'Zmiany i prawo',
            'blocks' => [
                'Gdy zmienimy regulamin, zaktualizujemy datę na górze strony. Regulamin podlega prawu polskiemu.',
                'Pytania i zgłoszenia: {contact}.',
            ],
        ],
    ],
    'en' => [
        [
            'heading' => 'What {app} is',
            'blocks' => [
                '{app} is a free bot that moderates chat on Discord, with a panel for its settings. The service is run by {operator}.',
                'By using the bot or the panel, you accept these terms. The Discord terms of service and guidelines apply too.',
            ],
        ],
        [
            'heading' => 'Who can add the bot',
            'blocks' => [
                'Only someone who can manage a server on Discord can add the bot to it and change its settings. Your access to that server\'s settings in the panel ends when you lose that permission.',
            ],
        ],
        [
            'heading' => 'What people who manage a server must do',
            'blocks' => [
                [
                    'Tell the members of the server that a bot checks the messages on the chosen channels, for example in the server rules.',
                    'Set the rules in line with the law and with the Discord guidelines.',
                    'Do not use the bot or the panel to harass or track people, or for anything else against the law.',
                ],
            ],
        ],
        [
            'heading' => 'Decisions of the model',
            'blocks' => [
                'The bot judges messages automatically, with a language model. The model can be wrong: it can let through a message that breaks the rules, or delete one that does not. The bot does not replace moderators, and the history of what it did is in the panel.',
            ],
        ],
        [
            'heading' => 'Availability',
            'blocks' => [
                'The service is in testing and is provided without any guarantee that it works. It can be interrupted, and while the model or the bot is down, messages pass unchecked.',
                'We can change or end the service, and remove the bot from a server where these terms are broken.',
            ],
        ],
        [
            'heading' => 'Liability',
            'blocks' => [
                'As far as the law allows, we are not liable for damage caused by the decisions of the model, by interruptions of the service or by how the people who manage a server set up the bot.',
            ],
        ],
        [
            'heading' => 'Changes and law',
            'blocks' => [
                'When we change these terms, we update the date at the top of the page. These terms are governed by Polish law.',
                'Questions and reports: {contact}.',
            ],
        ],
    ],
];
