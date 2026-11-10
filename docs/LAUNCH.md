# Launch guide

## Fastest path: the frontend only (no PHP, no database)
```bash
cd web && npm install && npm run dev      # http://localhost:5173
```
Pick a demo account on the sign-in page. A built-in mock API answers every request and applies the same masking rules as the real backend. See `web/README.md` for the walkthrough.

To host the demo for others: `npm run build:demo`, then upload `web/dist` to any static host.

## 0. What you need for the real backend
| Tool | Version | Check |
|---|---|---|
| PHP | 8.2+ with `mbstring xml curl pdo_mysql zip fileinfo` | `php -v` |
| Composer | 2.x | `composer -V` |
| MySQL | 8.x (installer, Homebrew, XAMPP, Laragon or a managed database) | `mysql --version` |
| Node.js | 20+ | `node -v` |
| Perl, make | preinstalled on macOS/Linux. On Windows use WSL2 | |

## 1. Run the real backend locally (about 10 minutes)

```bash
# from the project root
make setup     # builds the Laravel project inside ./api, installs web dependencies
make mysql     # creates databases and user (asks for your MySQL root password)
make db        # creates tables and demo users
```
No `make`? Run the commands inside each target of the `Makefile` by hand.

Then open three terminals:

```bash
make api                     # http://localhost:8000   (API)
make queue                   # queue worker (needed for "My unit only" and "All staff" sends)
cd web && npm run dev:api    # http://localhost:5173   (the app, talking to the real API)
```

Sign in at http://localhost:5173 with `sa001@ui.edu.ng` / `ChangeMe#2026`.
Other demo users: `vc001`, `dvc01`, `hd001`, `st001`, `st002` (all `@ui.edu.ng`, same password).
Run the backend tests with `make test`.

**Check the core feature**
1. Sign in as `vc001`, New memo, upload any PDF, "Specific people", Create draft.
2. Pick the Note tool, click the page, write a note, Pin note. Then Send to `DVC`.
3. Sign in as `dvc01` (use a private window for a second session), open the memo, Forward to `Ada Okafor`, keep "Hide executive notes" on, press Preview as recipient, then Forward.
4. Sign in as `st001`: the VC's note is gone. Run `make test` to prove the rules with automated tests.

No `make`? Run the commands inside the targets by hand (see `Makefile`).

## 2. Before real users

1. **Change every demo password** and delete the seeder users you do not need. Never run `DemoSeeder` in production; create the first SuperAdmin with `php artisan tinker`:
   ```php
   $u = new App\Models\User(['staff_no'=>'SA001','name'=>'Your Name','email'=>'you@ui.edu.ng','password'=>'a-long-unique-passphrase']);
   $u->role='super_admin'; $u->save();
   ```
2. Build the org tree (faculties, departments, units) through the admin API (`/api/v1/admin/...`), then add staff. There is no admin UI for this yet, so use the API or a short script.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, and strong `DB_PASSWORD` in `api/.env`.

## 3. Production deploy (Ubuntu 22.04/24.04 server, one domain)

```bash
# packages
sudo apt install nginx mysql-server php8.3-fpm php8.3-{mbstring,xml,curl,mysql,zip} supervisor certbot python3-certbot-nginx
# code
sudo mkdir -p /var/www/ui-memo && sudo chown $USER /var/www/ui-memo
git clone <your-repo> /var/www/ui-memo && cd /var/www/ui-memo
```

**Database**
```sql
CREATE DATABASE ui_memo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ui_memo'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL ON ui_memo.* TO 'ui_memo'@'localhost';
```

**API**
```bash
./scripts/bootstrap-api.sh            # then edit api/.env: DB_*, APP_ENV=production, APP_DEBUG=false, APP_URL
cd api && composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```
Set `upload_max_filesize = 25M` and `post_max_size = 30M` in `/etc/php/8.3/fpm/php.ini`, then `sudo systemctl restart php8.3-fpm`.

**Web**
```bash
cd ../web && npm ci && npm run build   # outputs web/dist
```

**nginx + HTTPS**
```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/ui-memo   # edit server_name and PHP socket version
sudo ln -s /etc/nginx/sites-available/ui-memo /etc/nginx/sites-enabled/
sudo certbot --nginx -d memo.example.edu.ng
sudo nginx -t && sudo systemctl reload nginx
```

**Queue worker**
```bash
sudo cp deploy/ui-memo-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
```
After every deploy: `php artisan migrate --force && php artisan config:cache && sudo supervisorctl restart ui-memo-worker:*`.

## 4. Operations checklist
- Back up MySQL nightly (`mysqldump`) **and** `api/storage/app/private/memos` (the original documents). Test a restore.
- Make `audit_logs` append-only: `REVOKE UPDATE, DELETE ON ui_memo.audit_logs FROM 'ui_memo'@'localhost';`
- Keep the server behind HTTPS only; tokens travel in the `Authorization` header.
- Monitor `/var/log/ui-memo-worker.log` and `api/storage/logs`.
- Load test a global broadcast to your full staff count on staging before first use.

## 5. Troubleshooting
| Symptom | Fix |
|---|---|
| `make setup` says a PHP extension is missing | Install the extension from section 0 and re-run |
| `SQLSTATE ... Connection refused` | MySQL is not running. Start it, then check `DB_HOST` and `DB_PORT` in `api/.env` |
| Login works but every call returns 401 | The browser kept a stale token: clear site storage and sign in again |
| Upload fails with 413 | Raise nginx `client_max_body_size` and PHP `upload_max_filesize` |
| "All staff" or unit memo sends but nobody receives it | The queue worker is not running (`make queue`) |
| Tests fail with unknown database `ui_memo_test` | Run `make mysql` (it creates both databases) |
| PDF page is blank in the viewer | Hard-refresh; check the browser console for a `pdf.worker` load error (the file must be served from the same origin) |
