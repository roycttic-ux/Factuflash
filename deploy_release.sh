#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
cd /home/ubuntu/sistemafacturacion
archive="${1:?release archive required}"
[[ "$archive" =~ ^release-[0-9-]+\.tar\.gz$ ]] || exit 2
release="${archive%.tar.gz}"
backup="$HOME/factuflash-backups/$release"
mkdir -p "$backup"
exec 9>"$HOME/factuflash-deploy.lock"
flock -n 9 || { echo DEPLOY_BUSY; exit 1; }
echo "Backing up $release"
cp docker-compose.yml "$backup/compose.yml"
cp docker/nginx/conf.d/default.conf "$backup/default.conf"
tar --exclude='api/vendor' --exclude='api/storage' --exclude='frontend/node_modules' --exclude='frontend/dist' \
  -czf "$backup/source.tar.gz" api frontend docker-compose.yml Dockerfile.nginx docker/nginx/conf.d/default.conf
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --no-tablespaces "$MYSQL_DATABASE"' | gzip > "$backup/database.sql.gz"
test -s "$backup/database.sql.gz"
old_api="$(docker inspect -f '{{.Image}}' factuflash_api)"
old_nginx="$(docker inspect -f '{{.Image}}' factuflash_nginx)"
docker image tag "$old_api" "factuflash-rollback-api:$release"
docker image tag "$old_nginx" "factuflash-rollback-nginx:$release"
switched=0
rollback() {
  code=$?
  trap - ERR
  echo "DEPLOY_FAILED code=$code backup=$backup"
  cp "$backup/compose.yml" docker-compose.yml
  cp "$backup/default.conf" docker/nginx/conf.d/default.conf
  docker image tag "$old_api" sistemafacturacion-api
  docker image tag "$old_nginx" sistemafacturacion-nginx
  if [[ "$switched" == 1 ]]; then
    docker compose up -d --no-build --no-deps api nginx || true
  fi
  exit "$code"
}
trap rollback ERR
# Never replace .env, persisted storage, SSL material or other sites' vhosts.
umask 022
tar -xzf "$archive"
docker compose config -q
echo BUILDING_IMAGES
docker compose build api nginx
docker compose run --rm --no-deps --entrypoint sh --user www-data api -c 'test -r public/index.php && test -x app && test -r vendor/autoload.php'
docker compose run --rm --no-deps --entrypoint sh --user nginx nginx -c 'test -r /var/www/html/landing/index.html && test -r /var/www/html/app/index.html'
echo VALIDATING_NGINX
docker compose run --rm --no-deps nginx nginx -t
echo MIGRATING
docker compose run --rm --no-deps --entrypoint php api artisan migrate --force
switched=1
docker compose up -d --no-build --no-deps api nginx scheduler
for attempt in $(seq 1 45); do
  code="$(curl -sk --connect-timeout 3 --max-time 5 -H 'Accept: application/json' -o /dev/null -w '%{http_code}' --resolve factuflash.com.ar:443:127.0.0.1 https://factuflash.com.ar/api/v1/subscription/plans || true)"
  echo "Health check attempt $attempt/45: code=$code"
  [[ "$code" == 401 ]] && break
  sleep 2
done
[[ "$code" == 401 ]] || { echo "Health check failed after 45 attempts with code=$code"; exit 1; }
curl -fsSk --resolve factuflash.com.ar:443:127.0.0.1 https://factuflash.com.ar/ >/dev/null
curl -fsSk --resolve factuflash.com.ar:443:127.0.0.1 https://factuflash.com.ar/app/ >/dev/null
docker compose exec -T nginx nginx -t
docker compose exec -T api php artisan migrate:status
docker compose exec -T api php artisan schedule:list
echo "DEPLOY_OK backup=$backup"
