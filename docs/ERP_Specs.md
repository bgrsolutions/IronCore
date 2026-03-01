# IronCore ERP Specs

## Deployment & First-Run Bootstrap (Ubuntu 24.04)

### One-command bootstrap

Use:

```bash
git clone https://github.com/bgrsolutions/IronCore.git /opt/ironcore && cd /opt/ironcore && bash scripts/install_ubuntu24.sh
```

The installer provisions Docker, PHP 8.4, Composer, Node 20, starts infra, creates MinIO bucket, prepares `.env`, clears caches, generates app key, installs dependencies, builds assets, migrates/seeds, and links storage.

### Architecture overview (docker compose)

Core infra services:

- `mariadb` (MariaDB 11)
- `redis` (Redis 7)
- `minio` (S3-compatible object storage)

MinIO is pinned to CPUv1 image for compatibility with older VPS CPUs.

### Known pitfalls and fixes

1. **MinIO CPU incompatibility (`x86-64-v2`)**  
   Fixed by pinning `minio/minio:RELEASE.2025-09-07T16-13-09Z-cpuv1`.

2. **MinIO bucket creation via `minio/mc`**  
   `minio/mc` does not provide `sh`; run `mc` directly and mount `/root/.mc` to persist alias config.

3. **Composer requires PHP >= 8.4**  
   Ubuntu bootstrap installs PHP 8.4 from Ondřej PPA.

4. **Missing `.env` / `APP_KEY`**  
   Installer copies from `.env.example`, clears caches, and runs `php artisan key:generate --force`.

5. **Laravel stale config using sqlite**  
   Installer clears config/cache before migrations.

6. **MySQL identifier >64 chars**  
   Migrations include explicit short names for long composite unique/index constraints.

7. **Filament CRUD actions missing**  
   Permissions are seeded for all resources, admin role receives all permissions, and resources/list pages define Create/Edit/Delete actions.

### Filament auth and permissions model

- Role/permission backbone uses `spatie/laravel-permission`.
- Seeders create canonical resource permissions (`viewAny`, `view`, `create`, `update`, `delete`).
- `admin` role is synchronized with all permissions.
- Seeded admin user (`admin@ironcore.local`) is assigned `admin` role.

### Company context model

- Admin panel auth middleware enforces active company selection.
- If no company context is selected, users are redirected to the Company Context page.
- Company context controls company-scoped resources and defaults.

### Migration compatibility rules

To preserve MySQL/MariaDB compatibility:

- Always provide explicit names for long composite indexes and unique constraints.
- Keep identifier names under 64 chars.
- Prefer concise names like `<abbr>_<abbr>_idx` / `<abbr>_uniq`.


### Linux user bootstrap

Installer always creates `ironcore:ironcore` before app commands:

- `groupadd -f ironcore`
- `id -u ironcore || useradd -m -s /bin/bash -g ironcore ironcore`
- `usermod -aG docker ironcore`

### Required bootstrap order (CACHE_STORE=database safe)

1. `composer install`
2. `npm install && npm run build`
3. `php artisan key:generate --force`
4. `php artisan migrate --seed --force`
5. `php artisan storage:link`
6. `php artisan optimize:clear`
7. `php artisan optimize`

This order avoids cache-table errors before migrations are applied.
