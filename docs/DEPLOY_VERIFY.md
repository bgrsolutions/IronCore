# Deploy Verification Runbook (Ubuntu 24.04)

Run after one-command install on VPS:

```bash
cd /opt/ironcore
bash -n scripts/install_ubuntu24.sh
test -f .env && echo ".env exists"
grep -E '^APP_KEY=base64:|^APP_URL=' .env
docker compose ps
php -v
php -l database/seeders/PermissionSeeder.php
php -l database/seeders/DatabaseSeeder.php
sudo -u ironcore -H php artisan migrate:fresh --seed --force
sudo -u ironcore -H php artisan test --filter=FilamentAuthTest
curl -I http://127.0.0.1:8000/admin/login
```

MinIO bucket check:

```bash
mkdir -p /root/.mc
docker run --rm --network host -v /root/.mc:/root/.mc minio/mc:latest alias set local http://127.0.0.1:9000 minio miniopassword
docker run --rm --network host -v /root/.mc:/root/.mc minio/mc:latest ls local/ironcore-documents
```
