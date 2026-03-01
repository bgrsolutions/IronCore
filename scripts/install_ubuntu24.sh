#!/usr/bin/env bash
set -euo pipefail

APP_USER="ironcore"
APP_GROUP="ironcore"
INSTALL_DIR="${INSTALL_DIR:-/opt/ironcore}"
REPO_URL="${REPO_URL:-https://github.com/bgrsolutions/IronCore.git}"
ENABLE_SYSTEMD="${ENABLE_SYSTEMD:-0}"
DEV_MODE="${DEV_MODE:-0}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-ironcore}"
DB_USERNAME="${DB_USERNAME:-ironcore}"
DB_PASSWORD="${DB_PASSWORD:-ironcore}"
MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://127.0.0.1:9000}"
MINIO_KEY="${MINIO_KEY:-minio}"
MINIO_SECRET="${MINIO_SECRET:-miniopassword}"
MINIO_BUCKET="${MINIO_BUCKET:-ironcore-documents}"

public_ip="${PUBLIC_IP:-}"
if [[ -z "$public_ip" ]]; then
  public_ip="$(curl -fsS --max-time 5 https://api.ipify.org || true)"
fi
if [[ -z "$public_ip" ]]; then
  public_ip="$(hostname -I | awk '{print $1}')"
fi
APP_URL="${APP_URL:-http://${public_ip}:8000}"

log() {
  printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

require_root() {
  if [[ "$EUID" -ne 0 ]]; then
    echo "ERROR: run as root (recommended on fresh Ubuntu 24.04)." >&2
    echo "Example: sudo -E bash scripts/install_ubuntu24.sh" >&2
    exit 1
  fi
}

run_as_app_user() {
  sudo -u "$APP_USER" -H bash -lc "cd '$INSTALL_DIR' && $*"
}

apt_install() {
  DEBIAN_FRONTEND=noninteractive apt-get install -y "$@"
}

ensure_linux_user() {
  log "Ensuring Linux user/group: ${APP_USER}:${APP_GROUP}"
  groupadd -f "$APP_GROUP"
  if ! id -u "$APP_USER" >/dev/null 2>&1; then
    useradd -m -s /bin/bash -g "$APP_GROUP" "$APP_USER"
  fi
}

ensure_install_dir() {
  log "Ensuring install directory: $INSTALL_DIR"
  mkdir -p "$INSTALL_DIR"

  if [[ ! -f "$INSTALL_DIR/artisan" ]]; then
    if [[ -d .git ]] && [[ -f artisan ]]; then
      log "Syncing current repository into $INSTALL_DIR"
      rsync -a --delete --exclude '.git' ./ "$INSTALL_DIR"/
    else
      log "Cloning repository into $INSTALL_DIR"
      git clone "$REPO_URL" "$INSTALL_DIR"
    fi
  fi

  chown -R "$APP_USER:$APP_GROUP" "$INSTALL_DIR"
}

install_base_packages() {
  log "Installing system dependencies"
  apt-get update
  apt_install ca-certificates curl gnupg lsb-release software-properties-common git unzip ufw rsync
}

install_docker() {
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
  usermod -aG docker "$APP_USER"
}

install_php84() {
  if ! php -v 2>/dev/null | head -n1 | grep -q 'PHP 8.4'; then
    log "Installing PHP 8.4 and extensions"
    add-apt-repository -y ppa:ondrej/php
    apt-get update
    apt_install php8.4 php8.4-cli php8.4-common php8.4-fpm php8.4-mysql php8.4-sqlite3 php8.4-xml php8.4-curl php8.4-mbstring php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd php8.4-redis
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
  log "Preparing .env"
  if [[ -f "$INSTALL_DIR/.env.example" ]]; then
    [[ -f "$INSTALL_DIR/.env" ]] || cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
  else
    cat > "$INSTALL_DIR/.env" <<EOF
APP_NAME=IronCore
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=${APP_URL}

DB_CONNECTION=mysql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=${MINIO_KEY}
AWS_SECRET_ACCESS_KEY=${MINIO_SECRET}
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=${MINIO_BUCKET}
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_ENDPOINT=${MINIO_ENDPOINT}
EOF
  fi

  sed -i "s|^APP_URL=.*|APP_URL=${APP_URL}|" "$INSTALL_DIR/.env"
  sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" "$INSTALL_DIR/.env"
  sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" "$INSTALL_DIR/.env"
  sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT}|" "$INSTALL_DIR/.env"
  sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|" "$INSTALL_DIR/.env"
  sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|" "$INSTALL_DIR/.env"
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" "$INSTALL_DIR/.env"
  sed -i "s|^AWS_ENDPOINT=.*|AWS_ENDPOINT=${MINIO_ENDPOINT}|" "$INSTALL_DIR/.env"
  sed -i "s|^AWS_ACCESS_KEY_ID=.*|AWS_ACCESS_KEY_ID=${MINIO_KEY}|" "$INSTALL_DIR/.env"
  sed -i "s|^AWS_SECRET_ACCESS_KEY=.*|AWS_SECRET_ACCESS_KEY=${MINIO_SECRET}|" "$INSTALL_DIR/.env"
  sed -i "s|^AWS_BUCKET=.*|AWS_BUCKET=${MINIO_BUCKET}|" "$INSTALL_DIR/.env"

  chown "$APP_USER:$APP_GROUP" "$INSTALL_DIR/.env"
}

start_infra() {
  log "Starting Docker services"
  run_as_app_user "docker compose up -d"
}

wait_for_services() {
  local mariadb_ready=0
  local minio_ready=0

  log "Waiting for MariaDB readiness"
  for _ in {1..60}; do
    if run_as_app_user "docker compose exec -T mariadb mariadb-admin ping -h 127.0.0.1 -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent" >/dev/null 2>&1; then
      mariadb_ready=1
      break
    fi
    sleep 2
  done

  log "Waiting for MinIO readiness"
  for _ in {1..60}; do
    if curl -fsS "${MINIO_ENDPOINT}/minio/health/live" >/dev/null 2>&1; then
      minio_ready=1
      break
    fi
    sleep 2
  done

  if [[ "$mariadb_ready" -ne 1 ]]; then
    echo "ERROR: MariaDB did not become ready in time." >&2
    exit 1
  fi

  if [[ "$minio_ready" -ne 1 ]]; then
    echo "ERROR: MinIO did not become ready in time." >&2
    exit 1
  fi
}

create_minio_bucket() {
  log "Creating MinIO bucket: ${MINIO_BUCKET}"
  mkdir -p /root/.mc
  docker run --rm --network host -v /root/.mc:/root/.mc minio/mc:latest alias set local "$MINIO_ENDPOINT" "$MINIO_KEY" "$MINIO_SECRET"
  docker run --rm --network host -v /root/.mc:/root/.mc minio/mc:latest mb -p "local/${MINIO_BUCKET}" || true
}

bootstrap_laravel() {
  log "Installing PHP dependencies"
  run_as_app_user "composer install --no-interaction --prefer-dist"

  log "Installing frontend dependencies"
  run_as_app_user "npm install"
  run_as_app_user "npm run build"

  log "Generating app key"
  run_as_app_user "php artisan key:generate --force"

  log "Running database migrations and seeders"
  if [[ "$DEV_MODE" == "1" ]]; then
    run_as_app_user "php artisan migrate:fresh --seed --force"
  else
    run_as_app_user "php artisan migrate --seed --force"
  fi

  log "Finalizing Laravel optimization"
  run_as_app_user "php artisan storage:link || true"
  run_as_app_user "php artisan optimize:clear"
  run_as_app_user "php artisan optimize"
}

write_systemd_units() {
  [[ "$ENABLE_SYSTEMD" == "1" ]] || return 0

  log "Writing optional systemd units (ENABLE_SYSTEMD=1)"

  cat > /etc/systemd/system/ironcore-serve.service <<EOF
[Unit]
Description=IronCore Laravel development server
After=network.target docker.service

[Service]
Type=simple
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${INSTALL_DIR}
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8000
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

  cat > /etc/systemd/system/ironcore-queue.service <<EOF
[Unit]
Description=IronCore queue worker
After=network.target docker.service

[Service]
Type=simple
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${INSTALL_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable --now ironcore-serve.service ironcore-queue.service
}

print_final_notes() {
  cat <<EOF

✅ IronCore installation complete.

URL: ${APP_URL}/admin/login
Default login: admin@ironcore.local / password

Troubleshooting:
- Tail Laravel log: tail -f ${INSTALL_DIR}/storage/logs/laravel.log
- Docker service logs: cd ${INSTALL_DIR} && docker compose logs -f mariadb minio
- Verify .env + key: grep -E '^APP_KEY=|^APP_URL=' ${INSTALL_DIR}/.env
- Re-run migrations: cd ${INSTALL_DIR} && sudo -u ${APP_USER} -H php artisan migrate --seed --force
EOF
}

main() {
  require_root
  ensure_linux_user
  install_base_packages
  install_docker
  install_php84
  install_composer
  install_node20
  ensure_install_dir
  prepare_env
  start_infra
  wait_for_services
  create_minio_bucket
  bootstrap_laravel
  write_systemd_units
  print_final_notes
}

main "$@"
