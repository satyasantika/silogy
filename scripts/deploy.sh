#!/usr/bin/env bash
#
# SILOGY — skrip rilis produksi (dijalankan DI SERVER).
#
# Job `deploy` di .github/workflows/ci.yml men-SSH ke server, melakukan
# `git reset --hard origin/main` di ~/docker-apps/silogy, lalu menjalankan skrip
# ini. Jadi prosedur rilis ikut ter-versioning bersama kode — tidak ada lagi
# salinan manual di ~/docker-apps/deploy.sh yang bisa menyimpang dari repo.
#
# Pemakaian:
#   bash scripts/deploy.sh                 # rilis commit yang sedang ter-checkout
#   bash scripts/deploy.sh --rollback SHA  # balik ke image lama (lihat daftar tag di akhir output)
#   bash scripts/deploy.sh --list          # tampilkan tag image yang tersedia
#
# Prasyarat: Docker + Compose v2, repo ter-clone di direktori ini, `.env` produksi terisi.
# Idempoten & aman diulang. Keluar non-zero bila health check gagal.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

COMPOSE_FILE="docker-compose.prod.yml"
COMPOSE=(docker compose -f "$COMPOSE_FILE")
IMAGE="silogy-app"
BACKUP_DIR="${SILOGY_BACKUP_DIR:-$HOME/docker-apps/backups/silogy}"
BACKUP_RETENTION_DAYS="${SILOGY_BACKUP_RETENTION_DAYS:-14}"
KEEP_IMAGE_TAGS="${SILOGY_KEEP_IMAGE_TAGS:-5}"
HEALTH_ATTEMPTS=24        # 24 x 5s = 2 menit (limiter 'health' = 60 req/menit, aman)
HEALTH_INTERVAL=5

log() { printf '\n==> %s\n' "$*"; }

# URL health diambil dari port mapping container yang sebenarnya (mis.
# 172.17.0.1:8081), BUKAN dari tebakan 127.0.0.1:8080 — di server itu port
# adminer, yang membalas 200 dan membuat deploy rusak terlihat "sehat".
health_url() {
  local mapped port
  mapped="$("${COMPOSE[@]}" port nginx 80 2>/dev/null || true)"

  if [[ -z "$mapped" ]]; then
    port="$(sed -n 's/^APP_PORT=\([0-9]\{1,5\}\).*/\1/p' .env 2>/dev/null | tail -n1)"
    mapped="172.17.0.1:${port:-8081}"
  fi

  echo "http://${mapped}/health"
}

wait_health() {
  local url attempt
  url="$(health_url)"
  log "health check → $url"

  for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
    if curl -fsS --max-time 10 "$url" >/dev/null 2>&1; then
      curl -fsS --max-time 10 "$url" || true
      printf '\nOK: aplikasi sehat (percobaan %d).\n' "$attempt"
      return 0
    fi
    sleep "$HEALTH_INTERVAL"
  done

  echo "GAGAL: /health tidak merespons setelah $((HEALTH_ATTEMPTS * HEALTH_INTERVAL)) detik." >&2
  echo "--- 50 baris terakhir log app ---" >&2
  "${COMPOSE[@]}" logs --tail=50 app >&2 || true
  return 1
}

# Dump DB sebelum container app baru start, karena entrypoint langsung
# menjalankan `migrate --force`. Kredensial dibaca dari environment container
# mysql, jadi skrip ini tidak perlu mem-parsing .env.
backup_db() {
  if [[ -z "$("${COMPOSE[@]}" ps -q mysql 2>/dev/null)" ]]; then
    log "lewati backup: container mysql belum berjalan (deploy pertama?)"
    return 0
  fi

  local file
  mkdir -p "$BACKUP_DIR"
  file="$BACKUP_DIR/pre-deploy_$(date +%Y%m%d_%H%M%S).sql.gz"

  log "backup DB pra-migrasi → $file"
  "${COMPOSE[@]}" exec -T mysql sh -c \
    'exec mysqldump --single-transaction --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    | gzip > "$file"

  find "$BACKUP_DIR" -name 'pre-deploy_*.sql.gz' -type f -mtime +"$BACKUP_RETENTION_DAYS" -delete 2>/dev/null || true
  ls -lh "$file"
}

list_tags() {
  docker image ls "$IMAGE" --format '{{.Tag}}\t{{.CreatedSince}}\t{{.Size}}' | sort
}

# Rollback = arahkan ulang tag :prod ke image lama lalu recreate. Tidak ada
# build ulang, jadi hitungan detik — bukan menit seperti build di 2 vCPU.
rollback() {
  local target="${1:?Usage: deploy.sh --rollback <sha>}"

  if ! docker image inspect "$IMAGE:$target" >/dev/null 2>&1; then
    echo "GAGAL: image $IMAGE:$target tidak ada di server. Tag tersedia:" >&2
    list_tags >&2
    exit 1
  fi

  log "rollback ke $IMAGE:$target"
  docker tag "$IMAGE:$target" "$IMAGE:prod"
  "${COMPOSE[@]}" up -d --no-build --force-recreate app queue
  wait_health

  cat <<EOF

CATATAN: rollback hanya mengembalikan KODE, bukan skema database. Bila rilis yang
gagal sempat menjalankan migrasi, restore dump terakhir dari $BACKUP_DIR:

  gunzip -c $BACKUP_DIR/pre-deploy_<stamp>.sql.gz \\
    | ${COMPOSE[*]} exec -T mysql sh -c 'exec mysql -uroot -p"\$MYSQL_ROOT_PASSWORD" "\$MYSQL_DATABASE"'

Working tree git masih di commit baru — sinkronkan bila rollback ini permanen.
EOF
}

# Sisakan beberapa tag terakhir supaya rollback tetap mungkin; sisanya dibuang
# agar disk tidak penuh (tiap image ~415 MB).
prune_images() {
  local old
  old="$(docker image ls "$IMAGE" --format '{{.Tag}} {{.CreatedAt}}' \
    | grep -v '^prod ' \
    | sort -k2 -r \
    | tail -n +"$((KEEP_IMAGE_TAGS + 1))" \
    | awk '{print $1}')"

  for tag in $old; do
    docker image rm "$IMAGE:$tag" >/dev/null 2>&1 || true
  done

  docker image prune -f >/dev/null 2>&1 || true
}

main() {
  case "${1:-}" in
    --rollback) rollback "${2:-}"; exit 0 ;;
    --list)     list_tags; exit 0 ;;
    '')         ;;
    *)          echo "Argumen tidak dikenal: $1" >&2; exit 2 ;;
  esac

  local sha
  sha="$(git rev-parse --short HEAD)"
  log "rilis commit $sha — $(git log -1 --pretty=%s)"

  backup_db

  log "build image produksi (aset Vite + vendor di-bake)"
  "${COMPOSE[@]}" build

  # Tag per-commit supaya rollback tidak perlu build ulang dari commit lama.
  docker tag "$IMAGE:prod" "$IMAGE:$sha"

  log "up -d (entrypoint menjalankan migrate + cache)"
  "${COMPOSE[@]}" up -d --remove-orphans

  wait_health

  prune_images

  log "status"
  "${COMPOSE[@]}" ps

  cat <<EOF

Selesai. Rilis $sha aktif.
Rollback cepat:  bash scripts/deploy.sh --rollback <sha>
Tag tersedia:
$(list_tags)
EOF
}

main "$@"
