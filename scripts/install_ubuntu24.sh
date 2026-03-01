#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="${REPO_DIR:-$(pwd)}"
APP_URL="${APP_URL:-http://$(hostname -I | awk '{print $1}')}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-ironcore}"
DB_USERNAME="${DB_USERNAME:-ironcore}"
DB_PASSWORD="${DB_PASSWORD:-ironcore}"
MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://127.0.0.1:9000}"
MINIO_KEY="${MINIO_KEY:-minio}"
MINIO_SECRET="${MINIO_SECRET:-miniopassword}"
MINIO_BUCKET="${MINIO_BUCKET:-ironcore-documents}"

log() { printf '\n[%s] %s\n' "$(date +%H:%M:%S)" "$*"; }

require_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    log "Re-running installer with sudo..."
    exec sudo -E bash "$0" "$@"
  fi
}

apt_install() {
  DEBIAN_FRONTEND=noninteractive apt-get install -y "$@"
}

install_system_dependencies() {
  log "Installing system dependencies"
  apt-get update
  apt_install ca-certificates curl gnupg lsb-release software-properties-common git unzip ufw
}

install_docker() {
  if ! command -v docker >/dev/null 2>&1; then
    log "Installing Docker Engine + Compose plugin"
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" > /etc/apt/sources.list.d/docker.list
    apt-get update
    apt_install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  fi
  systemctl enable --now docker
}

install_php84() {
  if ! php -v 2>/dev/null | head -n1 | grep -q 'PHP 8.4'; then
    log "Installing PHP 8.4 and extensions"
    add-apt-repository -y ppa:ondrej/php
    apt-get update
    apt_install php8.4 php8.4-cli php8.4-common php8.4-fpm php8.4-mysql php8.4-xml php8.4-curl php8.4-mbstring php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-redis php8.4-sqlite3
    update-alternatives --set php /usr/bin/php8.4 || true
  fi
}

install_composer() {
  if ! command -v composer >/dev/null 2>&1; then
    log "Installing Composer"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
  fi
}

install_node20() {
  if ! command -v node >/dev/null 2>&1 || ! node -v | grep -q '^v20'; then
    log "Installing Node.js 20"
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt_install nodejs
  fi
}

prepare_env() {
  log "Preparing Laravel environment file"
  cd "$REPO_DIR"
  [[ -f .env ]] || cp .env.example .env

  sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" .env
  sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
  sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" .env
  sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT}|" .env
  sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|" .env
  sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|" .env
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
  sed -i "s|^AWS_ENDPOINT=.*|AWS_ENDPOINT=${MINIO_ENDPOINT}|" .env
  sed -i "s|^AWS_ACCESS_KEY_ID=.*|AWS_ACCESS_KEY_ID=${MINIO_KEY}|" .env
  sed -i "s|^AWS_SECRET_ACCESS_KEY=.*|AWS_SECRET_ACCESS_KEY=${MINIO_SECRET}|" .env
  sed -i "s|^AWS_BUCKET=.*|AWS_BUCKET=${MINIO_BUCKET}|" .env
}

start_infra() {
  log "Starting MariaDB, Redis, and MinIO"
  cd "$REPO_DIR"
  docker compose up -d
}

create_minio_bucket() {
  log "Creating MinIO bucket: ${MINIO_BUCKET}"
  mkdir -p /var/lib/ironcore/mc
  docker run --rm --network host -v /var/lib/ironcore/mc:/root/.mc minio/mc alias set local "$MINIO_ENDPOINT" "$MINIO_KEY" "$MINIO_SECRET"
  docker run --rm --network host -v /var/lib/ironcore/mc:/root/.mc minio/mc mb --ignore-existing "local/${MINIO_BUCKET}"
}

install_app() {
  log "Installing PHP and Node dependencies"
  cd "$REPO_DIR"
  composer install --no-interaction --prefer-dist
  npm install
  npm run build

  log "Running Laravel bootstrap tasks"
  php artisan config:clear
  php artisan cache:clear
  php artisan key:generate --force
  php artisan migrate --seed --force
  php artisan storage:link || true
  php artisan optimize
}

write_systemd_hint() {
  cat > /etc/systemd/system/ironcore-artisan-queue.service <<SERVICE
[Unit]
Description=IronCore queue worker
After=network.target docker.service

[Service]
Type=simple
User=${SUDO_USER:-root}
WorkingDirectory=${REPO_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SERVICE
  systemctl daemon-reload
  log "Created optional service: /etc/systemd/system/ironcore-artisan-queue.service"
}

main() {
  require_root "$@"
  install_system_dependencies
  install_docker
  install_php84
  install_composer
  install_node20
  start_infra
  create_minio_bucket
  prepare_env
  install_app
  write_systemd_hint

  log "Install complete. Login with admin@ironcore.local / password"
}

main "$@"
