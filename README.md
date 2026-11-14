# ChatWarden

Bot moderujący czat i panel WWW do jego ustawień. Bot czyta wiadomości na wybranych kanałach Discorda, pyta lokalnie uruchomiony model, czy łamią zasady kanału, i oznacza je albo usuwa. W panelu osoba zarządzająca serwerem wybiera kanały, język, poziomy i akcje oraz przegląda historię działań bota.

Aplikacja jest napisana w PHP 8.3 bez frameworka, z własnym routingiem i MVC, i korzysta z PostgreSQL.

## Uruchomienie

```shell
cp .env.example .env
docker compose up --build -d
docker compose exec -T php composer install
```

Aplikacja będzie dostępna pod adresem http://localhost:8080.

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
| `db` | PostgreSQL, schemat w `database/schema.sql` | localhost:5433 |
| `pgadmin-wdpai` | pgAdmin | http://localhost:5050 |
| `mailpit` | skrzynka testowa | http://localhost:8025 |

## Sprawdzenie jednej wiadomości

```shell
docker compose exec php php bin/check-message.php pl "treść wiadomości"
```

Polecenie pokazuje oceny modelu i decyzję dla każdego poziomu.

## Testy jednostkowe

```shell
docker compose exec -T php php bin/test.php
# tylko klasy z podanym tekstem w nazwie
docker compose exec -T php php bin/test.php Permissions
```

Testy leżą w `tests/` i sprawdzają logikę, która nie potrzebuje bazy, sieci ani modelu: uprawnienia Discorda, listę kanałów widocznych dla bota oraz decyzję, czy wiadomość łamie zasadę. Każdy test działa na nowym obiekcie, więc wynik nie zależy od kolejności ani od poprzednich uruchomień. Projekt nie ma zależności testowych: `bin/test.php` uruchamia każdą metodę `test…` z klas `tests/*Test.php`, a kod wyjścia 1 oznacza nieudany test.

## Zadanie cykliczne (cron)

Historia moderacji zawiera treść wiadomości i dane ich autorów, więc nie jest przechowywana bez końca. Usługa `cron` uruchamia codziennie o 03:15 UTC polecenie `php bin/cleanup.php`, które w jednej transakcji:

- usuwa treść oraz autora ze zdarzeń starszych niż 30 dni; kategoria, akcja, wynik modelu i czas zostają do statystyk,
- usuwa serwery, z których bot został usunięty ponad 30 dni temu, razem z ich kanałami, zasadami i zdarzeniami (`ON DELETE CASCADE`).

Harmonogram jest zapisany w `docker-compose.yaml` jako `15 3 * * *`. Każde uruchomienie zapisuje jedną linię z liczbą zmienionych zdarzeń i usuniętych serwerów, widoczną w `docker compose logs cron`. Ponowne uruchomienie niczego nie zmienia, bo oczyszczone zdarzenia i usunięte serwery nie spełniają już warunków.

Testowanie bez czekania na harmonogram:

```shell
# tylko liczy, niczego nie zmienia
docker compose exec php php bin/cleanup.php --dry-run
# wykonuje czyszczenie
docker compose exec php php bin/cleanup.php
```
