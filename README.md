# Auction Website

Laravel 12 + Vue 3 SPA. SQLite. Runs on FrankenPHP.

## Run

```bash
docker build -t auction .
docker run -v auction-data:/data -p 443:443 -p 80:80 -e SERVER_NAME=auction.example.com auction
```

With monitoring (Prometheus + Grafana):

```bash
docker compose up --build
```

## Environment

See `.env.example` for all variables.

## Commands

All commands live under `app:`. Run inside the container with `docker exec -it <container> php artisan ...`.

```
app:create-admin {username} {password}   Create an admin user
app:make-admin {identifier}              Promote user to admin
app:remove-admin {identifier}            Demote admin to regular user
app:list-users                           List users (--search, --admins, --limit)
app:list-auctions                        List auctions (--status, --search, --limit)
app:list-bids {id}                       List bids for an auction (--limit)
app:update-auctions                      Bulk update auctions (--ids/--all-active, --add-time, --set-end, --status)
app:stats                                Show platform statistics
```

## Persistent data

The `/data` volume stores the SQLite database, uploaded images, and Caddy TLS certificates.
