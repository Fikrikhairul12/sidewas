#!/usr/bin/env bash

set -Eeuo pipefail

readonly REPOSITORY_PATH="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly APP_PATH="$REPOSITORY_PATH"
readonly PUBLIC_PATH="/home/sidewasi/public_html"
readonly HOME_ENV_PATH="/home/sidewasi/.env"
readonly LEGACY_APP_PATH="/home/sidewasi/sidewas-laravel"
readonly PUBLIC_INDEX_TEMPLATE="$REPOSITORY_PATH/resources/deployment/public-index.php"
readonly PUBLIC_STORAGE_BACKUP="/home/sidewasi/storage-backup-before-repository"
readonly STORAGE_MIGRATION_MARKER="$APP_PATH/storage/app/.repository-migration-complete"

log() {
    printf '[sidewas-deploy] %s\n' "$1"
}

find_executable() {
    local candidate

    for candidate in "$@"; do
        if [[ "$candidate" == */* ]]; then
            if [[ -x "$candidate" ]]; then
                printf '%s\n' "$candidate"
                return 0
            fi
        elif command -v "$candidate" >/dev/null 2>&1; then
            command -v "$candidate"
            return 0
        fi
    done

    return 1
}

copy_missing_tree() {
    local source_path="$1"
    local destination_path="$2"

    /bin/mkdir -p "$destination_path"
    /bin/cp -a -n -- "$source_path"/. "$destination_path"/
}

copy_public_tree() {
    local source_entry
    local entry_name

    shopt -s dotglob nullglob

    for source_entry in "$REPOSITORY_PATH/public"/*; do
        entry_name="${source_entry##*/}"

        case "$entry_name" in
            .htaccess|index.php|storage|.well-known|cgi-bin)
                continue
                ;;
        esac

        /bin/cp -a -- "$source_entry" "$PUBLIC_PATH"/
    done

    shopt -u dotglob nullglob
}

PHP_BIN="$(find_executable \
    /usr/local/bin/ea-php82 \
    /opt/cpanel/ea-php82/root/usr/bin/php \
    php)" || {
    log 'PHP 8.2 tidak ditemukan.'
    exit 1
}

PHP_VERSION_ID="$("$PHP_BIN" -r 'echo PHP_VERSION_ID;')"

if [[ "$PHP_VERSION_ID" -lt 80200 ]]; then
    log "PHP 8.2 atau lebih baru dibutuhkan; versi aktif adalah $("$PHP_BIN" -r 'echo PHP_VERSION;')."
    exit 1
fi

if [[ -f /opt/cpanel/composer/bin/composer ]]; then
    COMPOSER_COMMAND=("$PHP_BIN" /opt/cpanel/composer/bin/composer)
elif command -v composer >/dev/null 2>&1; then
    COMPOSER_COMMAND=("$(command -v composer)")
else
    log 'Composer tidak ditemukan.'
    exit 1
fi

if [[ ! -f "$REPOSITORY_PATH/public/build/manifest.json" ]]; then
    log 'public/build/manifest.json tidak ditemukan. Jalankan npm run build sebelum push.'
    exit 1
fi

if [[ ! -f "$PUBLIC_INDEX_TEMPLATE" ]]; then
    log 'Template public_html/index.php tidak ditemukan.'
    exit 1
fi

if [[ ! -f "$APP_PATH/.env" ]]; then
    if [[ -f "$HOME_ENV_PATH" ]]; then
        log 'Menyalin .env production dari home account.'
        /bin/cp -p "$HOME_ENV_PATH" "$APP_PATH/.env"
        /bin/chmod 600 "$APP_PATH/.env"
    elif [[ -f "$LEGACY_APP_PATH/.env" ]]; then
        log 'Menyalin .env production dari application root lama.'
        /bin/cp -p "$LEGACY_APP_PATH/.env" "$APP_PATH/.env"
        /bin/chmod 600 "$APP_PATH/.env"
    else
        log ".env tidak ditemukan di $APP_PATH, $HOME_ENV_PATH, maupun $LEGACY_APP_PATH."
        exit 1
    fi
fi

/bin/mkdir -p \
    "$PUBLIC_PATH" \
    "$APP_PATH/bootstrap/cache" \
    "$APP_PATH/storage/app/private" \
    "$APP_PATH/storage/app/public" \
    "$APP_PATH/storage/framework/cache/data" \
    "$APP_PATH/storage/framework/sessions" \
    "$APP_PATH/storage/framework/views" \
    "$APP_PATH/storage/logs"

if [[ ! -f "$STORAGE_MIGRATION_MARKER" ]]; then
    if [[ -d "$LEGACY_APP_PATH/storage/app" ]]; then
        log 'Menyalin data storage dari application root lama.'
        copy_missing_tree \
            "$LEGACY_APP_PATH/storage/app" "$APP_PATH/storage/app"
    fi

    /usr/bin/touch "$STORAGE_MIGRATION_MARKER"
fi

if [[ -d "$PUBLIC_PATH/storage" && ! -L "$PUBLIC_PATH/storage" ]]; then
    log 'Memindahkan public_html/storage lama ke penyimpanan Laravel.'
    copy_missing_tree \
        "$PUBLIC_PATH/storage" "$APP_PATH/storage/app/public"

    if [[ -e "$PUBLIC_STORAGE_BACKUP" || -L "$PUBLIC_STORAGE_BACKUP" ]]; then
        log "$PUBLIC_STORAGE_BACKUP sudah ada; storage lama tidak dapat diamankan."
        exit 1
    fi

    /bin/mv "$PUBLIC_PATH/storage" "$PUBLIC_STORAGE_BACKUP"
    /bin/ln -s "$APP_PATH/storage/app/public" "$PUBLIC_PATH/storage"
fi

/bin/chmod -R ug+rwX "$APP_PATH/storage" "$APP_PATH/bootstrap/cache"

log 'Memasang dependency PHP langsung di application root.'
(
    cd "$REPOSITORY_PATH"
    "${COMPOSER_COMMAND[@]}" install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --optimize-autoloader \
        --prefer-dist
)

maintenance_enabled=false

finish_deployment() {
    local exit_code=$?

    trap - EXIT

    if [[ "$maintenance_enabled" == true ]]; then
        "$PHP_BIN" "$APP_PATH/artisan" up || true
    fi

    if [[ $exit_code -eq 0 ]]; then
        log 'Deployment selesai.'
    else
        log "Deployment gagal dengan exit code $exit_code."
    fi

    exit "$exit_code"
}

trap finish_deployment EXIT

if [[ -f "$APP_PATH/artisan" && -f "$APP_PATH/vendor/autoload.php" ]]; then
    if "$PHP_BIN" "$APP_PATH/artisan" down --retry=15; then
        maintenance_enabled=true
    fi
fi

log 'Menjalankan migrasi dan membangun cache production.'
(
    cd "$APP_PATH"
    "${COMPOSER_COMMAND[@]}" dump-autoload \
        --no-dev \
        --no-interaction \
        --optimize
    "$PHP_BIN" artisan optimize:clear
    "$PHP_BIN" artisan migrate --force
    "$PHP_BIN" artisan optimize
)

log 'Menyinkronkan public Laravel ke document root.'
copy_public_tree

/bin/cp "$PUBLIC_INDEX_TEMPLATE" "$PUBLIC_PATH/index.php"

if [[ ! -f "$PUBLIC_PATH/.htaccess" ]]; then
    /bin/cp "$REPOSITORY_PATH/public/.htaccess" "$PUBLIC_PATH/.htaccess"
fi

if [[ ! -e "$APP_PATH/public/storage" ]]; then
    /bin/ln -s "$APP_PATH/storage/app/public" "$APP_PATH/public/storage"
fi

if [[ -L "$PUBLIC_PATH/storage" ]]; then
    /bin/ln -sfn "$APP_PATH/storage/app/public" "$PUBLIC_PATH/storage"
elif [[ ! -e "$PUBLIC_PATH/storage" ]]; then
    /bin/ln -s "$APP_PATH/storage/app/public" "$PUBLIC_PATH/storage"
else
    log 'public_html/storage tidak dapat diarahkan ke application root baru.'
    exit 1
fi

exit 0
