#!/usr/bin/env bash
#
# Encadena los pasos 1-4 del checklist de validación de:
# Estadísticas + Ventas + Pasarela de pago simulada
#
#   1. Levantar el stack de Docker (con messenger-worker y payment-gateway)
#   2. Migración de Doctrine + validación de esquema
#   3. Fixtures
#   4. Tests (PHPUnit)
#
# Uso:
#   ./scripts/validate-sales-payments.sh              # corre todo
#   ./scripts/validate-sales-payments.sh --skip-build  # reusa las imágenes ya construidas
#   ./scripts/validate-sales-payments.sh --skip-tests  # se detiene después de las fixtures
#
# No usa `set -e` a lo largo de todo el script a propósito: cada paso se
# reporta con su propio éxito/fallo y el resumen final indica qué revisar,
# en vez de cortar en seco en el primer problema.

cd "$(dirname "$0")/.." || exit 1

BUILD=1
RUN_TESTS=1
for arg in "$@"; do
    case "$arg" in
        --skip-build) BUILD=0 ;;
        --skip-tests) RUN_TESTS=0 ;;
        *) echo "Argumento desconocido: $arg" >&2; exit 1 ;;
    esac
done

STEP_RESULTS=()

section() {
    echo ""
    echo "==========================================="
    echo "  $1"
    echo "==========================================="
}

record() {
    STEP_RESULTS+=("$1: $2")
}

exec_php() {
    docker compose exec -T php "$@"
}

############################################################
# 1. Levantar el stack
############################################################

section "1/4 — Levantando el stack de Docker"

if [ "$BUILD" -eq 1 ]; then
    if docker compose up -d --build; then
        record "docker compose up --build" "✅ OK"
    else
        record "docker compose up --build" "❌ FALLÓ"
        echo "No se pudo levantar el stack — abortando."
        printf '%s\n' "${STEP_RESULTS[@]}"
        exit 1
    fi
else
    if docker compose up -d; then
        record "docker compose up" "✅ OK"
    else
        record "docker compose up" "❌ FALLÓ"
        echo "No se pudo levantar el stack — abortando."
        printf '%s\n' "${STEP_RESULTS[@]}"
        exit 1
    fi
fi

echo ""
echo "Esperando a que 'php' y 'mysql' estén healthy (hasta 90s)..."
ATTEMPTS=0
until [ "$(docker compose ps php --format '{{.Health}}' 2>/dev/null)" = "healthy" ] || [ "$ATTEMPTS" -ge 45 ]; do
    sleep 2
    ATTEMPTS=$((ATTEMPTS + 1))
done

if [ "$(docker compose ps php --format '{{.Health}}' 2>/dev/null)" = "healthy" ]; then
    record "php healthy" "✅ OK"
else
    record "php healthy" "⚠️  no confirmado tras 90s (puede seguir levantando — revisar 'docker compose logs php')"
fi

docker compose ps

############################################################
# 2. Migración
############################################################

section "2/4 — Migración de Doctrine"

if exec_php php bin/console doctrine:migrations:migrate --no-interaction; then
    record "doctrine:migrations:migrate" "✅ OK"
else
    record "doctrine:migrations:migrate" "❌ FALLÓ — revisar salida arriba"
fi

echo ""
if exec_php php bin/console doctrine:schema:validate; then
    record "doctrine:schema:validate" "✅ OK"
else
    record "doctrine:schema:validate" "⚠️  reportó diferencias — probablemente el índice único de payment_transactions.gateway_reference (migración escrita a mano). Ver fase-6-validacion-local.md, sección 2."
fi

############################################################
# 3. Fixtures
############################################################

section "3/4 — Fixtures"

if exec_php php bin/console doctrine:fixtures:load --no-interaction; then
    record "doctrine:fixtures:load" "✅ OK"
else
    record "doctrine:fixtures:load" "❌ FALLÓ — revisar salida arriba"
fi

############################################################
# 4. Tests
############################################################

if [ "$RUN_TESTS" -eq 1 ]; then
    section "4/4 — PHPUnit"

    if exec_php php bin/phpunit; then
        record "php bin/phpunit" "✅ OK"
    else
        record "php bin/phpunit" "❌ FALLÓ — correr por partes para localizar (ver fase-6-validacion-local.md, sección 4)"
    fi
else
    section "4/4 — PHPUnit (omitido por --skip-tests)"
    record "php bin/phpunit" "⏭️  omitido"
fi

############################################################
# Resumen
############################################################

section "RESUMEN"
printf '%s\n' "${STEP_RESULTS[@]}"

echo ""
echo "Siguiente: smoke test manual de la pasarela (paso 5) y QA en el navegador (paso 7)"

if printf '%s\n' "${STEP_RESULTS[@]}" | grep -q "❌"; then
    exit 1
fi
