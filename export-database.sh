#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"
OUTPUT_FILE="$SCRIPT_DIR/database.sql"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Error: $ENV_FILE not found" >&2
    exit 1
fi

get_env() {
    grep -E "^${1}=" "$ENV_FILE" | head -n 1 | cut -d '=' -f 2- | tr -d '"'
}

DB_HOST="$(get_env DB_HOST)"
DB_PORT="$(get_env DB_PORT)"
DB_DATABASE="$(get_env DB_DATABASE)"
DB_USERNAME="$(get_env DB_USERNAME)"
DB_PASSWORD="$(get_env DB_PASSWORD)"

if [[ "$(get_env DB_CONNECTION)" != "mysql" ]]; then
    echo "Error: DB_CONNECTION must be 'mysql'" >&2
    exit 1
fi

if [[ -z "$DB_DATABASE" ]]; then
    echo "Error: DB_DATABASE is empty" >&2
    exit 1
fi

MYSQLDUMP=$(command -v mysqldump)
if [[ -z "$MYSQLDUMP" ]]; then
    echo "Error: mysqldump not found in PATH" >&2
    exit 1
fi

ARGS=(
    --host="$DB_HOST"
    --port="$DB_PORT"
    --user="$DB_USERNAME"
    --no-tablespaces
    --single-transaction
    --skip-comments
    --routines
)

if [[ -n "$DB_PASSWORD" ]]; then
    ARGS+=(--password="$DB_PASSWORD")
fi

"$MYSQLDUMP" "${ARGS[@]}" "$DB_DATABASE" > "$OUTPUT_FILE"

echo "Database exported to $OUTPUT_FILE"
