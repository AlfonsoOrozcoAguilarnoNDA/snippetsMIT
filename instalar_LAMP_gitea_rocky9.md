![Logo de Vibe Coding México](instalarrocky.jpg)

[![Rocky Linux](https://img.shields.io/badge/rocky%20linux-9-10B981.svg)](https://rockylinux.org)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892bf.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

# 🪨 Manual: LAMP + Git + Gitea en Rocky Linux 9 (Vultr)

**Stack:** LAMP (Rocky Linux 9) + SSL Certbot + PHP 8.2 + Git + Gitea  
**Plataforma base:** Vultr VPS — La Maravilla de 5 USD  
**Fecha de redacción:** Marzo 2026

> La versión Debian de este stack está en [github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT](https://github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT)

---

## Por qué Rocky Linux aquí — y por qué Debian sigue siendo la recomendación base

Mi recomendación personal, y la que uso en [vibecodingmexico.com](https://vibecodingmexico.com), es **Debian** para servidores donde quieres control fino: instalas lo que necesitas, lo configuras a tu gusto y sabes exactamente qué hay adentro.

Uso Rocky Linux 9.0 con algunios clientes que no quieren o no pueden actualizar a Rocky 10, debe funcionar con cambios menores.

En lo eprsonal, uso Rocky Linux para servidores que no modifico el código, así que se que para mi Rocky Linux es respaldar solo base de datos.

Rocky Linux cumple un rol diferente en MI modelo mental. Cuando entro a tu panel de Vultr y veo el ícono de Rocky Linux, el mensaje es claro: **este servidor no se toca**. Está ahí para recibir deploys automáticos por pull, no para que le esté moviendo la configuración. Mi trabajo con él es simple: respaldar las bases de datos y dejarlo correr.

Dicho de otro modo:

| | Debian | Rocky Linux |
|---|---|---|
| **Perfil** | Control fino, configurable | Estable, no se toca |
| **Mentalidad** | "Yo configuro todo" | "Solo respaldo DBs y lo dejo correr" |
| **Uso típico** | Servidor principal, experimentos | Pull deployment automático |

Ambos son excelentes. La diferencia es de intención.

---

## Gestores de paquetes — por qué importa conocer más de uno

Debian usa `apt`. Rocky Linux usa `dnf`. Son herramientas diferentes que hacen lo mismo: instalar, actualizar y remover software en el sistema.

| Distribución | Familia | Gestor |
|---|---|---|
| Debian / Ubuntu | Debian | `apt` |
| Rocky Linux / AlmaLinux | Red Hat | `dnf` / `yum` |
| Arch Linux | Arch | `pacman` |

Aunque en la práctica puedes vivir toda tu carrera usando solo `apt`, conocer `dnf` te da algo más valioso que los comandos en sí: **un modelo mental de organización**. Cuando sabes que existen familias de distribuciones con sus propias herramientas, dejas de ver Linux como una cosa monolítica y empiezas a entender por qué un script que funciona en Debian no funciona en Rocky sin modificaciones. Eso te hace mejor administrador de sistemas.

---

## Scope de este manual — solo PHP

Este manual instala **únicamente el stack PHP**: Apache, MariaDB, PHP y Gitea.

No incluye Node.js, React ni Quarkus. Esto **no es un olvido** — es una decisión consciente.

En diciembre de 2025 se divulgaron cuatro vulnerabilidades críticas en el ecosistema React, incluyendo **CVE-2025-55182** ("React2Shell"), con puntuación CVSS 10.0 — la máxima posible — que permite ejecución remota de código en React Server Components. Grupos de amenaza persistente avanzada (APT) comenzaron a explotar la vulnerabilidad en horas. Las versiones de parche posteriores (19.0.2, 19.1.3, 19.2.2) resultaron igualmente vulnerables según **CVE-2025-67779**.

El ecosistema npm acumula cientos de dependencias transitivas que hacen difícil auditar la superficie de ataque. En un servidor de pull deployment — que es exactamente el uso que le damos a Rocky Linux en este modelo — no tiene sentido agregar esa superficie de riesgo.

Para Node, React y Quarkus, consulta el manual Debian completo en [github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT](https://github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT).

---

## ⚠️ Antes de empezar — Abre tu bloc de notas

Reserva espacio para estos datos. Los vas a necesitar varias veces:

```
IP del servidor:        _______________
Password root:          _______________
Password MariaDB root:  _______________
Usuario MariaDB app:    adminsql
Password MariaDB app:   _______________
Gitea Admin User:       _______________
Gitea Admin Password:   _______________   ← ANÓTALA AHORA
Gitea Admin Email:      _______________
URL de Gitea:           https://git.tu-dominio.com
```

> **Advertencia:** Gitea no tiene recuperación de contraseña por correo por defecto. Si la pierdes, tendrás que resetearla por línea de comandos. Anótala en papel si hace falta.

---

## Parte 1 — LAMP en Rocky Linux 9

---

## 1. Actualización del Sistema

```bash
dnf update -y
```

En Rocky Linux, `dnf` reemplaza a `apt`. El comportamiento es equivalente.

---

## 2. Instalar Apache

```bash
dnf install httpd -y

systemctl enable httpd
systemctl start httpd

# Verificar que está corriendo
systemctl status httpd
```

> En Debian el servicio se llama `apache2`. En Rocky Linux se llama `httpd`. El comportamiento es idéntico.

---

## 3. Firewall

Rocky Linux usa `firewalld` por defecto, no UFW. Los comandos son diferentes pero el resultado es el mismo: abrir solo los puertos necesarios.

```bash
# Habilitar firewalld si no está activo
systemctl enable firewalld
systemctl start firewalld

# Abrir puertos necesarios
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --permanent --add-service=ssh

# Aplicar cambios
firewall-cmd --reload

# Verificar
firewall-cmd --list-all
```

---

## 4. Instalar PHP 8.2

Rocky Linux 9 trae PHP en sus repositorios base, pero para tener PHP 8.2 con todas las extensiones necesarias usamos el repositorio **Remi**, que es el estándar de la comunidad para PHP en distribuciones Red Hat.

```bash
# Instalar repositorio EPEL (prerequisito de Remi)
dnf install epel-release -y

# Instalar repositorio Remi
dnf install https://rpms.remirepo.net/enterprise/remi-release-9.rpm -y

# Habilitar el módulo PHP 8.2 de Remi
dnf module reset php -y
dnf module enable php:remi-8.2 -y

# Instalar PHP y extensiones
dnf install php php-mysqlnd php-curl php-bcmath php-xml \
  php-mbstring php-zip php-gd php-intl php-fpm -y

# Verificar versión
php -v
```

**Por qué cada extensión importa:**

- `php-mysqlnd` — conexión nativa a MariaDB/MySQL
- `php-curl` — peticiones externas, APIs de pago, verificación de licencias
- `php-bcmath` — aritmética de precisión, indispensable en facturación electrónica
- `php-xml` — estructuras XML, CFDI, RSS
- `php-mbstring` — cadenas multibyte, caracteres especiales en español
- `php-zip` — manejo de archivos comprimidos
- `php-gd` — generación de imágenes
- `php-intl` — internacionalización

### Activar PHP-FPM

```bash
systemctl enable php-fpm
systemctl start php-fpm
systemctl status php-fpm
```

```bash
# Reiniciar Apache para que cargue PHP
systemctl restart httpd
```

---

## 5. Instalar MariaDB

```bash
dnf install mariadb-server -y

systemctl enable mariadb
systemctl start mariadb

# Verificar
systemctl status mariadb
```

### Seguridad inicial

```bash
mysql_secure_installation
```

Si falla, entra directamente a MariaDB:

```bash
mariadb
```

```sql
SET PASSWORD FOR 'root'@'localhost' = PASSWORD('TuClaveSeguraAqui');
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EXIT;
```

### Crear bases de datos y usuario de aplicación

> **Buena práctica:** Nunca uses `root` desde tu aplicación. Crea un usuario dedicado.

```bash
mysql -u root -p
```

```sql
CREATE USER 'adminsql'@'localhost' IDENTIFIED BY 'TuClaveSeguraAqui';

CREATE DATABASE db_principal;
CREATE DATABASE db_gitea;
CREATE DATABASE db_extra1;
CREATE DATABASE db_extra2;
CREATE DATABASE db_extra3;

GRANT ALL PRIVILEGES ON *.* TO 'adminsql'@'localhost' WITH GRANT OPTION;

FLUSH PRIVILEGES;
SHOW DATABASES;
EXIT;
```

---

## 6. VirtualHost Apache

### Crear directorios

```bash
mkdir -p /var/www/tu-dominio.com/public_html
chown -R apache:apache /var/www/tu-dominio.com
chmod -R 755 /var/www/tu-dominio.com
```

> En Rocky Linux el usuario de Apache se llama `apache`, no `www-data` como en Debian. Cualquier directorio que Apache deba leer necesita ese propietario.

Pon un archivo de prueba antes de continuar:

```bash
echo "<h1>Hola desde Rocky Linux</h1>" > /var/www/tu-dominio.com/public_html/index.html
```

### Archivo de configuración

En Rocky Linux los VirtualHosts van en `/etc/httpd/conf.d/`:

```bash
nano /etc/httpd/conf.d/tu-dominio.com.conf
```

```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    ServerAlias www.tu-dominio.com
    ServerAdmin tucuenta@gmail.com

    DocumentRoot /var/www/tu-dominio.com/public_html

    <Directory /var/www/tu-dominio.com/public_html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/httpd/tu-dominio-error.log
    CustomLog /var/log/httpd/tu-dominio-access.log combined
</VirtualHost>
```

```bash
# Verificar configuración
httpd -t

# Recargar Apache
systemctl reload httpd
```

### Nota sobre SELinux

Rocky Linux incluye **SELinux** activo por defecto. SELinux es una capa de seguridad adicional que puede bloquear Apache silenciosamente si los permisos no están correctos. Si Apache no sirve tus archivos aunque la configuración parece correcta, ejecuta:

```bash
# Ver si SELinux está bloqueando algo
ausearch -c 'httpd' --raw | audit2allow -M my-httpd
semodule -X 300 -i my-httpd.pp
```

Para el uso normal de este manual, el contexto por defecto de `/var/www` ya está configurado correctamente para Apache. El problema aparece principalmente cuando apuntas Apache a directorios fuera de `/var/www`.

---

## 7. Certificados SSL con Certbot

El dominio debe apuntar a la IP del VPS antes de ejecutar esto. Consulta el post [Apuntar Dominio a Vultr](https://vibecodingmexico.com/apuntar-dominio-a-vultr/) si necesitas ese paso.

```bash
dnf install certbot python3-certbot-apache -y

certbot --apache -d tu-dominio.com -d www.tu-dominio.com

# Verificar renovación automática
systemctl status certbot-renew.timer
certbot renew --dry-run
```

---

## Parte 2 — Git

---

## 8. Instalar Git

```bash
dnf install git -y

# Verificar
git --version
```

Esta es la parte más sencilla del manual. Git está disponible en los repositorios base de Rocky Linux sin configuración adicional.

---

## Parte 3 — Gitea

---

## 9. Apuntar el Subdominio

Antes de cualquier instalación, entra a tu panel de DNS (Vultr o tu registrador) y crea un registro:

```
Tipo:   A
Nombre: git
Valor:  [IP de tu VPS]
TTL:    300
```

Espera a que propague antes de continuar:

```bash
ping git.tu-dominio.com
```

No prossigas hasta que responda con la IP correcta.

---

## 10. Crear Usuario del Sistema para Gitea

Gitea necesita un usuario dedicado. **No lo ejecutes como root.**

```bash
useradd \
  --system \
  --shell /bin/bash \
  --comment 'Gitea' \
  --create-home \
  --home-dir /home/gitea \
  gitea
```

> En Rocky Linux el comando es `useradd` con parámetros equivalentes al `adduser` de Debian.

---

## 11. Crear Directorios de Gitea

```bash
mkdir -p /var/lib/gitea/{custom,data,log}
chown -R gitea:gitea /var/lib/gitea/
chmod -R 750 /var/lib/gitea/

mkdir -p /etc/gitea
chown root:gitea /etc/gitea
chmod 770 /etc/gitea
```

---

## 12. Descargar Gitea

Verifica la versión más reciente en [https://dl.gitea.com/gitea/](https://dl.gitea.com/gitea/)

```bash
wget -O /usr/local/bin/gitea \
  https://dl.gitea.com/gitea/1.22.3/gitea-1.22.3-linux-amd64

chmod +x /usr/local/bin/gitea

# Verificar
gitea --version
```

---

## 13. Crear el Servicio systemd

```bash
nano /etc/systemd/system/gitea.service
```

```ini
[Unit]
Description=Gitea (Git with a cup of tea)
After=syslog.target
After=network.target

[Service]
RestartSec=2s
Type=simple
User=gitea
Group=gitea
WorkingDirectory=/var/lib/gitea/
ExecStart=/usr/local/bin/gitea web --config /etc/gitea/app.ini
Restart=always
Environment=USER=gitea HOME=/home/gitea GITEA_WORK_DIR=/var/lib/gitea

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable gitea
systemctl start gitea

# Verificar
systemctl status gitea
```

---

## 14. Configurar Apache como Proxy Inverso

Gitea corre en el puerto 3000 internamente. Apache lo expone al mundo en los puertos 80/443.

Primero verifica que los módulos proxy estén disponibles:

```bash
httpd -M | grep proxy
```

Si no aparecen, edita la configuración:

```bash
nano /etc/httpd/conf.modules.d/00-proxy.conf
```

Asegúrate de que estas líneas estén descomentadas:

```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
```

Crea la configuración del subdominio:

```bash
nano /etc/httpd/conf.d/git.tu-dominio.com.conf
```

```apache
<VirtualHost *:80>
    ServerName git.tu-dominio.com
    ServerAdmin admin@tu-dominio.com

    ProxyPreserveHost On
    ProxyRequests Off
    ProxyPass / http://localhost:3000/
    ProxyPassReverse / http://localhost:3000/

    # Sin esto, commits de más de 1 MB fallan con error 413
    LimitRequestBody 536870912

    ErrorLog /var/log/httpd/gitea-error.log
    CustomLog /var/log/httpd/gitea-access.log combined
</VirtualHost>
```

```bash
httpd -t
systemctl reload httpd
```

### SELinux y el proxy — paso obligatorio en Rocky Linux

En Rocky Linux, SELinux bloquea por defecto que Apache haga conexiones de red hacia otros procesos locales. Para que el proxy inverso hacia Gitea funcione debes habilitarlo explícitamente:

```bash
setsebool -P httpd_can_network_connect 1
```

Sin este comando, Apache devolverá un error **502 Bad Gateway** aunque toda la configuración esté correcta. Es la diferencia más importante entre Rocky Linux y Debian en este contexto.

---

## 15. Certificado SSL para Gitea

Abre temporalmente el puerto 3000 para completar la instalación web:

```bash
firewall-cmd --permanent --add-port=3000/tcp
firewall-cmd --reload
```

Obtén el certificado:

```bash
certbot --apache -d git.tu-dominio.com
```

Verifica que funcione en `https://git.tu-dominio.com` antes de continuar.

---

## 16. Instalación Web de Gitea

Abre `https://git.tu-dominio.com` en tu navegador. Te aparecerá el asistente de instalación.

**Base de Datos:**

```
Tipo:  SQLite3  ← más simple para un VPS de 5 USD
Ruta:  /var/lib/gitea/data/gitea.db
```

> Si prefieres MariaDB, usa `db_gitea` que ya creaste en el paso 5.

**Configuración General:**

```
Título del sitio:  Tu nombre o el de tu proyecto
URL base:          https://git.tu-dominio.com/
```

**Cuenta Administrador — momento crítico:**

```
Usuario:    [el que elegiste]
Contraseña: [ANÓTALA AHORA en tu bloc de notas]
Email:      [tu correo]
```

Da clic en **Instalar Gitea** y espera. Puede tardar un par de minutos.

---

## 17. Configurar la Identidad de Gitea (app.ini)

Este paso es el que más se omite y el que más problemas causa después. Sin él, los enlaces de clonar apuntan a la IP del servidor en lugar del dominio.

```bash
nano /etc/gitea/app.ini
```

Busca o crea la sección `[server]`:

```ini
[server]
DOMAIN      = git.tu-dominio.com
HTTP_PORT   = 3000
ROOT_URL    = https://git.tu-dominio.com/
DISABLE_SSH = false
SSH_DOMAIN  = git.tu-dominio.com
SSH_PORT    = 22
```

```bash
systemctl restart gitea
```

Verifica en la interfaz web que los enlaces de clonar muestren `https://git.tu-dominio.com` y no la IP.

---

## 18. Desactivar Registro de Nuevos Usuarios

Por defecto Gitea permite que cualquiera se registre. Si tu instancia es privada, desactívalo inmediatamente.

En el mismo `app.ini`, busca o crea la sección `[service]`:

```ini
[service]
DISABLE_REGISTRATION             = true
SHOW_REGISTRATION_BUTTON         = false
ALLOW_ONLY_EXTERNAL_REGISTRATION = false
```

Las tres líneas trabajan juntas. No basta con una sola.

```bash
systemctl restart gitea
```

**Verificación:** Abre una ventana de incógnito y entra a `https://git.tu-dominio.com/user/sign_up`. Debe mostrar error o redirigir al login.

---

## 19. Cerrar el Puerto 3000

Una vez que Gitea opera a través de Apache con SSL, cierra el puerto 3000:

```bash
firewall-cmd --permanent --remove-port=3000/tcp
firewall-cmd --reload

# Verificar — solo deben aparecer ssh, http y https
firewall-cmd --list-all
```

---

## 20. Permisos Finales del Archivo de Configuración

```bash
chmod 750 /etc/gitea
chmod 640 /etc/gitea/app.ini
```

---

## ✅ Checklist Final

```bash
# Apache corriendo
systemctl status httpd                        # active (running)

# MariaDB corriendo
systemctl status mariadb                      # active (running)

# PHP disponible
php -v                                        # PHP 8.2.x

# Git disponible
git --version                                 # git version 2.x

# Gitea corriendo
systemctl status gitea                        # active (running)
curl -I http://127.0.0.1:3000                 # 200 OK

# Registro de Gitea deshabilitado
grep DISABLE_REGISTRATION /etc/gitea/app.ini  # true

# SELinux permite proxy
getsebool httpd_can_network_connect           # httpd_can_network_connect --> on

# Puertos abiertos (solo los necesarios)
firewall-cmd --list-all                       # ssh http https
```

---

## 📝 Comandos Útiles de Mantenimiento

```bash
# Ver logs de Apache en tiempo real
tail -f /var/log/httpd/error_log

# Ver logs de Gitea en tiempo real
journalctl -f -u gitea

# Reiniciar después de cambios en app.ini
systemctl restart gitea

# Backup manual de la base de datos SQLite
sudo -u gitea cp /var/lib/gitea/data/gitea.db \
  /root/respaldo-gitea-$(date +%Y-%m-%d).db

# Agregar usuarios manualmente (si el registro está desactivado)
# Panel de administración → Usuarios → Crear cuenta

# Ver estado del firewall
firewall-cmd --list-all

# Verificar que SELinux no está bloqueando nada
ausearch -c 'httpd' --raw | tail -20
```

---

## 📝 Notas Finales

- Sustituye `tu-dominio.com` y `git.tu-dominio.com` en **todos** los pasos.
- La contraseña del administrador de Gitea va en tu bloc de notas **antes** de instalar.
- El comando `setsebool -P httpd_can_network_connect 1` es **obligatorio** en Rocky Linux para el proxy — es el error más común al migrar desde Debian.
- El usuario de Apache en Rocky Linux es `apache`, no `www-data`. Cualquier directorio que Apache deba leer necesita ese propietario.
- Los logs en Rocky Linux están en `/var/log/httpd/`, no en `/var/log/apache2/`.
- El puerto 3000 nunca debe quedar abierto en producción — solo Apache lo consume internamente.
- Este manual cubre **Gitea base**. Para CI/CD automatizado necesitas `act_runner` por separado — eso es Gitea Actions y requiere su propio manual.
- La versión Debian completa con Node, React y Quarkus está en [github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT](https://github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT).

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

La única condición es mantener el aviso de copyright en las copias sustanciales.

---

## ✍️ Acerca del Autor

Este repositorio es parte de los experimentos documentados en
**[vibecodingmexico.com](https://vibecodingmexico.com)**.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991.

- **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
- **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)
- **Versión Debian:** [github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT](https://github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT)
