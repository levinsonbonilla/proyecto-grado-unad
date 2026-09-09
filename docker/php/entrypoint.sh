
set -e

echo ""
echo "==========================================="
echo "      PROYECTO DE GRADO — DEVELOPMENT ENVIRONMENT"
echo "==========================================="
echo ""

cd /var/www/html

if [ ! -f ".env.local" ]; then
    echo "📄 Copiando .env.local desde la plantilla..."

    cp docker/env/.env.local.template .env.local

    echo "✅ .env.local creado."
else
    echo "✅ .env.local encontrado."
fi

if [ ! -f ".env.test" ]; then
    echo "📄 Copiando .env.test desde la plantilla..."

    cp docker/env/.env.test.template .env.test

    echo "✅ .env.test creado."
else
    echo "✅ .env.test encontrado."
fi

if [ ! -f "vendor/autoload.php" ]; then

    echo ""
    echo "📦 Instalando dependencias Composer..."

    composer install --no-interaction --prefer-dist

    echo "✅ Composer finalizado."

else

    echo ""
    echo "✅ Dependencias Composer encontradas."

fi

echo ""
echo "⏳ Esperando que MySQL esté disponible..."

until php -r "
try {
    new PDO(
        'mysql:host=mysql;port=3306',
        'proyecto_grado_unad',
        'proyecto_grado_unad'
    );
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
"
do
    sleep 2
done

echo "✅ MySQL disponible."

echo ""
echo "🔍 Verificando si existen tablas..."

TABLE_COUNT=$(php bin/console doctrine:query:sql "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE();" --no-interaction 2>/dev/null | grep -oE '[0-9]+' | tail -1)
TABLE_COUNT=${TABLE_COUNT:-0}

if [ "$TABLE_COUNT" -eq "0" ]; then

    echo ""
    echo "🚀 Base de datos vacía."
    echo "Ejecutando configuración inicial..."

    php bin/console db:cf start || true
    echo "Código retorno: $?"

    echo ""
    echo "✅ Base de datos inicializada."

else

    echo ""
    echo "✅ Base de datos ya inicializada ($TABLE_COUNT tablas)."

fi

echo ""
echo "🧹 Limpiando caché de Symfony..."

php bin/console cache:clear --no-warmup || true

echo ""
echo "🔐 Ajustando permisos de var/cache y var/log..."

mkdir -p var/cache var/log
chown -R www-data:www-data var/cache var/log 2>/dev/null || true
chmod -R ug+rwX var/cache var/log

echo ""
echo "🚀 Iniciando PHP-FPM..."
echo ""

exec "$@"