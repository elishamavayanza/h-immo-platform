#!/usr/bin/env bash
set -Eeuo pipefail

: "${BACKUP_DB_NAME:?Définir BACKUP_DB_NAME}"
: "${BACKUP_DB_USER:?Définir BACKUP_DB_USER}"
: "${BACKUP_DB_PASSWORD:?Définir BACKUP_DB_PASSWORD}"
: "${BACKUP_MEDIA_DIR:?Définir BACKUP_MEDIA_DIR (ex. public/uploads)}"

backup_db_host="${BACKUP_DB_HOST:-127.0.0.1}"
backup_db_port="${BACKUP_DB_PORT:-3306}"
backup_root="${BACKUP_ROOT:-var/backups}"
backup_stamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_final_dir="${backup_root}/${backup_stamp}"
backup_work_dir="${backup_root}/.${backup_stamp}.partial"
backup_client_file="${backup_work_dir}/mariadb-client.cnf"

for option_value in "$backup_db_host" "$backup_db_port" "$BACKUP_DB_USER" "$BACKUP_DB_PASSWORD"; do
    if [[ "$option_value" == *$'\n'* || "$option_value" == *$'\r'* ]]; then
        echo "Les paramètres MariaDB ne peuvent pas contenir de retour à la ligne." >&2
        exit 1
    fi
done

escape_option_value() {
    local escaped="${1//\\/\\\\}"
    escaped="${escaped//\"/\\\"}"
    printf '"%s"' "$escaped"
}

if [[ ! -d "$BACKUP_MEDIA_DIR" ]]; then
    echo "Répertoire média absent : $BACKUP_MEDIA_DIR" >&2
    exit 1
fi
if [[ -e "$backup_final_dir" || -e "$backup_work_dir" ]]; then
    echo "Une sauvegarde porte déjà l'horodatage $backup_stamp." >&2
    exit 1
fi

umask 077
mkdir -p "$backup_root"
mkdir "$backup_work_dir"
trap 'rm -rf "$backup_work_dir"' ERR INT TERM

{
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "$(escape_option_value "$backup_db_host")" \
        "$backup_db_port" \
        "$(escape_option_value "$BACKUP_DB_USER")" \
        "$(escape_option_value "$BACKUP_DB_PASSWORD")"
} > "$backup_client_file"
chmod 600 "$backup_client_file"

mariadb-dump \
    --defaults-file="$backup_client_file" \
    --single-transaction \
    --routines \
    --events \
    --triggers \
    --hex-blob \
    "$BACKUP_DB_NAME" \
    | gzip -9 > "${backup_work_dir}/database.sql.gz"

tar -C "$BACKUP_MEDIA_DIR" -czf "${backup_work_dir}/uploads.tar.gz" .
rm -f "$backup_client_file"
{
    printf 'created_utc=%s\n' "$backup_stamp"
    printf 'database=%s\n' "$BACKUP_DB_NAME"
    printf 'media_source=%s\n' "$BACKUP_MEDIA_DIR"
    printf 'git_commit=%s\n' "$(git rev-parse HEAD 2>/dev/null || printf 'unknown')"
} > "${backup_work_dir}/metadata.txt"
(cd "$backup_work_dir" && sha256sum database.sql.gz uploads.tar.gz metadata.txt > SHA256SUMS)
mv "$backup_work_dir" "$backup_final_dir"
trap - ERR INT TERM
chmod -R go-rwx "$backup_final_dir"
printf 'Sauvegarde créée : %s\n' "$backup_final_dir"
printf 'Copiez-la hors de cette machine et testez périodiquement sa restauration.\n'
