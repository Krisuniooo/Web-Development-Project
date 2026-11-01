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
    "footer.note" => "FOOTER TODO",

    "notfound.title" => "Nie znaleziono strony",
    "notfound.text" => "Taka strona nie istnieje.",
    "notfound.back" => "Wróć na stronę główną",

    "home.title" => $appName,
    "home.hero.title" => "LOREM IPSUM",
    "home.hero.description" => "lorem ipsum dolor sit amet consectetur adipiscing elit",
    "home.hero.primary" => "Przycisk 1",
    "home.hero.secondary" => "Przycisk 2",
    "home.how.title" => "Jak to działa",
    "home.how.platform" => "Wybierz platformę",
    "home.how.model" => "Wybierz model",
    "home.how.limits" => "Ustaw limity",
    "home.how.watching" => "Pilnuję #general",
    "home.models.title" => "Obsługiwane modele",
    "home.models.description" => "lorem ipsum dolor sit amet consectetur adipiscing elit",
    "home.models.pro" => "Dostępne tylko w wersji PRO",
    "home.models.recommended" => "Polecany",
    "home.models.github" => "$appName na GitHubie",
    "home.cta.title" => "Lorem ipsum dolor sit amet consectetur",
    "home.cta.button" => "Wypróbuj",
    "home.cta.description" => "Lorem ipsum dolor sit amet consectetur adipiscing elit",

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
