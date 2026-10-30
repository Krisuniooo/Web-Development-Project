# CLAUDE.md

## Cel projektu
Prosta aplikacja edukacyjna w PHP 8.3 pokazująca routing i MVC. Na razie to głównie landing page, bez warstwy danych.

## Struktura
- `public/index.php` — punkt wejścia aplikacji.
- `public/views/` — widoki HTML z fragmentami PHP; sekcja sama dołącza swoje pliki przez `$stylesheets[]` i `$scripts[]`.
- `public/css/`, `public/js/` — style i skrypty, style sekcji strony głównej w `public/css/home/`.
- `config/` — dane dla widoków.
- `src/Routing.php` — dopasowanie tras i uruchamianie kontrolerów.
- `src/controllers/` — kontrolery w namespace `App\Controller`.

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
- Po zmianie trasy sprawdź poprawny adres i odpowiedź 404.
- Przed zakończeniem pokaż `git diff` i podsumuj zmienione pliki.

## Bezpieczeństwo
- Nie odczytuj ani nie commituj sekretów z `.env`.
- Nie wykonuj destrukcyjnych poleceń bez potwierdzenia.
- Nie zmieniaj wygenerowanych plików w `vendor/`.