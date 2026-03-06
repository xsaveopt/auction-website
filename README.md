# Auction House

Internal auction website for selling old hardware to employees.

## Docker

### Build

```bash
docker build -t auction .
```

### Run

```bash
docker run -d \
  -v auction-data:/data \
  -p 443:443 -p 80:80 \
  -e SERVER_NAME=auction.example.com \
  -e BIDDING_CLOSED_START=09:00 \
  -e BIDDING_CLOSED_END=18:00 \
  auction
```

### Create an admin user

```bash
docker exec -it <container> php artisan app:create-admin admin yourpassword
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `SERVER_NAME` | `:80` | Domain for Caddy. Set to your domain (e.g. `auction.example.com`) for automatic HTTPS via Let's Encrypt. Use `:80` for plain HTTP. |
| `APP_KEY` | *(auto-generated)* | Laravel encryption key. Auto-generated on first boot if not set. Set explicitly to keep sessions valid across container recreations. |
| `BIDDING_CLOSED_START` | `09:00` | Start of the office hours window (24h format). Bidding is disabled during office hours. |
| `BIDDING_CLOSED_END` | `18:00` | End of the office hours window (24h format). Bidding reopens at this time. |
| `APP_ENV` | `production` | Laravel environment. |
| `APP_DEBUG` | `false` | Enable debug mode. Do not enable in production. |

### Persistent data

The `/data` volume stores:

- `database.sqlite` — SQLite database
- `images/` — uploaded auction images
- `caddy_data/` — TLS certificates (Let's Encrypt)
- `caddy_config/` — Caddy configuration state
