#!/usr/bin/env bash
# Trae la última versión de GitHub y la pone en marcha. Los datos (base de datos y fotos) se conservan.
set -euo pipefail
cd "$(dirname "$0")"

[ -f .env ] || { echo "No hay .env: primero corre ./instalar.sh" >&2; exit 1; }
PERFIL=()
grep -q '^OTI_HTTPS_CADDY=1' .env && PERFIL=(--profile https)

echo "==> Respaldando .env"
cp -p .env ".env.respaldo-$(date +%F-%H%M)"

echo "==> Descargando cambios"
git pull --ff-only
sed -i 's|^APP_NAME=.*|APP_NAME="Soporte OTI"|' .env

echo "==> Reconstruyendo y reiniciando (las migraciones se aplican al iniciar)"
docker compose ${PERFIL[@]+"${PERFIL[@]}"} up -d --build

echo "==> Esperando a la aplicación"
PUERTO=${APP_PORT:-$(grep -E '^APP_PORT=' .env | cut -d= -f2 || true)}; PUERTO=${PUERTO:-8080}
for i in $(seq 1 60); do
    curl -fsS "http://127.0.0.1:$PUERTO/up" >/dev/null 2>&1 && break
    [ "$i" = 60 ] && { docker compose logs --tail 40 app; echo "La aplicación no respondió." >&2; exit 1; }
    sleep 3
done
docker compose exec -T app php artisan db:seed --force </dev/null

echo "==> Actualizado: $(git log -1 --format='%h %s')"
