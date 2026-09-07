#!/usr/bin/env bash

set -Eeuo pipefail

readonly REPOSITORY_PATH="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
readonly APP_PATH="$REPOSITORY_PATH"
readonly PUBLIC_PATH="/home/sidewasi/public_html"
readonly PUBLIC_INDEX_TEMPLATE="$REPOSITORY_PATH/resources/deployment/public-index.php"

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

RSYNC_BIN="$(find_executable /usr/bin/rsync rsync)" || {
    log 'rsync tidak ditemukan.'
    exit 1
}

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
    log ".env production tidak ditemukan di $APP_PATH/.env"
    exit 1
fi

if [[ -e "$PUBLIC_PATH/storage" && ! -L "$PUBLIC_PATH/storage" ]]; then
    log 'public_html/storage masih berupa folder biasa. Migrasikan isinya lalu ubah menjadi symlink sebelum deploy.'
    exit 1
fi

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

/bin/mkdir -p "$PUBLIC_PATH"

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

log 'Menyiapkan direktori runtime Laravel.'
/bin/mkdir -p \
    "$APP_PATH/storage/app/public" \
    "$APP_PATH/storage/framework/cache/data" \
    "$APP_PATH/storage/framework/sessions" \
    "$APP_PATH/storage/framework/views" \
    "$APP_PATH/storage/logs"

log 'Menyinkronkan public Laravel ke document root.'
"$RSYNC_BIN" -a --delete \
    --exclude='/.htaccess' \
    --exclude='/index.php' \
    --exclude='/storage/' \
    --exclude='/.well-known/' \
    --exclude='/cgi-bin/' \
    "$REPOSITORY_PATH/public/" "$PUBLIC_PATH/"

/bin/cp "$PUBLIC_INDEX_TEMPLATE" "$PUBLIC_PATH/index.php"

if [[ ! -f "$PUBLIC_PATH/.htaccess" ]]; then
    /bin/cp "$REPOSITORY_PATH/public/.htaccess" "$PUBLIC_PATH/.htaccess"
fi

if [[ ! -e "$APP_PATH/public/storage" ]]; then
    /bin/ln -s "$APP_PATH/storage/app/public" "$APP_PATH/public/storage"
fi

if [[ -L "$PUBLIC_PATH/storage" ]]; then
    /bin/ln -sfn "$APP_PATH/storage/app/public" "$PUBLIC_PATH/storage"
else
    /bin/ln -s "$APP_PATH/storage/app/public" "$PUBLIC_PATH/storage"
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

exit 0
