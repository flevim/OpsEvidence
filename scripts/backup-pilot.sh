#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PROJECT_DIR=$(dirname "$SCRIPT_DIR")
BACKUP_DIR=${BACKUP_DIR:-"$PROJECT_DIR/backups"}
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
OUTPUT="$BACKUP_DIR/opsevidence-$STAMP.sql.gz"

mkdir -p "$BACKUP_DIR"
umask 077

cd "$PROJECT_DIR"

docker compose \
    --env-file .env.pilot \
    -f docker-compose.pilot.yml \
    exec -T postgres \
    sh -c 'exec pg_dump --clean --if-exists --no-owner --no-privileges -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip -9 > "$OUTPUT"

test -s "$OUTPUT"
echo "Backup creado: $OUTPUT"
