# CLAUDE.md

## Cel projektu
ChatWarden: bot moderujący czat (na razie Discord) i panel WWW do jego ustawień, w PHP 8.3 bez frameworka, z własnym routingiem i MVC, bazą PostgreSQL i lokalnie uruchomionym modelem moderacji. To prawdziwy produkt, a zarazem projekt na zajęcia.

## Struktura
- `public/index.php` — punkt wejścia aplikacji.
- `public/views/` — widoki HTML z fragmentami PHP; sekcja sama dołącza swoje pliki przez `$stylesheets[]` i `$scripts[]`.
- `public/css/`, `public/js/` — style i skrypty pod tą samą ścieżką co widok, który je dołącza (np. `views/panel/logs.html.php` → `css/panel/logs.css`); wspólne style panelu w `css/panel.css`, globalne w `css/style.css`.
- `config/` — dane dla widoków.
- `src/Routing.php` — dopasowanie tras i uruchamianie kontrolerów.
- `src/controllers/` — kontrolery w namespace `App\Controller`.
- `src/discord/` — Discord: logowanie, REST, Gateway i uprawnienia, namespace `App\Discord`.
- `src/moderation/` — kolejka i pytania do modelu, wspólne dla platform, namespace `App\Moderation`.
- `src/repositories/` — zapytania SQL, namespace `App\Repository`; modele w `src/models/` (`App\Model`).
- `bin/` — procesy CLI: bot Discorda i sprawdzanie wiadomości.

## Zasady pracy
- Zachowuj `declare(strict_types=1)` w plikach PHP.
- Nie dodawaj frameworków ani zależności bez zgody.
- Kontroler przygotowuje dane, a widok odpowiada za prezentację.
- Dane wyświetlane w HTML zabezpieczaj przez `htmlspecialchars()`.
- Nie powielaj danych między PHP a JS: źródłem jest `config/`, a JS dostaje je z widoku przez atrybuty `data-*`.
- Wprowadzaj małe zmiany i nie modyfikuj plików niezwiązanych z zadaniem.

## Uruchamianie i weryfikacja
- Start: `docker compose up --build -d`, a po świeżym klonie także `docker compose exec -T php composer install`.
- Składnia: `docker compose exec -T php php -l ścieżka/do/pliku.php`
- Nowy folder z namespace dopisz do `autoload.psr-4` w `composer.json` i uruchom `docker compose exec -T php composer dump-autoload`.
- Po zmianie trasy sprawdź poprawny adres i odpowiedź 404.
- Przed zakończeniem pokaż `git diff` i podsumuj zmienione pliki.

## Bezpieczeństwo
- Nie odczytuj ani nie commituj sekretów z `.env`.
- Nie wykonuj destrukcyjnych poleceń bez potwierdzenia.
- Nie zmieniaj wygenerowanych plików w `vendor/`.