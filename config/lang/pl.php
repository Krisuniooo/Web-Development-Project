<?php

declare(strict_types=1);

$appName = (require dirname(__DIR__) . '/app.php')['name'];

return [
    "app.name" => $appName,
    "language.label" => "Język",

    "nav.overview" => "Przegląd",
    "nav.channels" => "Kanały",
    "nav.models" => "Modele",
    "nav.limits" => "Limity",
    "nav.logs" => "Logi",
    "nav.settings" => "Ustawienia",
    "nav.panel" => "Panel",

    "header.manage" => "Zarządzaj $appName",
    "header.manage_on" => "Zarządzaj $appName na",
    "theme.to_light" => "Włącz jasny motyw",
    "theme.to_dark" => "Włącz ciemny motyw",
    "panel.back_home" => "Strona główna",
    "panel.not_ready" => "Ta sekcja nie jest jeszcze gotowa.",
    "footer.note" => "Decyzje podejmują modele i mogą się mylić.",
    "status.title" => "Status serwerów",
    "status.not_ready" => "Ta strona nie jest jeszcze gotowa.",

    "notfound.title" => "Nie znaleziono strony",
    "notfound.text" => "Taka strona nie istnieje.",
    "notfound.back" => "Wróć na stronę główną",

    "home.title" => $appName,
    "home.hero.title" => "CZYSTY CZAT BEZ WYSIŁKU",
    "home.hero.description" => "Bot, który blokuje wiadomości rasistowskie, nękające i wulgarne, zanim popsują rozmowę.",
    "home.hero.primary" => "Otwórz panel",
    "home.hero.secondary" => "Jak to działa",
    "home.how.title" => "Jak to działa",
    "home.how.platform" => "Wybierz platformę",
    "home.how.model" => "Wybierz model",
    "home.how.limits" => "Ustaw limity",
    "home.how.watching" => "Pilnuję #general",
    "home.models.title" => "Obsługiwane modele",
    "home.models.description" => "Modele decyzyjne czytają każdą wiadomość i odpowiadają na jedno pytanie: czy łamie Twoje zasady? Obsługujemy angielski i polski.",
    "home.models.hint.pico" => "Polecany. Tylko angielski.",
    "home.models.hint.mini" => "Polski i angielski.",
    "home.models.hint.mini_gpu" => "Polski i angielski. Przyspieszany przez GPU, dostępny tylko w wersji PRO.",
    "home.models.recommended" => "Polecany",
    "home.models.github" => "$appName na GitHubie",
    "home.cta.title" => "Czas na spokojniejszy czat?",
    "home.cta.button" => "Wypróbuj",
    "home.cta.description" => "Zaloguj się przez swoją platformę i podłącz pierwszy kanał. Nie ma osobnego hasła.",

    "cta.discord" => "Chcę serwer Discorda bez toksyczności",
    "cta.twitch" => "Chcę czystego czatu na Twitchu podczas streamu",
    "cta.kick" => "Chcę blokować nienawiść na czacie Kicka na żywo",
    "cta.telegram" => "Chcę chronić grupy na Telegramie przed nękaniem",

    "category.harassment" => "Nękanie",
    "category.racism" => "Rasizm",
    "category.profanity" => "Przekleństwa",
    "level.off" => "Bez nadzoru",
    "level.relaxed" => "Luźno",
    "level.balanced" => "Średnio",
    "level.strict" => "Surowo",
];
