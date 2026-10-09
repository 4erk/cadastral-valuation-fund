#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
action="${1:-}"
shift || true

case "$action" in
    test)
        image="${DEVFLOW_PHP_IMAGE:-goskadastr-dev-web}"
        docker image inspect "$image" >/dev/null 2>&1 || {
            echo "Missing PHP 8.2 Docker image: $image" >&2
            exit 2
        }
        docker run --rm \
            --user "$(id -u):$(id -g)" \
            -v "$ROOT:/app" \
            -w /app \
            -e COMPOSER_HOME=/tmp/valuation-composer \
            -e COMPOSER_CACHE_DIR=/tmp/valuation-composer-cache \
            "$image" sh -ec '
                composer validate --strict --no-check-publish
                composer install --no-interaction --no-progress --prefer-dist
                composer audit --locked --no-dev --no-interaction
                for file in src/*.php src/Valuation/*.php tests/*.php tools/*.php; do
                    php -l "$file"
                done
                composer test
            '
        ;;
    deploy)
        echo 'This library is deployed by versioned Git/Composer upstream publication, not by copying files to production.' >&2
        exit 2
        ;;
    *)
        echo "Usage: .dev-flow/adapter.sh test" >&2
        exit 2
        ;;
esac
