# CLAUDE.md

## Cel projektu
Prosta aplikacja edukacyjna w PHP 8.3 pokazująca routing i MVC.

## Struktura
- `public/index.php` — punkt wejścia aplikacji.
- `public/views/` — widoki HTML z fragmentami PHP.
- `src/Routing.php` — dopasowanie tras i uruchamianie kontrolerów.
- `src/controllers/` — kontrolery w namespace `App\Controller`.

## Zasady pracy
- Zachowuj `declare(strict_types=1)` w plikach PHP.
- Nie dodawaj frameworków ani zależności bez zgody.
- Kontroler przygotowuje dane, a widok odpowiada za prezentację.
- Dane wyświetlane w HTML zabezpieczaj przez `htmlspecialchars()`.
- Wprowadzaj małe zmiany i nie modyfikuj plików niezwiązanych z zadaniem.

## Uruchamianie i weryfikacja
- Start: `docker compose up --build -d`
- Składnia: `docker compose exec -T php php -l ścieżka/do/pliku.php`
- Po zmianie trasy sprawdź poprawny adres i odpowiedź 404.
- Przed zakończeniem pokaż `git diff` i podsumuj zmienione pliki.

## Bezpieczeństwo
- Nie odczytuj ani nie commituj sekretów z `.env`.
- Nie wykonuj destrukcyjnych poleceń bez potwierdzenia.
- Nie zmieniaj wygenerowanych plików w `vendor/`.