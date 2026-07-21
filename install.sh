#!/usr/bin/env bash
#
# Instala el comando «flameware» enlazándolo en ~/.local/bin.
#
# El enlace apunta a este repositorio, así que un «git pull» actualiza el
# generador y el skeleton sin reinstalar nada.

set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="${REPO}/bin/flameware"
TARGET_DIR="${HOME}/.local/bin"
TARGET="${TARGET_DIR}/flameware"

red()   { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
warn()  { printf '\033[33m%s\033[0m\n' "$*"; }
dim()   { printf '\033[2m%s\033[0m\n' "$*"; }

fail() {
    echo
    red "  ✗ $*"
    echo
    exit 1
}

echo
echo "  Instalando Flameware"
echo

# --- Requisitos ------------------------------------------------------------

command -v php >/dev/null 2>&1 || fail "PHP no está instalado o no está en el PATH."

PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
PHP_OK="$(php -r 'echo PHP_VERSION_ID >= 80500 ? 1 : 0;')"

if [ "${PHP_OK}" != "1" ]; then
    fail "Flameware necesita PHP 8.5 o superior. Tienes ${PHP_VERSION} en $(command -v php)."
fi

green "  ✓ PHP ${PHP_VERSION}"

if command -v composer >/dev/null 2>&1; then
    green "  ✓ Composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}')"
else
    warn "  ! Composer no está en el PATH — tendrás que crear proyectos con --no-install."
fi

if command -v git >/dev/null 2>&1; then
    green "  ✓ git $(git --version | awk '{print $3}')"
else
    warn "  ! git no está en el PATH — tendrás que crear proyectos con --no-git."
fi

# El modo «make:resource --table» necesita un driver PDO para leer el esquema.
PDO_DRIVERS="$(php -r 'echo implode(", ", PDO::getAvailableDrivers());')"

if [ -z "${PDO_DRIVERS}" ]; then
    warn "  ! No hay drivers PDO activos: «make:resource --table» no podrá leer esquemas."
    dim  "    Descomenta «extension=pdo_mysql» en $(php -r 'echo php_ini_loaded_file();')"
    dim  "    El modo «make:resource --fields» funciona igual sin esto."
else
    green "  ✓ Drivers PDO: ${PDO_DRIVERS}"
fi

# --- Enlace ----------------------------------------------------------------

[ -f "${SOURCE}" ] || fail "No se encontró ${SOURCE} — ¿el repositorio está completo?"
[ -d "${REPO}/skeleton" ] || fail "Falta el directorio skeleton/ — ¿el repositorio está completo?"

mkdir -p "${TARGET_DIR}"
chmod +x "${SOURCE}"

if [ -e "${TARGET}" ] && [ ! -L "${TARGET}" ]; then
    fail "${TARGET} existe y no es un enlace. Muévelo o bórralo antes de instalar."
fi

ln -sfn "${SOURCE}" "${TARGET}"

echo
green "  ✓ flameware instalado en ${TARGET}"
dim   "    → ${SOURCE}"

# --- PATH ------------------------------------------------------------------

if ! echo ":${PATH}:" | grep -q ":${TARGET_DIR}:"; then
    echo
    warn "  ! ${TARGET_DIR} no está en tu PATH. Agrégalo a tu shell:"
    echo
    case "$(basename "${SHELL:-bash}")" in
        zsh)  dim "      echo 'export PATH=\"\$HOME/.local/bin:\$PATH\"' >> ~/.zshrc && source ~/.zshrc" ;;
        fish) dim "      fish_add_path \$HOME/.local/bin" ;;
        *)    dim "      echo 'export PATH=\"\$HOME/.local/bin:\$PATH\"' >> ~/.bashrc && source ~/.bashrc" ;;
    esac
fi

echo
echo "  Prueba:"
dim "      flameware help"
dim "      flameware new mi-api"
echo
