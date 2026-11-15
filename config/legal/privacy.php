<?php

declare(strict_types=1);

// The privacy policy, by language. It describes what the code really does, so a change in what is stored,
// for how long or who gets it needs a change here too, with a new date.
// A block is a paragraph, or a list of points; {app}, {operator} and {contact} come from config/app.php.
return [
    'updated' => '2026-11-15',
    'pl' => [
        [
            'heading' => 'Kto odpowiada za Twoje dane',
            'blocks' => [
                '{app} to bot moderujący czat na Discordzie i panel do jego ustawień. Ta polityka opisuje, jakie dane zbieramy przez stronę, panel i bota, po co je zbieramy i jak długo je przechowujemy.',
                'Administratorem danych jest {operator}. W sprawach danych osobowych napisz na {contact}.',
            ],
        ],
        [
            'heading' => 'Osoby, które logują się do panelu',
            'blocks' => [
                'Logowanie odbywa się przez Discorda. Nie mamy własnych kont ani haseł. Po zalogowaniu Discord przekazuje nam:',
                [
                    'identyfikator, nazwę i adres awatara Twojego konta,',
                    'listę serwerów, którymi możesz zarządzać, z ich nazwami i ikonami.',
                ],
                'Te dane trzymamy wyłącznie w sesji na serwerze, a nie w bazie danych. Znikają, gdy się wylogujesz albo gdy sesja wygaśnie. Token dostępu od Discorda wykorzystujemy jednorazowo przy logowaniu i nigdzie go nie zapisujemy.',
                'Gdy korzystasz z panelu, sprawdzamy przez Discorda, czy nadal możesz zarządzać wybranym serwerem. Tekst wpisany w pole testowe w zakładce Limity trafia do modelu moderacji i nie jest zapisywany.',
            ],
        ],
        [
            'heading' => 'Uczestnicy czatu na moderowanych kanałach',
            'blocks' => [
                'Bot czyta wiadomości tylko na kanałach, które osoba zarządzająca serwerem podłączyła w panelu, najwyżej na dwóch na serwer. Każdą wiadomość ocenia model moderacji działający na naszym serwerze. Treści wiadomości nie przekazujemy żadnej innej firmie.',
                'Wiadomości, które nie łamią zasad kanału, nie są zapisywane. Liczymy tylko, ile wiadomości sprawdzono na kanale danego dnia.',
                'Gdy wiadomość łamie zasadę kanału, bot ją usuwa albo oznacza i zapisuje to zdarzenie:',
                [
                    'treść wiadomości oraz identyfikator i nazwę jej autora,',
                    'kanał, identyfikator wiadomości i czas,',
                    'złamaną zasadę, ocenę modelu i wykonaną akcję, a gdy akcja się nie udała, przyczynę podaną przez Discorda.',
                ],
                'Zdarzenia widzą w panelu osoby, które mogą zarządzać danym serwerem.',
                'Model ocenia wiadomości automatycznie i może się mylić. Jeśli Twoja wiadomość została usunięta niesłusznie, zgłoś to osobom zarządzającym serwerem albo napisz do nas.',
            ],
        ],
        [
            'heading' => 'Dane serwerów',
            'blocks' => [
                'O serwerach, na które dodano bota, zapisujemy identyfikator, nazwę i to, czy bot nadal na nich jest, a także podłączone kanały i ich ustawienia.',
            ],
        ],
        [
            'heading' => 'Jak długo przechowujemy dane',
            'blocks' => [
                [
                    'Dane konta w panelu: do końca sesji.',
                    'Treść i autor wiadomości w zdarzeniu: 30 dni. Potem je usuwamy, a reszta zdarzenia, czyli zasada, akcja, ocena i czas, zostaje do statystyk.',
                    'Serwer razem z jego kanałami, ustawieniami, zdarzeniami i licznikami: do 30 dni po usunięciu bota z serwera.',
                    'Logi techniczne serwera z adresem IP, czasem i adresem zapytania: tylko tak długo, jak to potrzebne do usuwania błędów i ochrony przed nadużyciami.',
                ],
            ],
        ],
        [
            'heading' => 'Podstawa prawna',
            'blocks' => [
                [
                    'Logowanie i panel: świadczenie usługi, o którą prosisz (art. 6 ust. 1 lit. b RODO).',
                    'Moderacja czatu: prawnie uzasadniony interes osób zarządzających serwerem i jego społeczności w bezpiecznym czacie (art. 6 ust. 1 lit. f RODO).',
                    'Logi techniczne: prawnie uzasadniony interes w bezpiecznym i sprawnym działaniu usługi (art. 6 ust. 1 lit. f RODO).',
                ],
            ],
        ],
        [
            'heading' => 'Komu przekazujemy dane',
            'blocks' => [
                [
                    'Discord: przez jego platformę działają logowanie i bot, więc obowiązuje też polityka prywatności Discorda.',
                    'Dostawca serwera, na którym działa {app}.',
                    'Font Awesome (Fonticons, Inc.): przy każdej wizycie przeglądarka pobiera z jego serwerów ikony strony, więc poznaje Twój adres IP.',
                ],
                'Discord i Font Awesome działają w Stanach Zjednoczonych, więc dane mogą być przetwarzane poza Europejskim Obszarem Gospodarczym. Nie sprzedajemy danych i nie wykorzystujemy ich do reklam.',
            ],
        ],
        [
            'heading' => 'Ciasteczka i pamięć przeglądarki',
            'blocks' => [
                [
                    'Ciasteczko sesji: tylko po zalogowaniu, utrzymuje zalogowanie.',
                    'Ciasteczko lang: wybrany język, przez rok od jego wyboru.',
                    'Wpis theme w pamięci przeglądarki: wybrany motyw jasny albo ciemny.',
                ],
                'Nie używamy ciasteczek analitycznych ani reklamowych.',
            ],
        ],
        [
            'heading' => 'Twoje prawa',
            'blocks' => [
                'Masz prawo dostępu do swoich danych, ich sprostowania i usunięcia, ograniczenia przetwarzania oraz sprzeciwu wobec przetwarzania. Napisz na {contact} i podaj identyfikator swojego konta na Discordzie, bo tylko po nim możemy znaleźć Twoje dane.',
                'Masz też prawo złożyć skargę do Prezesa Urzędu Ochrony Danych Osobowych.',
            ],
        ],
        [
            'heading' => 'Zmiany',
            'blocks' => [
                'Gdy zmienimy tę politykę, zaktualizujemy datę na górze strony.',
            ],
        ],
    ],
    'en' => [
        [
            'heading' => 'Who is responsible for your data',
            'blocks' => [
                '{app} is a bot that moderates chat on Discord, with a panel for its settings. This policy describes the data we collect through the site, the panel and the bot, why we collect it and how long we keep it.',
                'The data controller is {operator}. For anything about personal data, write to {contact}.',
            ],
        ],
        [
            'heading' => 'People who log in to the panel',
            'blocks' => [
                'You log in through Discord. We have no accounts or passwords of our own. After you log in, Discord gives us:',
                [
                    'the ID, name and avatar address of your account,',
                    'the list of servers you can manage, with their names and icons.',
                ],
                'We keep this data only in your session on the server, not in a database. It is gone when you log out or the session expires. We use the access token from Discord once while you log in and never store it.',
                'While you use the panel, we ask Discord whether you can still manage the chosen server. Text you type into the test box on the Limits tab goes to the moderation model and is not stored.',
            ],
        ],
        [
            'heading' => 'People who chat on moderated channels',
            'blocks' => [
                'The bot reads messages only on the channels that someone who manages the server connected in the panel, at most two per server. A moderation model running on our server judges every message. We do not pass the content of messages to any other company.',
                'Messages that break no rule of the channel are not stored. We only count how many messages were checked on a channel each day.',
                'When a message breaks a rule of the channel, the bot deletes or flags it and stores the event:',
                [
                    'the content of the message and the ID and name of its author,',
                    'the channel, the ID of the message and the time,',
                    'the rule it broke, the model\'s score and the action taken, and when the action failed, the reason Discord gave.',
                ],
                'The events are shown in the panel to the people who can manage that server.',
                'The model judges messages automatically and can be wrong. If your message was deleted by mistake, tell the people who manage the server, or write to us.',
            ],
        ],
        [
            'heading' => 'Data about servers',
            'blocks' => [
                'For the servers the bot was added to, we store their ID, name and whether the bot is still there, and the connected channels with their settings.',
            ],
        ],
        [
            'heading' => 'How long we keep data',
            'blocks' => [
                [
                    'Your account data in the panel: until the session ends.',
                    'The content and author of a message in an event: 30 days. After that we delete them, and the rest of the event, that is the rule, the action, the score and the time, stays for statistics.',
                    'A server with its channels, settings, events and counts: until 30 days after the bot was removed from it.',
                    'Technical server logs with the IP address, time and address of a request: only as long as needed to fix errors and prevent abuse.',
                ],
            ],
        ],
        [
            'heading' => 'Legal basis',
            'blocks' => [
                [
                    'Logging in and the panel: providing the service you ask for (Article 6(1)(b) GDPR).',
                    'Chat moderation: the legitimate interest of the people who manage the server and of its community in a safe chat (Article 6(1)(f) GDPR).',
                    'Technical logs: the legitimate interest in a safe and working service (Article 6(1)(f) GDPR).',
                ],
            ],
        ],
        [
            'heading' => 'Who receives data',
            'blocks' => [
                [
                    'Discord: logging in and the bot work through its platform, so the Discord privacy policy applies too.',
                    'The provider of the server {app} runs on.',
                    'Font Awesome (Fonticons, Inc.): on every visit your browser loads the icons of the site from its servers, so it learns your IP address.',
                ],
                'Discord and Font Awesome operate in the United States, so data may be processed outside the European Economic Area. We do not sell data or use it for advertising.',
            ],
        ],
        [
            'heading' => 'Cookies and browser storage',
            'blocks' => [
                [
                    'The session cookie: only after you log in, keeps you logged in.',
                    'The lang cookie: the language you chose, for a year after you chose it.',
                    'The theme entry in browser storage: the light or dark theme you chose.',
                ],
                'We use no analytics or advertising cookies.',
            ],
        ],
        [
            'heading' => 'Your rights',
            'blocks' => [
                'You have the right to access your data, to have it corrected or deleted, to restrict its processing and to object to it. Write to {contact} and give the ID of your Discord account, since that is the only way we can find your data.',
                'You also have the right to complain to a data protection authority; in Poland that is the President of the Personal Data Protection Office.',
            ],
        ],
        [
            'heading' => 'Changes',
            'blocks' => [
                'When we change this policy, we update the date at the top of the page.',
            ],
        ],
    ],
];
