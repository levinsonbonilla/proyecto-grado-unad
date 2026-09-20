#!/bin/sh
set -e

echo ""
echo "==========================================="
echo "      PROYECTO DE GRADO — PRODUCTION"
echo "==========================================="
echo ""

cd /var/www/html

if [ ! -f "vendor/autoload.php" ]; then
    echo "📦 Instalando dependencias Composer..."
    composer install --no-interaction --prefer-dist --no-dev
    echo "✅ Composer finalizado."
else
    echo "✅ Dependencias Composer encontradas."
fi

echo ""
echo "🧹 Limpiando caché de Symfony..."
php bin/console cache:clear --no-warmup || true

echo ""
echo "🔐 Ajustando permisos de var/..."
mkdir -p var/cache var/log
chown -R www-data:www-data var 2>/dev/null || true
chmod -R ug+rwX var

echo ""
echo "🚀 Iniciando PHP-FPM..."
echo ""

exec "$@"
