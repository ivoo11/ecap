#!/bin/bash
set -euo pipefail

ECAP_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ECAP_PHP="${ECAP_PHP:-php}"

if ! command -v "$ECAP_PHP" >/dev/null 2>&1; then
    printf '%s\n' 'ERROR: PHP CLI no está disponible. Configurar ECAP_PHP con la ruta de PHP 8.3 del hosting.' >&2
    exit 1
fi
"$ECAP_PHP" -r 'if (PHP_VERSION_ID < 80300 || !extension_loaded("pdo_mysql") || !extension_loaded("mbstring")) { fwrite(STDERR, "ERROR: se requiere PHP CLI 8.3 o posterior con pdo_mysql y mbstring.\n"); exit(1); }'

# Sin argumentos conserva el comando configurado en Hostinger.
# --dry-run permite comprobarlo sin enviar ni registrar comunicaciones.
case "${1:---run}" in
    --run|--dry-run) ECAP_MODE="${1:---run}" ;;
    *) printf '%s\n' 'Uso: cron-recordatorios.sh [--run|--dry-run]' >&2; exit 1 ;;
esac
if (( $# > 1 )); then
    printf '%s\n' 'ERROR: no se admiten argumentos adicionales.' >&2
    exit 1
fi
exec "$ECAP_PHP" "$ECAP_ROOT/app/jobs/enviar-recordatorios.php" "$ECAP_MODE"
