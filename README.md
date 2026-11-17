# ChatWarden

Bot moderujący czat i panel WWW do jego ustawień. Bot czyta wiadomości na wybranych kanałach Discorda, pyta lokalnie uruchomiony model, czy łamią zasady kanału, i oznacza je albo usuwa. W panelu osoba zarządzająca serwerem wybiera kanały, język, poziomy i akcje, wskazuje, kogo bot ma pomijać (właściciel i administratorzy albo wybrane role), oraz przegląda historię działań bota.

Aplikacja jest napisana w PHP 8.3 bez frameworka, z własnym routingiem i MVC, i korzysta z PostgreSQL.

## Uruchomienie

```shell
cp .env.example .env
docker compose up --build -d
```

Przed pierwszym startem wpisz do `.env` klucz `APP_KEY`, wygenerowany raz poleceniem `openssl rand -base64 32`. Klucz szyfruje tokeny bota Twitcha w bazie, a aplikacja odrzuca krótszy niż 32 losowe bajty.

Aplikacja będzie dostępna pod adresem http://localhost:8080. Przy każdym starcie usługa `migrate` przygotowuje autoloader Composera i schemat bazy, a aplikacja, bot i cron czekają, aż skończy.

Przy pierwszym starcie kontener `basal-mini` pobiera model z Hugging Face, więc sprawdzanie wiadomości zaczyna działać dopiero po chwili.

## Discord

Logowanie do panelu i bot wymagają aplikacji w [Discord Developer Portal](https://discord.com/developers/applications). W `.env` uzupełnij:

- `DISCORD_CLIENT_ID` i `DISCORD_CLIENT_SECRET` — z zakładki OAuth2; dodaj tam adres powrotu `APP_URL/auth/discord/callback`.
- `DISCORD_BOT_TOKEN` — z zakładki Bot (Reset Token); na tej samej stronie włącz Message Content Intent.

Bez tokenu bot się nie uruchamia, a panel nie otwiera ustawień serwera. Po zmianie `.env` uruchom kontenery ponownie: `docker compose up -d`.

## Twitch

Logowanie przez Twitcha wymaga aplikacji w [Twitch Developer Console](https://dev.twitch.tv/console/apps). W `.env` uzupełnij `TWITCH_CLIENT_ID` i `TWITCH_CLIENT_SECRET`, a w konsoli dodaj adres powrotu `APP_URL/auth/twitch/callback`. Bez tych ustawień przycisk logowania przez Twitcha pokazuje, że logowanie nie jest skonfigurowane.

### Konto bota

Bot Twitcha działa jako zwykłe konto na Twitchu, osobne od Twojego, np. `ChatWardenBot`. Konto zakładasz sam, a potem:

1. Wpisz jego login (nazwę z adresu) w `.env` jako `TWITCH_BOT_LOGIN` i uruchom kontenery ponownie: `docker compose up -d`.
2. Zaloguj się na Twitchu tym kontem (najlepiej w oknie prywatnym) i otwórz `APP_URL/auth/twitch/bot`. Twitch pyta o zgodę na odczyt czatu, usuwanie wiadomości i listę kanałów, na których konto jest moderatorem. Konto autoryzowane przed dodaniem tej listy trzeba autoryzować tak samo jeszcze raz; do tego czasu bot słucha wszystkich podłączonych kanałów i mówi o tym w logach. Zapisane zostanie tylko konto o loginie z `TWITCH_BOT_LOGIN`, więc nikt inny nie może zostać botem. Zapisane konto jest potem rozpoznawane po identyfikatorze, nie po loginie: ponowna autoryzacja (np. po zmianie nazwy konta bota i `TWITCH_BOT_LOGIN`) przyjmuje tylko to samo konto, więc ktoś, kto przejmie dawną nazwę bota, nie zostanie botem. Żeby zmienić konto bota na inne, usuń poprzednie (użytkownik i baza jak `POSTGRES_USER` i `POSTGRES_DB` w `.env`): `docker compose exec db psql -U app -d app -c "DELETE FROM bot_accounts WHERE platform = 'twitch'"`, potem autoryzuj nowe i dodaj je jako moderatora na podłączonych kanałach.
3. Usługa `twitch-bot` sama zauważy konto (do minuty) i zacznie słuchać czatów podłączonych kanałów; nie trzeba jej restartować.
4. Moderatorem kanału bot staje się sam, gdy właściciel podłącza kanał w panelu (opis niżej). Ręczne `/mod ChatWardenBot` (z nazwą konta bota; panel podaje ją w zakładce Czat) jest potrzebne tylko wtedy, gdy się to nie udało albo ktoś później odebrał botu moderatora (`/unmod`). Bot co minutę sprawdza na Twitchu, gdzie jest moderatorem, i słucha tylko tych kanałów, bo tylko tam może usuwać wiadomości; ocenianie pozostałych zajmowałoby tylko model. Na kanale, na którym moderatorem nie jest, zakładka Czat pokazuje ostrzeżenie z komendą `/mod`, a po niej bot wraca w ciągu minuty.

Tokeny konta bota leżą w bazie (`bot_accounts`), zaszyfrowane kluczem wyprowadzonym z `APP_KEY` (`src/Secrets.php`), więc sama kopia bazy nie wystarcza, żeby ich użyć. Kto ma i bazę, i `APP_KEY`, może je odczytać, więc `APP_KEY` chroń jak hasło do bazy, a po jego zmianie konto bota trzeba autoryzować od nowa. Bot odświeża token sam, na pięć minut przed wygaśnięciem, i co godzinę pyta Twitcha, czy token nadal działa.

### Jak działa bot

Bot łączy się z EventSub Twitcha (`wss://eventsub.wss.twitch.tv/ws`) tylko wtedy, gdy ma jakiś kanał do słuchania, i subskrybuje czat każdego aktywnego kanału. Nowy kanał podłączony w panelu bot wykrywa przy najbliższej synchronizacji, czyli najpóźniej po minucie; z kanału wstrzymanego w tym czasie bot się wypisuje, a gdy nie zostaje żaden, zamyka połączenie. Kanał, którego Twitch nie pozwolił subskrybować, bot próbuje ponownie po pięciu minutach. Gdy Twitch każe przejść na inny serwer, bot otwiera nowe połączenie (tylko pod adres w domenie `twitch.tv`), zanim zamknie stare, więc żadna wiadomość nie przepada; po zerwaniu łączy się ponownie z coraz dłuższą przerwą. Twitch dopuszcza 300 subskrypcji na jedno połączenie, a ta liczba kanałów jest na razie górną granicą: kolejne kanały czekają w logach z odpowiedzią HTTP 429.

Bot pomija wiadomości właściciela kanału, moderatorów (także głównych, z odznaką `lead_moderator`), własne oraz te, które w czacie współdzielonym zostały wysłane na innym kanale (ocenia je moderacja tamtego kanału). Resztę ocenia model tak samo jak na Discordzie, z ustawieniami kanału z panelu. Usuwanie wiadomości działa, a wyciszenia i bany jeszcze nie (jak na Discordzie wiadomość jest wtedy usuwana).

Różnice w panelu względem Discorda:

- **Kanał to „serwer”.** Lista po zalogowaniu to własny kanał osoby oraz kanały, które moderuje (zakres `user:read:moderated_channels`). Ma jeden czat, więc zakładka Kanały pokazuje jedną kartę, a język startowy to ten, który właściciel ustawił na Twitchu.
- **Kanał podłącza tylko jego właściciel.** Na Twitchu nie ma zaproszenia bota, więc przycisk „Podłącz” przy własnym kanale prowadzi na Twitcha z prośbą o jedną zgodę (zakres `channel:manage:moderators`). Po niej aplikacja jednorazowo dodaje konto bota jako moderatora kanału tokenem właściciela, od razu go unieważnia na Twitchu i podłącza kanał z domyślnymi ustawieniami. Anulowanie zgody wraca do listy kanałów z komunikatem, a samo przejście na Twitcha działa tylko raz, zaraz po kliknięciu „Podłącz”. Gdy Twitch odmówi (bot ma na kanale rolę VIP, jest tam zbanowany, kanał ma komplet moderatorów), kanał i tak zostaje podłączony, a panel mówi, co zrobić ręcznie. Zgodę musi wyrazić konto, na które jesteś zalogowany w panelu, więc inne konto zalogowane na Twitchu w tej przeglądarce nie zadziała.
- **Moderatorzy zarządzają, ale nie podłączają.** Moderator widzi na liście kanał, który moderuje, i gdy kanał jest podłączony, zmienia jego ustawienia (kanał, model, limity, logi) tak samo jak właściciel. Przy niepodłączonym kanale widzi tylko informację, że podłącza go właściciel. Uprawnienia są sprawdzane na Twitchu przy każdej zmianie, więc moderator, który przestał nim być, od razu traci dostęp.
- **Token zostaje w sesji.** Na Twitchu tylko token samego użytkownika mówi, czy nadal moderuje dany kanał, więc token dostępu leży w sesji na serwerze (nie w bazie ani w ciasteczku) i nie trafia do strony. Przed każdą zmianą aplikacja pyta Twitcha o uprawnienia, jak na Discordzie. Logowanie kończy się razem z tokenem (około czterech godzin), a odrzucony przez Twitcha token kończy je od razu. Wylogowanie i ponowne logowanie unieważniają poprzedni token na Twitchu. Tokenu odświeżania aplikacja nie przechowuje.
- **Bez wyjątków dla ról.** Karty „Kogo bot pomija” nie ma, bo na Twitchu nie ma ról serwera; moderatorów i właściciela kanału bot pomija po odznakach czatu.

## Usługi

| Usługa | Rola | Adres |
| --- | --- | --- |
| `server`, `php` | strona i panel | http://localhost:8080 |
| `discord-bot` | bot, logi: `docker compose logs -f discord-bot` | — |
| `twitch-bot` | bot Twitcha, czeka sam na autoryzację konta bota, logi: `docker compose logs -f twitch-bot` | — |
| `cron` | codzienne czyszczenie danych, logi: `docker compose logs cron` | — |
| `basal-mini` | model moderacji dla polskiego i angielskiego, dostępny tylko dla kontenerów | — |
| `basal-pico` | mniejszy i szybszy model moderacji, tylko angielski | — |
| `migrate` | jednorazowo przy starcie: migracje bazy, logi: `docker compose logs migrate` | — |
| `db` | PostgreSQL, schemat w `database/migrations/` | localhost:5433 |
| `pgadmin-wdpai` | pgAdmin | http://localhost:5050 |
| `mailpit` | skrzynka testowa | http://localhost:8025 |

## Bezpieczeństwo

- **Nagłówki HTTP.** nginx (`docker/nginx/nginx.conf`) dodaje do każdej odpowiedzi Content-Security-Policy, która dopuszcza tylko skrypty, style i fonty z tej strony oraz obrazki z Discorda. Formularze trafiają tylko na tę stronę (`form-action 'self'`), a przeglądarka sprawdza tak też każde przekierowanie po formularzu; dlatego „Podłącz” przy kanale Twitcha, które kończy się na stronie zgody Twitcha, jest linkiem, a nie formularzem, i serwer przyjmuje je tylko po kliknięciu na tej stronie (`Sec-Fetch-Site: same-origin`). Wysyła też `X-Frame-Options: DENY`, więc panelu nie da się osadzić w ramce na obcej stronie, a do tego `X-Content-Type-Options`, `Referrer-Policy` i `Permissions-Policy`.
- **PHP.** `docker/php/app.ini` wyłącza pokazywanie błędów na stronach (trafiają do `docker compose logs`) i ukrywa wersję PHP.
- **Obrazki.** Content-Security-Policy dopuszcza obrazki tylko z tej strony, z Discorda i z `static-cdn.jtvnw.net` (zdjęcia profilowe z Twitcha); adres obrazka z innego hosta jest odrzucany, zanim trafi do strony.
- **Ikony.** Font Awesome jest serwowany z `public/assets/fontawesome/`, więc na stronach nie działa żaden skrypt z innej domeny.
- **Sesja.** Sesja należy do jednego konta: nowe logowanie zastępuje całą sesję, a jej identyfikator zmienia się przy logowaniu. Sesja nieużywana przez godzinę wygasa. Ciasteczko sesji jest niedostępne dla skryptów (`HttpOnly`), ma `SameSite=Lax`, a gdy strona działa po HTTPS, także `Secure`. Za serwerem pośredniczącym, który sam obsługuje HTTPS, decyduje o tym `APP_URL` zaczynający się od `https://`.
- **Formularze.** Każdy formularz POST jest sprawdzany pod kątem pochodzenia (`Sec-Fetch-Site` albo `Origin`).
- **Dostęp.** Dostęp do serwera jest sprawdzany na Discordzie przed każdą zmianą (`src/services/ServerAccess.php`).
- **Porty.** Baza, pgAdmin i Mailpit są wystawione tylko na `127.0.0.1`, więc z sieci widać wyłącznie stronę. Modele nie mają żadnego portu poza siecią Dockera.

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

Testy leżą w `tests/` i sprawdzają logikę, która nie potrzebuje bazy, sieci ani modelu: uprawnienia Discorda, rozpoznawanie administratorów serwera, adresy i obrazki z Twitcha, rozpoznawanie wiadomości z czatu Twitcha, szyfrowanie tokenów, wersje tekstów dla platformy, listę kanałów widocznych dla bota, decyzję, czy wiadomość łamie zasadę, dopasowanie tras oraz sprawdzanie pochodzenia formularzy. Każdy test działa na nowym obiekcie, więc wynik nie zależy od kolejności ani od poprzednich uruchomień. Projekt nie ma zależności testowych: `bin/test.php` uruchamia każdą metodę `test…` z klas `tests/*Test.php`, a kod wyjścia 1 oznacza nieudany test.

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
