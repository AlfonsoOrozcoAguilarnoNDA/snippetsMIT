# 🛠️ Manual: HestiaCP + Ubuntu 24.04 LTS en Vultr

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![HestiaCP](https://img.shields.io/badge/HestiaCP-latest-blue.svg)](https://hestiacp.com/)
[![Ubuntu](https://img.shields.io/badge/Ubuntu-24.04%20LTS-orange.svg)](https://ubuntu.com/)

**Stack:** HestiaCP + Nginx + MariaDB + PHP multi-versión + Roundcube  
**Plataforma base:** Vultr VPS — región México (Ciudad de México)  
**Fecha de redacción:** Mayo 2026  
**Tiempo estimado:** ~1 hora (HestiaCP automatiza la mayor parte)

> **¿Por qué esta guía existe y por qué Ubuntu en lugar de Debian?**
>
> La opción natural habría sido Debian 13 "Trixie", que salió estable el 9 de agosto de 2025. Sin embargo, a mayo de 2026 el hilo oficial de soporte de Debian 13 en el foro de HestiaCP fue cerrado por inactividad sin resolución. HestiaCP no soporta oficialmente Debian 13 todavía.
>
> Ubuntu 24.04 LTS ("Noble Numbat") es soportado oficialmente por HestiaCP, tiene soporte hasta abril 2029, y siendo el sistema más común en proveedores como Vultr facilita la comunicación con su soporte cuando necesitas que abran puertos específicos. Es la opción más pragmática para producción hoy.
>
> **Sobre la IA en Ubuntu:** Canonical anunció integración de IA para Ubuntu 26.10 (octubre 2026) en adelante, completamente opt-in y entregada como Snaps removibles. Ubuntu 24.04 LTS no tiene nada de eso — es un sistema limpio y estable.

---

## 📋 Premisas

1. Estás en **Vultr** con Ubuntu 24.04 LTS limpio, región **México (Ciudad de México)**.
2. Tienes acceso como **`root`** vía SSH.
3. Ya tienes un **dominio principal apuntando a la IP del VPS** — lee la nota abajo.
4. Reemplaza `midominio.com` en **todos** los scripts con tu dominio real.

> **Sobre el dominio principal:** HestiaCP usa el dominio del servidor como identidad del panel de control y del servidor de correo. Elige un dominio que **no vayas a mover, vender ni cancelar**. No tiene que ser tu sitio más importante — puede ser uno secundario o incluso uno comprado solo para este propósito. Ese dominio recibirá el correo interno del sistema (notificaciones de WordPress, alertas del servidor). En esta guía el ejemplo es `midominio.com`.

> Abre un bloc de notas y anota: IP del VPS, password root, password del panel HestiaCP, password de MariaDB. Los necesitarás varias veces.

---

## ⚠️ Región México en Vultr — por qué importa

Vultr tiene datacenter en **Ciudad de México** (código `mex`). Para sitios con audiencia mexicana esto reduce latencia de forma significativa frente a usar Dallas o Miami.

Al crear el VPS en Vultr selecciona:

```
Server Location: Mexico City, Mexico
Server Type:     Cloud Compute — Shared CPU
Operating System: Ubuntu 24.04 LTS x64
Server Size:     mínimo 2 GB RAM / 1 vCPU / 50 GB SSD
                 (recomendado 4 GB si corres 10+ WordPress)
```

> **Nota Practica:** Puedes crecerlo. No me extrañaria que funcione perfecto en uno de 5 USD. Es l oque voy a hacer yo primero.
> **Nota de Vultr:** Algunos puertos de correo (25, 465, 587) están bloqueados por defecto en cuentas nuevas. Como cliente con historial, puedes solicitar la apertura explicando el uso (notificaciones internas, no correo masivo). Detalla que es un servidor con panel de control propio y correo local. Ubuntu 24.04 LTS en la solicitud genera menos fricción que sistemas menos conocidos.

---

# FASE 1: Preparación del VPS

## 1. Primera conexión y actualización

```bash
# Conectar al VPS
ssh root@TU_IP_VULTR

# Actualizar el sistema antes de instalar cualquier cosa
apt update && apt upgrade -y
```

> No instales Nginx, Apache, PHP, MariaDB ni ningún servidor web manualmente. HestiaCP los instalará y configurará él solo. Si hay algo preinstalado, el instalador de HestiaCP puede fallar o quedar en estado inconsistente.

---

## 2. Configurar hostname (nombre del servidor)

HestiaCP usa el hostname como identidad del servidor de correo. Debe coincidir con el dominio principal.

```bash
hostnamectl set-hostname midominio.com
```

Verifica:

```bash
hostname
# debe mostrar: midominio.com
```

Edita también `/etc/hosts` para que el servidor se reconozca a sí mismo:

```bash
nano /etc/hosts
```

Añade o modifica la línea con tu IP:

```
127.0.0.1       localhost
TU_IP_DEL_VPS   midominio.com
```

---

# FASE 2: Instalación de HestiaCP

## 3. Descargar e instalar HestiaCP

HestiaCP tiene un instalador que configura automáticamente Nginx, MariaDB, PHP, Dovecot, Exim (correo) y Roundcube. No necesitas instalar nada por separado.

```bash
# Descargar el instalador oficial
wget https://raw.githubusercontent.com/hestiacp/hestiacp/release/install/hst-install.sh

# Verificar que se descargó correctamente
ls -lh hst-install.sh
```

### Ejecutar el instalador con opciones recomendadas

```bash
bash hst-install.sh \
  --nginx yes \
  --apache no \
  --phpfpm yes \
  --multiphp yes \
  --vsftpd no \
  --proftpd no \
  --named yes \
  --exim yes \
  --dovecot yes \
  --sieve no \
  --clamav no \
  --spamassassin no \
  --mysql yes \
  --postgresql no \
  --mysql8 no \
  --phpmyadmin yes \
  --roundcube yes \
  --hostname midominio.com \
  --email admin@midominio.com \
  --password TuPasswordSeguro123 \
  --lang es
```

**Qué instala cada opción:**

- `--nginx yes` — servidor web Nginx (LEMP)
- `--apache no` — sin Apache (no lo necesitas, nginx solo es suficiente)
- `--phpfpm yes / --multiphp yes` — PHP-FPM con soporte para múltiples versiones simultáneas
- `--vsftpd no / --proftpd no` — sin FTP (usarás SFTP con SSH, más seguro)
- `--named yes` — servidor DNS local (necesario para gestionar subdominios desde el panel)
- `--exim yes / --dovecot yes` — servidor de correo completo (para las notificaciones internas)
- `--clamav no / --spamassassin no` — sin antivirus ni filtro de spam (consumen RAM; no los necesitas para correo interno)
- `--mysql yes / --phpmyadmin yes` — MariaDB + phpMyAdmin para gestionar bases de datos
- `--roundcube yes` — cliente de correo web (accederás desde el navegador)

> El instalador tarda entre 10 y 20 minutos. Al finalizar te muestra las credenciales de acceso al panel. **Anótalas.**

### Al terminar verás algo así:

```
═══════════════════════════════════════════════
 HestiaCP ha sido instalado exitosamente
═══════════════════════════════════════════════
 Panel de control: https://midominio.com:8083
 Usuario:          admin
 Contraseña:       TuPasswordSeguro123
═══════════════════════════════════════════════
```

Accede al panel en el navegador: `https://midominio.com:8083`

> El navegador mostrará advertencia de certificado SSL la primera vez — es normal. El certificado propio se genera después. Acepta la excepción y continúa.

---

# FASE 3: Configuración inicial del panel

## 4. Primer acceso y SSL del panel

Una vez dentro del panel:

1. Ve a **Servidor → Configuración del servidor**
2. En la sección **SSL**, activa el certificado Let's Encrypt para el panel
3. El panel se reiniciará con HTTPS válido

Desde este momento el panel tiene candado verde real.

---

## 5. Configurar PHP — versiones disponibles

HestiaCP con `--multiphp yes` instala múltiples versiones de PHP. Para WordPress en 2026 la recomendación es:

- **PHP 8.2** — la más estable y compatible con casi todos los plugins
- **PHP 8.3** — opción más moderna, compatible con WordPress 6.5+
- **PHP 8.4** — disponible pero con más posibilidad de incompatibilidades en plugins

> El problema que tuviste en KnownHost con PHP 8.4 es común — muchos plugins aún no están completamente adaptados. Con HestiaCP puedes asignar una versión diferente a cada dominio sin afectar los demás. Empieza con 8.2 o 8.3 y migra cuando tus plugins estén listos.

Para cambiar la versión PHP de un dominio:

1. Panel → **Web** → clic en el dominio
2. Sección **PHP** → selecciona la versión
3. Guardar

---

# FASE 4: Primer dominio y WordPress de ejemplo

## 6. Añadir el dominio principal

En el panel de HestiaCP:

1. Ve a **Web** → botón **+** (añadir dominio)
2. Completa el formulario:

```
Dominio:         midominio.com
Alias:           www.midominio.com
Directorio root: /home/admin/web/midominio.com/public_html
PHP:             PHP 8.3
SSL:             Activar Let's Encrypt ✓
Webmail:         Activar ✓
```

3. Guardar — HestiaCP crea automáticamente la configuración de Nginx, el virtualhost y solicita el certificado SSL.

> En este momento `https://midominio.com` ya debe mostrar la página de bienvenida de HestiaCP.

---

## 7. Crear la base de datos para WordPress

Para esta guía usaremos **una sola base de datos** con prefijos distintos por sitio — así el backup es un solo archivo y la gestión es más simple.

En el panel de HestiaCP:

1. Ve a **Bases de datos** → botón **+**
2. Completa:

```
Base de datos:  db_wordpress
Usuario:        wp_admin
Contraseña:     TuClaveDB_Segura456
```

3. Guardar

> HestiaCP crea automáticamente el usuario con permisos solo sobre esa base de datos. Nunca uses el usuario root de MariaDB desde WordPress.

---

## 8. Instalar WordPress con WP-CLI (método recomendado)

WP-CLI permite instalar y gestionar WordPress desde la línea de comandos — sin subir archivos manualmente ni usar FTP.

### Instalar WP-CLI una sola vez en el servidor

```bash
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp

# Verificar
wp --info
```

### Instalar WordPress para midominio.com

```bash
# Ir al directorio del dominio
cd /home/admin/web/midominio.com/public_html

# Descargar WordPress en español
wp core download --locale=es_MX --allow-root

# Crear el wp-config.php con prefijo personalizado
# El prefijo distingue este WordPress de los demás en la misma base de datos
wp config create \
  --dbname=db_wordpress \
  --dbuser=wp_admin \
  --dbpass=TuClaveDB_Segura456 \
  --dbhost=localhost \
  --dbprefix=site01_ \
  --allow-root

# Instalar WordPress
wp core install \
  --url=https://midominio.com \
  --title="Mi Sitio Principal" \
  --admin_user=wpadmin \
  --admin_password=TuClaveWP_789 \
  --admin_email=admin@midominio.com \
  --allow-root

# Verificar
wp core version --allow-root
```

> El `--dbprefix=site01_` es la clave para que convivan múltiples WordPress en una sola base de datos. Para el siguiente sitio usarías `site02_`, para el siguiente `site03_`, y así sucesivamente hasta los 15 sitios.

### Ajustar permisos

```bash
chown -R www-data:www-data /home/admin/web/midominio.com/public_html
chmod -R 755 /home/admin/web/midominio.com/public_html
chmod 600 /home/admin/web/midominio.com/public_html/wp-config.php
```

Ahora `https://midominio.com` debe mostrar tu WordPress instalado.

---

# FASE 5: Correo y Roundcube

## 9. Crear cuenta de correo en HestiaCP

El correo interno del servidor se gestiona desde el panel:

1. Ve a **Correo** → botón **+**
2. Completa:

```
Dominio:    midominio.com
Cuenta:     admin
Contraseña: TuClaveCorreo_321
Cuota:      1024 MB (suficiente para notificaciones)
```

3. Guardar

Ahora tienes `admin@midominio.com` activo.

---

## 10. Acceder a Roundcube

Roundcube (el cliente de correo web) está disponible en:

```
https://midominio.com/webmail
```

Inicia sesión con:
- Usuario: `admin@midominio.com`
- Contraseña: la que definiste arriba

> Aquí llegarán todas las notificaciones de WordPress: actualizaciones disponibles, errores, alertas del sistema. No se envía correo a nadie externo — todo es interno al servidor.

---

# FASE 6: Notificaciones de WordPress

## 11. Activar notificaciones de actualización

WordPress envía notificaciones de actualización de forma nativa. Solo necesitas verificar que el correo del administrador es el correcto.

Desde el panel de WordPress de cada sitio:

1. Ve a **Usuarios → Tu perfil**
2. Verifica que el correo es `admin@midominio.com`
3. Ve a **Configuración → General**
4. Verifica que el correo del administrador es `admin@midominio.com`

WordPress notifica automáticamente cuando:
- Hay una actualización de core disponible
- Hay actualizaciones de plugins o temas
- Una actualización automática falla

### Forzar notificaciones más detalladas (opcional)

Añade esto al `wp-config.php` de cada sitio para recibir notificaciones incluso de actualizaciones menores:

```bash
# Para midominio.com
wp config set WP_AUTO_UPDATE_CORE minor --allow-root \
  --path=/home/admin/web/midominio.com/public_html
```

O añade manualmente en `wp-config.php`:

```php
// Notificaciones de actualizaciones automáticas
define('WP_AUTO_UPDATE_CORE', 'minor');
add_filter('auto_update_plugin', '__return_true');
add_filter('auto_update_theme', '__return_true');
```

---

# FASE 7: Backup

## 12. Configurar backup en HestiaCP

Una de las ventajas de HestiaCP es que el backup cubre todo de un solo golpe: archivos web, bases de datos, configuración de correo y DNS.

En el panel:

1. Ve a **Servidor → Configuración de backup**
2. Configura:

```
Directorio de backup:  /backup
Número de backups:     7 (guarda una semana)
Horario:               3:00 AM (cuando hay menos tráfico)
```

3. Guardar

Para hacer un backup manual inmediato desde SSH:

```bash
# Backup del usuario admin (incluye todos sus dominios y bases de datos)
v-backup-user admin
```

Para listar los backups existentes:

```bash
v-list-user-backups admin
```

Para restaurar:

```bash
v-restore-user admin admin.YYYY-MM-DD.tar
```

> Considera configurar también un backup remoto a un bucket de almacenamiento externo (Vultr Object Storage, BackBlaze B2, etc.) para tener copia fuera del servidor. El panel lo soporta desde Servidor → Configuración de backup → Backup remoto.

---

## ✅ Checklist Final

```bash
# Panel HestiaCP accesible con SSL válido
# https://midominio.com:8083 → candado verde

# Nginx
systemctl status nginx
# active (running)

# PHP-FPM (verifica la versión que instalaste)
systemctl status php8.3-fpm
# active (running)

# MariaDB
systemctl status mariadb
# active (running)

# Servidor de correo
systemctl status exim4
systemctl status dovecot
# ambos: active (running)

# WordPress accesible
curl -I https://midominio.com
# HTTP/2 200

# Roundcube accesible
curl -I https://midominio.com/webmail
# HTTP/2 200

# WP-CLI funciona
wp core version --allow-root \
  --path=/home/admin/web/midominio.com/public_html
# 6.x.x

# Versiones de PHP disponibles
ls /etc/php/
# 8.2  8.3  8.4

# Firewall
ufw status
# 22, 80, 443 abiertos
# 8083 abierto (panel HestiaCP)
```

---

## 📋 Estructura de los 15 sitios WordPress

Para escalar a 15 sitios en la misma base de datos, el patrón es siempre el mismo — solo cambia el dominio y el prefijo:

| Sitio | Dominio ejemplo | Prefijo BD | Directorio |
|-------|----------------|------------|------------|
| 1 | midominio.com | site01_ | /home/admin/web/midominio.com/public_html |
| 2 | sitio2.com | site02_ | /home/admin/web/sitio2.com/public_html |
| 3 | sitio3.com | site03_ | /home/admin/web/sitio3.com/public_html |
| ... | ... | ... | ... |
| 15 | sitio15.com | site15_ | /home/admin/web/sitio15.com/public_html |

Para cada sitio adicional: añadir el dominio en el panel HestiaCP (paso 6), luego instalar WordPress con WP-CLI (paso 8) cambiando el dominio y el prefijo.

---

## 📝 Notas Finales

- Sustituye `midominio.com` y todas las contraseñas de ejemplo en **todos** los pasos antes de ejecutar.
- El puerto 8083 (panel HestiaCP) puede restringirse a tu IP en el firewall de Vultr si quieres más seguridad.
- Para solicitar apertura de puertos de correo en Vultr, ve a soporte y menciona: Ubuntu 24.04 LTS, uso interno de notificaciones, panel HestiaCP. Con historial de cliente de más de un año la solicitud suele resolverse en horas.
- Nunca uses el mismo prefijo de base de datos en dos sitios — causaría corrupción de datos entre ellos.
- WP-CLI permite actualizar todos los plugins de todos los sitios desde un script batch si en algún momento quieres automatizar las actualizaciones en lugar de solo recibirlas por correo.
- **Sobre los Snaps en Ubuntu:** HestiaCP instala Nginx, PHP y MariaDB desde repositorios APT normales, no como Snaps. En un servidor con HestiaCP no hay impacto de la política de Snaps de Canonical.

---

## 🧪 Notas del Autor

Este repositorio es parte de los experimentos documentados en **[vibecodingmexico.com](https://vibecodingmexico.com)**.

El código aquí publicado fue generado con asistencia de LLMs — se documenta cuál modelo generó qué y en qué fecha, porque eso es parte del experimento. Los modelos cambian. Los resultados de hoy no garantizan los resultados de mañana. Por eso se fecha todo.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991.

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

El código es tuyo para usar, copiar, modificar y distribuir. La única condición es mantener el aviso de copyright en las copias sustanciales.

---

## ✍️ Acerca del Autor

- **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
- **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)
