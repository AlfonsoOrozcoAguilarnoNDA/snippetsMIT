#!/usr/bin/env bash
# SPDX-License-Identifier: MIT
# Copyright (c) 2026
# =============================================================================
#  EXPORTAR EL ESQUEMA (SOLO ESTRUCTURA, SIN DATOS) DE LA BASE DE DATOS "crm"
# =============================================================================
#
#  ¿QUÉ ES ESTE SCRIPT?
#    Genera un archivo con la ESTRUCTURA de la base de datos "crm" (tablas,
#    columnas, índices, vistas, procedimientos, triggers y eventos) y un
#    archivo con la versión del motor (MySQL o MariaDB) y su configuración
#    básica de charset/collation.
#
#  ¿PARA QUÉ SIRVE? (CASO DE USO)
#    Infraestructura necesita COMPARAR la estructura de la base "crm" entre
#    varios servidores (hoy son 5 o más con configuraciones distintas) para
#    saber en qué se diferencian. Este script se ejecuta en cada servidor y la
#    salida se devuelve a la persona responsable para compararla.
#
#  ¿QUÉ NO HACE?
#    - NO exporta datos de la aplicación (ni clientes, ni usuarios, ni claves).
#    - NO modifica nada: no ejecuta INSERT/UPDATE/DELETE/ALTER/DROP, no cambia
#      configuración, no reinicia servicios. Solo hace consultas de lectura
#      (SELECT/SHOW) y un mysqldump con --no-data.
#    - NO guarda la contraseña: se pide al ejecutar, se usa en un archivo
#      temporal con permisos 600 y se borra al terminar.
#
#  ¿QUÉ ESCRIBE?
#    Únicamente una carpeta nueva "esquema_crm_<servidor>_<fecha>" y su
#    .tar.gz, en el directorio actual (o en /tmp si no hay permiso de escritura).
#
#  SI ENCUENTRAS ESTE ARCHIVO EN UN SERVIDOR:
#    Es una herramienta temporal de diagnóstico. Una vez enviada la salida a
#    quien la solicitó (ver RESPONSABLE abajo) puede borrarse junto con la
#    carpeta y el .tar.gz que generó.
#
#  USO:
#    chmod +x exportar_esquema_crm.sh
#    ./exportar_esquema_crm.sh            (pide usuario y contraseña)
#    sudo ./exportar_esquema_crm.sh       (Enter en usuario = entrar como root
#                                          por socket, sin contraseña)
# =============================================================================

# ---------------------------- CONFIGURACIÓN ----------------------------------
DB_NAME="crm"
DB_HOST=""            # vacío = socket local. Ej: 127.0.0.1
DB_PORT=""            # vacío = puerto por defecto
RESPONSABLE="[NOMBRE / CORREO DE QUIEN RECIBE LA SALIDA]"
SCRIPT_VERSION="1.0"
# -----------------------------------------------------------------------------

umask 077   # lo que se genere solo lo puede leer quien ejecuta el script

HOST_NAME="$(hostname 2>/dev/null || echo desconocido)"
HOST_SAFE="$(printf '%s' "$HOST_NAME" | tr -c 'A-Za-z0-9_.-' '_')"
STAMP="$(date +%Y%m%d_%H%M%S)"

pick_bin() {
  local b
  for b in "$@"; do
    if command -v "$b" >/dev/null 2>&1; then command -v "$b"; return 0; fi
  done
  return 1
}

MYSQL_BIN="$(pick_bin mariadb mysql)"
DUMP_BIN="$(pick_bin mariadb-dump mysqldump)"
if [ -z "$MYSQL_BIN" ] || [ -z "$DUMP_BIN" ]; then
  echo "ERROR: no se encontró el cliente mysql/mariadb o mysqldump/mariadb-dump."
  echo "       Instalar el paquete cliente (p. ej. mariadb-client o mysql-client)."
  exit 1
fi

OUT_BASE="${OUT_BASE:-$PWD}"
if [ ! -w "$OUT_BASE" ]; then
  echo "AVISO: no hay permiso de escritura en $OUT_BASE, se usará /tmp"
  OUT_BASE="/tmp"
fi
OUT_NAME="esquema_${DB_NAME}_${HOST_SAFE}_${STAMP}"
OUT_DIR="$OUT_BASE/$OUT_NAME"
mkdir -p "$OUT_DIR" || { echo "ERROR: no se pudo crear $OUT_DIR"; exit 1; }
ERR="$OUT_DIR/errores.txt"
: > "$ERR"

CNF=""
TMP_DUMP="$OUT_DIR/.tmp_dump.sql"
trap 'rm -f "$CNF" "$TMP_DUMP"' EXIT
trap 'exit 1' INT TERM

# ----------------------------- CREDENCIALES ----------------------------------
echo "=== Exportación del esquema de '$DB_NAME' (solo lectura) ==="
read -r -p "Usuario de MySQL/MariaDB (Enter = usuario del sistema por socket, p. ej. root con sudo): " DB_USER
AUTH=()
if [ -n "$DB_USER" ]; then
  read -r -s -p "Contraseña de $DB_USER: " DB_PASS
  echo
  CNF="$(mktemp)"
  PW_ESC=${DB_PASS//\\/\\\\}
  PW_ESC=${PW_ESC//\"/\\\"}
  {
    echo "[client]"
    echo "user=\"$DB_USER\""
    echo "password=\"$PW_ESC\""
  } > "$CNF"
  AUTH=(--defaults-extra-file="$CNF")
  unset DB_PASS PW_ESC
fi
[ -n "$DB_HOST" ] && AUTH+=(--host="$DB_HOST")
[ -n "$DB_PORT" ] && AUTH+=(--port="$DB_PORT")

q() { "$MYSQL_BIN" "${AUTH[@]}" --batch --skip-column-names -e "$1" 2>>"$ERR"; }
qv() { local r; r="$(q "$1")"; if [ -n "$r" ]; then echo "$r"; else echo "N/D"; fi; }

# ------------------------ CONEXIÓN Y EXISTENCIA DE LA BD ---------------------
if ! q "SELECT 1;" >/dev/null; then
  echo "ERROR: no se pudo conectar al servidor de base de datos."
  echo "       Detalle en: $ERR"
  exit 1
fi
EXISTS="$(q "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';")"
if [ "$EXISTS" != "1" ]; then
  echo "ERROR: la base de datos '$DB_NAME' no existe en este servidor (o el usuario no la ve)."
  exit 1
fi

# ------------------------------ MOTOR Y VERSIÓN ------------------------------
VERSION="$(qv "SELECT VERSION();")"
VCOMMENT="$(qv "SELECT @@version_comment;")"
case "$VERSION $VCOMMENT" in
  *[Mm]aria[Dd][Bb]*) FLAVOR="MariaDB" ;;
  *Percona*)          FLAVOR="Percona Server (compatible MySQL)" ;;
  *)                  FLAVOR="MySQL" ;;
esac
OS_NAME="$( ( . /etc/os-release 2>/dev/null && echo "$PRETTY_NAME" ) )"
[ -z "$OS_NAME" ] && OS_NAME="N/D"

# ----------------------------- INFO DEL SERVIDOR -----------------------------
{
  echo "INFORMACIÓN DEL SERVIDOR (solo lectura) - exportar_esquema_crm.sh v$SCRIPT_VERSION"
  echo "Propósito: comparar la estructura de la base '$DB_NAME' entre servidores."
  echo "Devolver a: $RESPONSABLE"
  echo
  echo "== Servidor =="
  echo "Hostname:             $HOST_NAME"
  echo "IP(s):                $(hostname -I 2>/dev/null || echo N/D)"
  echo "Fecha:                $(date '+%Y-%m-%d %H:%M:%S %Z')"
  echo "Sistema operativo:    $OS_NAME"
  echo "Debian version:       $(cat /etc/debian_version 2>/dev/null || echo N/D)"
  echo
  echo "== Motor de base de datos =="
  echo "Motor detectado:      $FLAVOR"
  echo "Versión del servidor: $VERSION"
  echo "Comentario versión:   $VCOMMENT"
  echo "Cliente (consultas):  $("$MYSQL_BIN" --version 2>&1 | head -n1)"
  echo "Cliente (dump):       $("$DUMP_BIN" --version 2>&1 | head -n1)"
  echo "Charset/collation servidor: $(qv "SELECT CONCAT(@@character_set_server,' / ',@@collation_server);")"
  echo "Motor de tablas por defecto: $(qv "SELECT @@default_storage_engine;")"
  echo "lower_case_table_names: $(qv "SELECT @@lower_case_table_names;")"
  echo "sql_mode:             $(qv "SELECT @@sql_mode;")"
  echo
  echo "== Base de datos '$DB_NAME' =="
  echo "Charset/collation BD: $(qv "SELECT CONCAT(DEFAULT_CHARACTER_SET_NAME,' / ',DEFAULT_COLLATION_NAME) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}';")"
  echo "Tablas:               $(qv "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_TYPE='BASE TABLE';")"
  echo "Vistas:               $(qv "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_TYPE='VIEW';")"
  echo "Rutinas:              $(qv "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='${DB_NAME}';")"
  echo "Triggers:             $(qv "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='${DB_NAME}';")"
  echo "Eventos:              $(qv "SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='${DB_NAME}';")"
  echo
  echo "== Tablas y vistas (nombre | tipo | motor | collation | row_format) =="
  q "SELECT CONCAT_WS(' | ', TABLE_NAME, TABLE_TYPE, IFNULL(ENGINE,'-'), IFNULL(TABLE_COLLATION,'-'), IFNULL(ROW_FORMAT,'-')) FROM information_schema.TABLES WHERE TABLE_SCHEMA='${DB_NAME}';" | LC_ALL=C sort
  echo
  echo "== Rutinas (tipo | nombre) =="
  q "SELECT CONCAT_WS(' | ', ROUTINE_TYPE, ROUTINE_NAME) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='${DB_NAME}';" | LC_ALL=C sort
  echo
  echo "== Triggers (nombre | tabla) =="
  q "SELECT CONCAT_WS(' | ', TRIGGER_NAME, EVENT_OBJECT_TABLE) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='${DB_NAME}';" | LC_ALL=C sort
  echo
  echo "== Eventos =="
  q "SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA='${DB_NAME}';" | LC_ALL=C sort
} > "$OUT_DIR/info_servidor.txt"

# --------------------------------- VOLCADO -----------------------------------
# Solo estructura. Se intenta el volcado completo y, si faltan permisos o
# alguna opción no existe en esa versión, se reintenta con menos objetos y se
# deja anotado en el encabezado qué modo funcionó.
DUMP_BASE=(--no-data --skip-comments --skip-dump-date --skip-add-drop-table --skip-lock-tables)
DUMP_OK=""
DUMP_MODE=""

try_dump() {
  local label="$1"; shift
  "$DUMP_BIN" "${AUTH[@]}" "${DUMP_BASE[@]}" "$@" "$DB_NAME" > "$TMP_DUMP" 2>>"$ERR"
  local rc=$?
  if [ $rc -eq 0 ] && [ -s "$TMP_DUMP" ]; then
    DUMP_OK=1
    DUMP_MODE="$label"
    return 0
  fi
  echo "[intento '$label' falló, código $rc]" >> "$ERR"
  return 1
}

ATTEMPTS=(
  "completo (tablas, vistas, rutinas, triggers, eventos)|--routines --triggers --events"
  "completo con --no-tablespaces|--routines --triggers --events --no-tablespaces"
  "sin eventos (posible falta de permiso EVENT)|--routines --triggers"
  "sin eventos, con --no-tablespaces|--routines --triggers --no-tablespaces"
  "sin rutinas ni eventos (posible falta de permisos)|--triggers"
  "sin rutinas ni eventos, con --no-tablespaces|--triggers --no-tablespaces"
)
for a in "${ATTEMPTS[@]}"; do
  label="${a%%|*}"
  flags="${a#*|}"
  # shellcheck disable=SC2086
  try_dump "$label" $flags && break
done

write_header() {
  cat <<EOF
-- ============================================================================
-- ESQUEMA (SOLO ESTRUCTURA, SIN DATOS) DE LA BASE DE DATOS "$DB_NAME"
-- Generado con exportar_esquema_crm.sh v$SCRIPT_VERSION
-- Uso: comparar la estructura entre servidores (solicitado por infraestructura).
-- NO contiene datos de la aplicacion. NO esta pensado para restaurarse ni ejecutarse.
-- Si lo encuentras en un servidor, puedes borrarlo despues de enviarlo a:
--   $RESPONSABLE
-- Modo de exportacion: $DUMP_MODE
EOF
  if [ "$1" = "normalizado" ]; then
    echo "-- Version NORMALIZADA: sin AUTO_INCREMENT=N y sin DEFINER, para evitar diferencias falsas."
  else
    echo "-- Version RAW: tal como la entrego el dump."
  fi
  echo "-- ============================================================================"
}

RAW="$OUT_DIR/esquema_${DB_NAME}_${HOST_SAFE}_raw.sql"
NORM="$OUT_DIR/esquema_${DB_NAME}_${HOST_SAFE}_normalizado.sql"

if [ -n "$DUMP_OK" ]; then
  { write_header raw; cat "$TMP_DUMP"; } > "$RAW"
  {
    write_header normalizado
    sed -E -e 's/ AUTO_INCREMENT=[0-9]+//g' -e 's/DEFINER=`[^`]*`@`[^`]*`//g' "$TMP_DUMP"
  } > "$NORM"
  rm -f "$TMP_DUMP"
else
  echo "ERROR: no fue posible generar el volcado del esquema. Ver $ERR"
fi

# --------------------------------- LEEME -------------------------------------
cat > "$OUT_DIR/LEEME.txt" <<EOF
CONTENIDO DE ESTA CARPETA
-------------------------
Generada el $(date '+%Y-%m-%d %H:%M:%S') en el servidor $HOST_NAME por exportar_esquema_crm.sh v$SCRIPT_VERSION.

Es la exportacion de la ESTRUCTURA (esquema) de la base de datos "$DB_NAME",
SIN DATOS de la aplicacion. Su unico fin es comparar la estructura entre
servidores, a solicitud de infraestructura. No se modifico nada en el servidor.

Archivos:
  info_servidor.txt                  Motor (MySQL/MariaDB), version, charset, lista de objetos.
  esquema_${DB_NAME}_*_raw.sql          Esquema tal como lo entrego mysqldump.
  esquema_${DB_NAME}_*_normalizado.sql  Igual, sin AUTO_INCREMENT ni DEFINER (para comparar).
  sha256.txt                         Huellas de los archivos anteriores.
  errores.txt                        Mensajes de error/aviso (si esta vacio, todo salio bien).

Que hacer: enviar el archivo .tar.gz (o esta carpeta) a: $RESPONSABLE
Despues se puede borrar esta carpeta, el .tar.gz y el script exportar_esquema_crm.sh.
EOF

# ------------------------------- HUELLAS Y EMPAQUETADO -----------------------
if command -v sha256sum >/dev/null 2>&1; then
  ( cd "$OUT_DIR" && sha256sum ./*.sql info_servidor.txt > sha256.txt ) 2>>"$ERR"
fi

ARCHIVE="$OUT_BASE/$OUT_NAME.tar.gz"
PACKED=""
if command -v tar >/dev/null 2>&1; then
  if tar -czf "$ARCHIVE" -C "$OUT_BASE" "$OUT_NAME" 2>>"$ERR"; then PACKED=1; fi
fi

echo
echo "=============================================================="
echo " Motor detectado : $FLAVOR $VERSION"
echo " Modo de volcado : ${DUMP_MODE:-FALLÓ (ver errores.txt)}"
echo " Carpeta         : $OUT_DIR"
if [ -n "$PACKED" ]; then
  echo " Archivo a enviar: $ARCHIVE"
else
  echo " (No se pudo crear el .tar.gz; enviar la carpeta completa)"
fi
echo " Enviar a        : $RESPONSABLE"
echo " Después de enviarlo, borrar la carpeta, el .tar.gz y este script."
echo "=============================================================="
[ -s "$ERR" ] && echo " Aviso: errores.txt tiene mensajes; revisarlo antes de enviar."
exit 0
