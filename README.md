# REDL — Motor-Share API

Laravel API backend for [REDL](https://github.com/jimbes/Motor-share), a
Strava-style motorbike ride tracker. Sanctum token auth, rides with GPS
tracks + server-side polyline simplification, bikes ("garage"), and a
social layer (photos, likes, comments).

The Flutter app is in a separate repo:
[jimbes/Motor-share](https://github.com/jimbes/Motor-share).

## Stack

- Laravel 13, PHP 8.3+
- Laravel Sanctum (token auth)
- SQLite for local dev/tests, MySQL in production

## Quick start

```bash
# Docker (recommended - PHP-FPM + Nginx + MySQL, one command)
docker compose up -d --build
# API at http://localhost:8000/api

# --- or, plain PHP ---
composer install
touch database/database.sqlite
php artisan migrate
php artisan serve   # API at http://127.0.0.1:8000/api
```

## Testing

```bash
php artisan test
```

## Deploying to a real server

See `DEPLOY.md` — covers both the local Docker setup above and deploying to
a classic (non-Docker) PHP-FPM + Nginx/Apache server, including example
vhost configs and a redeploy script (`deploy/`).

## API overview

All endpoints under `/api`, Sanctum bearer token auth except register/login:

- `POST /register`, `POST /login`, `POST /logout`, `GET /me`, `GET /me/stats`
- `GET|POST /bikes`, `PUT|DELETE /bikes/{id}`
- `GET /rides` (paginated feed), `POST /rides`, `GET /rides/{id}`
- `POST /rides/{id}/photos`
- `POST|DELETE /rides/{id}/like`
- `GET|POST /rides/{id}/comments`, `DELETE /comments/{id}`
