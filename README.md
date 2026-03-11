### Create an admin user

```bash
docker exec -it <container> php artisan app:create-admin admin yourpassword
```

To promote an existing user (including Microsoft SSO users), use:

```bash
docker exec -it <container> php artisan app:make-admin user@example.com
```

### Environment variables

Read .env.example

### Persistent data

The `/data` volume stores:

- `database.sqlite` — SQLite database
- `images/` — uploaded auction images
- `caddy_data/` — TLS certificates (Let's Encrypt)
- `caddy_config/` — Caddy configuration state
