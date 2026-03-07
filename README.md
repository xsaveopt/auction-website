### Create an admin user

```bash
docker exec -it <container> php artisan app:create-admin admin yourpassword
```

To promote an existing user (including Microsoft SSO users), use:

```bash
docker exec -it <container> php artisan app:make-admin user@example.com
```

### Environment variables

| Variable                  | Default                                  | Description                                                                                                                          |
| ------------------------- | ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `SERVER_NAME`             | `:80`                                    | Domain for Caddy. Set to your domain (e.g. `auction.example.com`) for automatic HTTPS via Let's Encrypt. Use `:80` for plain HTTP.   |
| `APP_KEY`                 | _(auto-generated)_                       | Laravel encryption key. Auto-generated on first boot if not set. Set explicitly to keep sessions valid across container recreations. |
| `BIDDING_CLOSED_START`    | `09:00`                                  | Start of the office hours window (24h format). Bidding is disabled during office hours.                                              |
| `BIDDING_CLOSED_END`      | `18:00`                                  | End of the office hours window (24h format). Bidding reopens at this time.                                                           |
| `BIDDING_WEEKENDS_OPEN`   | `true`                                   | When `true`, bidding is always open on weekends regardless of the closed window. Set to `false` to enforce the closed hours every day. |
| `APP_ENV`                 | `production`                             | Laravel environment.                                                                                                                 |
| `APP_DEBUG`               | `false`                                  | Enable debug mode. Do not enable in production.                                                                                      |
| `MICROSOFT_CLIENT_ID`     | _(empty)_                                | Microsoft Entra application (client) ID. When set with `MICROSOFT_CLIENT_SECRET`, SSO is enabled and the entire app requires Microsoft authentication — unauthenticated users are redirected to Microsoft login. |
| `MICROSOFT_CLIENT_SECRET` | _(empty)_                                | Microsoft Entra client secret. Leave both SSO vars empty to use standard username/password auth.                                     |
| `MICROSOFT_REDIRECT_URI`  | `${APP_URL}/api/auth/microsoft/callback` | OAuth callback URL configured in Microsoft Entra app registration.                                                                   |
| `MICROSOFT_TENANT_ID`     | _(empty — allows any Microsoft account)_ | Azure AD tenant ID. Set to restrict login to your organization's users only.                                                         |

### Persistent data

The `/data` volume stores:

- `database.sqlite` — SQLite database
- `images/` — uploaded auction images
- `caddy_data/` — TLS certificates (Let's Encrypt)
- `caddy_config/` — Caddy configuration state
