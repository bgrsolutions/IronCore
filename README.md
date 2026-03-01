# IronCore ERP

IronCore is a Laravel 11 + Filament v3 multi-company ERP foundation for Canary Islands (IGIC) businesses.

## Requirements

- Ubuntu 24.04 LTS
- Docker Engine + Docker Compose plugin
- PHP 8.4 (Symfony 8 dependency)
- Composer
- Node.js 20 + npm

## One-command Ubuntu 24.04 install

From a fresh VPS:

```bash
git clone https://github.com/bgrsolutions/IronCore.git /opt/ironcore && cd /opt/ironcore && bash scripts/install_ubuntu24.sh
```

The installer is idempotent and safe to re-run. It will:

1. install required system packages,
2. install Docker and Compose plugin,
3. install PHP 8.4 + extensions,
4. install Composer + Node 20,
5. start `docker compose` services,
6. create MinIO bucket `ironcore-documents`,
7. create and normalize `.env`,
8. clear caches + generate `APP_KEY`,
9. run `composer install`, `npm install`, `npm run build`,
10. run `php artisan migrate --seed --force`, and
11. create `storage:link` and optimize cache.


## VPS installer guarantees

`scripts/install_ubuntu24.sh` now guarantees:

- root-only execution (fails early if not root),
- Linux user/group creation (`ironcore:ironcore`) before app commands,
- `/opt/ironcore` creation + ownership,
- `.env` creation from `.env.example` (or fallback template) on every fresh host,
- `APP_KEY` generation before app boot,
- safe Laravel bootstrap order for `CACHE_STORE=database`:
  1. `composer install`
  2. `npm install && npm run build`
  3. `php artisan key:generate --force`
  4. `php artisan migrate --seed --force`
  5. `php artisan storage:link`
  6. `php artisan optimize:clear`
  7. `php artisan optimize`
- Docker readiness wait for MariaDB + MinIO,
- MinIO bucket creation via direct `minio/mc` commands (no shell), with persisted `/root/.mc` config.

## Manual local setup (optional)

```bash
docker compose up -d
cp .env.example .env
php artisan config:clear
php artisan cache:clear
php artisan key:generate --force
composer install
npm install && npm run build
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Open Filament at: `http://127.0.0.1:8000/admin/login`.

## Seeded admin credentials (dev only)

- **Email:** `admin@ironcore.local`
- **Password:** `password`

## Troubleshooting

### MinIO crashes with `x86-64-v2`

`docker-compose.yml` is pinned to a CPUv1-compatible MinIO image:
`minio/minio:RELEASE.2025-09-07T16-13-09Z-cpuv1`.

### MinIO bucket creation fails (`minio/mc` has no `sh`)

Use direct `mc` command invocation (no shell wrapper), and persist config with mounted `/root/.mc`:

```bash
docker run --rm --network host -v /var/lib/ironcore/mc:/root/.mc minio/mc alias set local http://127.0.0.1:9000 minio miniopassword
docker run --rm --network host -v /var/lib/ironcore/mc:/root/.mc minio/mc mb --ignore-existing local/ironcore-documents
```

### Linux user `ironcore` missing

Installer creates the user/group automatically before running any `sudo -u ironcore` commands:

```bash
groupadd -f ironcore
id -u ironcore || useradd -m -s /bin/bash -g ironcore ironcore
```

### `.env` missing or `APP_KEY` missing

```bash
cp .env.example .env
php artisan config:clear
php artisan cache:clear
php artisan key:generate --force
```

### Laravel still tries sqlite

```bash
php artisan config:clear
php artisan cache:clear
```

Then verify `DB_CONNECTION=mysql` in `.env`.

### MySQL/MariaDB index name too long

Migrations now define explicit short names for long composite indexes/uniques. If you are on an old DB state, rollback and migrate again:

```bash
php artisan migrate:fresh --seed
```

### Filament Create/Edit/Delete buttons missing

Ensure permissions are seeded and admin role has permissions:

```bash
php artisan db:seed --class=PermissionSeeder
php artisan permission:cache-reset || true
```

Also ensure your user has role `admin`.


## Verification Runbook

See `docs/DEPLOY_VERIFY.md` for the exact VPS validation commands.

## Notes

- Default disk is S3-compatible MinIO (`FILESYSTEM_DISK=s3`).
- Company context selection is required before company-scoped resources are available.
- `scripts/install_ubuntu24.sh` writes an optional queue-worker systemd service definition at `/etc/systemd/system/ironcore-artisan-queue.service`.
