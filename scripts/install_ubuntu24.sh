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
QUEUE_SERVICE_PATH="/etc/systemd/system/ironcore-artisan-queue.service"

log() {
  printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

run_in_repo() {
  cd "$REPO_DIR"
  "$@"
}

ensure_root() {
  if [[ "${EUID}" -ne 0 ]]; then
    log "Re-running installer with sudo"
    exec sudo -E bash "$0" "$@"
  fi
}

apt_install() {
  DEBIAN_FRONTEND=noninteractive apt-get install -y "$@"
}

install_base_packages() {
  log "Installing base packages"
  apt-get update
  apt_install ca-certificates curl gnupg lsb-release software-properties-common git unzip ufw
}

install_docker_and_compose() {
  if ! command -v docker >/dev/null 2>&1; then
    log "Installing Docker Engine + Compose plugin"
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    local codename
    codename="$(. /etc/os-release && echo "$VERSION_CODENAME")"
    cat > /etc/apt/sources.list.d/docker.list <<EOF

deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu ${codename} stable
EOF
    apt-get update
    apt_install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  fi

  systemctl enable --now docker
}

install_php_84() {
  if ! php -v 2>/dev/null | head -n 1 | grep -q 'PHP 8.4'; then
    log "Installing PHP 8.4 and required extensions"
    add-apt-repository -y ppa:ondrej/php
    apt-get update
    apt_install \
      php8.4 php8.4-cli php8.4-common php8.4-fpm php8.4-mysql php8.4-sqlite3 \
      php8.4-xml php8.4-curl php8.4-mbstring php8.4-zip php8.4-bcmath php8.4-intl \
      php8.4-gd php8.4-redis
    update-alternatives --set php /usr/bin/php8.4 || true
  fi
}

install_composer() {
  if ! command -v composer >/dev/null 2>&1; then
    log "Installing Composer"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
  fi
}

install_node_20() {
  if ! command -v node >/dev/null 2>&1 || ! node -v | grep -q '^v20'; then
    log "Installing Node.js 20"
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt_install nodejs
  fi
}

start_compose_services() {
  log "Starting Docker services"
  run_in_repo docker compose up -d
}

create_minio_bucket() {
  log "Ensuring MinIO bucket exists: ${MINIO_BUCKET}"
  mkdir -p /var/lib/ironcore/mc

  docker run --rm --network host \
    -v /var/lib/ironcore/mc:/root/.mc \
    minio/mc alias set local "$MINIO_ENDPOINT" "$MINIO_KEY" "$MINIO_SECRET"

  docker run --rm --network host \
    -v /var/lib/ironcore/mc:/root/.mc \
    minio/mc mb --ignore-existing "local/${MINIO_BUCKET}"
}

prepare_env_file() {
  log "Preparing .env file"
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

bootstrap_laravel() {
  log "Installing PHP dependencies"
  run_in_repo composer install --no-interaction --prefer-dist

  log "Installing frontend dependencies"
  run_in_repo npm install
  run_in_repo npm run build

  log "Running Laravel bootstrap commands"
  run_in_repo php artisan config:clear
  run_in_repo php artisan cache:clear
  run_in_repo php artisan key:generate --force
  run_in_repo php artisan migrate --seed --force
  run_in_repo php artisan storage:link || true
  run_in_repo php artisan optimize
}

write_optional_systemd_service() {
  log "Writing optional queue worker service"
  cat > "$QUEUE_SERVICE_PATH" <<EOF
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
EOF

  systemctl daemon-reload
}

main() {
  ensure_root "$@"
  install_base_packages
  install_docker_and_compose
  install_php_84
  install_composer
  install_node_20

  start_compose_services
  create_minio_bucket

  prepare_env_file
  bootstrap_laravel
  write_optional_systemd_service

  log "Install complete"
  log "Application URL: ${APP_URL}"
  log "Admin login: admin@ironcore.local / password"
}

main "$@"
