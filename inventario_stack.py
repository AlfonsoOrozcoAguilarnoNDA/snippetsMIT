#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
INVENTARIO DEL STACK DE APLICACIÓN (SOLO LECTURA)
=================================================
Licencia MIT 21/nov/2026
Alfonso Orozco Aguilar
=================================================


¿QUÉ ES ESTE SCRIPT?
    Reporta qué hay instalado y corriendo en ESTE servidor para la aplicación
    (modificar ruta): servidores web (Apache/Nginx/PHP-FPM), gunicorn/uwsgi, entornos
    Python (venv) con sus paquetes, configuración de los vhosts, unidades
    systemd/supervisor, puertos en escucha y dependencias externas del código.
    También reconstruye un requirements.txt SUGERIDO, porque el proyecto no
    tiene uno.

¿PARA QUÉ SIRVE? (CASO DE USO)
    Hay varios servidores con configuraciones distintas y nadie sabe con
    certeza qué corre en cada uno. Este script se ejecuta en cada servidor y el
    archivo de salida se devuelve a quien lo solicitó para compararlos.

¿QUÉ NO HACE?
    - NO modifica nada: no instala, no reinicia, no cambia configuración.
    - NO guarda contraseñas ni claves: de los .env solo se listan los NOMBRES de
      las variables, y los valores sospechosos (password, token, secret...) de
      comandos y configuraciones se reemplazan por ***.
    - NO envía nada por red.
    - Lo único que escribe es UN archivo de texto con el reporte.

FAILSAFE
    Cada sección corre por separado: si una falla, se anota el error y se sigue
    con la siguiente. Si no hay permiso para escribir el reporte en la carpeta
    actual, se intenta /tmp y el home; como último recurso se imprime en pantalla.

USO
    sudo python3 inventario_stack.py                  (recomendado: con root)
    sudo python3 inventario_stack.py --proyecto /ruta/del/sistema
    python3 inventario_stack.py --stdout              (imprime, no escribe archivo)
    python3 inventario_stack.py --sin-ejecutar        (no ejecuta python/pip de la app)

SI ENCUENTRAS ESTE ARCHIVO EN UN SERVIDOR
    Es una herramienta temporal de diagnóstico. Después de enviar el reporte a
    quien lo solicitó, el script y el reporte pueden borrarse.

Compatible con Python 3.5 o superior (sin dependencias externas).
Se usa el texto crm, cambialo por el nombre de tu proyecto
"""

import argparse
import glob
import hashlib
import importlib.util
import os
import platform
import pwd
import re
import shutil
import site
import socket
import subprocess
import sys
import sysconfig
from datetime import datetime

VERSION_SCRIPT = "2.0"

# Ruta del proyecto que usaba el script original. Se usa solo si existe y no se
# indica --proyecto. Además se detectan rutas desde procesos y unidades systemd.
DEFAULT_PROJECTS = ["/root/turuta"]

MAX_ARCHIVOS = 20000          # tope de archivos recorridos por proyecto
MAX_BYTES_TEXTO = 1024 * 1024  # no se leen archivos de texto mayores a 1 MB

SKIP_DIRS = set([".git", ".svn", ".hg", "node_modules", "__pycache__",
                 "site-packages", "dist-packages", ".tox", ".mypy_cache",
                 ".pytest_cache"])

# Rutas demasiado amplias: no se recorren si se detectan automáticamente.
ROOTS_PROHIBIDAS = set(["/", "/usr", "/etc", "/var", "/proc", "/sys", "/dev",
                        "/bin", "/sbin", "/lib", "/lib64", "/boot", "/root",
                        "/home", "/tmp", "/run", "/opt", "/srv"])

TEXT_EXT = (".py", ".cfg", ".ini", ".txt", ".yaml", ".yml", ".json", ".toml",
            ".conf", ".sh")

# Patrones de dependencias externas (lista de tuplas para mantener el orden).
PATRONES = [
    ("API REST (requests)", r"requests\.(get|post|put|delete|patch)"),
    ("HTTPX", r"httpx\."),
    ("AIOHTTP", r"aiohttp"),
    ("SMTP", r"smtplib|SMTP_SSL|SMTP\("),
    ("IMAP", r"imaplib"),
    ("SFTP", r"paramiko|SFTPClient"),
    ("FTP", r"ftplib"),
    ("WebSocket", r"websocket|socketio|flask_socketio"),
    ("Webhook", r"webhook"),
    ("Redis", r"redis"),
    ("RabbitMQ", r"pika"),
    ("Celery", r"celery"),
    ("SQLAlchemy", r"SQLAlchemy|create_engine|SQLALCHEMY_DATABASE_URI"),
    ("PyMySQL", r"pymysql"),
    ("MySQL", r"mysql"),
    ("PostgreSQL", r"postgres|psycopg"),
    ("MongoDB", r"pymongo|mongodb"),
    ("Flask", r"^\s*(from|import)\s+flask\b"),
    ("Django", r"^\s*(from|import)\s+django\b"),
    ("FastAPI", r"^\s*(from|import)\s+fastapi\b"),
    ("Gunicorn", r"gunicorn"),
    ("uWSGI", r"uwsgi"),
    ("Comandos del sistema (subprocess/os.system)", r"subprocess|os\.system|os\.popen"),
    ("Tareas programadas", r"apscheduler|schedule\.every|crontab"),
    ("Correo (APIs)", r"flask_mail|yagmail|sendgrid|mailgun"),
]
PATRONES_RE = [(n, re.compile(p, re.I | re.M)) for n, p in PATRONES]

KEY_FILES_RE = re.compile(
    r"^(wsgi\.py|asgi\.py|manage\.py|app\.py|main\.py|run\.py|"
    r"gunicorn.*\.(py|conf)|uwsgi.*\.ini|requirements.*\.txt|Pipfile(\.lock)?|"
    r"pyproject\.toml|setup\.(py|cfg)|Dockerfile|docker-compose.*\.ya?ml|"
    r"alembic\.ini|celery.*\.py|\.env.*|.*\.env)$")

# Nombre de import -> paquete pip habitual (cuando difieren).
IMPORT_A_PIP = {
    "yaml": "PyYAML", "PIL": "Pillow", "cv2": "opencv-python",
    "bs4": "beautifulsoup4", "dateutil": "python-dateutil", "jwt": "PyJWT",
    "MySQLdb": "mysqlclient", "sklearn": "scikit-learn",
    "dotenv": "python-dotenv", "flask_sqlalchemy": "Flask-SQLAlchemy",
    "flask_cors": "Flask-Cors", "flask_login": "Flask-Login",
    "flask_migrate": "Flask-Migrate", "flask_mail": "Flask-Mail",
    "flask_socketio": "Flask-SocketIO", "flask_wtf": "Flask-WTF",
    "Crypto": "pycryptodome", "serial": "pyserial", "OpenSSL": "pyOpenSSL",
    "magic": "python-magic", "docx": "python-docx", "git": "GitPython",
    "psycopg2": "psycopg2 / psycopg2-binary", "attr": "attrs",
    "jinja2": "Jinja2", "werkzeug": "Werkzeug", "flask": "Flask",
    "pymysql": "PyMySQL", "sqlalchemy": "SQLAlchemy", "markdown": "Markdown",
    "mysql": "mysql-connector-python", "openpyxl": "openpyxl",
    "xlrd": "xlrd", "pandas": "pandas", "numpy": "numpy",
}

RE_APP = re.compile(r"gunicorn|uwsgi|uvicorn|hypercorn|waitress|celery|flask|manage\.py|python", re.I)
RE_SVC = re.compile(r"apache2|httpd|nginx|php-fpm|php[0-9.]*-fpm|mysqld|mariadbd|redis-server|supervisord|memcached|rabbitmq", re.I)
RE_RUIDO = re.compile(r"unattended-upgrade|networkd-dispatcher|/usr/share/|fail2ban|cloud-init|landscape|ansible|salt-|inventario_stack", re.I)
RE_UNIT = re.compile(r"(apache2|httpd|nginx|php[0-9.]*-fpm|gunicorn|uwsgi|supervisor|celery|uvicorn|mariadb|mysql|redis|memcached|rabbitmq|cron|(?:^|[-_.])crm(?:[-_.]|$))", re.I)
RE_SECRETO = re.compile(r"(pass(word|wd)?|pwd|secret|token|api[_-]?key|private[_-]?key|credential)", re.I)

APACHE_DIR = re.compile(
    r"^\s*(<VirtualHost\b.*|ServerName\b.*|ServerAlias\b.*|DocumentRoot\b.*|"
    r"Listen\b.*|WSGI\w+\b.*|ProxyPass\w*\b.*|ProxyPassReverse\b.*|"
    r"SetHandler\b.*|AddHandler\b.*|ScriptAlias\b.*|Alias\b.*|SSLEngine\b.*|"
    r"SSLCertificateFile\b.*|SSLProtocol\b.*|php_(admin_)?(value|flag)\b.*|"
    r"Require\s+ip\b.*|Allow\s+from\b.*)$", re.I)
NGINX_DIR = re.compile(
    r"^\s*(server_name\b.*|listen\b.*|root\b.*|alias\b.*|location\b.*\{|"
    r"proxy_pass\b.*|fastcgi_pass\b.*|uwsgi_pass\b.*|upstream\b.*\{|"
    r"ssl_certificate\b.*|ssl_protocols\b.*|client_max_body_size\b.*|"
    r"allow\b.*|deny\b.*)$", re.I)
SYSTEMD_DIR = re.compile(
    r"^\s*(ExecStart|ExecStartPre|ExecReload|WorkingDirectory|User|Group|"
    r"EnvironmentFile|Environment|Restart|Type|After|Requires)\s*=(.*)$")
SUPERVISOR_DIR = re.compile(
    r"^\s*(\[(program|group|supervisord|unix_http_server|inet_http_server)[^\]]*\]|"
    r"(command|directory|user|autostart|autorestart|environment)\s*=.*)$")

SYS_SP = ["/usr/lib/python3/dist-packages"] + \
    sorted(glob.glob("/usr/local/lib/python3*/dist-packages")) + \
    sorted(glob.glob("/usr/local/lib/python3*/site-packages")) + \
    sorted(glob.glob("/usr/lib/python3*/site-packages"))


# ----------------------------------------------------------------------------
# Utilidades
# ----------------------------------------------------------------------------

class Reporte(object):
    def __init__(self):
        self.lineas = []
        self.errores = []

    def w(self, s=""):
        self.lineas.append(s)

    def titulo(self, t):
        self.lineas.extend(["", "=" * 70, t, "=" * 70])


def seccion(rep, titulo, func, *args):
    """Ejecuta una sección; si falla, anota el error y continúa."""
    rep.titulo(titulo)
    try:
        func(rep, *args)
    except Exception as e:
        rep.w("[ERROR en esta sección: %s: %s]" % (type(e).__name__, e))
        rep.errores.append(titulo)


def run(cmd, timeout=20, merge=False):
    """Ejecuta un comando (lista, sin shell). Devuelve su salida o '' si falla."""
    try:
        p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                           universal_newlines=True, timeout=timeout)
        out = p.stdout or ""
        if merge:
            out += p.stderr or ""
        return out.strip()
    except Exception:
        return ""


def redactar_linea(s):
    """Oculta credenciales en URLs y valores de variables/argumentos sospechosos."""
    s = re.sub(r"(://)[^/@\s:]+:[^/@\s]*@", r"\1***:***@", s)
    s = re.sub(r"(?i)((?:--?)?[\w.-]*(?:pass(?:word|wd)?|pwd|secret|token|api[_-]?key)[\w.-]*)([= ]+)(\S+)",
               r"\1\2***", s)
    return s


def redactar_directiva(linea):
    """En configuraciones: si la línea parece contener un secreto, no se muestra."""
    linea = linea.strip()
    if RE_SECRETO.search(linea):
        partes = linea.split()
        return (partes[0] if partes else "") + " [REDACTADO]"
    return redactar_linea(linea)


def hash_archivo(path):
    try:
        h = hashlib.sha256()
        with open(path, "rb") as f:
            for bloque in iter(lambda: f.read(65536), b""):
                h.update(bloque)
        return h.hexdigest()
    except Exception:
        return None


def leer_texto(path, limite=MAX_BYTES_TEXTO):
    try:
        with open(path, "r", encoding="utf-8", errors="ignore") as f:
            return f.read(limite)
    except Exception:
        return ""


def fecha_mod(path):
    try:
        return datetime.fromtimestamp(os.stat(path).st_mtime).strftime("%Y-%m-%d %H:%M:%S")
    except Exception:
        return "N/D"


def usuario_de(uid):
    try:
        return pwd.getpwuid(uid).pw_name
    except Exception:
        return str(uid)


def es_venv(path):
    return (os.path.exists(os.path.join(path, "pyvenv.cfg")) or
            (os.path.exists(os.path.join(path, "bin", "activate")) and
             os.path.isdir(os.path.join(path, "lib"))))


def venv_desde_ruta(p):
    """Sube desde un archivo dentro de un venv hasta encontrar la raíz del venv."""
    d = os.path.dirname(os.path.abspath(p))
    for _ in range(4):
        if es_venv(d):
            return d
        nd = os.path.dirname(d)
        if nd == d:
            break
        d = nd
    return None


def es_stdlib(mod, stdlib_dir):
    if mod in sys.builtin_module_names:
        return True
    try:
        spec = importlib.util.find_spec(mod)
    except Exception:
        return False
    if spec is None:
        return False
    origin = getattr(spec, "origin", None)
    if origin in (None, "built-in", "frozen"):
        return True
    return (bool(stdlib_dir) and origin.startswith(stdlib_dir) and
            "site-packages" not in origin and "dist-packages" not in origin)


def leer_dist_info(sp_dirs):
    """Lee paquetes instalados desde dist-info/egg-info (sin ejecutar nada)."""
    res = {}
    for sp in sp_dirs:
        try:
            entradas = os.listdir(sp)
        except OSError:
            continue
        for e in entradas:
            m = re.match(r"^(.+?)-(\d[^-]*?)(?:-py[\d.]+)?\.(dist-info|egg-info)$", e)
            if not m:
                continue
            nombre, ver = m.group(1), m.group(2)
            tops = set()
            try:
                with open(os.path.join(sp, e, "top_level.txt")) as f:
                    tops = set(l.strip() for l in f if l.strip())
            except Exception:
                pass
            if not tops:
                tops.add(nombre.replace("-", "_"))
            clave = nombre.lower().replace("_", "-")
            res[clave] = (nombre, ver, tops)
    return res


def sp_de_venv(v):
    return sorted(glob.glob(os.path.join(v, "lib", "python*", "site-packages")) +
                  glob.glob(os.path.join(v, "lib64", "python*", "site-packages")))


def sp_sistema():
    extra = []
    try:
        extra = list(site.getsitepackages())
    except Exception:
        pass
    vistos, res = set(), []
    for p in SYS_SP + extra:
        if p not in vistos and os.path.isdir(p):
            vistos.add(p)
            res.append(p)
    return res


def obtener_dist(ctx, clave):
    """Paquetes instalados de un entorno ('SISTEMA' o ruta de venv), con caché."""
    if clave not in ctx["dist"]:
        sps = sp_sistema() if clave == "SISTEMA" else sp_de_venv(clave)
        ctx["dist"][clave] = leer_dist_info(sps)
    return ctx["dist"][clave]


def colapsar_roots(roots):
    """Quita rutas que están dentro de otra ya incluida."""
    res = []
    for r in sorted(set(roots), key=lambda x: (len(x), x)):
        if not any(r == k or r.startswith(k.rstrip("/") + "/") for k in res):
            res.append(r)
    return res


def limpiar_url(u):
    u = u.rstrip(".,;:)>]}'\"")
    u = re.sub(r"(://)[^/@\s:]+:[^/@\s]*@", r"\1***:***@", u)
    return u.split("?")[0]


# ----------------------------------------------------------------------------
# Secciones
# ----------------------------------------------------------------------------

def sec_encabezado(rep, ctx, args):
    rep.w("Reporte generado por inventario_stack.py v%s (SOLO LECTURA)" % VERSION_SCRIPT)
    rep.w("Propósito: documentar qué corre en este servidor para compararlo con los demás.")
    rep.w("No modifica nada ni incluye contraseñas. Se puede borrar tras enviarlo.")
    rep.w("")
    rep.w("Hostname:          %s" % socket.gethostname())
    rep.w("IP(s):             %s" % (run(["hostname", "-I"]) or "N/D"))
    rep.w("Fecha:             %s" % datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
    rep.w("Ejecutado como:    %s (uid %s)" % (usuario_de(os.geteuid()), os.geteuid()))
    if os.geteuid() != 0:
        rep.w("AVISO: sin root faltará información (procesos de otros usuarios, puertos, configs).")
    os_name = "N/D"
    txt = leer_texto("/etc/os-release", 8192)
    m = re.search(r'PRETTY_NAME="?([^"\n]+)"?', txt)
    if m:
        os_name = m.group(1)
    rep.w("Sistema operativo: %s" % os_name)
    rep.w("Debian version:    %s" % (leer_texto("/etc/debian_version", 64).strip() or "N/D"))
    rep.w("Kernel:            %s" % platform.platform())
    rep.w("Python (script):   %s en %s" % (sys.version.split()[0], sys.executable))
    rep.w("Python3 en PATH:   %s" % (shutil.which("python3") or "N/D"))


def sec_servicios(rep, ctx):
    if not shutil.which("systemctl"):
        rep.w("systemctl no disponible (¿sin systemd?). Ver PROCESOS RELEVANTES.")
        return
    txt = run(["systemctl", "list-unit-files", "--type=service", "--no-legend", "--no-pager"])
    unidades = set()
    for l in txt.splitlines():
        partes = l.split()
        if partes and RE_UNIT.search(partes[0]):
            unidades.add(partes[0])
    if not unidades:
        rep.w("No se encontraron unidades relacionadas (apache, nginx, gunicorn, mysql, etc.).")
        return
    rep.w("%-45s %-12s %s" % ("unidad", "activo", "habilitado"))
    for u in sorted(unidades):
        act = run(["systemctl", "is-active", u]) or "N/D"
        hab = run(["systemctl", "is-enabled", u]) or "N/D"
        rep.w("%-45s %-12s %s" % (u, act, hab))


def virtual_env_de(pid):
    try:
        with open("/proc/%s/environ" % pid, "rb") as f:
            raw = f.read()
    except Exception:
        return None
    for kv in raw.split(b"\0"):
        if kv.startswith(b"VIRTUAL_ENV="):
            return kv[len(b"VIRTUAL_ENV="):].decode("utf-8", "ignore")
    return None


def sec_procesos(rep, ctx):
    me = os.getpid()
    filas = []
    for pid in sorted((p for p in os.listdir("/proc") if p.isdigit()), key=int):
        if int(pid) == me:
            continue
        try:
            with open("/proc/%s/cmdline" % pid, "rb") as f:
                raw = f.read()
        except Exception:
            continue
        if not raw:
            continue
        args = [a.decode("utf-8", "ignore") for a in raw.split(b"\0") if a]
        cmd = " ".join(args)
        if RE_RUIDO.search(cmd):
            continue
        if RE_SVC.search(cmd):
            tipo = "servicio"
        elif RE_APP.search(cmd):
            tipo = "python/app"
        else:
            continue
        try:
            usuario = usuario_de(os.stat("/proc/%s" % pid).st_uid)
        except Exception:
            usuario = "?"
        try:
            cwd = os.readlink("/proc/%s/cwd" % pid)
        except Exception:
            cwd = "?"
        filas.append((pid, usuario, tipo, cwd, redactar_linea(cmd)[:300]))

        if tipo == "python/app":
            cand = set()
            for a in args:
                if a.startswith("/"):
                    v = venv_desde_ruta(a)
                    if v:
                        cand.add(v)
            ve = virtual_env_de(pid)
            if ve and os.path.isdir(ve):
                cand.add(ve)
            for v in cand:
                ctx["venvs"].setdefault(v, set()).add("proceso %s (%s)" % (pid, usuario))
                ctx["venv_cwds"].setdefault(v, set()).add(cwd)
            if cwd.startswith("/") and cwd not in ROOTS_PROHIBIDAS:
                ctx["roots_auto"].append(cwd)
    ctx["procesos"] = filas
    if not filas:
        rep.w("No se encontraron procesos de aplicación ni servicios conocidos.")
        return
    if os.geteuid() != 0:
        rep.w("(Sin root puede faltar el directorio de trabajo de procesos de otros usuarios.)")
    for pid, usuario, tipo, cwd, cmd in filas:
        rep.w("[%s] pid=%s usuario=%s cwd=%s" % (tipo, pid, usuario, cwd))
        rep.w("    %s" % cmd)


def sec_puertos(rep, ctx):
    out = run(["ss", "-tulpn"])
    if not out:
        out = run(["netstat", "-tulpn"])
    rep.w(out or "No fue posible obtener los puertos (ss/netstat no disponibles).")
    if os.geteuid() != 0:
        rep.w("")
        rep.w("Nota: sin root no se ve qué proceso escucha en cada puerto.")


def _directivas(rep, path, rx):
    n = 0
    for linea in leer_texto(path, 512 * 1024).splitlines():
        if linea.strip().startswith("#"):
            continue
        if rx.match(linea):
            rep.w("      " + redactar_directiva(linea))
            n += 1
    if n == 0:
        rep.w("      (sin directivas relevantes)")


def sec_web(rep, ctx):
    rep.w("-- Versiones --")
    for nombre, cmd in [("apache2", ["apache2", "-v"]), ("httpd", ["httpd", "-v"]),
                        ("nginx", ["nginx", "-v"]), ("php", ["php", "-v"])]:
        if shutil.which(cmd[0]):
            salida = run(cmd, merge=True).splitlines()
            rep.w("%-10s %s" % (nombre, salida[0] if salida else "(sin salida)"))
        else:
            rep.w("%-10s no instalado" % nombre)
    for fpm in sorted(glob.glob("/usr/sbin/php-fpm*")):
        salida = run([fpm, "-v"], merge=True).splitlines()
        rep.w("%-10s %s" % (os.path.basename(fpm), salida[0] if salida else "(sin salida)"))
    for nombre in ("gunicorn", "uwsgi", "supervisord"):
        rep.w("%-10s %s" % (nombre + " (PATH)", shutil.which(nombre) or "no está en el PATH del sistema"))

    rep.w("")
    rep.w("-- Módulos de Apache --")
    mods = ""
    for ctl in ("apache2ctl", "apachectl", "httpd"):
        if shutil.which(ctl):
            mods = run([ctl, "-M"])
            if mods:
                break
    if mods:
        nombres = sorted(set(re.findall(r"^\s*(\w+)_module", mods, re.M)))
        rep.w(", ".join(nombres))
    elif os.path.isdir("/etc/apache2/mods-enabled"):
        nombres = sorted(set(re.sub(r"\.(load|conf)$", "", x) for x in os.listdir("/etc/apache2/mods-enabled")))
        rep.w("(desde mods-enabled) " + ", ".join(nombres))
    else:
        rep.w("No disponible.")

    rep.w("")
    rep.w("-- Módulos de PHP (php -m) --")
    if shutil.which("php"):
        mp = run(["php", "-m"])
        rep.w(", ".join(sorted(set(l.strip() for l in mp.splitlines()
                                   if l.strip() and not l.startswith("[")))) or "No disponible.")
    else:
        rep.w("php no instalado.")

    rep.w("")
    rep.w("-- Archivos de configuración (ruta | sha256 | bytes | modificado) --")
    grupos = [
        ("Apache", ["/etc/apache2/apache2.conf", "/etc/apache2/ports.conf",
                    "/etc/apache2/sites-enabled/*", "/etc/apache2/conf-enabled/*",
                    "/etc/httpd/conf/httpd.conf", "/etc/httpd/conf.d/*.conf"], APACHE_DIR),
        ("Nginx", ["/etc/nginx/nginx.conf", "/etc/nginx/sites-enabled/*",
                   "/etc/nginx/conf.d/*.conf"], NGINX_DIR),
    ]
    encontrados = 0
    for etiqueta, patrones, rx in grupos:
        for patron in patrones:
            for p in sorted(glob.glob(patron)):
                if not os.path.isfile(p):
                    continue
                encontrados += 1
                real = os.path.realpath(p)
                destino = "" if real == p else "  -> %s" % real
                try:
                    tam = os.path.getsize(p)
                except Exception:
                    tam = "N/D"
                rep.w("[%s] %s%s" % (etiqueta, p, destino))
                rep.w("    %s | %s | %s" % (hash_archivo(p) or "N/D", tam, fecha_mod(p)))
                _directivas(rep, p, rx)
    if not encontrados:
        rep.w("No se encontraron archivos de configuración de Apache ni Nginx en rutas estándar.")

    rep.w("")
    rep.w("-- Paquetes instalados relacionados (dpkg) --")
    if shutil.which("dpkg-query"):
        txt = run(["dpkg-query", "-W", "-f", "${Package}==${Version}\n"], timeout=60)
        rx = re.compile(r"^(apache2|libapache2|nginx|php|uwsgi|gunicorn|python3-gunicorn|"
                        r"supervisor|mariadb|mysql|redis|memcached)", re.I)
        lista = sorted(l for l in txt.splitlines() if rx.match(l))
        rep.w("\n".join(lista) if lista else "Ninguno encontrado.")
    else:
        rep.w("dpkg-query no disponible.")


def sec_unidades(rep, ctx):
    rep.w("-- systemd (unidades de la aplicación) --")
    candidatos = []
    for p in sorted(glob.glob("/etc/systemd/system/*.service")):
        if re.search(r"gunicorn|uwsgi|celery|uvicorn|python|flask|django|(?:^|[-_./])crm(?:[-_./]|$)", leer_texto(p, 65536), re.I):
            candidatos.append(p)
    for base in ("/lib/systemd/system", "/usr/lib/systemd/system"):
        for p in sorted(glob.glob(base + "/*.service")):
            if re.search(r"gunicorn|uwsgi|celery|uvicorn|supervisor|(?:^|[-_.])crm(?:[-_.]|$)|flask|django",
                         os.path.basename(p), re.I):
                candidatos.append(p)
    vistos = set()
    n = 0
    for p in candidatos:
        real = os.path.realpath(p)
        if real in vistos:
            continue
        vistos.add(real)
        n += 1
        rep.w("[unit] %s" % p)
        rep.w("    sha256: %s" % (hash_archivo(p) or "N/D"))
        for linea in leer_texto(p, 65536).splitlines():
            m = SYSTEMD_DIR.match(linea)
            if not m:
                continue
            clave, valor = m.group(1), m.group(2).strip()
            if clave == "Environment":
                nombres = re.findall(r"([A-Za-z_][A-Za-z0-9_]*)=", valor)
                rep.w("    Environment (solo nombres): %s" % ", ".join(nombres))
                continue
            if clave == "WorkingDirectory" and valor.startswith("/") and valor not in ROOTS_PROHIBIDAS:
                ctx["roots_auto"].append(valor)
            rep.w("    %s=%s" % (clave, redactar_linea(valor)))
    if not n:
        rep.w("No se encontraron unidades de aplicación.")

    rep.w("")
    rep.w("-- supervisor --")
    sup = sorted(glob.glob("/etc/supervisor/conf.d/*") + glob.glob("/etc/supervisord.d/*.ini") +
                 glob.glob("/etc/supervisor/supervisord.conf"))
    sup = [p for p in sup if os.path.isfile(p)]
    if not sup:
        rep.w("No se encontró configuración de supervisor.")
    for p in sup:
        rep.w("[conf] %s" % p)
        rep.w("    sha256: %s" % (hash_archivo(p) or "N/D"))
        for linea in leer_texto(p, 65536).splitlines():
            if linea.strip().startswith(";") or linea.strip().startswith("#"):
                continue
            if SUPERVISOR_DIR.match(linea):
                if linea.strip().lower().startswith("environment"):
                    nombres = re.findall(r"([A-Za-z_][A-Za-z0-9_]*)\s*=", linea.split("=", 1)[1])
                    rep.w("    environment (solo nombres): %s" % ", ".join(nombres))
                else:
                    rep.w("    " + redactar_directiva(linea))
                    m = re.match(r"^\s*directory\s*=\s*(/\S+)", linea)
                    if m and m.group(1) not in ROOTS_PROHIBIDAS:
                        ctx["roots_auto"].append(m.group(1))


def escanear(root, stdlib_dir):
    r = {"root": root, "archivos": 0, "truncado": False, "claves": [], "deps": {},
         "urls": set(), "imports": {}, "envs": {}, "venvs": [], "locales": set(),
         "pyfiles": 0}
    for n, _ in PATRONES_RE:
        r["deps"][n] = set()
    cont = [0]
    hecho = False
    for base, dirs, files in os.walk(root, followlinks=False):
        for d in list(dirs):
            full = os.path.join(base, d)
            if d in SKIP_DIRS or d.endswith(".egg-info"):
                dirs.remove(d)
                continue
            if es_venv(full):
                r["venvs"].append(full)
                dirs.remove(d)
                continue
            r["locales"].add(d)
        dirs.sort()
        for fn in sorted(files):
            cont[0] += 1
            if cont[0] > MAX_ARCHIVOS:
                r["truncado"] = True
                hecho = True
                break
            full = os.path.join(base, fn)
            rel = os.path.relpath(full, root)
            if os.path.islink(full):
                continue
            try:
                tam = os.path.getsize(full)
            except Exception:
                continue
            es_env = fn == ".env" or fn.startswith(".env.") or fn.endswith(".env")
            if KEY_FILES_RE.match(fn):
                r["claves"].append((rel, hash_archivo(full) or "N/D", tam))
            if fn.endswith(".py"):
                r["locales"].add(fn[:-3])
                r["pyfiles"] += 1
            if not (fn.endswith(TEXT_EXT) or es_env) or tam > MAX_BYTES_TEXTO:
                continue
            texto = leer_texto(full)
            if not texto:
                continue
            for nombre, rx in PATRONES_RE:
                if rx.search(texto):
                    r["deps"][nombre].add(rel)
            for u in re.findall(r"https?://[^\s'\"<>]+", texto):
                r["urls"].add(limpiar_url(u))
            if es_env:
                nombres = []
                for linea in texto.splitlines():
                    linea = linea.strip()
                    if not linea or linea.startswith("#") or "=" not in linea:
                        continue
                    nombres.append(re.sub(r"^export\s+", "", linea.split("=", 1)[0]).strip())
                r["envs"][rel] = nombres
            if fn.endswith(".py"):
                mods = set()
                for m in re.finditer(r"^[ \t]*from[ \t]+([A-Za-z_]\w*)", texto, re.M):
                    mods.add(m.group(1))
                for m in re.finditer(r"^[ \t]*import[ \t]+([^\n#]+)", texto, re.M):
                    for parte in m.group(1).split(","):
                        tok = parte.strip().split(" ")[0].split(".")[0]
                        if re.match(r"^[A-Za-z_]\w*$", tok):
                            mods.add(tok)
                for mod in mods:
                    r["imports"].setdefault(mod, set()).add(rel)
        if hecho:
            break
    r["archivos"] = min(cont[0], MAX_ARCHIVOS)
    # Imports de terceros: ni stdlib ni módulos locales del proyecto.
    terceros = {}
    for mod, archivos in r["imports"].items():
        if mod in r["locales"] or es_stdlib(mod, stdlib_dir):
            continue
        terceros[mod] = len(archivos)
    r["terceros"] = terceros
    return r


def sec_proyectos(rep, ctx, args):
    if args.proyecto:
        roots = [os.path.abspath(p) for p in args.proyecto]
    else:
        roots = []
        for d in list(ctx["roots_auto"]) + DEFAULT_PROJECTS:
            d = os.path.abspath(d)
            if d not in ROOTS_PROHIBIDAS and os.path.isdir(d):
                roots.append(d)
    roots = [r for r in colapsar_roots(roots) if r != "/" and os.path.isdir(r)]
    if not roots:
        rep.w("No se detectó ningún proyecto automáticamente.")
        rep.w("Volver a ejecutar indicando la ruta:  python3 inventario_stack.py --proyecto /ruta/del/sistema")
        return
    try:
        stdlib_dir = sysconfig.get_paths().get("stdlib", "")
    except Exception:
        stdlib_dir = ""
    for root in roots:
        rep.w("#" * 70)
        rep.w("PROYECTO: %s" % root)
        try:
            r = escanear(root, stdlib_dir)
        except Exception as e:
            rep.w("[ERROR al recorrer el proyecto: %s: %s]" % (type(e).__name__, e))
            rep.errores.append("proyecto " + root)
            continue
        ctx["proyectos"].append(r)
        try:
            dueno = usuario_de(os.stat(root).st_uid)
        except Exception:
            dueno = "?"
        rep.w("Dueño: %s | archivos recorridos: %d | archivos .py: %d%s" % (
            dueno, r["archivos"], r["pyfiles"],
            " | RECORRIDO TRUNCADO (límite %d)" % MAX_ARCHIVOS if r["truncado"] else ""))
        if r["venvs"]:
            rep.w("Entornos virtuales dentro del proyecto: %s" % ", ".join(sorted(r["venvs"])))
        else:
            rep.w("Entornos virtuales dentro del proyecto: ninguno")

        rep.w("")
        rep.w("-- Archivos clave (ruta relativa | sha256 | bytes) --")
        if r["claves"]:
            for rel, h, tam in sorted(r["claves"]):
                rep.w("%s | %s | %s" % (rel, h, tam))
            if not any(re.match(r"requirements.*\.txt$", os.path.basename(c[0])) for c in r["claves"]):
                rep.w("(No existe ningún requirements*.txt en el proyecto.)")
        else:
            rep.w("(ninguno)")

        rep.w("")
        rep.w("-- Variables definidas en archivos .env (SOLO NOMBRES, nunca valores) --")
        if r["envs"]:
            for rel in sorted(r["envs"]):
                rep.w("%s: %s" % (rel, ", ".join(r["envs"][rel]) or "(vacío)"))
        else:
            rep.w("No existe ningún archivo .env.")

        rep.w("")
        rep.w("-- Dependencias externas detectadas en el código --")
        alguna = False
        for nombre, _ in PATRONES_RE:
            archivos = sorted(r["deps"][nombre])
            if not archivos:
                continue
            alguna = True
            rep.w("[%s] (%d archivos)" % (nombre, len(archivos)))
            for a in archivos[:15]:
                rep.w("    " + a)
            if len(archivos) > 15:
                rep.w("    ... (+%d más)" % (len(archivos) - 15))
        if not alguna:
            rep.w("(ninguna)")

        rep.w("")
        rep.w("-- Librerías de terceros importadas por el código --")
        if r["terceros"]:
            for mod in sorted(r["terceros"], key=lambda m: (-r["terceros"][m], m)):
                pista = IMPORT_A_PIP.get(mod)
                rep.w("%-28s %4d archivo(s)%s" % (mod, r["terceros"][mod],
                                                  "   (paquete pip: %s)" % pista if pista else ""))
        else:
            rep.w("(ninguna)")

        rep.w("")
        rep.w("-- URLs encontradas (sin credenciales ni parámetros) --")
        if r["urls"]:
            for u in sorted(r["urls"])[:200]:
                rep.w(u)
            if len(r["urls"]) > 200:
                rep.w("... (+%d más)" % (len(r["urls"]) - 200))
        else:
            rep.w("No se encontraron URLs.")


def pip_freeze(py, args):
    if args.sin_ejecutar:
        return ""
    return run([py, "-m", "pip", "freeze", "--disable-pip-version-check"], timeout=60)


def listar_paquetes(rep, ctx, clave, py, args):
    dist = obtener_dist(ctx, clave)
    freeze = pip_freeze(py, args) if py else ""
    if freeze:
        lineas = sorted(l for l in freeze.splitlines() if l.strip())
        rep.w("pip freeze (%d paquetes):" % len(lineas))
        rep.w("\n".join(lineas))
        ctx["npaquetes"] = max(ctx["npaquetes"], len(lineas))
    elif dist:
        lineas = sorted("%s==%s" % (n, v) for (n, v, _t) in dist.values())
        rep.w("Paquetes leídos de dist-info/egg-info (%d) [pip no se ejecutó o no respondió]:" % len(lineas))
        rep.w("\n".join(lineas))
        ctx["npaquetes"] = max(ctx["npaquetes"], len(lineas))
    else:
        rep.w("No fue posible obtener la lista de paquetes de este entorno.")


def sec_entornos(rep, ctx, args):
    rep.w("-- Python del sistema --")
    py3 = shutil.which("python3")
    rep.w("python3: %s  %s" % (py3 or "N/D", run([py3, "--version"], merge=True) if py3 else ""))
    rep.w("pip del sistema: %s" % (run([sys.executable, "-m", "pip", "--version"]) or "no disponible"))
    listar_paquetes(rep, ctx, "SISTEMA", sys.executable, args)
    if shutil.which("dpkg-query"):
        txt = run(["dpkg-query", "-W", "-f", "${Package}==${Version}\n", "python3-*"], timeout=60)
        lista = sorted(l for l in txt.splitlines() if l.strip())
        rep.w("")
        rep.w("Paquetes apt python3-* (%d):" % len(lista))
        rep.w("\n".join(lista))

    venvs = set(ctx["venvs"].keys())
    for p in ctx["proyectos"]:
        venvs.update(p["venvs"])
    ctx["todos_venvs"] = sorted(venvs)
    if not venvs:
        rep.w("")
        rep.w("No se encontraron entornos virtuales (ni en procesos ni dentro de los proyectos).")
    for v in sorted(venvs):
        rep.w("")
        rep.w("-- Entorno virtual: %s --" % v)
        origen = sorted(ctx["venvs"].get(v, []))
        rep.w("Detectado por: %s" % (", ".join(origen) if origen else "ubicado dentro del proyecto"))
        cfg = leer_texto(os.path.join(v, "pyvenv.cfg"), 4096)
        if cfg:
            for l in cfg.splitlines():
                if re.match(r"^\s*(home|version|version_info|include-system-site-packages)\s*=", l):
                    rep.w("pyvenv.cfg: " + l.strip())
        py = os.path.join(v, "bin", "python")
        if not os.access(py, os.X_OK):
            py = None
        listar_paquetes(rep, ctx, v, py, args)


def sec_requirements(rep, ctx):
    rep.w("Reconstruido cruzando las librerías que IMPORTA el código con los paquetes")
    rep.w("INSTALADOS en el entorno donde corre. Solo incluye dependencias directas;")
    rep.w("para la lista completa (con dependencias transitivas) ver 'pip freeze' arriba.")
    if not ctx["proyectos"]:
        rep.w("")
        rep.w("(No hay proyectos analizados.)")
        return
    for r in ctx["proyectos"]:
        root = r["root"]
        rep.w("")
        rep.w("# Proyecto: %s" % root)
        envs = []
        for v in ctx.get("todos_venvs", []):
            cwds = ctx["venv_cwds"].get(v, set())
            if (v in r["venvs"] or v.startswith(root.rstrip("/") + "/") or
                    any(c == root or c.startswith(root.rstrip("/") + "/") for c in cwds)):
                envs.append(v)
        envs.append("SISTEMA")
        rep.w("# Entornos consultados (en orden de prioridad): %s" % ", ".join(envs))
        mapa = {}
        for clave in reversed(envs):          # el último en escribirse tiene prioridad
            for (nombre, ver, tops) in obtener_dist(ctx, clave).values():
                for t in tops:
                    mapa[t.lower()] = (nombre, ver, clave)
        sugeridos, faltantes = set(), []
        for mod in sorted(r["terceros"]):
            hit = mapa.get(mod.lower())
            if hit:
                sugeridos.add("%s==%s" % (hit[0], hit[1]) + ("   # instalado a nivel sistema" if hit[2] == "SISTEMA" else ""))
            else:
                faltantes.append(mod)
        rep.w("# --- requirements.txt sugerido ---")
        rep.w("\n".join(sorted(sugeridos)) if sugeridos else "(ninguno)")
        if faltantes:
            rep.w("# --- Imports de terceros SIN paquete instalado en esos entornos (revisar) ---")
            for mod in faltantes:
                pista = IMPORT_A_PIP.get(mod)
                rep.w("%s%s" % (mod, "   (probable paquete: %s)" % pista if pista else ""))


def sec_resumen(rep, ctx):
    total_deps = sum(len(v) for r in ctx["proyectos"] for v in r["deps"].values())
    urls = sum(len(r["urls"]) for r in ctx["proyectos"])
    rep.w("Procesos relevantes:        %d" % len(ctx["procesos"]))
    rep.w("Proyectos analizados:       %d" % len(ctx["proyectos"]))
    rep.w("Entornos virtuales:         %d" % len(ctx.get("todos_venvs", [])))
    rep.w("Dependencias (archivos):    %d" % total_deps)
    rep.w("URLs encontradas:           %d" % urls)
    rep.w("Paquetes (entorno mayor):   %d" % ctx["npaquetes"])
    if rep.errores:
        rep.w("Secciones con error:        %s" % "; ".join(rep.errores))
    else:
        rep.w("Secciones con error:        ninguna")


# ----------------------------------------------------------------------------

def guardar(rep, args):
    texto = "\n".join(rep.lineas) + "\n"
    if args.stdout:
        print(texto)
        return None
    host = re.sub(r"[^A-Za-z0-9_.-]", "_", socket.gethostname())
    nombre = "inventario_stack_%s_%s.txt" % (host, datetime.now().strftime("%Y%m%d_%H%M%S"))
    dirs = [args.salida] if args.salida else []
    dirs += [os.getcwd(), "/tmp", os.path.expanduser("~")]
    for d in dirs:
        try:
            ruta = os.path.join(d, nombre)
            with open(ruta, "w", encoding="utf-8") as f:
                f.write(texto)
            return ruta
        except Exception:
            continue
    print("AVISO: no hubo dónde escribir el reporte; se imprime en pantalla.\n")
    print(texto)
    return None


def main():
    ap = argparse.ArgumentParser(description="Inventario de solo lectura del stack de la aplicación.")
    ap.add_argument("--proyecto", action="append", default=[],
                    help="ruta del proyecto a analizar (se puede repetir). Por defecto se detecta sola.")
    ap.add_argument("--salida", default=None, help="carpeta donde escribir el reporte (por defecto, la actual)")
    ap.add_argument("--stdout", action="store_true", help="imprimir en pantalla en lugar de escribir archivo")
    ap.add_argument("--sin-ejecutar", action="store_true",
                    help="no ejecutar python/pip de los entornos de la app; solo leer archivos")
    args = ap.parse_args()

    os.umask(0o077)   # el reporte solo lo puede leer quien lo ejecuta
    rep = Reporte()
    ctx = {"procesos": [], "venvs": {}, "venv_cwds": {}, "roots_auto": [],
           "proyectos": [], "dist": {}, "npaquetes": 0}

    seccion(rep, "ENCABEZADO", sec_encabezado, ctx, args)
    seccion(rep, "SERVICIOS (systemd)", sec_servicios, ctx)
    seccion(rep, "PROCESOS RELEVANTES (gunicorn, uwsgi, python, apache, nginx, php-fpm, bases de datos)", sec_procesos, ctx)
    seccion(rep, "PUERTOS EN ESCUCHA", sec_puertos, ctx)
    seccion(rep, "SERVIDORES WEB, PHP Y CONFIGURACIÓN (apache / nginx)", sec_web, ctx)
    seccion(rep, "UNIDADES SYSTEMD Y SUPERVISOR DE LA APLICACIÓN", sec_unidades, ctx)
    seccion(rep, "PROYECTO(S) DETECTADO(S)", sec_proyectos, ctx, args)
    seccion(rep, "ENTORNOS PYTHON Y PAQUETES", sec_entornos, ctx, args)
    seccion(rep, "REQUIREMENTS.TXT RECONSTRUIDO", sec_requirements, ctx)
    seccion(rep, "RESUMEN", sec_resumen, ctx)

    ruta = guardar(rep, args)
    if ruta:
        print("\nReporte generado correctamente: %s" % ruta)
        if rep.errores:
            print("Aviso: hubo errores en estas secciones (el resto se generó): %s" % "; ".join(rep.errores))
        print("Enviar ese archivo a quien lo solicitó. Después se puede borrar junto con este script.")


if __name__ == "__main__":
    main()
