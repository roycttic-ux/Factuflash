#!/usr/bin/env bash
# Shared by backup, restore and release. Never source application .env as shell code.
set -Eeuo pipefail

ops_init() {
  umask 077
  PROJECT_DIR="$(cd "${FACTUFLASH_PROJECT_DIR:-$(dirname "${BASH_SOURCE[0]}")/..}" && pwd -P)"
  cd "$PROJECT_DIR"
  BACKUP_ROOT="${FACTUFLASH_BACKUP_DIR:-$HOME/factuflash-backups}"
  mkdir -p "$BACKUP_ROOT"
  BACKUP_ROOT="$(cd "$BACKUP_ROOT" && pwd -P)"
  if [[ "${FACTUFLASH_LOCK_HELD:-0}" != 1 ]]; then
    exec 9>"$PROJECT_DIR/.operations.lock"
    flock -n 9 || { echo 'Another deployment/backup/restore is running.' >&2; exit 1; }
    export FACTUFLASH_LOCK_HELD=1
  fi
  export FACTUFLASH_PROJECT_DIR="$PROJECT_DIR" FACTUFLASH_BACKUP_DIR="$BACKUP_ROOT"
}

ops_quiesce() {
  # Stop every application writer; leave Nginx and unrelated vhosts running.
  docker compose stop --timeout 60 api scheduler
}

ops_database_dump() {
  docker compose exec -T mysql sh -ec 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --triggers --events --hex-blob --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"'
}

ops_database_restore() {
  docker compose exec -T mysql sh -ec 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$MYSQL_DATABASE"'
}

ops_storage_export() {
  docker compose run --rm --no-deps --entrypoint tar api -C /var/www/html/storage -czf - .
}

ops_storage_import() {
  # This fixed container path is a Docker volume, never a computed host directory.
  docker compose run --rm -T --no-deps --entrypoint sh api -ec 'find /var/www/html/storage -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +; tar -xzf - -C /var/www/html/storage; chown -R www-data:www-data /var/www/html/storage'
}

ops_wait_database() {
  local attempt
  for attempt in $(seq 1 30); do
    if docker compose exec -T mysql sh -ec 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -Nse "SELECT 1" "$MYSQL_DATABASE"' >/dev/null 2>&1; then return 0; fi
    sleep 2
  done
  echo 'Database did not become ready.' >&2
  return 1
}
