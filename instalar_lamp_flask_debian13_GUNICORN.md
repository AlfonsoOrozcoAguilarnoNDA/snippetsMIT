![Logo de Vibe Coding México](lampgunicornpython.jpg)
# 🛠️ Manual: Apache + Flask (Gunicorn) + MariaDB en Debian 13, con HTTPS autofirmado en red interna

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![Python](https://img.shields.io/badge/python-3.13-3776ab.svg)](https://www.python.org/)

**Stack:** Debian 13 + Apache 2.4 (proxy inverso) + HTTPS con certificado autofirmado (por IP) + Python 3.13 + Flask + Gunicorn + MariaDB 11.8 (modo no estricto, decisión consciente)  
**Entorno:** servidor en red interna, **sin dominio** (se accede por dirección IP) y **sin salida permanente a internet**  
**Público objetivo:** personal de **infraestructura** responsable del mantenimiento de servidores. No está escrito para programadores backend o frontend: el código de la aplicación y sus dependencias son responsabilidad de Desarrollo, y esta guía solo marca dónde termina una responsabilidad y empieza la otra.  
**Fecha de redacción:** Noviembre 2026  
**Tiempo estimado:** ~2 horas con breaks

> **Estado:** borrador **sin probar todavía en el servidor del cliente**. Pruébalo primero en un clon o en un snapshot. Cuando funcione, anota aquí la fecha, el nombre del snapshot y quién lo probó (ejemplo: `debian13-crm-2026-11-20`). En seis meses esa línea vale oro.

> **Adaptado de:** el manual *Nginx + Flask (Gunicorn) + MariaDB en Debian 13*. Mantiene Debian 13 y cambia el servidor web (Apache en lugar de Nginx) y el certificado (autofirmado por IP en lugar de Certbot). Se agregan: despliegue por SFTP con **reload ordenado**, instalación **sin internet**, inventario previo y una **tabla de hallazgos**.

---

## 🎯 Alcance de este manual

**Qué cubre:** dejar el CRM (Flask) corriendo de forma reproducible: Gunicorn como servicio de systemd, Apache al frente con HTTPS, MariaDB local y un procedimiento de despliegue de código que sí refleja los cambios.

**Qué NO cubre** (es responsabilidad del cliente y queda registrado en la tabla de hallazgos):

- Antivirus / EDR en las PCs Windows.
- Segmentación de red y políticas de acceso.
- Política de parches del sistema operativo (aquí solo se propone una rutina).
- Auditoría del código de la aplicación (cookies propias, SQL, validaciones).

## NO se explica instalación de PHP 

No doy guía de instalación de PHP debido a que no sé cuál es la que se usa, pero debe funcionar con los procedimientos estándar; no documento porque no sé cuál es el procedimiento estándar que usa el lector o la empresa para PHP en entorno financiero, en cuanto a módulos y versiones.

- NO es omisión es decisión deliberada. Se sugiere 8.4.27 al 17/nov/2026

---

## 📌 Hallazgos de la evaluación inicial

Esta tabla documenta lo encontrado, lo recomendado y a quién le corresponde. La última columna es para que el cliente anote su decisión (atender, posponer o aceptar el riesgo), con nombre y fecha.

| # | Hallazgo | Riesgo | Recomendación | Responsable | Decisión del cliente (nombre / fecha) |
|---|---|---|---|---|---|
| 1 | El CRM se sirve por **HTTP** (sin cifrar) | Credenciales y datos de cobranza visibles para cualquiera en la red | HTTPS con certificado autofirmado (Fase 4) | Infraestructura | |
| 2 | Servidor actual con Debian 12 (ya en su fase LTS), Apache y/o nginx **sin parches de seguridad recientes** | CVEs conocidos explotables | Construir el servidor nuevo en **Debian 13** con esta guía (Fases 1 a 4) y adoptar una ventana mensual de parches (Notas finales) | Infraestructura | |
| 3 | Gunicorn instalado por un tercero, **sin documentar** | Se desconoce versión, usuario, puerto de escucha y forma de arranque | Reinstalar con venv, versión fija y servicio systemd (Fase 3) | Infraestructura | |
| 4 | `gunicorn` **no aparece en `requirements.txt`** | Despliegue no reproducible | Se agrega con versión fija (paso 13); el repositorio debe conservarlo | Desarrollo | |
| 5 | Cambios de código subidos por SFTP **no se reflejan** | Se opera con código viejo; errores en cobranza | Reload ordenado (Fase 5) y revisar procesos duplicados (Fase 0) | Infraestructura + Desarrollo | |
| 6 | PCs Windows **sin antivirus / EDR** | Malware en equipos con acceso al CRM; HTTPS no protege contra eso | Instalar antivirus / EDR (fuera de alcance de este manual) | Cliente / TI | |
| 7 | MariaDB en **modo no estricto** | Datos truncados o inválidos se aceptan sin error | Documentado (advertencia 1); migrar a modo estricto cuando el legacy lo permita | Desarrollo | |
| 8 | Certificado **autofirmado** | Cada PC debe confiar en él; caduca | Instalarlo en los equipos (paso 23) y calendarizar la renovación (Anexo 2) | Infraestructura | |
| 9 | Servidor aislado: **sin parches automáticos** | `unattended-upgrades` no puede descargar nada sin internet | Ventana mensual con conexión temporal (Notas finales) | Infraestructura | |

---

## ⚠️ Advertencia 1: modo no estricto en MariaDB

Este manual configura MariaDB con `sql_mode = "NO_ENGINE_SUBSTITUTION"`, lo que **desactiva el modo estricto**. Consecuencias:

- Se aceptan fechas `0000-00-00` y fechas parcialmente inválidas (`2015-00-12`) con **warning**, no con error.
- Se truncan los datos en lugar de fallar (textos largos y también números fuera de rango).
- Se aceptan inserts sin valor en columnas `NOT NULL`, con warning.

**Es una decisión consciente para poder correr un sistema legacy que depende de esos valores.** No es un descuido. En un proyecto nuevo, el modo estricto debe estar activo.

> **Alternativa más segura:** en lugar de aflojar el modo en todo el servidor, afloja solo la conexión del legacy. Con SQLAlchemy:
> `create_engine(url, connect_args={"init_command": "SET sql_mode='NO_ENGINE_SUBSTITUTION'"})`
> Así lo que escribas nuevo sigue siendo estricto. Si prefieres esto, omite el `sql_mode` del paso 9.

Si se migra el legacy a un esquema limpio, vuelve a activar el modo estricto:

```ini
sql_mode = "STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
```

---

## ⚠️ Advertencia 2: `--reload` de Gunicorn y por qué NO se deja en producción

`--reload` hace que Gunicorn vigile los archivos `.py` y reinicie los workers cuando cambian. Está pensado para **desarrollo**. Con subidas por SFTP en producción tiene dos problemas, y no mantenerlo tampoco es solución:

| Situación | Qué pasa |
|---|---|
| Con `--reload` y se suben 5 archivos | Puede reiniciar varias veces, **con el despliegue a medias** (un módulo nuevo y otro viejo): errores 500 intermitentes |
| Con `--reload`, cambia una plantilla HTML o un archivo de configuración | No se entera (solo vigila los `.py` importados) |
| Sin ningún reload | Gunicorn sigue ejecutando el código viejo en memoria: **los cambios no se reflejan** |

**Lo que hace este manual:** un **reload ordenado** (`systemctl reload`, que envía la señal `HUP`): se suben **todos** los archivos y *después* se recargan los workers una sola vez, de uno en uno, sin cortar a quien está conectado. Resuelve los dos problemas a la vez. El procedimiento está en la **Fase 5**, incluida una variante automática (subir un archivo `reload.flag` al final).

Para que el reload funcione, `preload_app` debe quedar en `False` (ya viene así en la configuración del paso 16). Con `True`, el reload **no** recarga el código de la aplicación.

---

## ⚠️ Advertencia 3: qué es Gunicorn y por qué le importa a Infraestructura

El servidor de desarrollo de Flask (`flask run` / `python app.py`) atiende una petición a la vez y no está pensado para producción. **Gunicorn** es el servidor WSGI que ejecuta la aplicación Flask en producción: levanta varios procesos (*workers*), los supervisa y los reemplaza si fallan. **Apache** va delante: recibe las conexiones de los agentes, maneja el HTTPS y le pasa las peticiones a Gunicorn. **systemd** arranca todo al encender el servidor y lo reinicia si se cae.

Consecuencia práctica: **Gunicorn es parte del proyecto** y debe estar en `requirements.txt` con versión fija, igual que Flask. Infraestructura es dueña del servidor, del servicio y de las dependencias instaladas; Desarrollo es dueño del código.

---

## 📋 Premisas

1. **Debian 13 (trixie)** recién instalado, con acceso como `root` (o `sudo`) por SSH. El servidor actual del cliente corre Debian 12: esta guía se aplica a un servidor **nuevo** con Debian 13.
2. El servidor tiene una **IP fija** en la intranet. No hay dominio: los agentes entran a `https://IP-DEL-SERVIDOR/`.
3. Hay **una ventana de conexión temporal a internet** para instalar paquetes. Después se retira. Si ni siquiera eso es posible, ver el **Anexo 3** (instalación sin internet).
4. El proyecto Flask tiene `requirements.txt` y un objeto Flask llamado `app` dentro de `app.py`. Si el módulo u objeto se llaman distinto, solo cambia `wsgi.py` (paso 15). Debian 13 trae **Python 3.13**: Desarrollo debe confirmar que las dependencias del CRM lo soportan (ver paso 13).
5. Se construye en un **servidor nuevo** y se cambia la IP al final, en lugar de actualizar producción en caliente. Prueba el CRM completo en el servidor nuevo antes de migrar.
6. Quien despliega código (el desarrollador) lo hace por **SFTP** con un usuario limitado (`deploy`, paso 11).

> Abre un bloc de notas y anota: IP del servidor, red de los agentes, IP de administración, y dónde quedaron las contraseñas generadas en el paso 10. Las necesitarás varias veces.

---

# FASE 0: Antes de tocar nada

## 1. Respaldo previo

En el servidor actual en producción, **no cambies nada sin esto** (sirve también para migrar al servidor nuevo):

```bash
# Snapshot o clon de la máquina virtual (si es VM), y además:
mkdir -p /root/antes-del-cambio
tar -czf /root/antes-del-cambio/codigo-y-config-$(date +%F).tar.gz \
    /etc/apache2 /etc/nginx /etc/systemd/system /etc/mysql 2>/dev/null
# Cambia la ruta por la del código actual del CRM
tar -czf /root/antes-del-cambio/crm-codigo-$(date +%F).tar.gz /ruta/del/crm-actual
mysqldump --single-transaction --routines NOMBRE_DE_LA_BD | gzip > /root/antes-del-cambio/bd-$(date +%F).sql.gz
chmod -R go-rwx /root/antes-del-cambio
```

---

## 2. Inventario del servidor actual: qué hay corriendo y quién lo puso

Esto es lo más importante cuando "alguien instaló gunicorn" y nadie sabe cómo. Un caso típico de cambios que no se reflejan es que haya **dos copias del código** o **dos procesos de Gunicorn** (uno viejo ocupando el puerto).

```bash
cat /etc/os-release | head -3

# Paquetes y de dónde vienen (debian-security = bien; repo de terceros o compilado a mano = revisar)
dpkg -l | grep -Ei 'apache2|nginx|gunicorn|mariadb|mysql'
apt policy apache2 nginx 2>/dev/null

# Quién escucha en qué puerto
ss -tlnp

# Procesos y desde cuándo corren
ps -eo pid,user,lstart,cmd | grep -Ei 'gunicorn|apache|nginx|python' | grep -v grep

# Servicios, cron y arranques "manuales"
systemctl list-units --type=service | grep -Ei 'gunicorn|flask|apache|nginx|maria|mysql'
crontab -l 2>/dev/null; ls /etc/cron.d
grep -rl gunicorn /etc/rc.local /etc/systemd /etc/cron* /root /home 2>/dev/null

# Gunicorn instalado con pip (fuera de Debian, nadie lo parcha)
find / -xdev -name gunicorn -type f 2>/dev/null
pip list 2>/dev/null | grep -i gunicorn

# ¿Con qué versión corre y en qué dirección escucha?
# Si aparece 0.0.0.0:PUERTO, está expuesto a toda la red sin pasar por Apache.
```

Anota los hallazgos en la tabla de arriba. Si hay **nginx y Apache** instalados a la vez, solo debe quedar uno escuchando en 80/443. Este manual usa Apache: si nginx no se usa para nada más, `systemctl disable --now nginx`.

---

# FASE 1: Base del sistema

## 3. Variables de esta sesión

Ajusta los valores y pega el bloque en la terminal. Se pierden al cerrar la sesión: si te desconectas, vuelve a ejecutarlo.

```bash
export SERVER_IP="10.0.0.10"          # IP del servidor (la que usan los agentes)
export AGENTES_NET="10.0.0.0/24"      # red desde donde se conectan los agentes
export ADMIN_IP="10.0.0.5"            # IP desde donde administras por SSH
```

---

## 4. Actualización y paquetes

Con la ventana de internet abierta:

```bash
apt update && apt upgrade -y
apt install -y apache2 openssl ufw curl rsync vim htop sudo needrestart cron \
    build-essential pkg-config libmariadb-dev libmariadb-dev-compat \
    python3 python3-venv python3-pip python3-dev

python3 --version   # Python 3.13.x
apache2 -v

# Confirma que el repositorio de seguridad está configurado
grep -rE "security" /etc/apt/sources.list /etc/apt/sources.list.d/ 2>/dev/null
# Debe aparecer: trixie-security
```

> `libmariadb-dev` y `pkg-config` son necesarios si el proyecto usa `mysqlclient` (compila contra C). Si usa `PyMySQL`, no hacen falta, pero no estorban. `needrestart` sirve en la ventana de parches para saber qué servicios hay que reiniciar.
>
> **Debian 13 (trixie)** es la versión estable vigente, con soporte de seguridad regular por varios años. El servidor actual, con Debian 12, ya está en la fase LTS (verifica el calendario en `debian.org/lts`): por eso esta guía construye un servidor nuevo en Debian 13 en lugar de seguir parchando el viejo.

---

## 5. Firewall (UFW)

Permite SSH **primero**, o te quedas fuera:

```bash
ufw default deny incoming
ufw default allow outgoing

ufw allow from $ADMIN_IP to any port 22 proto tcp          # SSH solo desde administración
ufw allow from $AGENTES_NET to any port 80 proto tcp       # HTTP (solo redirige a HTTPS)
ufw allow from $AGENTES_NET to any port 443 proto tcp      # HTTPS

ufw enable
ufw status verbose
```

Nunca abras puertos para Gunicorn (usa un socket Unix, no hay puerto) ni para MariaDB (3306).

> Si ya existe un firewall de red delante del servidor, esto es una segunda capa. Coordina con quien lo administra.
>
> Cuando el desarrollador necesite subir código, se abre su IP **temporalmente** (`ufw allow from IP_DEV to any port 22 proto tcp`) y se cierra al terminar (`ufw status numbered` → `ufw delete N`).

---

## 6. SSH: llaves y usuario de despliegue limitado

**Antes de desactivar contraseñas:** copia tu llave pública y comprueba en una segunda terminal que entras con ella.

```bash
# Desde tu máquina local
ssh-copy-id usuario@IP-DEL-SERVIDOR
```

Solo cuando funcione:

```bash
cat > /etc/ssh/sshd_config.d/10-endurecimiento.conf <<'EOF'
PasswordAuthentication no
PermitRootLogin prohibit-password
EOF

sshd -t && systemctl reload ssh
```

El usuario `deploy` (paso 11) solo podrá usar SFTP, encerrado en `/opt/crm`, sin shell:

```bash
cat > /etc/ssh/sshd_config.d/20-deploy-sftp.conf <<'EOF'
Match User deploy
    ChrootDirectory /opt/crm
    ForceCommand internal-sftp -u 027
    AllowTcpForwarding no
    X11Forwarding no
    PasswordAuthentication no
EOF

sshd -t && systemctl reload ssh
```

> `-u 027` hace que lo que suba el desarrollador quede legible para el grupo `crm` (el usuario de la app) y para nadie más. Sin esto, un archivo subido con permisos `600` produce un `Permission denied` al importar.
>
> El desarrollador necesita una **llave pública SSH** (WinSCP, FileZilla y PuTTYgen generan una en dos minutos).

---

## 7. Zona horaria y hora

```bash
timedatectl set-timezone America/Mexico_City
timedatectl status
```

> Un servidor aislado deriva su hora con el tiempo. Eso rompe logs, tokens y la validez del certificado. Apúntalo a un servidor NTP interno (`/etc/systemd/timesyncd.conf`, opción `NTP=`) o verifica la hora en cada ventana de mantenimiento.

---

# FASE 2: MariaDB en modo no estricto

## 8. Instalación y seguridad inicial

```bash
apt install -y mariadb-server
systemctl status mariadb --no-pager

mariadb-secure-installation
```

> En Debian el usuario `root` de MariaDB entra por **socket Unix** (sin contraseña, solo como root del sistema). Es seguro, así que responde **n** a "Switch to unix_socket authentication" y a "Change the root password". El resto, **Y**.
>
> Por lo mismo, todos los comandos de este manual usan `mariadb` a secas, sin `-u root -p`.

---

## 9. Configuración: modo no estricto + memoria

```bash
nano /etc/mysql/mariadb.conf.d/99-ajustes-legacy.cnf
```

```ini
[mysqld]
# Modo no estricto: permite 0000-00-00 y datos truncados (ver advertencia 1)
sql_mode = "NO_ENGINE_SUBSTITUTION"

# Memoria (ajusta según la RAM del servidor)
innodb_buffer_pool_size = 512M
max_connections = 200
```

```bash
systemctl restart mariadb

# Debe mostrar solo NO_ENGINE_SUBSTITUTION
mariadb -e "SELECT @@GLOBAL.sql_mode;"

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

> Si el `sql_mode` no cambia, otro archivo lo está sobreescribiendo: `grep -r "sql_mode" /etc/mysql/`. El archivo `99-` se carga al final; revisa también que no haya un `SET GLOBAL` en algún script de arranque.

---

## 10. Crear base de datos y usuario

La contraseña se **genera al azar** y se guarda en `/root/secretos/` para que el paso 14 la use; no hay contraseñas escritas a mano que se puedan desincronizar:

```bash
install -d -m 700 /root/secretos

PASS=$(openssl rand -hex 16)
echo -n "$PASS" > /root/secretos/db_crm
chmod 600 /root/secretos/db_crm

mariadb <<SQL
CREATE DATABASE IF NOT EXISTS db_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'user_crm'@'localhost' IDENTIFIED BY '$PASS';
ALTER USER 'user_crm'@'localhost' IDENTIFIED BY '$PASS';
GRANT ALL PRIVILEGES ON db_crm.* TO 'user_crm'@'localhost';
SQL

mariadb -e "SHOW DATABASES;"
mariadb -e "SELECT User, Host FROM mysql.user;"
```

> `ALL PRIVILEGES` sobre su propia base es lo mínimo razonable para un legacy que crea tablas.
>
> **Si ya existe una base en producción**, restáurala aquí con el respaldo del paso 1: `mariadb db_crm < bd-AAAA-MM-DD.sql` (descomprime antes si es `.gz`). Haz la restauración **después** de configurar el modo no estricto (paso 9).

---

# FASE 3: Flask + Gunicorn

La app se llama `crm`. Todos los nombres salen de ahí: usuario `crm`, código en `/opt/crm/app`, servicio `flask@crm`, socket `/run/flask-crm/gunicorn.sock`. Si algún día hay una segunda app, se repite el procedimiento con otro nombre.

## 11. Usuarios y directorios

```bash
# Usuario que EJECUTA la app (sin shell, sin login)
adduser --system --group --no-create-home --home /opt/crm --shell /usr/sbin/nologin crm

# Usuario que SUBE el código por SFTP (miembro del grupo crm)
adduser --disabled-password --gecos "" deploy
usermod -aG crm deploy

install -d -o root   -g crm -m 750  /opt/crm        # raíz del chroot SFTP: debe ser de root
install -d -o deploy -g crm -m 2750 /opt/crm/app    # el código (setgid: lo nuevo hereda el grupo crm)

# Llave pública del desarrollador
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
nano /home/deploy/.ssh/authorized_keys        # pega aquí la llave pública
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
```

Cómo queda repartido y por qué:

| Qué | Dueño | Razón |
|---|---|---|
| `/opt/crm/app/` (código) | `deploy:crm` | El desarrollador lo escribe; la app solo lo lee, así que una vulnerabilidad en la app no puede reescribir su propio código |
| `/opt/crm/venv/` (dependencias) | `root` | Las dependencias las instala Infraestructura, no la app |
| `/etc/flask/crm.env` (secretos) | `root:crm` | Un SFTP no puede sobreescribirlos |
| `/etc/gunicorn/crm.conf.py` (configuración) | `root` | Configuración separada del código |
| `/var/lib/flask-crm/` (archivos generados) | `crm` | Lo único que la app sí escribe, junto con sus logs |

Los directorios de logs, sockets y archivos generados (`/var/log/flask-crm`, `/run/flask-crm`, `/var/lib/flask-crm`) **no se crean a mano**: los crea systemd con el dueño correcto (paso 18).

---

## 12. Subir el código

El desarrollador sube **todo** el contenido del proyecto a `/app` por SFTP (dentro del chroot, `/app` es `/opt/crm/app`). La primera vez puede copiarlo Infraestructura:

```bash
# Desde la máquina con el código (excluye lo que no debe viajar)
rsync -av \
  --exclude 'venv' --exclude '__pycache__' --exclude '.git' \
  --exclude '.env' --exclude '*.db' \
  ./crm/ root@IP-DEL-SERVIDOR:/opt/crm/app/

# En el servidor, normaliza dueño y permisos
chown -R deploy:crm /opt/crm/app
chmod -R u=rwX,g=rX,o= /opt/crm/app
find /opt/crm/app -type d -exec chmod g+s {} +
```

> `.env` y `*.db` se excluyen a propósito: un `.env` local con credenciales reales no debe viajar al servidor. En el servidor se genera uno nuevo (paso 14).

---

## 13. Virtualenv y dependencias (con Gunicorn en `requirements.txt`)

Esto lo ejecuta Infraestructura como `root`:

```bash
python3 -m venv /opt/crm/venv
/opt/crm/venv/bin/pip install --upgrade pip
```

**Primero, agrega Gunicorn a `requirements.txt` con versión fija.** Usa la versión estable más reciente (consúltala en `pypi.org/project/gunicorn` desde una máquina con internet) y, como mínimo, la **23.0.0**: las versiones anteriores tienen vulnerabilidades de *request smuggling* que importan precisamente cuando hay un proxy inverso delante.

```bash
echo "gunicorn==23.0.0" >> /opt/crm/app/requirements.txt     # reemplaza por la versión vigente
```

```bash
/opt/crm/venv/bin/pip install -r /opt/crm/app/requirements.txt
/opt/crm/venv/bin/pip freeze > /opt/crm/requirements.freeze.txt
chown root:crm /opt/crm/requirements.freeze.txt
```

> **Entrega al desarrollador el `requirements.txt` actualizado** para que lo conserve en su repositorio. Mientras Gunicorn no esté ahí, el siguiente que reconstruya el entorno vuelve a tener este problema.
>
> `requirements.freeze.txt` guarda las versiones exactas instaladas hoy. Si el `requirements.txt` del legacy no fija versiones, esto es lo único que hace reproducible el entorno.
>
> Si `mysqlclient` falla al compilar, confirma `libmariadb-dev` y `pkg-config` (paso 4).
>
> **Python 3.13:** si `pip install` falla en alguna librería, casi siempre es una versión vieja fijada en `requirements.txt` que no soporta Python 3.13 (módulos retirados como `distutils`, `imp` o `cgi`, o paquetes sin wheel para 3.13). No es un problema del servidor: Desarrollo debe actualizar esas dependencias. Verifícalo en una prueba antes de la fecha de migración.

Verifica:

```bash
sudo -H -u crm /opt/crm/venv/bin/python -c "import flask, gunicorn; print(flask.__version__, gunicorn.__version__)"
# Agrega aquí el módulo de base de datos que use el proyecto: MySQLdb (mysqlclient) o pymysql
```

---

## 14. Archivo de entorno (secretos)

Vive **fuera del código**, para que una subida por SFTP no lo sobreescriba:

```bash
install -d -o root -g crm -m 750 /etc/flask

cat > /etc/flask/crm.env <<EOF
SECRET_KEY=$(openssl rand -hex 32)
SESSION_COOKIE_NAME=flask_crm
DATABASE_URL=mysql+mysqldb://user_crm:$(cat /root/secretos/db_crm)@localhost/db_crm?charset=utf8mb4
DATA_DIR=/var/lib/flask-crm
UPLOAD_DIR=/var/lib/flask-crm/uploads
GENERATED_DIR=/var/lib/flask-crm/generated
PYTHONPYCACHEPREFIX=/var/lib/flask-crm/pycache
EOF

chown root:crm /etc/flask/crm.env
chmod 640 /etc/flask/crm.env
```

Notas:

- **Ajusta los nombres de variable a lo que lea el legacy.** Búscalos con `grep -rnE "environ|getenv|dotenv" --include=*.py /opt/crm/app`. Si el código tiene la cadena de conexión escrita a mano, conviene moverla a una variable de entorno; es un cambio del desarrollador.
- No vuelvas a ejecutar este bloque sin necesidad: genera una `SECRET_KEY` nueva y **cierra las sesiones de todos los agentes**.
- `PYTHONPYCACHEPREFIX` manda los `.pyc` a un directorio escribible, porque el código es de solo lectura para la app.
- Cambiar este archivo requiere `systemctl restart` (no basta el reload; ver paso 25).
- Usa `localhost` (socket Unix) en `DATABASE_URL`, como está arriba. Con `127.0.0.1` (TCP), MariaDB 11.x puede exigir verificación de certificado y rechazar la conexión.

Verifica sin imprimir secretos:

```bash
ls -l /etc/flask/crm.env
grep -c . /etc/flask/crm.env
```

---

## 15. `wsgi.py`

Detrás de Apache, Flask necesita saber que el acceso original fue por HTTPS y a qué host. Se resuelve con `ProxyFix`, sin tocar el código legacy:

```bash
cat > /opt/crm/app/wsgi.py <<'EOF'
import os
from werkzeug.middleware.proxy_fix import ProxyFix

from app import app  # ajusta si tu módulo u objeto se llaman distinto

app.wsgi_app = ProxyFix(app.wsgi_app, x_for=1, x_proto=1, x_host=1)

if os.environ.get("SECRET_KEY"):
    app.config["SECRET_KEY"] = os.environ["SECRET_KEY"]

app.config.update(
    SESSION_COOKIE_NAME=os.environ.get("SESSION_COOKIE_NAME", "session"),
    SESSION_COOKIE_SECURE=True,      # solo viaja por HTTPS
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE="Lax",
)
EOF

chown deploy:crm /opt/crm/app/wsgi.py
chmod 640 /opt/crm/app/wsgi.py
```

> **Este archivo debe quedar en el repositorio del proyecto**, junto con `requirements.txt`. Una subida por SFTP no lo borra, pero si alguien reconstruye la carpeta desde cero, sí.
>
> Esto solo protege la cookie de sesión de Flask. Si el legacy guarda datos en cookies propias (rol, nombre de usuario), el servidor **no** las protege: eso se corrige en el código de la app.

---

## 16. Configuración de Gunicorn

Un archivo, fuera del código, con todo lo que antes estaba disperso en comandos y scripts:

```bash
install -d -m 755 /etc/gunicorn

cat > /etc/gunicorn/crm.conf.py <<'EOF'
# /etc/gunicorn/crm.conf.py — configuración de Gunicorn para el CRM
bind = "unix:/run/flask-crm/gunicorn.sock"   # socket Unix: no hay puerto TCP expuesto
umask = 0o007                                # socket 0660: solo el grupo (www-data, Apache)

workers = 3          # punto de partida: (2 x núcleos) + 1; ajustar con RAM y carga
timeout = 120        # igual que ProxyTimeout de Apache

accesslog = "/var/log/flask-crm/access.log"
errorlog = "/var/log/flask-crm/error.log"
loglevel = "info"

# NO cambiar estos dos (ver advertencia 2):
preload_app = False  # con True, el reload (HUP) no recarga el código de la app
reload = False       # --reload es de desarrollo; en producción se usa reload ordenado
EOF
chmod 644 /etc/gunicorn/crm.conf.py
```

---

## 17. Probar Gunicorn a mano antes de systemd

```bash
cd /opt/crm/app
sudo -H -u crm bash -c 'set -a; . /etc/flask/crm.env; set +a; exec /opt/crm/venv/bin/gunicorn --workers 1 --bind 127.0.0.1:8001 wsgi:app'

# En otra terminal:
curl -I http://127.0.0.1:8001/
# Ctrl+C cuando responda
```

Cualquier código HTTP vale (200, 302, 401, 404): lo que importa es que responda. Si falla aquí, no sigas: el problema está en la app, no en Apache.

---

## 18. Servicio systemd

Una plantilla (`flask@.service`): `%i` es el nombre de la app (`crm`).

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
SupplementaryGroups=%i
WorkingDirectory=/opt/%i/app
EnvironmentFile=/etc/flask/%i.env
ExecStartPre=/usr/bin/mkdir -p /var/lib/flask-%i/uploads /var/lib/flask-%i/generated /var/lib/flask-%i/pycache
ExecStart=/opt/%i/venv/bin/gunicorn -c /etc/gunicorn/%i.conf.py wsgi:app
ExecReload=/bin/kill -s HUP $MAINPID
KillMode=mixed
TimeoutStopSec=30
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
systemctl enable --now flask@crm

systemctl status flask@crm --no-pager
ls -la /run/flask-crm/gunicorn.sock       # esperado: srw-rw---- crm www-data
tail -20 /var/log/flask-crm/error.log
```

Por qué así:

- **`Group=www-data` + `umask = 0o007`** dejan el socket como `0660` con grupo `www-data`, para que Apache pueda usarlo y nadie más.
- **`SupplementaryGroups=%i`** le devuelve al proceso el grupo `crm`, necesario para leer el código (`deploy:crm`) y el archivo de entorno. Sin esta línea, `Group=www-data` lo deja sin acceso y el servicio no arranca.
- **El socket vive en `/run/flask-crm/`**, no en `/run/`: el usuario de la app no puede crear archivos directamente en `/run`, y `RuntimeDirectory` le entrega su propio directorio.
- **`Wants=` y no `Requires=`** con MariaDB: con `Requires=`, un `systemctl restart mariadb` reiniciaría también la app.
- `Type=notify` es el modo documentado de Gunicorn para systemd, y `ExecReload` con `HUP` es lo que hace posible el reload ordenado.

---

# FASE 4: Apache + HTTPS autofirmado

## 19. Instalar y endurecer Apache

Ya se instaló en el paso 4. Ahora los módulos y el endurecimiento:

```bash
a2enmod ssl proxy proxy_http headers
a2dissite 000-default default-ssl 2>/dev/null

# Evita el aviso "Could not reliably determine the server's fully qualified domain name"
echo "ServerName $SERVER_IP" > /etc/apache2/conf-available/servername.conf
a2enconf servername

# No revelar versión (se llama zz- para cargarse después de security.conf)
cat > /etc/apache2/conf-available/zz-hardening.conf <<'EOF'
ServerTokens Prod
ServerSignature Off
TraceEnable Off
EOF
a2enconf zz-hardening

apache2ctl configtest
```

---

## 20. Certificado autofirmado (con la IP en el SAN)

Un detalle que casi todas las guías omiten: los navegadores modernos **ignoran el CN** y exigen el campo `subjectAltName`. Para acceder por IP, el certificado debe llevar `IP:x.x.x.x` en el SAN; si no, marca error **aunque el certificado esté instalado como confiable**.

```bash
openssl req -x509 -newkey rsa:3072 -sha256 -days 1095 -nodes \
  -keyout /etc/ssl/private/crm.key -out /etc/ssl/certs/crm.crt \
  -subj "/O=Nombre-de-la-empresa/CN=$SERVER_IP" \
  -addext "subjectAltName=IP:$SERVER_IP" \
  -addext "basicConstraints=critical,CA:FALSE" \
  -addext "keyUsage=critical,digitalSignature,keyEncipherment" \
  -addext "extendedKeyUsage=serverAuth"

chmod 600 /etc/ssl/private/crm.key
chmod 644 /etc/ssl/certs/crm.crt

# Verifica: debe aparecer la IP en Subject Alternative Name y las fechas de vigencia
openssl x509 -in /etc/ssl/certs/crm.crt -noout -text | grep -A1 "Subject Alternative Name"
openssl x509 -in /etc/ssl/certs/crm.crt -noout -dates
```

Notas:

- Si el servidor tiene **más de una IP** por la que se accederá: `-addext "subjectAltName=IP:10.0.0.10,IP:10.0.1.10"`.
- **Anota la fecha de vencimiento en un calendario.** Dentro de tres años, el CRM mostrará un error de certificado a todos los agentes a la vez.
- Si cada renovación implicara tocar cientos de PCs, usa mejor una **mini CA interna** (Anexo 2): se instala una vez y solo se renueva el certificado del servidor.

---

## 21. VirtualHost de Apache

```bash
cat > /etc/apache2/sites-available/crm.conf <<'EOF'
# HTTP: solo redirige a HTTPS
<VirtualHost *:80>
    ServerName IP_DEL_SERVIDOR
    Redirect permanent / https://IP_DEL_SERVIDOR/
</VirtualHost>

# HTTPS: proxy inverso hacia Gunicorn por socket Unix
<VirtualHost *:443>
    ServerName IP_DEL_SERVIDOR

    SSLEngine on
    SSLCertificateFile    /etc/ssl/certs/crm.crt
    SSLCertificateKeyFile /etc/ssl/private/crm.key
    SSLProtocol           -all +TLSv1.2 +TLSv1.3

    ErrorLog  ${APACHE_LOG_DIR}/crm-error.log
    CustomLog ${APACHE_LOG_DIR}/crm-access.log combined

    LimitRequestBody 33554432      # 32 MB para subidas
    ProxyTimeout 120               # igual que timeout en gunicorn

    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"

    # retry=0: si Gunicorn se reinicia, Apache reintenta de inmediato
    # en vez de devolver 503 durante 60 segundos
    ProxyPass        "/" "unix:/run/flask-crm/gunicorn.sock|http://localhost/" retry=0
    ProxyPassReverse "/" "http://localhost/"
</VirtualHost>
EOF

sed -i "s/IP_DEL_SERVIDOR/$SERVER_IP/g" /etc/apache2/sites-available/crm.conf
```

> **Cómo funciona el `ProxyPass`:** la parte `unix:/ruta/socket|http://localhost/` le indica a Apache que hable con el socket Unix (el `|` es obligatorio). `RequestHeader set X-Forwarded-Proto "https"` es lo que `ProxyFix` en `wsgi.py` lee para saber que el acceso original fue por HTTPS; sin él, Flask construye redirecciones a `http://` y el login entra en un bucle.
>
> HSTS **no** se configura: los navegadores lo ignoran cuando se accede por IP.
>
> Si quieres una capa extra a nivel de Apache, agrega `Require ip 10.0.0.0/24` (la red de los agentes) dentro de un `<Location "/">`.

---

## 22. Activar el sitio y verificar

```bash
a2ensite crm
apache2ctl configtest
systemctl reload apache2

curl -I  http://$SERVER_IP/                       # 301 hacia https
curl -kI https://$SERVER_IP/                      # -k: todavía no confía en el certificado; 200, 302, 401 o 404
curl -I --cacert /etc/ssl/certs/crm.crt https://$SERVER_IP/    # sin -k: valida certificado y SAN
```

La tercera prueba es la importante: si responde sin error de certificado, el SAN y la IP están bien. Si dan **503**, Apache no alcanza el socket de Gunicorn: ver el **Anexo 1**.

---

## 23. Que las PCs confíen en el certificado

Hasta aquí, cada navegador mostrará "conexión no privada". Hay que instalar `crm.crt` (**solo el `.crt`, nunca el `.key`**) en las PCs de los agentes:

```bash
# Copia el certificado a un lugar desde donde se pueda repartir (USB, carpeta compartida)
cp /etc/ssl/certs/crm.crt /root/crm.crt
```

En **Windows**, como administrador, por PC:

```bat
certutil -addstore -f "ROOT" crm.crt
```

- **Con Active Directory:** GPO → *Configuración del equipo → Directivas → Configuración de Windows → Configuración de seguridad → Directivas de clave pública → Entidades de certificación raíz de confianza*. Se despliega a todas las PCs de una vez.
- **Chrome y Edge** usan el almacén de Windows. **Firefox** usa el suyo: importar el certificado ahí o activar `security.enterprise_roots.enabled` en `about:config`.
- Si algún navegador sigue marcando error con el certificado ya instalado, pasa a la **mini CA** del Anexo 2.

> Si hay **otros sistemas que consuman el CRM por `http://`** (marcador automático, integraciones, webhooks), la redirección a HTTPS puede romperlos. Actualiza su URL a `https://` e instala el `.crt` también en esos equipos.

---

# FASE 5: Cómo desplegar cambios de código

## 24. Procedimiento (el que deben seguir siempre)

La regla: **primero se suben todos los archivos, y después se recarga una sola vez.** Nunca recargar a medias.

1. **Infraestructura abre el acceso** SFTP desde la IP del desarrollador, de forma temporal (paso 5).
2. **El desarrollador sube todos los archivos** del cambio a `/app`, en una sola operación.
3. **Se recarga Gunicorn** con `systemctl reload flask@crm`. Quien tenga acceso de administración lo ejecuta, o el desarrollador sube el archivo disparador (variante automática, más abajo).
4. **Se verifica** que el cambio quedó cargado (paso 26).
5. **Infraestructura cierra el acceso** de la IP del desarrollador.

**Variante automática (el desarrollador no necesita shell ni sudo):** una unidad `systemd.path` vigila un archivo. Al terminar de subir, el desarrollador sube/reemplaza `reload.flag` **como último archivo**, y systemd hace el reload ordenado:

```bash
cat > /etc/systemd/system/flask-reload@.path <<'EOF'
[Unit]
Description=Disparador de reload para %i

[Path]
PathChanged=/opt/%i/app/reload.flag
Unit=flask-reload@%i.service

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/flask-reload@.service <<'EOF'
[Unit]
Description=Reload ordenado de flask@%i

[Service]
Type=oneshot
ExecStart=/usr/bin/systemctl reload flask@%i.service
EOF

touch /opt/crm/app/reload.flag
chown deploy:crm /opt/crm/app/reload.flag
systemctl daemon-reload
systemctl enable --now flask-reload@crm.path
systemctl status flask-reload@crm.path --no-pager
```

> `reload.flag` **no** va en el repositorio ni debe subirse con el resto. Es solo la señal de "terminé".

---

## 25. ¿Reload o restart?

| Qué cambió | Qué hacer |
|---|---|
| Código `.py`, plantillas, archivos estáticos | `systemctl reload flask@crm` |
| Nuevas librerías instaladas con `pip` en el venv | `systemctl reload flask@crm` |
| `/etc/flask/crm.env` (variables de entorno) | `systemctl restart flask@crm` |
| `/etc/gunicorn/crm.conf.py`: workers, timeout, logs | `systemctl reload flask@crm` |
| `/etc/gunicorn/crm.conf.py`: `bind` / el propio Gunicorn actualizado | `systemctl restart flask@crm` |
| Unidad de systemd | `systemctl daemon-reload` y luego `restart` |

El `reload` recicla los workers de forma ordenada y sin cortar conexiones. El `restart` corta por un instante: úsalo en horas de poca carga.

---

## 26. Verificar que el cambio sí quedó cargado

Los "desfases" casi siempre son un reload que nunca ocurrió. En lugar de adivinar:

```bash
# 1. Los workers deben haber arrancado DESPUÉS de la hora de la subida
ps -o pid,lstart,cmd -u crm | grep gunicorn

# 2. El log debe mostrar la señal de reload y los workers nuevos
grep -Ei "hup|booting worker" /var/log/flask-crm/error.log | tail -10

# 3. El servicio sigue sano
systemctl status flask@crm --no-pager | head -5
```

> **Sugerencia para Desarrollo:** una ruta `/version` (o una fecha de versión en el pie de página) que devuelva un identificador del despliegue. Así cualquiera confirma desde el navegador que está viendo el código nuevo.

---

## ✅ Checklist final

```bash
# Sistema
timedatectl | grep "Time zone"                       # America/Mexico_City
python3 --version                                    # 3.13.x
apache2 -v

# MariaDB
systemctl status mariadb --no-pager                  # active (running)
mariadb -e "SELECT @@GLOBAL.sql_mode;"               # NO_ENGINE_SUBSTITUTION
mariadb -e "SHOW DATABASES;"                         # db_crm
ss -tlnp | grep 3306                                 # solo 127.0.0.1

# Gunicorn
systemctl is-active flask@crm                        # active
ls -la /run/flask-crm/gunicorn.sock                  # srw-rw---- crm www-data
/opt/crm/venv/bin/pip show gunicorn | head -2        # versión fija
grep -i gunicorn /opt/crm/app/requirements.txt       # debe estar en requirements.txt

# Apache + HTTPS
apache2ctl configtest                                # Syntax OK
curl -I http://$SERVER_IP/ | head -1                 # 301
curl -I --cacert /etc/ssl/certs/crm.crt https://$SERVER_IP/ | head -1
openssl x509 -in /etc/ssl/certs/crm.crt -noout -enddate    # anótalo en el calendario

# Despliegue
ls -l /etc/flask/crm.env                             # -rw-r----- root crm
ls -ld /opt/crm /opt/crm/app                         # root:crm 750  /  deploy:crm 2750

# Firewall y puertos
ufw status                                           # SSH solo desde ADMIN_IP; 80/443 solo desde la red de agentes
ss -tlnp                                             # nada de 3306 ni de puertos de Gunicorn hacia afuera
```

---

## 📝 Notas finales

- Documenta qué versión de Python, Flask, Gunicorn, Apache y MariaDB instalaste y en qué fecha (`requirements.freeze.txt` ayuda con la parte de Python).
- **Nunca** expongas credenciales en repositorios. Los `.env` y las llaves (`.key`) van excluidos del control de versiones.
- No hay puertos de Gunicorn: la app habla con Apache por un socket Unix. MariaDB (3306) **nunca debe abrirse en UFW**.
- **Sobre el modo no estricto:** es deuda técnica consciente. Anótalo como pendiente para cuando el legacy migre a un esquema limpio.

### Rutina mensual de parches (servidor aislado)

Sin internet permanente no hay `unattended-upgrades` que valga. Hay que hacerlo con una **ventana planeada**:

```bash
# 1. Conectar el servidor temporalmente (ventana acordada)
apt update
apt list --upgradable
apt upgrade -y
needrestart                       # indica qué servicios requieren reinicio
# 2. Retirar la conexión
# 3. Verificar
/opt/diagnose.sh                  # Anexo 5
openssl x509 -in /etc/ssl/certs/crm.crt -noout -enddate
```

- Un `apache2 -v` de aspecto viejo **no** quiere decir que falte el parche: Debian aplica las correcciones de seguridad sobre la versión base. La referencia es el *Security Tracker* de Debian (`security-tracker.debian.org`), buscando el paquete y *trixie*.
- Las librerías de Python (Flask, Gunicorn, etc.) **no** las parcha Debian. Revisa `pip list --outdated` en la ventana, y actualiza primero en un clon.

### Lo que suele aparecer en la semana 2

- **Rutas hardcodeadas en el legacy** (`/var/www/html/...`, `C:\...`). Decide por app: parchar el código para leer `GENERATED_DIR` del entorno, o crear un symlink desde la ruta vieja hacia `/var/lib/flask-crm/generated`.
- **`Permission denied` tras una subida:** un archivo quedó sin permiso de lectura para el grupo `crm`. Ver Anexo 1.
- **`mysqlclient` compila contra `libmariadb-dev`.** Si esa librería se actualiza y haces `pip install` en un venv viejo, puede fallar. Anota la versión instalada (`dpkg -l libmariadb-dev`).
- **El certificado caduca.** Calendarízalo (paso 20).

---

## Anexo 1: Troubleshooting

```bash
# 1. ¿El servicio vive?
systemctl status flask@crm --no-pager

# 2. ¿El socket existe y con qué permisos?
ls -la /run/flask-crm/gunicorn.sock          # esperado: srw-rw---- crm www-data

# 3. ¿Apache puede leerlo?
sudo -u www-data ls -la /run/flask-crm/

# 4. ¿Qué dice el log de Gunicorn?
tail -50 /var/log/flask-crm/error.log
journalctl -u flask@crm -n 50 --no-pager

# 5. ¿Qué dice el log de Apache?
tail -50 /var/log/apache2/crm-error.log

# 6. ¿Responde directo, sin Apache?
curl --unix-socket /run/flask-crm/gunicorn.sock -I http://localhost/
```

Causas comunes:

- **503 de Apache y el socket no existe:** el servicio no arrancó. Mira `journalctl` (casi siempre es un `import` que falla en `wsgi.py`).
- **503 y el socket existe:** permisos. Debe ser `srw-rw---- crm www-data`. Revisa `Group=www-data` en la unidad y `umask = 0o007` en la configuración. En el log de Apache buscarás un error tipo `AH01114`.
- **502 o 500 de la app:** el error está en el código de Flask; mira `error.log` de Gunicorn.
- **`wsgi:app` falla al importar:** `from app import app` no coincide con tu módulo u objeto; edita `/opt/crm/app/wsgi.py`.
- **`Permission denied` al importar módulos:** un archivo subido sin lectura para el grupo. Corrige con `chmod -R g+rX /opt/crm/app` y revisa que el `ForceCommand internal-sftp -u 027` del paso 6 esté activo.
- **El servicio no arranca y el log dice `Permission denied` leyendo `/etc/flask/crm.env`:** falta `SupplementaryGroups=%i` en la unidad.
- **El `.env` no se cargó:** lo lee systemd (`EnvironmentFile`), no Gunicorn. Un error de sintaxis en el archivo lo anula. Después de editarlo hace falta `restart`.
- **Login en bucle de redirecciones:** falta el `RequestHeader set X-Forwarded-Proto "https"` en el VirtualHost, o el `ProxyFix` en `wsgi.py`.
- **Los cambios de código no se reflejan:** nunca hubo reload. Sigue el paso 26. Si el `lstart` de los workers es anterior a la subida, ejecuta el reload. Si sigue igual, busca procesos duplicados (paso 2).
- **413 Request Entity Too Large:** sube `LimitRequestBody` en el VirtualHost.
- **504 / el cliente espera y falla:** una consulta tarda más de 120 s. Sube `timeout` en `crm.conf.py` **y** `ProxyTimeout` en Apache a la par.

---

## Anexo 2: Mini CA interna (renovar sin tocar las PCs)

Con el certificado autofirmado del paso 20, cada renovación obliga a reinstalar el certificado en todas las PCs. Con una **CA interna**, se instala **una sola vez** el certificado de la CA (vigencia larga) y la renovación afecta solo al servidor. Reemplaza al paso 20.

```bash
install -d -m 700 /root/ca
cd /root/ca

# 1. La CA (una sola vez, 10 años)
openssl genrsa -out ca.key 4096
chmod 600 ca.key
openssl req -x509 -new -key ca.key -sha256 -days 3650 \
  -subj "/O=Nombre-de-la-empresa/CN=CA interna CRM" \
  -addext "basicConstraints=critical,CA:TRUE,pathlen:0" \
  -addext "keyUsage=critical,keyCertSign,cRLSign" \
  -out ca.crt

# 2. Certificado del servidor, firmado por la CA (repetir solo esta parte al renovar)
openssl genrsa -out crm.key 3072
openssl req -new -key crm.key -subj "/O=Nombre-de-la-empresa/CN=$SERVER_IP" -out crm.csr
cat > crm.ext <<EOF
subjectAltName=IP:$SERVER_IP
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
EOF
openssl x509 -req -in crm.csr -CA ca.crt -CAkey ca.key -CAcreateserial \
  -days 825 -sha256 -extfile crm.ext -out crm.crt

install -m 600 crm.key /etc/ssl/private/crm.key
install -m 644 crm.crt /etc/ssl/certs/crm.crt
systemctl reload apache2
```

- A las PCs se les instala **`ca.crt`** (paso 23, mismo comando `certutil`), no `crm.crt`.
- **`ca.key` es el secreto más importante.** Después de firmar, guárdala fuera del servidor (USB en un lugar seguro) y bórrala de `/root/ca`. Quien la tenga puede emitir certificados que las PCs aceptarán.
- Renovación: repite solo el punto 2 (con la `ca.key` de vuelta temporalmente) y recarga Apache.

---

## Anexo 3: Instalación sin internet (paquetes de Python)

Si no hay ventana de conexión, se prepara todo en una máquina con internet y se lleva en USB. La máquina de preparación debe ser **Debian 13, misma arquitectura y Python 3.13**.

```bash
# En la máquina CON internet
mkdir wheelhouse
pip download -r requirements.txt gunicorn==23.0.0 -d wheelhouse/
tar -czf wheelhouse.tar.gz wheelhouse/

# En el servidor SIN internet (venv ya creado)
tar -xzf wheelhouse.tar.gz
/opt/crm/venv/bin/pip install --no-index --find-links wheelhouse -r /opt/crm/app/requirements.txt gunicorn==23.0.0
```

> `mysqlclient` no trae wheel para Linux: se instala desde el código fuente y necesita `build-essential`, `pkg-config` y `libmariadb-dev` ya instalados en el servidor (paquetes de apt). Para los paquetes de Debian sin internet: `apt-get install --download-only` en una máquina equivalente, o `apt-offline`, o un espejo interno (`apt-cacher-ng`, `aptly`).

---

## Anexo 4: Respaldo mínimo

```bash
install -d -m 700 /var/backups/crm

cat > /usr/local/sbin/respaldo-crm.sh <<'EOF'
#!/bin/bash
set -euo pipefail
DEST=/var/backups/crm
FECHA=$(date +%F)

mariadb-dump --single-transaction --routines db_crm | gzip > "$DEST/db_crm-$FECHA.sql.gz"
tar -czf "$DEST/archivos-$FECHA.tar.gz" /var/lib/flask-crm /opt/crm/app
tar -czf "$DEST/config-$FECHA.tar.gz" /etc/flask /etc/gunicorn /etc/apache2 /etc/mysql /etc/systemd/system
chmod 600 "$DEST"/*-"$FECHA".*

# Conserva 7 días
find "$DEST" -type f -mtime +7 -delete
EOF
chmod 750 /usr/local/sbin/respaldo-crm.sh

echo '30 2 * * * root /usr/local/sbin/respaldo-crm.sh' > /etc/cron.d/respaldo-crm
```

**Prueba la restauración** (un respaldo que nunca se restauró es una suposición):

```bash
/usr/local/sbin/respaldo-crm.sh
mariadb -e "CREATE DATABASE prueba_restauracion;"
gunzip -c /var/backups/crm/db_crm-$(date +%F).sql.gz | mariadb prueba_restauracion
mariadb -e "SHOW TABLES;" prueba_restauracion
mariadb -e "DROP DATABASE prueba_restauracion;"
```

> Los respaldos en el mismo disco no sirven si el servidor se pierde. Copia `/var/backups/crm/` a otro equipo. El archivo de configuración contiene secretos (`/etc/flask`): trátalo como tal.

---

## Anexo 5: Script de diagnóstico

Guarda esto en `/opt/diagnose.sh`:

```bash
#!/bin/bash
# /opt/diagnose.sh — ejecutar como root
APP=crm
IP="IP_DEL_SERVIDOR"     # reemplaza por la IP del servidor

echo "=== Diagnóstico del CRM ==="
echo ""
echo "─── Gunicorn ($APP) ───"

if systemctl is-active --quiet flask@$APP; then
  echo "  systemd:  OK (running)"
else
  echo "  systemd:  FAIL ($(systemctl is-active flask@$APP))"
fi

if [ -S /run/flask-$APP/gunicorn.sock ]; then
  echo "  socket:   OK (/run/flask-$APP/gunicorn.sock)"
else
  echo "  socket:   FAIL (no existe)"
fi

CODE=$(curl -s -o /dev/null -w "%{http_code}" \
  --unix-socket /run/flask-$APP/gunicorn.sock http://localhost/ 2>/dev/null)
if [ "$CODE" != "000" ] && [ "$CODE" -lt 500 ] 2>/dev/null; then
  echo "  HTTP:     OK ($CODE)"
else
  echo "  HTTP:     FAIL ($CODE)"
fi

echo "  workers desde:"
ps -o pid,lstart -u $APP --no-headers 2>/dev/null | sed 's/^/    /'

ERRS=$(tail -5 /var/log/flask-$APP/error.log 2>/dev/null | grep -iE "error|traceback|exception" || true)
if [ -n "$ERRS" ]; then
  echo "  últ. errores:"
  echo "$ERRS" | sed 's/^/    /'
fi
echo ""

echo "─── Apache ───"
if systemctl is-active --quiet apache2; then echo "  systemd:  OK"; else echo "  systemd:  FAIL"; fi
apache2ctl configtest 2>&1 | sed 's/^/    /'
echo ""

echo "─── HTTPS ───"
CODE=$(curl -s -o /dev/null -w "%{http_code}" --cacert /etc/ssl/certs/crm.crt https://$IP/ 2>/dev/null)
echo "  https://$IP/:  $CODE"
echo -n "  certificado vence: "
openssl x509 -in /etc/ssl/certs/crm.crt -noout -enddate | cut -d= -f2
echo ""

echo "─── MariaDB ───"
if systemctl is-active --quiet mariadb; then echo "  systemd:  OK"; else echo "  systemd:  FAIL"; fi
echo -n "  sql_mode: "; mariadb -N -e "SELECT @@GLOBAL.sql_mode;" 2>/dev/null
```

Uso:

```bash
sed -i "s/IP_DEL_SERVIDOR/$SERVER_IP/" /opt/diagnose.sh
chmod 750 /opt/diagnose.sh
/opt/diagnose.sh
```

Cuando algo falle, **primero esto**: te dice si el problema es systemd, el socket, Gunicorn o Apache, y desde cuándo corren los workers.

---

## 🧪 Notas del Autor

Este repositorio NO es parte de los experimentos documentados en **[vibecodingmexico.com](https://vibecodingmexico.com)**.

## 🤔 ¿Por qué existe este manual?

Un sistema que "funciona" porque alguien lo dejó corriendo hace años no es un despliegue: es una suposición. Cuando nadie sabe cómo arranca Gunicorn, desde dónde corre el código ni por qué un cambio subido por SFTP no aparece, cada ajuste es un riesgo. Este manual deja todo por escrito y reproducible, y separa lo que es del servidor de lo que es del código.

**La analogía del restaurante**

- **El servidor de desarrollo de Flask** es un chef que además atiende las mesas: sirve para probar recetas, no para un restaurante lleno.
- **Gunicorn** es el equipo de meseros: toman varias órdenes en paralelo y, si uno falla, el proceso maestro pone otro.
- **Apache** es el anfitrión de la puerta: recibe a los clientes, maneja el HTTPS y los manda a la mesa correcta.
- **systemd** es el gerente que abre el restaurante cada mañana y reemplaza a quien no llegó.
- **El reload ordenado** es cambiar el menú cuando el servicio terminó, no a mitad de una comida.

- **Borrador inicial (versión Nginx):** 13 de noviembre de 2026.
- **Adaptación a Apache y red interna (Debian 13):** 15 de noviembre de 2026, con asistencia de Claude (Anthropic). **Pendiente de probar** en el entorno real.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991 y DEVOPS.

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

El código es tuyo para usar, copiar, modificar y distribuir. La única condición es mantener el aviso de copyright en las copias sustanciales.

---

## ✍️ Acerca del Autor

- **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
- **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)
