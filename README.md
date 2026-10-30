# Mesa de ayuda OTI

Sistema de tickets de la Oficina de Tecnologías de la Información: un solo canal para recibir pedidos (portal web, WhatsApp, correo, llamada o presencial), derivarlos por especialidad y medir tiempos por sede.

Laravel 13 + Blade + Tailwind + Alpine.js + MySQL, empaquetado en Docker.

## Roles

Todos ingresan con su correo institucional **@uniq.edu.pe** (Google). La primera vez quedan como **Usuario**; solo el administrador cambia roles en *Usuarios → Perfil*.

- **Usuario**: registra sus pedidos desde el portal (`/login`, con QR para el celular) y sigue su avance.
- **Mesa de ayuda**: registra pedidos de otros canales, los clasifica y los asigna desde la bandeja.
- **Técnico / especialista**: soporte, redes, servidores o web. Avanza sus tickets y los cierra con diagnóstico y solución.
- **Administrador**: todo lo anterior, más usuarios y perfiles, categorías y reportes.

## Ingreso con Google (correo UNIQ)

1. En [Google Cloud Console](https://console.cloud.google.com/) (con una cuenta de la UNIQ): *APIs y servicios → Pantalla de consentimiento OAuth*, tipo **Interno** (así solo entran cuentas de la UNIQ).
2. *Credenciales → Crear credenciales → ID de cliente de OAuth → Aplicación web*. En **URI de redireccionamiento autorizados** pon `https://TU-DOMINIO/auth/google/callback`.
3. Copia el ID y el secreto al `.env`:
   ```
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
   OTI_LOGIN_PASSWORD=false
   ```
4. `OTI_LOGIN_PASSWORD=false` quita el ingreso con contraseña (que solo existe para pruebas). Además del tipo *Interno*, el sistema rechaza cualquier cuenta que no sea del dominio `uniq.edu.pe`.

El QR de la página de ingreso apunta a `APP_URL/login`: en producción `APP_URL` debe ser el dominio real para que funcione desde los celulares.

## Desarrollo (Windows)

```bash
docker compose up -d mysql          # MySQL en el puerto 3307
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan webpush:vapid           # requiere OPENSSL_CONF (ver abajo)
php artisan migrate --seed
npm run build && php artisan serve
```

Los usuarios de prueba y su contraseña están en `database/seeders/DatabaseSeeder.php`. Cámbialos antes de producción.

En Windows, PHP necesita `OPENSSL_CONF=C:\php\extras\ssl\openssl.cnf` para generar las claves VAPID y cifrar los avisos, y `curl.cainfo` configurado en `php.ini` para enviarlos. En Docker no hace falta nada de esto.

## Producción (Docker)

```bash
docker compose up -d --build        # app en el puerto 8080
docker compose exec app php artisan db:seed --force   # solo la primera vez
```

En el `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con el dominio real y una contraseña fuerte en `DB_PASSWORD`.

Los avisos push **requieren HTTPS**: pon la app detrás de un proxy con certificado (Nginx, Caddy o el balanceador de la institución). En iPhone, el aviso solo llega si el usuario agrega la web a su pantalla de inicio.

## Pruebas

```bash
php artisan test
```

Usa la base `tickets_oti_test` en el mismo MySQL.
