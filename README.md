# Auction Website

Laravel 12 + Vue 3 SPA. SQLite. Runs on FrankenPHP.

## Environment

See `.env.example` for all variables.

## Run

```bash
docker build -t auction .
docker run -v auction-data:/data -p 443:443 -p 80:80 --env-file .env auction
```

With monitoring (Prometheus + Grafana):

```bash
docker compose up --build
```

## Commands

All commands live under `app:`. Run inside the container with `docker exec -it <container> php artisan ...`.

## Persistent data

The `/data` volume stores the SQLite database, etc.
