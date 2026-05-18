#!/usr/bin/env bash
# SILOGY — backup harian database + storage publik (System Architecture v6 §5.1)
set -euo pipefail

if [[ -f /etc/silogy/backup.env ]]; then
  # shellcheck source=/dev/null
  source /etc/silogy/backup.env
fi

DATE="$(date +%Y%m%d_%H%M)"
BACKUP_DIR="${SILOGY_BACKUP_DIR:-/var/backups/silogy}"
APP_ROOT="${SILOGY_APP_ROOT:-/var/www/silogy}"
DB_NAME="${SILOGY_DB_NAME:-silogy_prod}"
DB_HOST="${SILOGY_DB_HOST:-127.0.0.1}"
DB_PORT="${SILOGY_DB_PORT:-3306}"
DB_USER="${SILOGY_DB_USER:?SILOGY_DB_USER wajib di-set}"
DB_PASS="${SILOGY_DB_PASS:?SILOGY_DB_PASS wajib di-set}"
BACKUP_KEY="${SILOGY_BACKUP_KEY:?SILOGY_BACKUP_KEY wajib di-set}"
RETENTION_DAYS="${SILOGY_BACKUP_RETENTION_DAYS:-30}"

mkdir -p "${BACKUP_DIR}"

echo "[${DATE}] Memulai backup SILOGY → ${BACKUP_DIR}"

mysqldump \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --single-transaction \
  --routines \
  --triggers \
  -u "${DB_USER}" \
  -p"${DB_PASS}" \
  "${DB_NAME}" \
  | gzip \
  | openssl enc -aes-256-cbc -salt -pbkdf2 -pass "pass:${BACKUP_KEY}" \
  > "${BACKUP_DIR}/db_${DATE}.sql.gz.enc"

if [[ -d "${APP_ROOT}/storage/app/public" ]]; then
  tar -czf "${BACKUP_DIR}/storage_${DATE}.tar.gz" \
    -C "${APP_ROOT}" storage/app/public
else
  echo "[${DATE}] Peringatan: ${APP_ROOT}/storage/app/public tidak ditemukan, lewati tar storage."
fi

find "${BACKUP_DIR}" -type f -mtime +"${RETENTION_DAYS}" -delete

echo "[${DATE}] Backup selesai."
