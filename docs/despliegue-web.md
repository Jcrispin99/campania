# Despliegue web en CloudPanel

El sitio sirve Expo Web y Laravel desde `https://campania.ikoodev.com`.
La interfaz utiliza `/api/v1` en el mismo dominio. No se necesita un proceso
Expo ni una compilación Android en producción.

## Requisitos

- PHP 8.4 con las extensiones exigidas por Composer, SQLite y ZIP.
- Composer 2 y Node 22.13 o superior con npm.
- HTTPS y raíz del sitio en
  `/home/ikoodev-campania/htdocs/campania.ikoodev.com/public`.
- Ejecutar Composer, npm y Artisan como `ikoodev-campania`.

## Primera instalación

Desde la carpeta del proyecto, instalar dependencias:

```sh
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
cp .env.example .env
```

Configurar `.env` antes de continuar:

```dotenv
APP_NAME=Campania
APP_ENV=production
APP_DEBUG=false
APP_URL=https://campania.ikoodev.com
APP_LOCALE=es
APP_FALLBACK_LOCALE=en
LOG_LEVEL=warning
DB_CONNECTION=sqlite
DB_DATABASE=/home/ikoodev-campania/htdocs/campania.ikoodev.com/database/database.sqlite
SESSION_SECURE_COOKIE=true
CAMPANIA_PUBLIC_REGISTRATION=false
```

SQLite permite iniciar sin configurar otro servicio de base de datos.
El archivo debe estar fuera de `public/`, con permisos para el usuario del sitio.
También se puede configurar MySQL usando las variables `DB_*` de Laravel.

```sh
touch database/database.sqlite
chmod 600 .env
chmod -R u+rwX storage bootstrap/cache
php artisan key:generate --force
php artisan migrate --force
cd frontend
npm ci
npm run build:web
cd ..
php artisan optimize
php artisan campania:crear-usuario tu-correo@example.com
```

El último comando solicita una contraseña oculta de al menos 12 caracteres.
El registro público está deshabilitado en producción; las cuentas se crean
desde el comando o el módulo Usuarios después de iniciar sesión.
No ejecutar los seeders generales: incluyen cuentas y operaciones de ejemplo.
Configurar una copia diaria de SQLite mediante su API de backup y conservarla
fuera del directorio público. Respaldar también `.env` y `storage/app`.

## Actualizaciones

Respaldar la base de datos antes de aplicar migraciones y luego ejecutar:

```sh
git pull --ff-only origin main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
cd frontend
npm ci
npm run build:web
cd ..
php artisan optimize
```

La compilación copia los archivos de Expo a `public/`, conserva los assets
anteriores y publica el HTML como `app.html`, sin reemplazar `index.php`.
Las rutas del navegador pasan por Laravel; `/api`, `/docs`, `/up` y los archivos
estáticos conservan sus respuestas propias.

## Verificación

Comprobar `/`, `/login`, `/module/atenciones`, `/up` y que
`/api/v1/pacientes` responda 401 sin sesión. Verificar también que `/.env`
y `/database/database.sqlite` no entreguen archivos. Probar inicio de sesión,
creación de paciente y registro de atención desde el navegador.

El sitio requiere conexión a Internet. No incluye funcionamiento sin conexión.
