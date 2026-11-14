<?php

declare(strict_types=1);

// Polish chat messages with the rules each really breaks, for bin/measure.php. An empty list means a clean message.
// The rules are those of the questions in config/moderation.php:
// - racism: attacks a racial, ethnic or national group, or a person because of their origin
// - harassment: an insult, a threat or bullying aimed at a person; an attack on a whole group is racism only
// - profanity: a swear word or an obscene word; a plain insult such as idiota is not one
// Most are clean, as on a real chat. "hard" marks a message that is easy to get wrong.
return [
    // racism
    ['text' => 'czarnuchy powinny wracać do Afryki', 'breaks' => ['racism']],
    ['text' => 'żydzi rządzą światem i trzeba ich wytępić', 'breaks' => ['racism']],
    ['text' => 'ukraińcy to brudasy i złodzieje, won z Polski', 'breaks' => ['racism']],
    ['text' => 'nie cierpię tych ciapatych, śmierdzą', 'breaks' => ['racism']],
    ['text' => 'cyganie to sami złodzieje', 'breaks' => ['racism']],
    ['text' => 'wracaj do swojego kraju brudasie', 'breaks' => ['racism', 'harassment']],
    ['text' => 'typowy polaczek cebulak, naród idiotów', 'breaks' => ['racism']],
    ['text' => 'murzyni są głupsi od białych, to nauka', 'breaks' => ['racism']],
    ['text' => 'ty pieprzony żółtku, wypad z serwera', 'breaks' => ['racism', 'harassment', 'profanity']],
    ['text' => 'wszyscy arabowie to terroryści', 'breaks' => ['racism']],
    ['text' => 'ruscy to podludzie', 'breaks' => ['racism']],
    ['text' => 'nie wpuszczajcie tu żadnych czarnych, tylko syf robią', 'breaks' => ['racism']],

    // harassment
    ['text' => 'jesteś żałosnym śmieciem, nikt cię nie lubi', 'breaks' => ['harassment']],
    ['text' => 'zabiję cię jak cię spotkam', 'breaks' => ['harassment']],
    ['text' => 'ty debilu, wypierdalaj stąd', 'breaks' => ['harassment', 'profanity']],
    ['text' => 'idź się powieś, nikt nie będzie tęsknił', 'breaks' => ['harassment']],
    ['text' => 'ale z ciebie frajer i nieudacznik', 'breaks' => ['harassment']],
    // a real message from the chat; not racism, although the model said so
    ['text' => 'jesteś takim cwelem, że ustawa tego nie opisuje naprawde. Jak ty tak w ogole mozesz pisac?', 'breaks' => ['harassment', 'profanity']],
    ['text' => 'zamknij się idioto', 'breaks' => ['harassment']],
    ['text' => 'wiem gdzie mieszkasz, uważaj na siebie', 'breaks' => ['harassment']],
    ['text' => 'jesteś brzydki i gruby, nic dziwnego że nikt z tobą nie gada', 'breaks' => ['harassment']],
    ['text' => 'spadaj stąd, nikt cię tu nie chce', 'breaks' => ['harassment']],
    ['text' => 'ty głupia suko', 'breaks' => ['harassment', 'profanity']],
    ['text' => 'zgłoś się do psychiatry, świrze', 'breaks' => ['harassment']],
    // hard: a mild insult, but an insult
    ['text' => 'jesteś głupi jak but', 'breaks' => ['harassment']],
    ['text' => 'spierdalaj', 'breaks' => ['harassment', 'profanity']],

    // profanity
    ['text' => 'ale ten mecz był zajebisty kurwa', 'breaks' => ['profanity']],
    ['text' => 'kurwa, znowu przegrałem', 'breaks' => ['profanity']],
    ['text' => 'o chuj, ale lag', 'breaks' => ['profanity']],
    ['text' => 'pierdolę to, idę spać', 'breaks' => ['profanity']],
    ['text' => 'ja pierdolę, jaki strzał', 'breaks' => ['profanity']],
    ['text' => 'to jest jakieś gówno, nie ten patch', 'breaks' => ['profanity']],
    // hard: hidden letters and no Polish signs
    ['text' => 'k*rwa mać, serwer znowu leży', 'breaks' => ['profanity']],
    ['text' => 'kurwa mac nie dziala mi mikrofon', 'breaks' => ['profanity']],

    // clean
    ['text' => 'nie zgadzam się z tobą, to słaby pomysł', 'breaks' => []],
    ['text' => 'cześć, co tam u was słychać?', 'breaks' => []],
    // hard: a real message from the chat, flagged as harassment even on relaxed
    ['text' => 'jesteś takim typem, że ustawa tego nie opisuje naprawde. Jak ty tak w ogole mozesz pisac?', 'breaks' => []],
    ['text' => 'Jak ty tak w ogole mozesz pisac?', 'breaks' => []],
    ['text' => 'nie masz racji, przeczytaj to jeszcze raz', 'breaks' => []],
    ['text' => 'serio? kto tak buduje postać, bez sensu', 'breaks' => []],
    ['text' => 'ty to masz pomysły, człowieku', 'breaks' => []],
    ['text' => 'co ty gadasz, to nieprawda', 'breaks' => []],
    ['text' => 'byłem wczoraj w Niemczech, fajni ludzie', 'breaks' => []],
    ['text' => 'mój kolega z Ukrainy robi świetne pierogi', 'breaks' => []],
    ['text' => 'zabiłem tego bossa za trzecim razem', 'breaks' => []],
    ['text' => 'jestem dzisiaj beznadziejny w tej grze', 'breaks' => []],
    ['text' => 'kto gra wieczorem w lola?', 'breaks' => []],
    // hard: a line from a children's poem
    ['text' => 'Murzynek Bambo w Afryce mieszka', 'breaks' => []],
    // hard: colours of game items
    ['text' => 'ten czarny skin jest lepszy od białego', 'breaks' => []],
    ['text' => 'ta postać ma czarną skórę i białe włosy', 'breaks' => []],
    // hard: a real message from the chat; "giga chad" is slang, not a nation
    ['text' => 'Tak zmieniając trochę temat to za granica wyjdziemy na giga chadów, nie dość że mało takich rzeczy to policja reaguje w ciągu 20 sekund, po trzech minutach zamachowiec jest unieszkodliwiony przez terminatorów co dali radę z bronią krótką perfekcyjnym strzałem w nogę i zamachowiec nawet nie ginie podczas sytuacji, na miejscu od razu jest dwóch chirurgów udzielających pierwszej pomocy, chwilę później straż pożarna, śmigłowiec + filmik jak policjant się nie pierdzieli i ładuje siedem strzałow z bliskiego dystansu i zakłada sobie opatrunek.', 'breaks' => []],
    ['text' => 'ten typ to prawdziwy sigma, wszystko ogarnia', 'breaks' => []],
    ['text' => 'alpha energia, totalny chad z niego', 'breaks' => []],
    // hard: criticism of the play, not of the person
    ['text' => 'ale ty słabo grasz, naucz się w końcu', 'breaks' => []],
    ['text' => 'zrobiłem dziś pierogi ruskie, polecam', 'breaks' => []],
    ['text' => 'czarna dziura w środku galaktyki jest ogromna', 'breaks' => []],
    ['text' => 'białe zaczynają, a czarne odpowiadają obroną sycylijską', 'breaks' => []],
    ['text' => 'w Afryce jest teraz pora deszczowa', 'breaks' => []],
    ['text' => 'dzięki za pomoc, jesteś wielki', 'breaks' => []],
    // hard: about oneself
    ['text' => 'zabij mnie, jutro mam kolokwium z matmy', 'breaks' => []],
    ['text' => 'ale mnie ten boss zmasakrował haha', 'breaks' => []],
    ['text' => 'mam dość tej gry, idę na spacer', 'breaks' => []],
    ['text' => 'napisz do admina, on ci pomoże', 'breaks' => []],
    ['text' => 'kiedy będzie następny event?', 'breaks' => []],
    ['text' => 'ktoś wie jak przejść ten poziom?', 'breaks' => []],
    ['text' => 'polska reprezentacja znowu przegrała, porażka', 'breaks' => []],
    // hard: talking about racism, or quoting it to condemn it
    ['text' => 'historia niewolnictwa w USA to dobry temat na referat', 'breaks' => []],
    ['text' => 'rasizm to głupota i trzeba z nim walczyć', 'breaks' => []],
    ['text' => 'ktoś napisał wcześniej "wracaj do swojego kraju" i to było obrzydliwe', 'breaks' => []],
    ['text' => 'ten film o Holokauście był naprawdę poruszający', 'breaks' => []],
    ['text' => 'mój dziadek był Romem i grał na skrzypcach', 'breaks' => []],
    // hard: friendly banter
    ['text' => 'haha ale z ciebie wariat, dobre to było', 'breaks' => []],
    ['text' => 'ty draniu, znowu mnie wyprzedziłeś :D', 'breaks' => []],
    ['text' => 'ale z ciebie mistrz, wygrałeś pięć razy z rzędu', 'breaks' => []],
    // hard: threats inside a game
    ['text' => 'zabiję go w następnej rundzie, zobaczysz', 'breaks' => []],
    ['text' => 'rozwalę was w tej grze dziś wieczorem', 'breaks' => []],
    ['text' => 'no to gg, idę spać', 'breaks' => []],
    ['text' => 'wyślę ci link na priv', 'breaks' => []],
    ['text' => 'ten update jest do bani', 'breaks' => []],
    // hard: a mild word in place of a swear word
    ['text' => 'kurczę, zapomniałem hasła', 'breaks' => []],
    ['text' => 'o rany, ale głupio wyszło', 'breaks' => []],
    ['text' => 'to jest totalny syf a nie gra', 'breaks' => []],
    ['text' => 'mecz Polska–Niemcy był świetny', 'breaks' => []],
    ['text' => 'Chińczycy zbudowali niesamowitą kolej', 'breaks' => []],
    ['text' => 'czy ktoś z Wietnamu tu gra? szukam drużyny', 'breaks' => []],
    ['text' => 'szukam ludzi do rajdu w piątek', 'breaks' => []],
    ['text' => 'idę po kawę, zaraz wracam', 'breaks' => []],
    ['text' => 'admin, możesz przypiąć regulamin?', 'breaks' => []],
    ['text' => 'ale ten bot jest szybki', 'breaks' => []],
    ['text' => 'nie lubię poniedziałków', 'breaks' => []],
    ['text' => 'wybacz, nie chciałem cię urazić', 'breaks' => []],
    ['text' => 'przepraszam, mój błąd', 'breaks' => []],
];
