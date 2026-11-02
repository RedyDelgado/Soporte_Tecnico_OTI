#!/usr/bin/env bash
# Instalador de la Mesa de ayuda OTI en un servidor Linux con Docker.
#   git clone https://github.com/RedyDelgado/Soporte_Tecnico_OTI.git && cd Soporte_Tecnico_OTI && ./instalar.sh
# Se puede volver a correr: conserva el .env (claves y base de datos) y solo crea las cuentas que pidas.
# Sin preguntas: define las respuestas como variables (lo usa desplegar-soporteoti.sh):
#   INSTALAR_DOMINIO, INSTALAR_GOOGLE_ID, INSTALAR_GOOGLE_SECRET, INSTALAR_PROXY_PROPIO (s/n),
#   INSTALAR_ADMIN, INSTALAR_ADMIN_NOMBRE, INSTALAR_PERSONAL (una línea por persona: correo|Nombre|rol|especialidad)
set -euo pipefail
cd "$(dirname "$0")"

DOMINIO_DEF="llamaoti.uniq.edu.pe"
ADMIN_DEF="rdelgado@uniq.edu.pe"
CRED="credenciales-instalacion.txt"

paso()      { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
aviso()     { printf '\033[1;33m%s\033[0m\n' "$*"; }
error()     { printf '\033[1;31mError: %s\033[0m\n' "$*" >&2; exit 1; }
preguntar() { # preguntar "texto" "valor por defecto" [VARIABLE que ya trae la respuesta]
    local v=""
    if [ -n "${3:-}" ] && [ -n "${!3:-}" ]; then echo "${!3}"; return; fi
    [ -t 0 ] && read -rp "$1 [$2]: " v || true
    echo "${v:-$2}"
}
b64url()    { base64 | tr '+/' '-_' | tr -d '=\n'; }
leer_env()  { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"'; }
fijar() { # fijar CLAVE valor: reemplaza la línea en .env o la agrega
    if grep -qE "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi
}

paso "Revisando requisitos"
command -v docker >/dev/null || error "Falta Docker. Instálalo con: curl -fsSL https://get.docker.com | sh"
docker compose version >/dev/null 2>&1 || error "Falta el plugin 'docker compose' (Docker Compose v2)."
command -v openssl >/dev/null || error "Falta openssl."
echo "Docker y openssl disponibles."

paso "Configuración (.env)"
if [ -f .env ]; then
    echo "Ya existe .env: se conservan sus claves y la contraseña de la base de datos."
else
    cp .env.example .env
    fijar APP_KEY "base64:$(openssl rand -base64 32)"
    fijar DB_DATABASE tickets_oti
    fijar DB_USERNAME oti
    fijar DB_PASSWORD "$(openssl rand -hex 20)"
    # Claves VAPID para los avisos push (curva P-256), sin necesitar PHP en el servidor.
    tmp=$(mktemp)
    openssl ecparam -name prime256v1 -genkey -noout -out "$tmp"
    fijar VAPID_PRIVATE_KEY "$(openssl ec -in "$tmp" -outform DER 2>/dev/null | tail -c +8 | head -c 32 | b64url)"
    fijar VAPID_PUBLIC_KEY "$(openssl ec -in "$tmp" -pubout -outform DER 2>/dev/null | tail -c 65 | b64url)"
    rm -f "$tmp"
    echo "Claves de la aplicación, de la base de datos y de avisos generadas."
fi
chmod 600 .env

DEF=$(leer_env DOMINIO || true)
DOMINIO=$(preguntar "Dominio del sistema" "${DEF:-$DOMINIO_DEF}" INSTALAR_DOMINIO)
fijar DOMINIO "$DOMINIO"
fijar APP_NAME '"Llama OTI"'
fijar APP_ENV production
fijar APP_DEBUG false
fijar APP_URL "https://$DOMINIO"
fijar GOOGLE_REDIRECT_URI "https://$DOMINIO/auth/google/callback"
fijar LOG_LEVEL warning
fijar SESSION_SECURE_COOKIE true
fijar OTI_DOMINIO uniq.edu.pe

echo
echo "Ingreso con Google (correo @uniq.edu.pe). Si aún no tienes las credenciales, deja vacío y agrégalas luego al .env (ver README)."
GID=$(preguntar "GOOGLE_CLIENT_ID" "$(leer_env GOOGLE_CLIENT_ID || true)" INSTALAR_GOOGLE_ID)
GSECRET=$(preguntar "GOOGLE_CLIENT_SECRET" "$(leer_env GOOGLE_CLIENT_SECRET || true)" INSTALAR_GOOGLE_SECRET)
fijar GOOGLE_CLIENT_ID "$GID"
fijar GOOGLE_CLIENT_SECRET "$GSECRET"
# El ingreso con contraseña queda activo como respaldo; desactívalo con OTI_LOGIN_PASSWORD=false cuando Google funcione.
fijar OTI_LOGIN_PASSWORD true

echo
CADDY_ACTUAL=$(leer_env OTI_HTTPS_CADDY || true)
RESP=$(preguntar "¿Este servidor ya tiene Nginx/Apache con SSL en los puertos 80/443? (s/n)" "$([ "${CADDY_ACTUAL:-1}" = 1 ] && echo n || echo s)" INSTALAR_PROXY_PROPIO)
if [[ "$RESP" =~ ^[sS] ]]; then
    fijar OTI_HTTPS_CADDY 0
    PERFIL=()
    aviso "Configura tu proxy para que $DOMINIO apunte a http://127.0.0.1:${APP_PORT:-$(leer_env APP_PORT || echo 8080)} y envíe X-Forwarded-Proto."
else
    fijar OTI_HTTPS_CADDY 1
    PERFIL=(--profile https)
    echo "Se usará Caddy: obtiene el certificado HTTPS de $DOMINIO automáticamente (el DNS debe apuntar a este servidor)."
fi

paso "Construyendo e iniciando contenedores (puede tardar unos minutos)"
docker compose ${PERFIL[@]+"${PERFIL[@]}"} up -d --build

paso "Esperando a que la aplicación responda"
PUERTO=${APP_PORT:-$(leer_env APP_PORT || true)}; PUERTO=${PUERTO:-8080}
for i in $(seq 1 60); do
    if curl -fsS "http://127.0.0.1:$PUERTO/up" >/dev/null 2>&1; then echo "Aplicación lista."; break; fi
    [ "$i" = 60 ] && { docker compose logs --tail 40 app; error "La aplicación no respondió. Revisa los logs de arriba."; }
    sleep 3
done

paso "Cargando sedes y categorías"
docker compose exec -T app php artisan db:seed --force </dev/null

crear_cuenta() { # crear_cuenta correo nombre rol especialidad
    local salida clave
    salida=$(docker compose exec -T app php artisan oti:usuario "$1" "$2" --rol="$3" --especialidad="${4:-}" </dev/null) || { echo "$salida"; return 1; }
    clave=$(echo "$salida" | grep '^CLAVE=' | cut -d= -f2- | tr -d '\r')
    echo "$salida" | grep -v '^CLAVE='
    sed -i "/^$1 /d" "$CRED" # la clave anterior de esa cuenta ya no sirve
    printf '%-38s %-24s %s\n' "$1" "$3${4:+ ($4)}" "$clave" >> "$CRED"
}

paso "Cuentas de ingreso"
touch "$CRED"; chmod 600 "$CRED"
if [ ! -s "$CRED" ]; then
    printf 'Llama OTI - https://%s/login\nGenerado el %s. Guárdalo en un lugar seguro y borra este archivo.\n\n%-38s %-24s %s\n' \
        "$DOMINIO" "$(date '+%d/%m/%Y %H:%M')" CORREO ROL CONTRASEÑA >> "$CRED"
fi

ADMIN=$(preguntar "Correo del administrador" "$ADMIN_DEF" INSTALAR_ADMIN)
ADMIN_NOMBRE=$(preguntar "Nombre del administrador" "Redy Delgado" INSTALAR_ADMIN_NOMBRE)
crear_cuenta "$ADMIN" "$ADMIN_NOMBRE" jefe ""

# Personal indicado por variable (sin preguntas)
while IFS='|' read -r CORREO NOMBRE ROL ESP; do
    CORREO=$(echo "$CORREO" | tr -d '[:space:]')
    [ -z "$CORREO" ] && continue
    crear_cuenta "$CORREO" "$NOMBRE" "${ROL:-tecnico}" "$ESP" || aviso "No se creó $CORREO; revisa los datos."
done <<< "${INSTALAR_PERSONAL:-}"

# Personal preguntando (solo si hay alguien en la terminal)
[ -t 0 ] && {
echo
echo "Ahora el personal de la OTI (mesa de ayuda y técnicos). Deja el correo vacío para terminar."
echo "Los demás usuarios no necesitan cuenta: entran solos con su correo UNIQ y quedan como 'usuario'."
}
while [ -t 0 ]; do
    CORREO=$(preguntar "Correo" "")
    [ -z "$CORREO" ] && break
    NOMBRE=$(preguntar "  Nombre" "")
    ROL=$(preguntar "  Rol (mesa / tecnico / jefe)" "tecnico")
    ESP=""
    [ "$ROL" = tecnico ] && ESP=$(preguntar "  Especialidad (soporte / redes / servidores / web)" "soporte")
    crear_cuenta "$CORREO" "$NOMBRE" "$ROL" "$ESP" || aviso "No se creó $CORREO; revisa los datos."
done

paso "Listo"
cat <<FIN
  Sistema:       https://$DOMINIO
  Contraseñas:   $(pwd)/$CRED  (solo legible por root)
  Actualizar:    ./actualizar.sh

  Pendiente:
  1. DNS: un registro A de $DOMINIO hacia la IP pública de este servidor.
  2. Google Cloud: la URI de redirección https://$DOMINIO/auth/google/callback debe estar registrada$([ -z "$GID" ] && echo ' y faltan las credenciales: vuelve a correr ./instalar.sh para pegarlas').
  3. Acceso de emergencia con contraseña: https://$DOMINIO/login/clave (desactívalo con OTI_LOGIN_PASSWORD=false en .env y ./actualizar.sh).
FIN
