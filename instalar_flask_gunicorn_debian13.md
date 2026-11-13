# 🛠️ Manual: Nginx + 4 apps Flask (Gunicorn) + MariaDB en Debian 13

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Python](https://img.shields.io/badge/python-3.13-3776ab.svg)](https://www.python.org/)

**Stack:** Debian 13 + Nginx + SSL Certbot + Python 3.13 + Flask ×4 + Gunicorn ×4 + MariaDB 11.8 (modo no estricto, decisión consciente)  
**Plataforma base:** Vultr VPS (o similar con root real)  
**Fecha de redacción:** Noviembre 2026  
**Tiempo estimado:** ~2.5 horas con breaks

> **Estado:** pendiente de desplegar. Cuando funcione, anota aquí la fecha y el nombre del snapshot de Vultr o del server (ejemplo: `debian13-flask-2026-11-13`). En seis meses esa línea vale oro.

> **Complemento del manual LEMP:** este manual reemplaza al de LEMP + Node + React + PM2 + Gitea + Quarkus (`instalar_LEMP_debian13.md`). Aquí no hay Node, PM2, React, Gitea, Quarkus ni PHP: solo Nginx, cuatro apps Flask servidas por Gunicorn y MariaDB. Si necesitas PHP-FPM, usa el otro manual.

---

## ⚠️ Advertencia: datos reales

Este es un **entorno de pruebas personal**. Úsalo solo con datos inventados.

- No restaures dumps de producción "nomás para probar", ni siquiera por una tarde.
- No subas código ni bases de una empresa a un VPS tuyo sin autorización por escrito.
- Si algún día necesitas datos reales, el entorno correcto es uno de la organización dueña de esos datos, con sus controles.

El diseño de este manual asume que `/pruebas/` es un sandbox vacío. Por eso queda **protegido con contraseña desde el inicio**: un sandbox abierto atrae scanners que buscan paneles de admin, `.env` y endpoints de debug, aunque no haya nada dentro.

---

## ⚠️ Advertencia: modo no estricto en MariaDB

Este manual configura MariaDB con `sql_mode = "NO_ENGINE_SUBSTITUTION"`, lo que **desactiva el modo estricto**. Consecuencias:

- Se aceptan fechas `0000-00-00` y fechas parcialmente inválidas (`2015-00-12`) con **warning**, no con error.
- Se truncan los datos en lugar de fallar (textos largos y también números fuera de rango).
- Se aceptan inserts sin valor en columnas `NOT NULL`, con warning.

**Es una decisión consciente para poder correr un sistema legacy que depende de esos valores.** No es un descuido. En un proyecto nuevo, el modo estricto debe estar activo.

> **Alternativa más segura:** en lugar de aflojar el modo en todo el servidor, afloja solo la conexión del legacy. Con SQLAlchemy:
> `create_engine(url, connect_args={"init_command": "SET sql_mode='NO_ENGINE_SUBSTITUTION'"})`
> Así lo que escribas nuevo sigue siendo estricto. Si prefieres esto, omite el `sql_mode` del paso 7.

Si migras el legacy a un esquema limpio, vuelve a activar el modo estricto:

```ini
sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
```

---

## ⚠️ Advertencia: cuatro apps, cuatro procesos, cuatro puntos de falla

Este manual despliega **cuatro apps Flask independientes**, no un monolito con cuatro blueprints. Cada una tiene:

| Recurso | Ejemplo para `crm` |
|---|---|
| Usuario del sistema | `crm` |
| Directorio de código | `/opt/crm/` |
| Virtualenv | `/opt/crm/venv/` |
| Servicio systemd (plantilla) | `flask@crm.service` |
| Socket Unix | `/run/flask-crm/gunicorn.sock` |
| Logs | `/var/log/flask-crm/` |
| Archivos generados y subidos | `/var/lib/flask-crm/` |

**Costo:** más setup inicial. **Beneficio:** cuando una falla, reinicias solo esa; las otras tres no se enteran. El **Anexo 6** tiene un script que diagnostica las cuatro en un comando.

> Para una sola app, deja un único nombre en los bucles `for APP in ...` y borra los `location` que sobren en Nginx.

---

## 📋 Premisas

1. Estás en **Vultr** con Debian 13 limpio o tienes acceso a un Debian real con root Si usas otro proveedor, necesitas cliente SSH (PuTTY u otro).
2. Tienes acceso como **`root`** con llave SSH (ver paso 3).
3. Ya tienes un **dominio apuntando a la IP del VPS**, con registro `A` para `tu-dominio.com` **y** para `www`.
4. Reemplaza `tu-dominio.com` en **todos** los scripts con tu dominio real, incluyendo extensión.
5. Tienes **cuatro proyectos Flask** separados (`crm`, `app`, `pruebas`, `api`), cada uno con su `requirements.txt` y un objeto Flask llamado `app` dentro de `app.py`. Si tu módulo u objeto se llaman distinto, solo cambia `wsgi.py` (paso 13).
6. **Toma un snapshot del VPS en Vultr antes de empezar** (instancia → Snapshots → *Take Snapshot*). Cuesta centavos y te ahorra el día que un `rm -rf` se equivoque de carpeta.

> Abre un bloc de notas y anota: IP, usuario SSH, contraseña root de MariaDB (si decides ponerle una) y la ubicación de las contraseñas generadas en el paso 8. Los necesitarás varias veces.

---

# FASE 1: Base del sistema

## 1. Actualización y paquetes

```bash
apt update && apt upgrade -y
apt install -y ufw fail2ban unattended-upgrades curl git vim htop sudo rsync \
    apache2-utils build-essential pkg-config \
    libmariadb-dev libmariadb-dev-compat \
    python3 python3-venv python3-pip python3-dev

python3 --version   # Python 3.13.x
```

> `libmariadb-dev` y `pkg-config` son obligatorios: `mysqlclient` compila contra C y sin ellos `pip install` falla. `rsync` debe estar también en el servidor, o la subida del paso 10 falla. `apache2-utils` solo trae `htpasswd`, no instala Apache.

---

## 2. Firewall (UFW)

```bash
ufw default deny incoming
ufw default allow outgoing

ufw limit 22/tcp      # SSH, con límite de intentos
ufw allow 80/tcp
ufw allow 443/tcp

ufw enable
ufw status
```

Solo deben quedar 22, 80 y 443. **Nunca** abras puertos para Gunicorn (usa sockets Unix, no hay puerto) ni para MariaDB (3306).

---

## 3. Endurecimiento básico (SSH, fail2ban, actualizaciones)

**Antes de tocar SSH:** copia tu llave pública desde tu máquina y **comprueba en una segunda terminal** que entras con ella.

```bash
# Desde tu máquina local
ssh-copy-id root@tu-ip-vultr
```

Solo cuando funcione, desactiva la contraseña:

```bash
cat > /etc/ssh/sshd_config.d/10-endurecimiento.conf <<'EOF'
PasswordAuthentication no
PermitRootLogin prohibit-password
EOF

sshd -t && systemctl reload ssh
```

Fail2ban y actualizaciones de seguridad automáticas:

```bash
systemctl enable --now fail2ban
fail2ban-client status sshd

dpkg-reconfigure -f noninteractive unattended-upgrades
```

---

## 4. Swap (recomendado en VPS de 4 GB o menos)

Si `free -h` no muestra swap:

```bash
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
free -h
```

---

## 5. Zona horaria

```bash
timedatectl set-timezone America/Mexico_City
timedatectl status
```

---

# FASE 2: MariaDB en modo no estricto

## 6. Instalación y seguridad inicial

```bash
apt install -y mariadb-server
systemctl status mariadb --no-pager

mariadb-secure-installation
```

> En Debian el usuario `root` de MariaDB ya entra por **socket Unix** (sin contraseña, solo como root del sistema). Es seguro, así que responde **n** a "Switch to unix_socket authentication" y a "Change the root password". El resto, **Y**.
>
> Por lo mismo, todos los comandos de este manual usan `mariadb` a secas, sin `-u root -p`.
>
> Si el script falla, ve al **Anexo 1**.

---

## 7. Configuración: modo no estricto + memoria

```bash
nano /etc/mysql/mariadb.conf.d/99-ajustes-legacy.cnf
```

```ini
[mysqld]
# Modo no estricto: permite 0000-00-00 y datos truncados (ver advertencia)
sql_mode = "NO_ENGINE_SUBSTITUTION"

# Memoria (ajusta según la RAM del VPS)
innodb_buffer_pool_size = 512M
max_connections = 200
```

```bash
systemctl restart mariadb

# Debe mostrar solo NO_ENGINE_SUBSTITUTION
mariadb -e "SELECT @@GLOBAL.sql_mode;"

# Debe mostrar 536870912
mariadb -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"

# MariaDB solo debe escuchar en 127.0.0.1
ss -tlnp | grep 3306

# Prueba: se acepta 0000-00-00
mariadb -e "
  CREATE DATABASE test_zero;
  USE test_zero;
  CREATE TABLE t (d DATE);
  INSERT INTO t VALUES ('0000-00-00');
  SELECT * FROM t;
  DROP DATABASE test_zero;
"
```

> Si el `sql_mode` no cambia, otro archivo lo está sobreescribiendo. Ver **Anexo 2**.

---

## 8. Crear bases de datos y usuarios

Tres bases en MariaDB (`crm`, `app`, `api`). `/pruebas/` usa SQLite local, por diseño (sandbox vacío): no se crea `db_pruebas` ni `user_pruebas`. Si algún día lo necesita, se crea entonces.

Las contraseñas se **generan al azar** y se guardan en `/root/secretos/` para que el paso 12 las use; no hay contraseñas escritas a mano que se puedan desincronizar:

```bash
install -d -m 700 /root/secretos

for APP in crm app api; do
  PASS=$(openssl rand -hex 16)
  echo -n "$PASS" > /root/secretos/db_$APP
  chmod 600 /root/secretos/db_$APP

  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS db_$APP CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'user_$APP'@'localhost' IDENTIFIED BY '$PASS';
ALTER USER 'user_$APP'@'localhost' IDENTIFIED BY '$PASS';
GRANT ALL PRIVILEGES ON db_$APP.* TO 'user_$APP'@'localhost';
SQL
done

# Verifica
mariadb -e "SHOW DATABASES;"                     # db_crm, db_app, db_api (sin db_pruebas)
mariadb -e "SELECT User, Host FROM mysql.user;"  # user_crm, user_app, user_api
```

> `ALL PRIVILEGES` sobre su propia base es lo mínimo razonable para un legacy que crea tablas. Cada usuario ve solo su base: si una app se compromete, las demás bases no.

---

# FASE 3: Cuatro apps Flask + Gunicorn

## 9. Usuarios del sistema y directorios

```bash
for APP in crm app pruebas api; do
  adduser --system --group --shell /usr/sbin/nologin --home /opt/$APP $APP
  install -d -o $APP -g $APP -m 750 /opt/$APP
done

# Verifica
id crm; id app; id pruebas; id api
```

> Los usuarios no tienen shell de login. Todos los comandos posteriores usan `sudo -H -u APP ...`, que no la necesita.
>
> Los directorios de logs, sockets y archivos generados (`/var/log/flask-APP`, `/run/flask-APP`, `/var/lib/flask-APP`) **no se crean a mano**: los crea systemd con el dueño correcto (paso 15).

---

## 10. Subir el código de cada app

Desde tu máquina local, para cada proyecto:

```bash
# Ejemplo para crm (repetir para app, pruebas, api)
rsync -avz \
  --exclude 'venv' --exclude '__pycache__' --exclude '.git' \
  --exclude '.env' --exclude '*.db' \
  ./crm/ root@tu-ip-vultr:/opt/crm/
```

> `.env` y `*.db` se excluyen a propósito: un `.env` local con credenciales reales no debe viajar al servidor. En el servidor se genera uno nuevo (paso 12).

O con git, en el servidor (el directorio debe estar vacío):

```bash
sudo -H -u crm git clone https://tu-repo/crm.git /opt/crm
# Repetir por cada app
```

Permisos (nota la `X` mayúscula: da permiso de ejecución solo a directorios, no a todos los archivos):

```bash
for APP in crm app pruebas api; do
  chown -R $APP:$APP /opt/$APP
  chmod -R u=rwX,g=rX,o= /opt/$APP
done
```

---

## 11. Virtualenv e instalación de dependencias (uno por app)

Muchos `requirements.txt` de legacy **no incluyen Gunicorn**, así que se instala aparte:

```bash
for APP in crm app pruebas api; do
  echo "=== $APP ==="
  cd /opt/$APP
  sudo -H -u $APP python3 -m venv venv
  sudo -H -u $APP venv/bin/pip install --upgrade pip
  sudo -H -u $APP venv/bin/pip install -r requirements.txt
  sudo -H -u $APP venv/bin/pip install gunicorn
  sudo -H -u $APP venv/bin/pip freeze > /opt/$APP/requirements.freeze.txt
done
```

> `requirements.freeze.txt` guarda las versiones exactas que se instalaron hoy. Si el `requirements.txt` del legacy no fija versiones, esto es lo único que hace reproducible el entorno.
>
> `-H` hace que pip use el HOME del usuario de la app y no el de root. Si `mysqlclient` falla, confirma `libmariadb-dev` y `pkg-config` (paso 1).

Verifica las cuatro (quita lo que una app no use):

```bash
for APP in crm app pruebas api; do
  echo "=== $APP ==="
  sudo -H -u $APP /opt/$APP/venv/bin/python -c "import flask, sqlalchemy, MySQLdb, gunicorn; print('OK')"
done
```

---

## 12. Archivos `.env` (uno por app)

```bash
# crm, app, api → MariaDB
for APP in crm app api; do
  cat > /opt/$APP/.env <<EOF
SECRET_KEY=$(openssl rand -hex 32)
SESSION_COOKIE_NAME=flask_$APP
WEB_CONCURRENCY=2
DATABASE_URL=mysql+mysqldb://user_$APP:$(cat /root/secretos/db_$APP)@localhost/db_$APP?charset=utf8mb4
DATA_DIR=/var/lib/flask-$APP
UPLOAD_DIR=/var/lib/flask-$APP/uploads
GENERATED_DIR=/var/lib/flask-$APP/generated
EOF
  chown root:$APP /opt/$APP/.env
  chmod 640 /opt/$APP/.env
done

# pruebas → SQLite local (sandbox vacío, sin MariaDB), 1 worker
cat > /opt/pruebas/.env <<EOF
SECRET_KEY=$(openssl rand -hex 32)
SESSION_COOKIE_NAME=flask_pruebas
WEB_CONCURRENCY=1
DATABASE_URL=sqlite:////opt/pruebas/pruebas.db
DATA_DIR=/var/lib/flask-pruebas
UPLOAD_DIR=/var/lib/flask-pruebas/uploads
GENERATED_DIR=/var/lib/flask-pruebas/generated
EOF
chown root:pruebas /opt/pruebas/.env
chmod 640 /opt/pruebas/.env
```

Notas:

- El `.env` es de `root` y legible por el grupo de la app: la app puede leerlo pero no modificarlo.
- `WEB_CONCURRENCY` es la variable que Gunicorn lee como número de workers. 4 apps × 2 workers = 8 procesos; con pandas/numpy (~100 MB cada uno) son ~800 MB. En un VPS de 4 GB, con MariaDB (512 MB de buffer) y Nginx, cabe. **Con 2 GB, pon `1` en las cuatro.**
- `SESSION_COOKIE_NAME` distinto por app es importante: las cuatro viven en el mismo dominio y, con el nombre por defecto (`session`), se pisarían las cookies de sesión y cerrarían la sesión entre sí.
- **Ajusta los nombres de variable a lo que lea tu legacy.** Búscalos con `grep -rnE "environ|getenv|dotenv" --include=*.py /opt/crm`.

Verifica sin imprimir secretos:

```bash
ls -l /opt/*/.env
grep -c . /opt/crm/.env
```

---

## 13. `wsgi.py` (uno por app)

Detrás de Nginx, Flask necesita saber el protocolo, el host y el prefijo real (`/crm`) para construir bien redirecciones y `url_for`. Se resuelve con `ProxyFix`, sin tocar el código legacy:

```bash
for APP in crm app pruebas api; do
  cat > /opt/$APP/wsgi.py <<'EOF'
import os
from werkzeug.middleware.proxy_fix import ProxyFix

from app import app  # ajusta si tu módulo u objeto se llaman distinto

app.wsgi_app = ProxyFix(app.wsgi_app, x_for=1, x_proto=1, x_host=1, x_prefix=1)

if os.environ.get("SECRET_KEY"):
    app.config["SECRET_KEY"] = os.environ["SECRET_KEY"]

app.config.update(
    SESSION_COOKIE_NAME=os.environ.get("SESSION_COOKIE_NAME", "session"),
    SESSION_COOKIE_SECURE=True,      # solo viaja por HTTPS (no funcionará en HTTP plano)
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE="Lax",
)
EOF
  chown $APP:$APP /opt/$APP/wsgi.py
  chmod 640 /opt/$APP/wsgi.py
done
```

> Esto solo protege la cookie de sesión de Flask. Si el legacy guarda datos en cookies propias (rol, nombre de usuario), el servidor **no** las protege: eso se corrige en el código de la app, no en este manual.

---

## 14. Probar cada Gunicorn manualmente antes de systemd

Una por una, para aislar fallos:

```bash
# CRM
cd /opt/crm
sudo -H -u crm bash -c 'set -a; . ./.env; set +a; exec venv/bin/gunicorn --workers 1 --bind 127.0.0.1:8001 wsgi:app'

# En otra terminal:
curl -I http://127.0.0.1:8001/
# Ctrl+C cuando responda
```

Repite para las otras tres (puertos 8002, 8003 y 8004) y confirma que cada una responde **antes** de pasar a systemd. Cualquier código HTTP vale (200, 302, 401, 404): lo que importa es que responda. Si una falla aquí, no sigas: el problema está en esa app, no en Nginx.

---

## 15. Servicio systemd (una plantilla para las cuatro)

Un solo archivo, `flask@.service`, sirve para todas: `%i` es el nombre de la app.

```bash
cat > /etc/systemd/system/flask@.service <<'EOF'
[Unit]
Description=Flask (Gunicorn) - %i
After=network.target mariadb.service
Wants=mariadb.service

[Service]
Type=notify
User=%i
Group=www-data
WorkingDirectory=/opt/%i
EnvironmentFile=/opt/%i/.env
Environment=PATH=/opt/%i/venv/bin:/usr/bin:/bin
ExecStartPre=/usr/bin/mkdir -p /var/lib/flask-%i/uploads /var/lib/flask-%i/generated
ExecStart=/opt/%i/venv/bin/gunicorn \
    --bind unix:/run/flask-%i/gunicorn.sock \
    --umask 007 \
    --access-logfile /var/log/flask-%i/access.log \
    --error-logfile /var/log/flask-%i/error.log \
    --timeout 120 \
    wsgi:app
ExecReload=/bin/kill -s HUP $MAINPID
Restart=on-failure
RestartSec=5s

# systemd crea estos directorios con el dueño correcto
RuntimeDirectory=flask-%i
RuntimeDirectoryMode=0750
StateDirectory=flask-%i
StateDirectoryMode=0750
LogsDirectory=flask-%i
LogsDirectoryMode=0750

# Endurecimiento básico
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectSystem=full

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
```

Por qué así:

- **El socket vive en `/run/flask-APP/`**, no en `/run/`: el usuario de la app no puede crear archivos directamente en `/run`, y `RuntimeDirectory` le entrega su propio directorio.
- **`Group=www-data` + `--umask 007`** dejan el socket como `0660` con grupo `www-data`, para que Nginx pueda leerlo y nadie más.
- **`Wants=` y no `Requires=`** con MariaDB: con `Requires=`, un `systemctl restart mariadb` reiniciaría también las cuatro apps.
- **Los logs los crea `LogsDirectory`**: si el directorio fuera de `root`, Gunicorn no podría escribir su log.
- `Type=notify` es el modo documentado de Gunicorn para systemd.

**Arranca uno primero, verifica, y luego el resto:**

```bash
systemctl enable --now flask@crm
systemctl status flask@crm --no-pager
ls -la /run/flask-crm/gunicorn.sock
tail -20 /var/log/flask-crm/error.log
```

Si `crm` va bien:

```bash
for APP in app pruebas api; do
  systemctl enable --now flask@$APP
done

for APP in crm app pruebas api; do
  echo "=== $APP ==="
  systemctl status flask@$APP --no-pager | head -5
  ls -la /run/flask-$APP/gunicorn.sock
done
```

> **No arranques las cuatro en un solo bucle sin verificar**: si `crm` no arranca, el bucle sigue y arranca los otros tres.

---

# FASE 4: Nginx + SSL

## 16. Instalar Nginx

```bash
apt install -y nginx

# No revelar la versión de Nginx
echo 'server_tokens off;' > /etc/nginx/conf.d/00-hardening.conf
```

---

## 17. Configuración del sitio (HTTP primero)

Primero, la contraseña para `/pruebas/` y un snippet con los encabezados que comparten las cuatro apps:

```bash
htpasswd -c /etc/nginx/.htpasswd-pruebas pruebas

cat > /etc/nginx/snippets/flask-proxy.conf <<'EOF'
proxy_set_header Host              $host;
proxy_set_header X-Real-IP         $remote_addr;
proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host  $host;
EOF
```

Ahora el sitio:

```bash
nano /etc/nginx/sites-available/tu-dominio.com
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tu-dominio.com www.tu-dominio.com;

    access_log /var/log/nginx/tu-dominio.access.log;
    error_log  /var/log/nginx/tu-dominio.error.log;

    client_max_body_size 32M;
    proxy_connect_timeout 10s;
    proxy_send_timeout    120s;
    proxy_read_timeout    120s;

    location = / { return 302 /crm/; }

    # CRM
    location = /crm { return 301 /crm/; }
    location /crm/ {
        proxy_pass http://unix:/run/flask-crm/gunicorn.sock:/;
        include snippets/flask-proxy.conf;
        proxy_set_header X-Forwarded-Prefix /crm;
    }

    # App principal
    location = /app { return 301 /app/; }
    location /app/ {
        proxy_pass http://unix:/run/flask-app/gunicorn.sock:/;
        include snippets/flask-proxy.conf;
        proxy_set_header X-Forwarded-Prefix /app;
    }

    # Pruebas (sandbox, protegido con contraseña)
    location = /pruebas { return 301 /pruebas/; }
    location /pruebas/ {
        auth_basic "Solo autorizado";
        auth_basic_user_file /etc/nginx/.htpasswd-pruebas;
        proxy_pass http://unix:/run/flask-pruebas/gunicorn.sock:/;
        include snippets/flask-proxy.conf;
        proxy_set_header X-Forwarded-Prefix /pruebas;
    }

    # API
    location = /api { return 301 /api/; }
    location /api/ {
        proxy_pass http://unix:/run/flask-api/gunicorn.sock:/;
        include snippets/flask-proxy.conf;
        proxy_set_header X-Forwarded-Prefix /api;
    }

    # Archivos ocultos (menos /.well-known, que necesita Certbot)
    location ~ /\.(?!well-known) {
        deny all;
        access_log off;
        log_not_found off;
    }
}
```

**Cómo funciona el `proxy_pass`:**

- `http://unix:/run/flask-crm/gunicorn.sock:/;` — el `:` antes de la ruta es obligatorio; sin él, Nginx intenta leer la ruta como parte del nombre del socket.
- La `/` final **quita el prefijo**: Flask recibe `/login` cuando el navegador pidió `/crm/login`, y `X-Forwarded-Prefix` + `ProxyFix` hacen que las URL generadas vuelvan a llevar `/crm`.
- **Si tu legacy ya define sus rutas con el prefijo** (`@app.route("/crm/login")`), cambia a `proxy_pass http://unix:/run/flask-crm/gunicorn.sock;` (sin ruta, pasa el path completo) y borra la línea `X-Forwarded-Prefix` de esa app. Si lo mezclas, obtendrás 404 en `/crm/` aunque `/` funcione.

---

## 18. Activar el sitio y verificar antes de SSL

```bash
ln -s /etc/nginx/sites-available/tu-dominio.com /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl reload nginx

curl -I http://tu-dominio.com/crm/
curl -I http://tu-dominio.com/app/
curl -I http://tu-dominio.com/pruebas/   # debe dar 401 (pide contraseña)
curl -I http://tu-dominio.com/api/
```

Los de `crm`, `app` y `api` deben dar 200, 302, 401 o 404 (la app respondió). Si dan **502**, el socket de esa app no responde o tiene permisos incorrectos: ver **Anexo 3**.

---

## 19. Certbot (SSL)

> El dominio (y `www`) debe apuntar a la IP del VPS **antes** de ejecutar esto.

```bash
apt install -y certbot python3-certbot-nginx

certbot --nginx -d tu-dominio.com -d www.tu-dominio.com \
  --redirect -m tu@correo.com --agree-tos

# Verifica la renovación automática (simulación, no emite nada)
certbot renew --dry-run
systemctl list-timers | grep certbot
```

Verifica:

```bash
nginx -t && systemctl reload nginx
curl -I https://tu-dominio.com/crm/
curl -I http://tu-dominio.com/crm/       # debe redirigir (301) a https
```

Ahora sí puedes probar el login: la cookie de sesión lleva `Secure` y solo viaja por HTTPS.

---

## ✅ Checklist final

```bash
# Sistema
free -h                                              # swap presente si RAM <= 4GB
timedatectl | grep "Time zone"                       # America/Mexico_City
python3 --version                                    # 3.13.x
fail2ban-client status sshd                          # jail activo

# MariaDB
systemctl status mariadb --no-pager                  # active (running)
mariadb -e "SELECT @@GLOBAL.sql_mode;"               # NO_ENGINE_SUBSTITUTION
mariadb -e "SHOW DATABASES;"                         # db_crm, db_app, db_api (sin db_pruebas)
ss -tlnp | grep 3306                                 # solo 127.0.0.1

# Las cuatro apps: imports OK
for APP in crm app pruebas api; do
  echo "=== $APP ==="
  sudo -H -u $APP /opt/$APP/venv/bin/python -c "import flask, sqlalchemy, MySQLdb, gunicorn; print('OK')"
done

# Los cuatro servicios
for APP in crm app pruebas api; do
  echo "=== $APP ==="
  systemctl is-active flask@$APP                     # active
  ls -la /run/flask-$APP/gunicorn.sock               # srw-rw---- APP www-data
done

# /pruebas/ usa SQLite y está protegido
grep DATABASE_URL /opt/pruebas/.env                  # sqlite:////opt/pruebas/pruebas.db
curl -s -o /dev/null -w "%{http_code}\n" https://tu-dominio.com/pruebas/   # 401

# Nginx y rutas
systemctl status nginx --no-pager                    # active (running)
nginx -t                                             # syntax is ok
for RUTA in crm app pruebas api; do
  echo "=== /$RUTA/ ==="
  curl -sI https://tu-dominio.com/$RUTA/ | head -1
done

# SSL
certbot renew --dry-run                              # success

# Firewall y puertos
ufw status                                           # solo 22, 80, 443
ss -tlnp                                             # nada de 8000-8004 ni 3306 hacia afuera
```

---

## 📝 Notas finales

- Sustituye `tu-dominio.com` en **todos** los pasos.
- Nunca expongas credenciales en repositorios públicos. Los `.env` van excluidos del control de versiones.
- No hay puertos de Gunicorn: las apps hablan con Nginx por sockets Unix. MariaDB (3306) **nunca debe abrirse en UFW**.
- Documenta qué versión de Python, Flask, Gunicorn y MariaDB instalaste y en qué fecha (`requirements.freeze.txt` ayuda con la parte de Python).
- **Sobre el modo no estricto:** es deuda técnica consciente. Anótalo como pendiente para cuando el legacy migre a un esquema limpio.
- **Sobre cuatro apps:** el **Anexo 6** diagnostica las cuatro en un comando. Úsalo cada vez que algo falle, antes de tocar nada a mano.
- **Sobre archivos generados** (PDFs, QRs, uploads): el manual los espera en `/var/lib/flask-APP/`. Si el legacy los escribe en otra parte, ver el **Anexo 7**.
- **Sobre respaldos:** el **Anexo 8** es lo mínimo. Un VPS sin snapshot y sin dump diario es una bomba de tiempo.
- **Opcional, después de que HTTPS funcione:** agrega HSTS (`add_header Strict-Transport-Security "max-age=300" always;`) con un valor bajo y súbelo cuando estés seguro de que todo sirve por HTTPS.

### Lo que suele aparecer en la semana 2

Nada de esto invalida el trabajo; son las cosas que siempre salen después:

- **Rutas hardcodeadas en el legacy** (`/var/www/html/facturas/`, `C:\xampp\...`). Decide por app, no global: parchar el código para leer `GENERATED_DIR` del `.env`, o crear un symlink desde la ruta vieja. Ver **Anexo 7**.
- **pandas + numpy en cuatro venvs** son cuatro copias de las mismas librerías (~400 MB extra de disco). No importa hoy; ténlo en el radar si el disco es pequeño.
- **`mysqlclient` compila contra `libmariadb-dev`.** Si Debian actualiza esa librería y haces `pip install` en un venv viejo, puede fallar. Anota la versión instalada (`dpkg -l libmariadb-dev`).
- **Certbot renueva cada 90 días.** Si el dominio deja de apuntar al VPS (DNS, migración), la renovación falla y a los 90 días el sitio muestra "certificado expirado", no "Certbot falló". Revisa `certbot renew --dry-run` de vez en cuando.

---

## Anexo 1: MariaDB modo seguro manual en Debian 13

Si `mariadb-secure-installation` falla:

```bash
mariadb
```

```sql
-- Revisa qué usuarios existen
SELECT User, Host FROM mysql.user;

-- Elimina usuarios anónimos (si aparecen) y la base de prueba
DROP USER IF EXISTS ''@'localhost';
DROP DATABASE IF EXISTS test;

-- Root solo local, por socket Unix
ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket;
DROP USER IF EXISTS 'root'@'%';

EXIT;
```

---

## Anexo 2: Forzar `sql_mode` cuando hay múltiples archivos

MariaDB en Debian carga archivos en orden alfabético/numérico. Si tu cambio no aplica, otro archivo lo sobreescribe. Busca cuál:

```bash
grep -r "sql_mode" /etc/mysql/
```

El archivo `99-ajustes-legacy.cnf` se carga al final. Si aun así no aplica:

```bash
systemctl restart mariadb
mariadb -e "SELECT @@GLOBAL.sql_mode;"   # Debe mostrar: NO_ENGINE_SUBSTITUTION
```

> `@@GLOBAL.sql_mode` es el valor del servidor. Una sesión ya abierta (o una app con `init_command`) puede tener otro: es el valor de `@@SESSION.sql_mode`.

---

## Anexo 3: Troubleshooting de una app específica

Cuando **una** de las cuatro falla, no toques las otras. Aísla:

```bash
# 1. ¿El servicio vive?
systemctl status flask@crm --no-pager

# 2. ¿El socket existe y con qué permisos?
ls -la /run/flask-crm/gunicorn.sock
# Esperado: srw-rw---- crm www-data

# 3. ¿Nginx puede leerlo?
sudo -u www-data ls -la /run/flask-crm/

# 4. ¿Qué dice el log de Gunicorn de ESA app?
tail -50 /var/log/flask-crm/error.log
journalctl -u flask@crm -n 50 --no-pager

# 5. ¿Qué dice el log de Nginx?
tail -50 /var/log/nginx/tu-dominio.error.log | grep crm

# 6. ¿Responde directo, sin Nginx?
curl --unix-socket /run/flask-crm/gunicorn.sock -I http://localhost/
```

Causas comunes:

- **502 y el socket no existe:** el servicio no arrancó; mira `journalctl` (casi siempre es un `import` que falla en `wsgi.py`).
- **502 y el socket existe:** permisos. Debe ser `srw-rw---- APP www-data`. Revisa `Group=www-data` y `--umask 007` en la plantilla.
- **`wsgi:app` falla al importar:** `from app import app` no coincide con tu módulo u objeto; edita `/opt/APP/wsgi.py`.
- **El `.env` no se cargó:** lo lee systemd (`EnvironmentFile`), no Gunicorn. Un error de sintaxis en el archivo lo anula.
- **Una app rompió una dependencia y otra no:** por eso el venv es separado; `pip install` en `crm` no afecta a `api`.
- **Se cierra la sesión entre apps:** dos apps con el mismo `SESSION_COOKIE_NAME`. Cada una debe tener el suyo (paso 12).

### 404 en `/crm/` pero `/` funciona

Flask no tiene la ruta registrada. Confirma si el legacy usa el prefijo en sus rutas y ajusta el `proxy_pass` como se explica al final del paso 17.

### 413 Request Entity Too Large

Sube `client_max_body_size` en Nginx (ya está en 32M).

### 504 Gateway Timeout

pandas o pysftp tardaron más de 120 s. Sube `--timeout` en la plantilla y `proxy_read_timeout` en Nginx a la par, **solo en la app que lo necesita**: crea un archivo de override con `systemctl edit flask@crm` y ajusta ahí. No subas el timeout global.

---

## Anexo 4: Cambiar la protección de `/pruebas/`

`/pruebas/` ya viene con contraseña (Basic Auth, paso 17). Dos variantes:

**Opción A — Cambiar o agregar usuarios:**

```bash
htpasswd /etc/nginx/.htpasswd-pruebas otro-usuario
```

**Opción B — Solo desde tu IP, sin contraseña:**

```nginx
location /pruebas/ {
    allow TU.IP.PUBLICA.AQUI;
    deny all;
    # ... resto de la config (proxy_pass, include, X-Forwarded-Prefix)
}
```

```bash
nginx -t && systemctl reload nginx
```

> Abrir `/pruebas/` a todo el mundo es posible (quita `auth_basic` y `auth_basic_user_file`), pero no se recomienda. Si lo haces, **nunca** lo llenes con datos reales.

---

## Anexo 5: Limpieza total — regresar a sistema limpio

> Esto **borra el código, los archivos generados y las bases**. Haz un snapshot antes.

```bash
for APP in crm app pruebas api; do
  systemctl disable --now flask@$APP 2>/dev/null || true
  rm -rf /opt/$APP /var/lib/flask-$APP /var/log/flask-$APP
  userdel $APP 2>/dev/null || true
done

rm -f /etc/systemd/system/flask@.service
systemctl daemon-reload

# Bases y usuarios de MariaDB
for APP in crm app api; do
  mariadb -e "DROP DATABASE IF EXISTS db_$APP; DROP USER IF EXISTS 'user_$APP'@'localhost';"
done
rm -rf /root/secretos

# Nginx: quita los location del sitio (conserva el bloque server)
nano /etc/nginx/sites-available/tu-dominio.com
rm -f /etc/nginx/.htpasswd-pruebas /etc/nginx/snippets/flask-proxy.conf
nginx -t && systemctl restart nginx
```

---

## Anexo 6: Script de diagnóstico de las cuatro apps

Guarda esto en `/opt/diagnose.sh`:

```bash
#!/bin/bash
# /opt/diagnose.sh — ejecutar como root
DOMINIO="tu-dominio.com"

echo "=== Estado de las 4 apps Flask ==="
echo ""

for APP in crm app pruebas api; do
  echo "─── $APP ───"

  # Servicio
  if systemctl is-active --quiet flask@$APP; then
    echo "  systemd:  OK (running)"
  else
    echo "  systemd:  FAIL ($(systemctl is-active flask@$APP))"
  fi

  # Socket
  if [ -S /run/flask-$APP/gunicorn.sock ]; then
    echo "  socket:   OK (/run/flask-$APP/gunicorn.sock)"
  else
    echo "  socket:   FAIL (no existe)"
  fi

  # HTTP directo por socket: cualquier respuesta < 500 significa que la app contesta
  CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    --unix-socket /run/flask-$APP/gunicorn.sock http://localhost/ 2>/dev/null)
  if [ "$CODE" != "000" ] && [ "$CODE" -lt 500 ] 2>/dev/null; then
    echo "  HTTP:     OK ($CODE)"
  else
    echo "  HTTP:     FAIL ($CODE)"
  fi

  # Últimos errores (si hay)
  ERRS=$(tail -5 /var/log/flask-$APP/error.log 2>/dev/null | grep -iE "error|traceback|exception" || true)
  if [ -n "$ERRS" ]; then
    echo "  últ. errores:"
    echo "$ERRS" | sed 's/^/    /'
  fi
  echo ""
done

echo "─── Nginx ───"
if systemctl is-active --quiet nginx; then
  echo "  systemd:  OK"
else
  echo "  systemd:  FAIL"
fi
nginx -t 2>&1 | sed 's/^/    /'
echo ""

echo "─── HTTPS por Nginx (los 4 endpoints) ───"
for RUTA in crm app pruebas api; do
  CODE=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMINIO/$RUTA/ 2>/dev/null)
  echo "  /$RUTA/:  $CODE"     # /pruebas/ debe dar 401
done
```

Uso:

```bash
chmod 750 /opt/diagnose.sh
/opt/diagnose.sh
```

Cuando algo falle, **primero esto**. Te dice si el problema es systemd, el socket, Gunicorn o Nginx, y en qué app. Sin adivinar.

---

## Anexo 7: Dónde guarda archivos el legacy (PDFs, QRs, uploads)

El manual espera los archivos en `/var/lib/flask-APP/{uploads,generated}` (variables `UPLOAD_DIR` y `GENERATED_DIR` del `.env`). Un legacy suele escribir donde le dio la gana. Cómo averiguarlo:

```bash
# 1. En el código: escrituras de archivos y rutas fijas
grep -rnE "\.output\(|send_file|send_from_directory|\.save\(|makedirs|open\(.*['\"][wa]b?['\"]" --include=*.py /opt/crm
grep -rnE "/var/www|/home/|C:\\\\|/tmp/" --include=*.py /opt/crm

# 2. En ejecución: genera un PDF desde la interfaz y mira qué cambió en disco
touch /tmp/marca
# ... genera el PDF/QR desde la app ...
find / -xdev -newer /tmp/marca -type f \( -name '*.pdf' -o -name '*.png' -o -name '*.xlsx' \) 2>/dev/null
```

> El servicio usa `PrivateTmp=true`: si el legacy escribe en `/tmp`, el archivo aparecerá bajo `/tmp/systemd-private-*-flask@crm.service-*/tmp/`. Con `ProtectSystem=full`, una escritura en `/usr` o `/etc` fallará con un error claro en `error.log`, lo cual también delata la ruta.

Decide **por app**, no global:

- **Parchar el código** para leer `GENERATED_DIR` del entorno (`os.environ["GENERATED_DIR"]`). Es lo correcto a largo plazo.
- **Symlink desde la ruta vieja** hacia `/var/lib/flask-APP/generated`. Es más rápido si el legacy tiene la ruta repetida en muchos lugares: `ln -s /var/lib/flask-crm/generated /ruta/vieja`.

---

## Anexo 8: Respaldo mínimo

Un snapshot de Vultr cubre el servidor entero; esto cubre las bases y los archivos con un punto de restauración diario.

```bash
install -d -m 700 /var/backups/flask

cat > /usr/local/sbin/respaldo-flask.sh <<'EOF'
#!/bin/bash
set -euo pipefail
DEST=/var/backups/flask
FECHA=$(date +%F)

for DB in db_crm db_app db_api; do
  mariadb-dump --single-transaction --routines "$DB" | gzip > "$DEST/$DB-$FECHA.sql.gz"
done

tar -czf "$DEST/archivos-$FECHA.tar.gz" /var/lib/flask-*

# Conserva 7 días
find "$DEST" -type f -mtime +7 -delete
EOF
chmod 750 /usr/local/sbin/respaldo-flask.sh

echo '30 2 * * * root /usr/local/sbin/respaldo-flask.sh' > /etc/cron.d/respaldo-flask
```

**Prueba la restauración** (un respaldo que nunca se restauró es una suposición):

```bash
/usr/local/sbin/respaldo-flask.sh
mariadb -e "CREATE DATABASE prueba_restauracion;"
gunzip -c /var/backups/flask/db_crm-$(date +%F).sql.gz | mariadb prueba_restauracion
mariadb -e "SHOW TABLES;" prueba_restauracion
mariadb -e "DROP DATABASE prueba_restauracion;"
```

> Los respaldos en el mismo disco no sirven si el VPS se pierde. Copia `/var/backups/flask/` fuera (Vultr Object Storage, `rclone` o `restic`) y guarda también el snapshot.

---

## 🧪 Notas del Autor

Este repositorio NO es parte de los experimentos documentados en **[vibecodingmexico.com](https://vibecodingmexico.com)**.

## 🤔 ¿Por qué existe este manual?

Una cosa es probar un sistema en un LAMP con Apache para cinco usuarios, y otra muy distinta es poner en producción una aplicación para 250 usuarios que trabajan al mismo tiempo con datos financieros. Para lo primero basta un despliegue simple (que ya tengo documentado en otro lado). Para lo segundo hace falta una arquitectura de varias piezas, y cada pieza resuelve un problema distinto.

250 usuarios no son 250 peticiones simultáneas, pero sí bastantes para que un solo proceso sin supervisión se convierta en el cuello de botella, y en un sistema financiero una caída o un bloqueo cuestan dinero real.

**La analogía del restaurante**

- **El servidor de desarrollo de Flask** es un chef que además atiende las mesas: toma la orden, cocina, sirve y cobra, una mesa a la vez. Sirve para probar recetas, no para un restaurante lleno. Si se cae, nadie lo reemplaza.
- **Gunicorn** es un equipo de meseros: toman varias órdenes en paralelo y dejan al chef (tu código Flask) concentrarse en cocinar. Si un mesero falla, el proceso maestro pone otro.
- **Nginx** es el anfitrión de la puerta: recibe a los clientes, maneja el HTTPS, retiene a los lentos para que no ocupen a los meseros y los manda a la mesa correcta (`/crm/`, `/api/`).
- **systemd** es el gerente que abre el restaurante cada mañana y reemplaza a quien no llegó.

En entornos serios debe usarse Gunicorn u otro servidor WSGI, detrás de un proxy inverso. El servidor de desarrollo de Flask no está pensado para producción.

**Por qué exige más experiencia**

Un despliegue simple falla de forma obvia. Este falla de formas sutiles: un 502 por el permiso de un socket, un servicio que no arranca porque un directorio pertenece a otro usuario, una cookie de sesión que se pisa entre apps. Hay que entender a la vez sockets Unix, usuarios del sistema, systemd, proxy inverso y certificados. Por eso el manual incluye un diagnóstico de las cuatro apps en un comando (Anexo 6) y una guía de fallas (Anexo 3).

- **Borrador inicial:** 13 de noviembre de 2026.
- **Revisión y correcciones:** Claude Sonnet 5.5 (Anthropic), 13 de noviembre de 2026. Los comandos YA SE PROBARON en un Debian 13 limpio: el primer despliegue real fue la prueba.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991. y DEVOPS

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

El código es tuyo para usar, copiar, modificar y distribuir. La única condición es mantener el aviso de copyright en las copias sustanciales.

---

## ✍️ Acerca del Autor

- **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
- **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)
