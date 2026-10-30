# Web-Development-Project

Edukacyjna aplikacja w PHP 8.3 pokazująca routing i MVC.

## Uruchomienie

```shell
cp .env.example .env
docker compose up --build -d
docker compose exec -T php composer install
```

Aplikacja będzie dostępna pod adresem http://localhost:8080.
