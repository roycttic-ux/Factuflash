#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

main() {
  ops_init
  local label="${1:-backup-$(date -u +%Y%m%d-%H%M%S)}" keep_stopped="${2:-}"
  [[ "$label" =~ ^(backup|release|before-restore)-[0-9-]+$ ]] || { echo 'Invalid backup label.' >&2; return 2; }
  [[ -z "$keep_stopped" || "$keep_stopped" == --keep-stopped ]] || return 2
  local destination="$BACKUP_ROOT/$label" working="$BACKUP_ROOT/.$label.incomplete" running
  [[ ! -e "$destination" && ! -e "$working" ]] || { echo 'Backup already exists.' >&2; return 2; }
  running="$(docker compose ps --status running --services)"
  mkdir -m 700 "$working"
  # EXIT covers explicit exits as well as command errors; failed snapshots stay marked incomplete.
  BACKUP_RESTART_SERVICES=()
  while IFS= read -r service; do
    [[ "$service" == api || "$service" == scheduler ]] && BACKUP_RESTART_SERVICES+=("$service")
  done <<< "$running"
  trap 'code=$?; if [[ ${#BACKUP_RESTART_SERVICES[@]} -gt 0 ]]; then docker compose start "${BACKUP_RESTART_SERVICES[@]}" || code=1; fi; exit "$code"' EXIT
  ops_quiesce
  ops_database_dump | gzip > "$working/database.sql.gz"
  ops_storage_export > "$working/storage.tar.gz"
  # Include the Laravel key, database settings and only this application's vhost/certificate.
  local config_files=(.env docker-compose.yml docker/nginx/conf.d/default.conf)
  for file in docker/nginx/conf.d/selfsigned.crt docker/nginx/conf.d/selfsigned.key; do
    [[ ! -f "$file" ]] || config_files+=("$file")
  done
  tar -czf "$working/config.tar.gz" "${config_files[@]}"
  if [[ -n "${FACTUFLASH_SOURCE_SNAPSHOT:-}" ]]; then
    cp "$FACTUFLASH_SOURCE_SNAPSHOT" "$working/source.tar.gz"
  else
    bash ops/package.sh "$working/source.tar.gz"
  fi
  docker compose exec -T mysql sh -ec 'printf "%s\n" "$MYSQL_DATABASE"' > "$working/database-name.txt"
  docker inspect -f '{{.Image}}' factuflash_api > "$working/api-image.txt"
  docker inspect -f '{{.Image}}' factuflash_nginx > "$working/nginx-image.txt"
  printf '1\n' > "$working/format-version.txt"
  (cd "$working" && sha256sum database.sql.gz storage.tar.gz config.tar.gz source.tar.gz database-name.txt api-image.txt nginx-image.txt format-version.txt > SHA256SUMS)
  python3 ops/verify_backup.py "$working"
  mv "$working" "$destination"
  # Replicate only a verified snapshot. Configure SSH keys/known_hosts and an encrypted remote volume.
  if [[ -n "${FACTUFLASH_BACKUP_REMOTE:-}" ]]; then
    rsync -a --chmod=D700,F600 -- "$destination" "${FACTUFLASH_BACKUP_REMOTE%/}/"
  fi
  if [[ "$keep_stopped" == --keep-stopped ]]; then BACKUP_RESTART_SERVICES=(); fi
  echo "BACKUP_OK $destination"
}
main "$@"
