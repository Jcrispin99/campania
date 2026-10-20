# Despliegue web en CloudPanel

El sitio sirve Expo Web y Laravel desde `https://campania.ikoodev.com`.
La interfaz utiliza `/api/v1` en el mismo dominio. No se necesita un proceso
Expo ni una compilación Android en producción.

## Requisitos

- PHP 8.4 con las extensiones exigidas por Composer, PDO MySQL y ZIP.
- Composer 2 y Node 22.13 o superior con npm.
- HTTPS y raíz del sitio en
  `/home/ikoodev-campania/htdocs/campania.ikoodev.com/public`.
- Ejecutar Composer, npm y Artisan como `ikoodev-campania`.

## Primera instalación

Desde la carpeta del proyecto, instalar dependencias:

```sh
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
test -f .env || cp .env.example .env
```

Configurar `.env` antes de continuar:

```dotenv
APP_NAME=Campania
APP_ENV=production
APP_DEBUG=false
APP_URL=https://campania.ikoodev.com
APP_TIMEZONE=America/Lima
APP_LOCALE=es
APP_FALLBACK_LOCALE=en
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=campania
# Conservar DB_USERNAME y DB_PASSWORD configurados en el VPS.
SESSION_SECURE_COOKIE=true
CAMPANIA_PUBLIC_REGISTRATION=false
```

El VPS utiliza MySQL. Conservar sus credenciales al configurar producción.
SQLite también es compatible si se configura explícitamente un archivo fuera
de `public/`, con permisos para el usuario del sitio.

```sh
chmod 600 .env
chmod -R u+rwX storage bootstrap/cache

# Solo en la primera instalación, si APP_KEY está vacío:
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
Configurar una copia diaria de MySQL con mysqldump y conservarla
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

## Correos de pacientes

Un paciente puede tener varios correos; cada dirección pertenece a un solo
paciente y, si tiene correos, uno es predeterminado. El campo original
`correo_electronico` se conserva como el correo predeterminado para los clientes
anteriores. La interfaz permite guardar los correos desde Atención sin registrar
una atención. Se puede crear un paciente sin correo.

Para cruzar un XLSX con columnas `DNI` y `Correo Electrónico`:

```sh
php artisan pacientes:importar-correos /ruta/Book1.xlsx --simular --reporte=/ruta/privada/vista-previa.json
php artisan pacientes:importar-correos /ruta/Book1.xlsx --reporte=/ruta/privada/resultado.json
```

El cruce agrega correos por DNI, respeta el predeterminado existente y puede
repetirse sin duplicar datos. Omite los correos inválidos, los que figuran en más
de un DNI y los que ya pertenecen a otro paciente. No crea pacientes sin nombre
cuando un DNI no existe. Estas incidencias se registran en el reporte privado.
Los Excel y los reportes deben mantenerse fuera de `public/` y del repositorio.
