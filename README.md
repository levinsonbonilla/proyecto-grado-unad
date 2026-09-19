# Proyecto de Grado

Plataforma web construida con Symfony que incluye un panel de administración (dashboard) para gestionar catálogos de productos, categorías, cupones, promociones, condiciones por puntos, tipos de descuento y métodos de pago. Sigue una arquitectura por capas (Controller → Handler/UseCase → Entity/Repository → Base de datos) organizada en módulos, con el módulo `Products` como módulo activo actual.


## Tecnologías utilizadas

- **Backend:** Symfony 7.1, PHP 8.3
- **ORM:** Doctrine ORM (`doctrine/orm` ^3.1) + Doctrine Migrations Bundle
- **Base de datos:** MySQL 8.4
- **Cola de mensajes:** Symfony Messenger (transporte Doctrine, `doctrine://default`)
- **Correo:** Symfony Mailer (SMTP vía Mailpit en desarrollo)
- **Almacenamiento de archivos:** AWS SDK PHP (`aws/aws-sdk-php`) contra un endpoint S3-compatible (AWS S3 en producción, MinIO en desarrollo)
- **Geolocalización:** GeoIP2 (base de datos MaxMind GeoLite2)
- **Detección de dispositivo:** Matomo Device Detector
- **Editor de texto:** FriendsOfSymfony CKEditor Bundle
- **Plantillas:** Twig
- **Calidad de código:** PHPStan, Rector, PHPUnit
- **Contenedores:** Docker / Docker Compose

## Requisitos

- PHP **8.3** o superior, con las extensiones: `ctype`, `iconv`, `pdo_mysql`, `mbstring`, `exif`, `pcntl`, `bcmath`, `gd` (con freetype y jpeg), `intl`, `zip`, `opcache`
- Composer 2
- MySQL 8.4 (o compatible)
- Docker y Docker Compose (recomendado; ver más abajo)
- Git

## Instalación en entorno local (sin Docker)

Estos pasos replican lo que hace automáticamente el contenedor PHP (`docker/php/entrypoint.sh`), adaptado a una instalación sin Docker:

1. Clonar el repositorio.
2. Instalar PHP 8.3 con las extensiones listadas en **Requisitos**.
3. Instalar dependencias:
   ```bash
   composer install --no-interaction --prefer-dist
   ```
4. Crear `.env.local` a partir de `docker/env/.env.local.template` y `.env.test` a partir de `docker/env/.env.test.template`, ajustando `DATABASE_HOST` a la dirección de tu MySQL local (el template usa `mysql` como host, pensado para Docker).
5. Tener un servidor MySQL 8.4 accesible con la base de datos definida en `DATABASE_URL`.
6. Limpiar caché:
   ```bash
   php bin/console cache:clear --no-warmup
   ```
7. Inicializar la base de datos. El proyecto usa el comando:
   ```bash
   php bin/console db:cf start
   ```
   **Nota:** este comando se ejecuta en el arranque de Docker, pero no se encontró su clase dentro de `src/Command` — no se pudo determinar con precisión si proviene de un bundle de terceros o de otra ubicación. Verificarlo antes de depender de él en un entorno sin Docker.

> No se detectó un `Makefile` ni un script definido para levantar un servidor de desarrollo local sin Docker. Una alternativa estándar de Symfony sería `symfony server:start` (si tenés el Symfony CLI instalado) o `php -S localhost:8000 -t public`, pero ninguno de los dos está configurado explícitamente en este proyecto.

## Instalación con Docker

1. Construir las imágenes y levantar los contenedores:
   ```bash
   docker compose up -d --build
   ```
2. El contenedor `php` ejecuta automáticamente (`docker/php/entrypoint.sh`) al iniciar:
   - Crea `.env.local` y `.env.test` desde sus plantillas en `docker/env/` si no existen.
   - Instala dependencias con `composer install --no-interaction --prefer-dist` si falta `vendor/autoload.php`.
   - Espera a que MySQL esté disponible (reintentos hasta que responda).
   - Si la base de datos está vacía, ejecuta `php bin/console db:cf start` para inicializarla.
   - Limpia caché con `php bin/console cache:clear --no-warmup`.
   - Ajusta permisos de `var/cache` y `var/log` para `www-data`.
3. El servicio `minio-seed` corre una sola vez (`restart: "no"`) y siembra el bucket de assets — ver sección **Assets**.
4. La aplicación queda disponible en `http://localhost:8060`.

Para reiniciar limpio:
```bash
docker compose down
docker compose up -d --build
```

## Servicios disponibles

| Servicio | URL | Descripción |
|---|---|---|
| Aplicación Symfony (Nginx) — sitio principal | http://localhost:8060 | Tenant `proyecto-grado-unad` — punto de entrada de la aplicación |
| Aplicación Symfony (Nginx) — segundo sitio | http://localhost:8090 | Tenant `tenant-demo-2` — mismo backend, para probar multi-tenant en local sin DNS/subdominios reales (ver más abajo) |
| Adminer | http://localhost:8080 | Cliente web para administrar la base de datos MySQL |
| Mailpit | http://localhost:8025 | Interfaz web para ver correos capturados (SMTP interno en el puerto 1025) |
| MinIO API | http://localhost:9000 | Endpoint S3-compatible para almacenamiento de assets |
| MinIO Consola | http://localhost:9001 | Interfaz web de administración de MinIO |
| MySQL | http://localhost:3306 | Base de datos (accesible también desde fuera de Docker) |
| Pasarela de pago simulada | http://localhost:8070 | Mock de checkout con pasarela (`docker/payment-gateway/`) — ver sección **Pasarela de pago simulada** |

### Multi-tenant en local — segundo sitio (puerto 8090)

La app resuelve a qué tenant pertenece una request **por el header `Host`** (`GetDomainData`,
`src/Handler/Configuration/GetDomainData.php`), no por configuración de Nginx — por eso alcanza
con que Nginx escuche en dos puertos (`docker/nginx/default.conf`: `listen 80; listen 8090;`)
reenviando ambos al mismo PHP-FPM. `compose.yaml` publica `8090:8090` en el servicio `nginx`
junto al `8060:80` ya existente.

Fixtures (`preloaded_data/tenants.yaml` + `domains.yaml`, cargadas por `AppFixtures`) siembran
un **segundo tenant** (`tenant-demo-2`) con su domain en `http://localhost:8090`, separado del
tenant principal (`proyecto-grado-unad`, `http://localhost:8060`). Cada `domain` en `domains.yaml` declara a
qué tenant pertenece vía `tenantIndex` (índice en `tenants.yaml`) — sin ese campo, cae por
defecto en el tenant `0` (`proyecto-grado-unad`), preservando el comportamiento de cuando solo existía un
tenant.

Sirve para probar aislamiento real entre tenants (catálogo, usuarios, pedidos, dashboard —
cada uno completamente separado) con dos sitios fijos, sin necesitar DNS. Es independiente del
flujo de registro público de tenants (`/comenzar`, subdominios dinámicos) — ver la sección
siguiente para probar ese.

**Nota:** si corrés `doctrine:fixtures:load` en un entorno con datos reales que querés
conservar, esto purga toda la base — en ese caso sembrar el segundo tenant/domain a mano
(mismos valores que en `domains.yaml`/`tenants.yaml`) en vez de recargar fixtures.

### DNS wildcard local — subdominios dinámicos de `/comenzar`

El registro público de tenants (`/comenzar`) crea un domain
nuevo por cada alta (`{slug}.PLATFORM_BASE_DOMAIN`) — a diferencia del segundo sitio de arriba
(fijo, sembrado por fixtures), acá el subdominio es dinámico y no se puede pre-registrar en
`/etc/hosts` uno por uno. `docker/dns/` levanta un servidor DNS (dnsmasq) que resuelve
**cualquier** subdominio de `proyecto-grado-unad.test` a `127.0.0.1` — wildcard de verdad, no una lista.

```bash
export WSL_DNS_BIND_IP=$(hostname -I | awk '{print $1}')   # solo WSL2, ver docker/dns/README.md
docker compose --profile dns up -d dns
```

En dev local, `.env.local` ya trae `PLATFORM_BASE_DOMAIN=proyecto-grado-unad.test` y `PLATFORM_SCHEME=http`
(en vez de `proyecto-grado-unad.app`/`https` de producción) — así los tenants que crees con `/comenzar` en
este entorno quedan resueltos por este DNS sin necesitar TLS/certificados. `compose.yaml`
también publica el puerto `80` del host además del `8060` (mismo backend) — las URLs de tenant
no llevan puerto (`http://{slug}.proyecto-grado-unad.test`, no `:8060`), así que hace falta.

Ver `docker/dns/README.md` para el detalle completo, incluida la configuración de Windows
(fuera de Docker) para que el navegador resuelva `*.proyecto-grado-unad.test` automáticamente, y un caveat
importante de WSL2 con el puerto 53 (`WSL_DNS_BIND_IP` no es opcional ahí).



## Pasarela de pago simulada

`docker/payment-gateway/` es un microservicio Node/Express aparte que emula el contrato mínimo
de una pasarela de pago real (crear intención de pago, página de "pagar", webhook de
confirmación firmado HMAC) para poder probar el checkout de punta a punta sin credenciales
reales. No procesa dinero real ni pide datos de tarjeta — ver `docker/payment-gateway/README.md`
para el contrato completo. Corre en `http://localhost:8070`, y el proyecto le habla vía
`PAYMENT_GATEWAY_INTERNAL_URL` (red interna de Docker) y `PAYMENT_GATEWAY_SECRET` (firma HMAC
compartida).

## Geografía — países, departamentos y ciudades

`Countries`/`Regions`/`Cities` no tienen CRUD de admin — son catálogo global, cargado por
`AppFixtures` desde `preloaded_data/{countries,regions,cities}.yaml` en dev/test únicamente
(`doctrine:fixtures:load` purga toda la base, ver nota más arriba).

Para agregar un país completo con sus departamentos/regiones y ciudades principales en un
ambiente con datos reales (sin purgar nada), usar el comando de importación:

```bash
php bin/console app:geo:import-country CO
php bin/console app:geo:import-country MX --min-population=10000
```

Trae los datos del dump público de [GeoNames](https://www.geonames.org/) (licencia
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/) — atribución: datos geográficos de
GeoNames.org). Es aditivo e idempotente: correrlo de nuevo para el mismo país no duplica el
país, sus regiones ni sus ciudades ya importadas — sirve para, por ejemplo, bajar
`--min-population` más adelante y sumar ciudades más chicas sin re-crear las que ya estaban.
Por defecto solo trae, además de las capitales de país/departamento, las ciudades con
población ≥ 5000 (`--min-population=0` trae todas las localidades pobladas del país — pueden
ser decenas de miles). Ver `App\Service\Geo\GeoNamesCountryImporterInterface`.

⚠️ Si la base ya tiene países/regiones cargados a mano o vía los fixtures de dev con códigos
propios (ej. `preloaded_data/regions.yaml` usa `DC`/`CUN` en vez del código admin1 real de
GeoNames), el comando no los reconoce como la misma región — crea una región nueva con el
código de GeoNames en paralelo. No aplica a una base de producción que arranca limpia con
este comando desde el principio.

## Assets

Los archivos estáticos (imágenes, recursos del frontend, etc.) se gestionan mediante un almacenamiento **S3-compatible**, a través de `aws/aws-sdk-php`:

- **En producción:** apunta a un bucket real de AWS S3 (`proyecto-grado-unad-assets`, servido vía `CDN_BASE_URL=https://proyecto-grado-unad-assets.s3.us-east-2.amazonaws.com`).
- **En desarrollo:** se reemplaza por **MinIO** (mismo nombre de bucket, `proyecto-grado-unad-assets`), corriendo como contenedor Docker.

El contenido real de los assets vive en un **repositorio hermano separado**: `../thorha-assets` (fuera de este proyecto, al mismo nivel que la carpeta de este repo). El nombre del repositorio hermano es independiente del nombre del bucket/proyecto.

El servicio `minio-seed` (imagen `minio/mc`) se ejecuta **una sola vez** al levantar Docker Compose (`restart: "no"`), después de que MinIO esté saludable, y hace lo siguiente:
1. Monta `../thorha-assets` en modo solo lectura dentro del contenedor.
2. Crea el bucket `proyecto-grado-unad-assets` en MinIO si no existe (`mc mb --ignore-existing`).
3. Marca el bucket como de descarga pública (`mc anonymous set download`).
4. Copia (`mc mirror`) todo el contenido de `../thorha-assets` hacia ese bucket, excluyendo la carpeta `.git`.

Esto significa que **si el repositorio `thorha-assets` no está clonado junto a este proyecto**, el servicio `minio-seed` fallará al intentar montar esa ruta.

## Variables de entorno

Variables detectadas en `.env` / `docker/env/*.template` (se omiten los valores reales por seguridad):

| Variable | Descripción |
|---|---|
| `APP_ENV` | Entorno de la aplicación (`dev`, `test`, `prod`) |
| `APP_SECRET` | Secreto interno de Symfony |
| `DATABASE_URL` / `DATABASE_HOST` / `DATABASE_PORT` / `DATABASE_NAME` / `DATABASE_USER` / `DATABASE_PASSWORD` | Conexión a MySQL |
| `MESSENGER_TRANSPORT_DSN` | Transporte de Symfony Messenger (usa Doctrine, no Redis/AMQP) |
| `MAILER_DSN` | Conexión SMTP para envío de correo (Mailpit en desarrollo) |
| `MAILER_SENDER_EMAIL` / `MAILER_SENDER_NAME` | Remitente por defecto de los correos |
| `URL_TOKEN_DOWNLOAD` | Token usado para URLs de descarga (uso específico no determinado) |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | Credenciales del almacenamiento S3-compatible (MinIO en desarrollo) |
| `AWS_REGION` | Región S3 (dummy en desarrollo con MinIO) |
| `BUCKET_NAME` | Nombre del bucket de assets (`proyecto-grado-unad-assets`) |
| `AWS_ENDPOINT` | Endpoint del storage S3-compatible (MinIO en desarrollo) |
| `CDN_BASE_URL` | URL base pública para servir los assets |
| `PAYMENT_GATEWAY_INTERNAL_URL` / `PAYMENT_GATEWAY_SECRET` | URL interna y secreto HMAC compartido con la pasarela de pago simulada (ver sección **Pasarela de pago simulada**) |
| `PLATFORM_BASE_DOMAIN` | Dominio base para subdominios de tenants nuevos (`{slug}.PLATFORM_BASE_DOMAIN`) creados vía el registro público `/comenzar` |
| `PLATFORM_SCHEME` | Scheme de esos domains (`https` en prod; `http` en dev local, ver sección **DNS wildcard local**) |
| `SESSION_COOKIE_DOMAIN` | Vacío en dev. En prod, `.{PLATFORM_BASE_DOMAIN}` — comparte sesión entre el dominio plataforma y los subdominios de tenants para que el superadmin no tenga que loguearse en cada uno |

## Estructura del proyecto

```
src/
  Controller/        # Controladores HTTP, organizados por módulo (Modules/Products, Configurations)
  Handler/UseCase/    # Lógica de negocio (casos de uso)
  Interface/UseCase/  # Interfaces de los casos de uso
  Entity/             # Entidades Doctrine, organizadas por módulo
  Repository/         # Repositorios Doctrine
  Form/               # Form Types de Symfony
  ArgumentHandler/     # DTOs de entrada
templates/            # Plantillas Twig (e_commerce/theme_1, dashboard/modules/...)
config/                # Configuración de Symfony (packages, routes, services)
docker/                # Dockerfiles, entrypoint.sh, configuración de Nginx, plantillas de .env
migrations/            # Migraciones de Doctrine
tests/                 # Tests (PHPUnit)
public/                # Document root (index.php de Symfony)
geolocalization/       # Base de datos MaxMind GeoLite2 para geolocalización por IP
preloaded_data/        # YAML cargado por AppFixtures (src/DataFixtures/AppFixtures.php) — tenants, domains, usuarios, productos, etc. de dev/test
```

## Desarrollo

# Consola de Symfony
php bin/console <comando>

# Tests (PHPUnit, configurado en phpunit.xml.dist)
php bin/phpunit

# Análisis estático (PHPStan, configurado en phpstan.dist.neon)
vendor/bin/phpstan analyse -c phpstan.dist.neon

# Refactors automáticos (Rector, configurado en rector.php)
vendor/bin/rector process
```

## Solución de problemas

- **La app no arranca de inmediato la primera vez:** es esperado. El contenedor `php` espera activamente a que MySQL esté disponible antes de continuar (ver `entrypoint.sh`); Nginx además tiene configurado `depends_on: php: condition: service_healthy`.
- **Permisos en `var/cache` / `var/log`:** se ajustan automáticamente al arrancar el contenedor (`chown`/`chmod` en `entrypoint.sh`); si corrés sin Docker vas a necesitar hacerlo manualmente.
- **`minio-seed` falla o no sube archivos:** revisá que el repositorio `../thorha-assets` exista clonado junto a este proyecto — `minio-seed` depende de esa ruta para sembrar el bucket.
- **Dependencias de Composer no instaladas:** el contenedor las instala solo si falta `vendor/autoload.php`; si cambiaste `composer.json` tras el primer arranque, corré `composer install` manualmente dentro del contenedor.

