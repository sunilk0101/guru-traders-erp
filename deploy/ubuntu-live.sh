#!/usr/bin/env bash
# Deploy Guru Traders Export ERP on Ubuntu (Nginx + PHP-FPM + SQLite or MySQL).
# Usage (on the server as root):
#   curl -fsSL … | bash   OR   bash deploy/ubuntu-live.sh
# Optional env:
#   APP_DIR=/var/www/guru-traders-erp
#   APP_URL=http://82.29.167.99
#   GIT_REPO=https://github.com/NitinThilakLakshminathan/guru-traders-erp.git
#   GIT_BRANCH=main
#   DB_DRIVER=sqlite   # or mysql

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/guru-traders-erp}"
APP_URL="${APP_URL:-http://82.29.167.99}"
GIT_REPO="${GIT_REPO:-https://github.com/NitinThilakLakshminathan/guru-traders-erp.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
DB_DRIVER="${DB_DRIVER:-sqlite}"
PHP_VERSION="${PHP_VERSION:-8.3}"

export DEBIAN_FRONTEND=noninteractive

echo "==> Installing packages"
apt-get update -y
apt-get install -y \
  nginx git unzip curl ca-certificates \
  "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mbstring" \
  "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" \
  "php${PHP_VERSION}-gd" "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-intl" \
  "php${PHP_VERSION}-sqlite3" "php${PHP_VERSION}-mysql"

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Fetching application into ${APP_DIR}"
mkdir -p "$(dirname "$APP_DIR")"
if [[ -d "${APP_DIR}/.git" ]]; then
  git -C "$APP_DIR" fetch --all --prune
  git -C "$APP_DIR" checkout "$GIT_BRANCH"
  git -C "$APP_DIR" pull --ff-only origin "$GIT_BRANCH"
else
  rm -rf "$APP_DIR"
  git clone --branch "$GIT_BRANCH" "$GIT_REPO" "$APP_DIR"
fi

cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate --force
fi

# Keep APP_URL and a writable local DB for quick client demos.
php -r "
\$env = file_get_contents('.env');
\$env = preg_replace('/^APP_URL=.*/m', 'APP_URL=${APP_URL}', \$env);
\$env = preg_replace('/^APP_ENV=.*/m', 'APP_ENV=production', \$env);
\$env = preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=false', \$env);
if ('${DB_DRIVER}' === 'sqlite') {
  \$env = preg_replace('/^DB_CONNECTION=.*/m', 'DB_CONNECTION=sqlite', \$env);
}
file_put_contents('.env', \$env);
"

if [[ "$DB_DRIVER" == "sqlite" ]]; then
  mkdir -p database
  touch database/database.sqlite
  chown www-data:www-data database/database.sqlite
fi

# Bundled company logo for PDF letterheads
mkdir -p storage/app/public/company-profile
if [[ -f public/images/gt-logo.png ]]; then
  cp -f public/images/gt-logo.png storage/app/public/company-profile/gt-logo.png
fi

php artisan storage:link || true
php artisan migrate --force
php artisan db:seed --force || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache database
chmod -R ug+rwx storage bootstrap/cache

SITE=/etc/nginx/sites-available/guru-traders-erp
cat > "$SITE" <<NGINX
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 32M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sfn "$SITE" /etc/nginx/sites-enabled/guru-traders-erp
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php${PHP_VERSION}-fpm"
systemctl reload nginx

echo
echo "==> Live URL: ${APP_URL}"
echo "    Login (after seed): check SuperAdmin seeder / admin@gurutraders.com"
echo "Done."
