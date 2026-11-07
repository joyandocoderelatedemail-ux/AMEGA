#!/usr/bin/env bash
#
# Run on the server after cPanel's "Deploy HEAD Commit", from the repository folder:
#
#   cd ~/repositories/amegatravelandtour
#   bash scripts/post-deploy.sh                 # back up, migrate, clear caches
#   bash scripts/post-deploy.sh --seed-cruise   # the same, and add the cruise packages and card (first release only)
#   bash scripts/post-deploy.sh --dry-run       # show what it would do, change nothing
#
# It never runs ServiceSeeder (that re-saves every core service and would overwrite admin edits).
# See DEPLOY.md for the whole checklist and how to roll back.

set -euo pipefail

DRY_RUN=0
SEED_CRUISE=0
BACKUP_ONLY=0
SKIP_BACKUP=0

for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --seed-cruise) SEED_CRUISE=1 ;;
        --backup-only) BACKUP_ONLY=1 ;;
        --skip-backup) SKIP_BACKUP=1 ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
        *) echo "Unknown option: $arg (try --help)" >&2; exit 2 ;;
    esac
done

PHP="${PHP:-php}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups}"
KEEP_BACKUPS="${KEEP_BACKUPS:-10}"

say() { printf '\n==> %s\n' "$*"; }
run() {
    if [ "$DRY_RUN" -eq 1 ]; then
        printf '   [dry run] %s\n' "$*"
    else
        "$@"
    fi
}

# --- sanity: we are in the app's folder and it has settings -------------------------------------
[ -f artisan ] || { echo "Run this from the folder that holds 'artisan' (the repository)." >&2; exit 1; }
[ -f .env ] || { echo "No .env here: is this the right folder?" >&2; exit 1; }

env_value() {
    # The last KEY=value in .env, without quotes or a Windows line ending.
    local value
    value="$(grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '\r')"
    value="${value%\"}"; value="${value#\"}"; value="${value%\'}"; value="${value#\'}"
    printf '%s' "$value"
}

# --- 1. back up the database before anything changes ---------------------------------------------
backup_database() {
    local connection database
    connection="$(env_value DB_CONNECTION)"
    database="$(env_value DB_DATABASE)"

    if [ "${connection:-mysql}" != "mysql" ] && [ "${connection:-mysql}" != "mariadb" ]; then
        echo "Database is '$connection', not MySQL: back it up by hand, then re-run with --skip-backup." >&2
        exit 1
    fi

    if ! command -v mysqldump >/dev/null 2>&1; then
        echo "mysqldump is not available here. Export the database from cPanel (phpMyAdmin > Export), then re-run with --skip-backup." >&2
        exit 1
    fi

    local stamp file
    stamp="$(date +%Y%m%d-%H%M%S)"
    file="$BACKUP_DIR/${database}-${stamp}.sql.gz"

    say "Backing up '$database' to $file"
    if [ "$DRY_RUN" -eq 1 ]; then
        printf '   [dry run] mysqldump ... | gzip > %s\n' "$file"
        return
    fi

    mkdir -p "$BACKUP_DIR"
    # The password goes in the environment, never on the command line where others could read it.
    # If the dump fails (or comes out tiny) nothing has been changed yet: remove the leftover and stop.
    if ! MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump \
        --host="$(env_value DB_HOST)" --port="${DB_PORT_OVERRIDE:-$(env_value DB_PORT)}" \
        --user="$(env_value DB_USERNAME)" \
        --single-transaction --routines --no-tablespaces \
        "$database" | gzip > "$file" || [ "$(wc -c < "$file")" -lt 1024 ]; then
        rm -f "$file"
        echo "The backup failed, so nothing was changed. Check the database settings in .env." >&2
        exit 1
    fi

    echo "   saved ($(du -h "$file" | cut -f1))"

    # Keep the newest few, remove the rest.
    ls -1t "$BACKUP_DIR"/"${database}"-*.sql.gz 2>/dev/null | tail -n +"$((KEEP_BACKUPS + 1))" | xargs -r rm -f --
}

if [ "$SKIP_BACKUP" -eq 1 ]; then
    say "Skipping the backup (--skip-backup). Make sure you have your own."
else
    backup_database
fi

[ "$BACKUP_ONLY" -eq 1 ] && { say "Backup done (--backup-only)."; exit 0; }

# --- 2. the database changes ---------------------------------------------------------------------
say "Pending migrations"
run "$PHP" artisan migrate:status --pending || true

say "Running migrations"
run "$PHP" artisan migrate --force

# --- 3. first release only: the cruise content (both seeders only add what is missing) -----------
if [ "$SEED_CRUISE" -eq 1 ]; then
    say "Adding the cruise packages and the Cruise Services card (existing ones are left alone)"
    run "$PHP" artisan db:seed --class=CruisePackageSeeder --force
    run "$PHP" artisan db:seed --class=CruiseServiceSeeder --force
fi

# --- 4. caches -----------------------------------------------------------------------------------
say "Clearing caches"
run "$PHP" artisan optimize:clear

say "Done. Now open the site and go through the smoke checks in DEPLOY.md."
