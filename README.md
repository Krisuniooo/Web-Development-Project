# ChatWarden

Bot moderujący czat i panel WWW do jego ustawień. Bot czyta wiadomości na wybranych kanałach Discorda, pyta lokalnie uruchomiony model, czy łamią zasady kanału, i oznacza je albo usuwa. W panelu osoba zarządzająca serwerem wybiera kanały, język, poziomy i akcje oraz przegląda historię działań bota.

Aplikacja jest napisana w PHP 8.3 bez frameworka, z własnym routingiem i MVC, i korzysta z PostgreSQL.

## Uruchomienie

```shell
cp .env.example .env
docker compose up --build -d
```

Aplikacja będzie dostępna pod adresem http://localhost:8080. Przy każdym starcie usługa `migrate` przygotowuje autoloader Composera i schemat bazy, a aplikacja, bot i cron czekają, aż skończy.

Przy pierwszym starcie kontener `basal-mini` pobiera model z Hugging Face, więc sprawdzanie wiadomości zaczyna działać dopiero po chwili.

## Discord

Logowanie do panelu i bot wymagają aplikacji w [Discord Developer Portal](https://discord.com/developers/applications). W `.env` uzupełnij:

- `DISCORD_CLIENT_ID` i `DISCORD_CLIENT_SECRET` — z zakładki OAuth2; dodaj tam adres powrotu `APP_URL/auth/discord/callback`.
- `DISCORD_BOT_TOKEN` — z zakładki Bot (Reset Token); na tej samej stronie włącz Message Content Intent.

Bez tokenu bot się nie uruchamia, a panel nie otwiera ustawień serwera. Po zmianie `.env` uruchom kontenery ponownie: `docker compose up -d`.

## Usługi

| Usługa | Rola | Adres |
| --- | --- | --- |
| `server`, `php` | strona i panel | http://localhost:8080 |
| `discord-bot` | bot, logi: `docker compose logs -f discord-bot` | — |
| `cron` | codzienne czyszczenie danych, logi: `docker compose logs cron` | — |
| `basal-mini` | model moderacji dla polskiego i angielskiego, dostępny tylko dla kontenerów | — |
| `basal-pico` | mniejszy i szybszy model moderacji, tylko angielski | — |
| `migrate` | jednorazowo przy starcie: migracje bazy, logi: `docker compose logs migrate` | — |
| `db` | PostgreSQL, schemat w `database/migrations/` | localhost:5433 |
| `pgadmin-wdpai` | pgAdmin | http://localhost:5050 |
| `mailpit` | skrzynka testowa | http://localhost:8025 |

## Bezpieczeństwo

- **Nagłówki HTTP.** nginx (`docker/nginx/nginx.conf`) dodaje do każdej odpowiedzi Content-Security-Policy, która dopuszcza tylko skrypty, style i fonty z tej strony oraz obrazki z Discorda. Wysyła też `X-Frame-Options: DENY`, więc panelu nie da się osadzić w ramce na obcej stronie, a do tego `X-Content-Type-Options`, `Referrer-Policy` i `Permissions-Policy`.
- **PHP.** `docker/php/app.ini` wyłącza pokazywanie błędów na stronach (trafiają do `docker compose logs`) i ukrywa wersję PHP.
- **Ikony.** Font Awesome jest serwowany z `public/assets/fontawesome/`, więc na stronach nie działa żaden skrypt z innej domeny.
- **Sesja.** Sesja należy do jednego konta: nowe logowanie zastępuje całą sesję, a jej identyfikator zmienia się przy logowaniu. Sesja nieużywana przez godzinę wygasa.
- **Formularze.** Każdy formularz POST jest sprawdzany pod kątem pochodzenia (`Sec-Fetch-Site` albo `Origin`).
- **Dostęp.** Dostęp do serwera jest sprawdzany na Discordzie przed każdą zmianą (`src/services/ServerAccess.php`).

Po zmianie `nginx.conf` albo `app.ini` obrazy trzeba zbudować ponownie: `docker compose up --build -d`.

## Migracje bazy

Schemat bazy powstaje z plików `database/migrations/*.sql`, wykonywanych w kolejności numerów. `bin/migrate.php` uruchamia każdy plik tylko raz, w osobnej transakcji, i zapisuje go w tabeli `schema_migrations`. Nieudana migracja niczego nie zmienia, a usługi czekające na `migrate` się nie uruchamiają.

Zmiana schematu to nowy plik z kolejnym numerem, np. `0002_add_feedback.sql`, bez `BEGIN` i `COMMIT`. Wykonane już pliki zostają bez zmian. Migracje bez restartu kontenerów:

```shell
docker compose exec -T php php bin/migrate.php
```

Baza utworzona wcześniej ze starego `database/schema.sql` ma już schemat pierwszej migracji, więc przy pierwszym uruchomieniu ta migracja jest tylko zapisywana jako wykonana.

## Sprawdzenie jednej wiadomości

```shell
docker compose exec php php bin/check-message.php pl "treść wiadomości"
```

Polecenie pokazuje oceny modelu, którego dostają nowe kanały (`config/channel-defaults.php`), i decyzję dla każdego poziomu.

## Pomiar modeli

```shell
docker compose exec -T php php bin/measure.php basal-mini pl
# nowe pytanie dla jednej zasady, bez zmiany konfiguracji
docker compose exec -T php php bin/measure.php basal-mini pl --racism="Czy ta wiadomość ..."
```

Polecenie wysyła do modelu opisane wiadomości z `tests/models/<język>.php` i dla każdej zasady oraz poziomu podaje, ile naruszeń model złapał i ile czystych wiadomości zablokował, a na końcu wypisuje pomyłki. Proponowany próg dobiera na co drugiej wiadomości i sprawdza na pozostałych. Odpowiedzi modelu zapamiętuje w katalogu tymczasowym kontenera, więc ponowny pomiar z tymi samymi pytaniami trwa chwilę. Pierwszy trwa kilka minut i w tym czasie bot odpowiada wolniej, bo dzieli z nim model.

Nową wiadomość dopisuje się do zbioru razem z listą zasad, które naprawdę łamie; pusta lista oznacza czystą wiadomość.

## Testy jednostkowe

```shell
docker compose exec -T php php bin/test.php
# tylko klasy z podanym tekstem w nazwie
docker compose exec -T php php bin/test.php Permissions
```

Testy leżą w `tests/` i sprawdzają logikę, która nie potrzebuje bazy, sieci ani modelu: uprawnienia Discorda, listę kanałów widocznych dla bota, decyzję, czy wiadomość łamie zasadę, dopasowanie tras oraz sprawdzanie pochodzenia formularzy. Każdy test działa na nowym obiekcie, więc wynik nie zależy od kolejności ani od poprzednich uruchomień. Projekt nie ma zależności testowych: `bin/test.php` uruchamia każdą metodę `test…` z klas `tests/*Test.php`, a kod wyjścia 1 oznacza nieudany test.

Te same testy i sprawdzenie składni wszystkich plików PHP uruchamia GitHub Actions przy każdym pushu (`.github/workflows/ci.yml`), bez Dockera i bazy.

## Zadanie cykliczne (cron)

Historia moderacji zawiera treść wiadomości i dane ich autorów, więc nie jest przechowywana bez końca. Usługa `cron` uruchamia codziennie o 03:15 UTC polecenie `php bin/cleanup.php`, które w jednej transakcji:

- usuwa treść oraz autora ze zdarzeń starszych niż 30 dni; kategoria, akcja, wynik modelu i czas zostają do statystyk,
- usuwa serwery, z których bot został usunięty ponad 30 dni temu, razem z ich kanałami, zasadami i zdarzeniami (`ON DELETE CASCADE`).

Okresy przechowywania są ustawione w kodzie obok danych: `ModerationEventRepository::KEEP_CONTENT_DAYS` i `ServerRepository::KEEP_REMOVED_DAYS`. Harmonogram jest zapisany w `docker-compose.yaml` jako `15 3 * * *`. Każde uruchomienie zapisuje jedną linię z liczbą zmienionych zdarzeń i usuniętych serwerów, widoczną w `docker compose logs cron`. Ponowne uruchomienie niczego nie zmienia, bo oczyszczone zdarzenia i usunięte serwery nie spełniają już warunków.

Testowanie bez czekania na harmonogram:

```shell
# tylko liczy, niczego nie zmienia
docker compose exec php php bin/cleanup.php --dry-run
# wykonuje czyszczenie
docker compose exec php php bin/cleanup.php
```
