#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    echo "Usage : $0 <répertoire-sauvegarde> --confirm"
    echo "Définir RESTORE_DB_NAME, RESTORE_DB_USER, RESTORE_DB_PASSWORD et RESTORE_MEDIA_DIR."
    echo "RESTORE_REMOVE_DEFINERS=1 attribue les objets SQL au compte de restauration."
}

if [[ $# -ne 2 || "$2" != "--confirm" ]]; then
    usage >&2
    exit 2
fi
: "${RESTORE_DB_NAME:?Définir le nom de la base cible RESTORE_DB_NAME}"
: "${RESTORE_DB_USER:?Définir RESTORE_DB_USER}"
: "${RESTORE_DB_PASSWORD:?Définir RESTORE_DB_PASSWORD}"
: "${RESTORE_MEDIA_DIR:?Définir le répertoire média cible RESTORE_MEDIA_DIR}"

restore_source_dir="$(realpath "$1")"
restore_db_host="${RESTORE_DB_HOST:-127.0.0.1}"
restore_db_port="${RESTORE_DB_PORT:-3306}"
restore_work_dir="$(mktemp -d)"
restore_client_file="$(mktemp)"
trap 'rm -rf "$restore_work_dir" "$restore_client_file"' EXIT

for option_value in "$restore_db_host" "$restore_db_port" "$RESTORE_DB_USER" "$RESTORE_DB_PASSWORD"; do
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
{
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "$(escape_option_value "$restore_db_host")" \
        "$restore_db_port" \
        "$(escape_option_value "$RESTORE_DB_USER")" \
        "$(escape_option_value "$RESTORE_DB_PASSWORD")"
} > "$restore_client_file"
chmod 600 "$restore_client_file"

if [[ ! -f "${restore_source_dir}/SHA256SUMS" ]]; then
    echo "Manifeste de contrôle absent dans $restore_source_dir." >&2
    exit 1
fi
(cd "$restore_source_dir" && sha256sum --check SHA256SUMS)

gzip -t "${restore_source_dir}/database.sql.gz"
while IFS= read -r archive_entry; do
    if [[ "$archive_entry" == /* || "$archive_entry" == ".." || "$archive_entry" == ../* || "$archive_entry" == */../* || "$archive_entry" == */.. ]]; then
        echo "Chemin interdit dans l'archive média : $archive_entry" >&2
        exit 1
    fi
done < <(tar -tzf "${restore_source_dir}/uploads.tar.gz")
if tar -tvzf "${restore_source_dir}/uploads.tar.gz" | awk 'substr($1, 1, 1) == "l" || substr($1, 1, 1) == "h" { found = 1 } END { exit !found }'; then
    echo "Les liens symboliques et matériels sont refusés dans l'archive média." >&2
    exit 1
fi
tar --no-same-owner --no-same-permissions -xzf "${restore_source_dir}/uploads.tar.gz" -C "$restore_work_dir"
if [[ -e "$RESTORE_MEDIA_DIR" ]] && [[ -n "$(find "$RESTORE_MEDIA_DIR" -mindepth 1 -maxdepth 1 -print -quit 2>/dev/null)" ]]; then
    echo "Le répertoire média cible doit être absent ou vide : $RESTORE_MEDIA_DIR" >&2
    exit 1
fi

mariadb \
    --defaults-file="$restore_client_file" \
    --database="$RESTORE_DB_NAME" \
    --batch --skip-column-names \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()" > "${restore_work_dir}/table-count.txt"
restore_table_count="$(cat "${restore_work_dir}/table-count.txt")"
if [[ "$restore_table_count" != "0" ]]; then
    echo "La base cible $RESTORE_DB_NAME contient déjà $restore_table_count table(s). Restaurez dans une base vide." >&2
    exit 1
fi
rm -f "${restore_work_dir}/table-count.txt"

echo "Restauration confirmée vers la base vide '$RESTORE_DB_NAME' et les médias '$RESTORE_MEDIA_DIR'."
if [[ "${RESTORE_REMOVE_DEFINERS:-0}" == "1" ]]; then
    # Un compte de restauration limité à sa base ne peut pas définir un autre
    # propriétaire SQL sans SET USER. Sans la clause, MariaDB prend le compte
    # courant comme définisseur, ce qui rend la sauvegarde portable.
    gzip -dc "${restore_source_dir}/database.sql.gz" \
        | sed -E 's#(/\*![0-9]+[[:space:]]+)DEFINER=`(``|[^`])*`@`(``|[^`])*`[[:space:]]*#\1#g' \
        | mariadb --defaults-file="$restore_client_file" --database="$RESTORE_DB_NAME"
else
    gzip -dc "${restore_source_dir}/database.sql.gz" | mariadb \
        --defaults-file="$restore_client_file" \
        --database="$RESTORE_DB_NAME"
fi

mkdir -p "$(dirname "$RESTORE_MEDIA_DIR")"
if [[ -d "$RESTORE_MEDIA_DIR" ]]; then
    rmdir "$RESTORE_MEDIA_DIR"
fi
mv "$restore_work_dir" "$RESTORE_MEDIA_DIR"
rm -f "$restore_client_file"
trap - EXIT
rm -rf "$restore_work_dir"
printf 'Restauration terminée. Vérifiez les migrations, les médias et les parcours de connexion avant de remettre le service en ligne.\n'
