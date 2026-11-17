# CLAUDE.md

## Cel projektu
ChatWarden: bot moderujący czat (na razie Discord) i panel WWW do jego ustawień, w PHP 8.3 bez frameworka, z własnym routingiem i MVC, bazą PostgreSQL i lokalnie uruchomionym modelem moderacji. To prawdziwy produkt, a zarazem projekt na zajęcia.

## Struktura
- `public/index.php` — punkt wejścia aplikacji.
- `public/views/` — widoki HTML z fragmentami PHP; sekcja sama dołącza swoje pliki przez `$stylesheets[]` i `$scripts[]`.
- `public/css/`, `public/js/` — style i skrypty pod tą samą ścieżką co widok, który je dołącza (np. `views/panel/logs.html.php` → `css/panel/logs.css`); wspólne style panelu w `css/panel.css`, globalne w `css/style.css`.
- `config/` — dane dla widoków i ustawienia; kod czyta je przez `App\Config::load('nazwa')`.
- `src/Routing.php` — dopasowanie tras i uruchamianie kontrolerów.
- `src/controllers/` — kontrolery w namespace `App\Controller`; panel dzieli się na strony (`PanelController`), formularze kanałów (`ChannelController`) i test wiadomości (`MessageCheckController`), ze wspólną bazą `AbstractPanelController`.
- `src/discord/` — Discord: logowanie, REST, Gateway i uprawnienia, namespace `App\Discord`.
- `src/twitch/` — Twitch: logowanie, lista kanałów użytkownika i bot (EventSub, token konta bota), namespace `App\Twitch`; `config/platforms.php` mówi, czym platforma różni się w panelu (zaproszenie czy przycisk „Podłącz”, liczba czatów, wyjątki).
- `src/moderation/` — kolejka i pytania do modelu oraz `Moderator` (co robić z wiadomością łamiącą zasadę), wspólne dla platform, namespace `App\Moderation`; platforma podaje tylko, jak usunąć wiadomość.
- `src/repositories/` — zapytania SQL, namespace `App\Repository`; modele w `src/models/` (`App\Model`).
- `src/services/` — logika współdzielona przez akcje kontrolera, np. dostęp do serwerów w panelu, namespace `App\Service`.
- `bin/` — procesy CLI: boty Discorda i Twitcha, sprawdzanie wiadomości, pomiar modeli, codzienne czyszczenie danych (cron) i uruchamianie testów.
- `tests/models/` — opisane wiadomości do pomiaru modeli przez `bin/measure.php`; zmiana pytań lub progów w `config/moderation.php` wymaga nowego pomiaru.
- `tests/` — testy jednostkowe bez zależności, namespace `Tests`; klasa `NazwaTest` rozszerza `TestCase`, a `bin/test.php` uruchamia jej metody `test…`.

## Zasady pracy
- Zachowuj `declare(strict_types=1)` w plikach PHP.
- Nie dodawaj frameworków ani zależności bez zgody.
- Kontroler przygotowuje dane, a widok odpowiada za prezentację.
- Dane wyświetlane w HTML zabezpieczaj w widokach przez `$e()` (`htmlspecialchars()`), a teksty interfejsu przez `$t()`.
- Nie powielaj danych między PHP a JS: źródłem jest `config/`, a JS dostaje je z widoku przez atrybuty `data-*`.
- Wprowadzaj małe zmiany i nie modyfikuj plików niezwiązanych z zadaniem.

## Uruchamianie i weryfikacja
- Start: `docker compose up --build -d`; usługa `migrate` przygotowuje autoloader i schemat bazy.
- Zmiana schematu: nowy plik `database/migrations/NNNN_opis.sql`, bez edycji wykonanych już migracji.
- Składnia: `docker compose exec -T php php -l ścieżka/do/pliku.php`
- Testy: `docker compose exec -T php php bin/test.php`
- Nowy folder z namespace dopisz do `autoload.psr-4` w `composer.json` i uruchom `docker compose exec -T php composer dump-autoload`.
- Po zmianie trasy sprawdź poprawny adres i odpowiedź 404.
- Przed zakończeniem pokaż `git diff` i podsumuj zmienione pliki.

## Bezpieczeństwo
- Nie odczytuj ani nie commituj sekretów z `.env`.
- Nie wykonuj destrukcyjnych poleceń bez potwierdzenia.
- Nie zmieniaj wygenerowanych plików w `vendor/`.