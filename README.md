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
| `basal-mini` | model moderacji, dostępny tylko dla kontenerów | — |
| `db` | PostgreSQL, schemat w `database/schema.sql` | localhost:5433 |
| `pgadmin-wdpai` | pgAdmin | http://localhost:5050 |
| `mailpit` | skrzynka testowa | http://localhost:8025 |

## Sprawdzenie jednej wiadomości

```shell
docker compose exec php php bin/check-message.php pl "treść wiadomości"
```

Polecenie pokazuje oceny modelu i decyzję dla każdego poziomu.
