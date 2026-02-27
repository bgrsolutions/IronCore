# IronCore ERP

IronCore is a Laravel 11 + Filament v3 multi-company ERP foundation for Canary Islands (IGIC) businesses.

## Local Setup

### 1) Start infrastructure
```bash
docker compose up -d
```

### 2) Install dependencies and app config
```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 3) Prepare database and storage
```bash
php artisan migrate --seed
php artisan storage:link
```

### 4) Run app
```bash
php artisan serve
```

Open Filament at: `http://127.0.0.1:8000/admin/login`

## Seeded admin credentials (dev only)
- **Email:** `admin@ironcore.local`
- **Password:** `password`

## Notes
- Default file disk is S3-compatible (`minio`) using `.env` values in `.env.example`.
- If MinIO is unavailable, switch `FILESYSTEM_DISK=local` in `.env`.
- Company context selection is required before data resources become available.
