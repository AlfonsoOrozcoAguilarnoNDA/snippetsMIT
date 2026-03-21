![Logo de Vibe Coding México](instalarlemp.jpg)

# 🛠️ Manual: LEMP + Node + React + PM2 + Gitea + Quarkus en Debian 13

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892bf.svg)](https://www.php.net/)

**Stack:** LEMP (Debian 13) + SSL Certbot + PHP 8.4 + Node 22 + React/Vite + PM2 + Gitea + Quarkus Dev  
**Plataforma base:** Vultr VPS (o similar con root real)  
**Fecha de redacción:** Enero 2026  
**Tiempo estimado:** ~3 horas con breaks

> **Complemento del manual LAMP:** Si necesitas Apache en lugar de Nginx, revisa `instalar_LAMP_React_PM2_Gitea_Quarkus_debian13.md`. La diferencia central entre ambos manuales está en el servidor web: Apache usa `VirtualHost`, `DocumentRoot` y `RewriteRule`; Nginx usa bloques `server`, `alias` y `location`. Los demás pasos son prácticamente idénticos.

---

## ⚠️ Advertencia: React y Seguridad (enero 2026)

> El autor redactó este manual en enero de 2026 y recomienda ir evaluando alternativas a React para proyectos nuevos.

En diciembre de 2025 se divulgó **CVE-2025-55182** ("React2Shell"), vulnerabilidad RCE con CVSS 10.0 en React Server Components, seguida de tres CVE adicionales en días:

- **CVE-2025-55183** (CVSS 5.3): Exposición de código fuente del servidor — una Server Function puede devolver su propio código, exponiendo lógica de negocio, cadenas de conexión y claves de API.
- **CVE-2025-55184** (CVSS 7.5): DoS por bucle infinito al recibir petición HTTP maliciosa.
- **CVE-2025-67779** (CVSS 7.5): Parche incompleto; las versiones 19.0.2, 19.1.3 y 19.2.2 seguían siendo vulnerables.

Grupos APT alineados con China comenzaron a explotar React2Shell horas después de su divulgación, desplegando backdoors (HISONIC, COMPOOD), mineros y túneles de red.

**Este manual usa React en modo estático** — archivos compilados servidos directamente por Nginx, sin React Server Components. En ese modo los CVE anteriores no aplican directamente. Sin embargo, el volumen de dependencias transitivas de npm hace que React **no sea la opción recomendada por defecto** en entornos regulados (facturación electrónica, salud, sistemas financieros) donde se requiere auditoría formal de dependencias.

**Alternativas a evaluar en 2026:** Vue.js, Astro, Next.js con RSC desactivado, o PHP puro según el caso.

---

## ⚠️ Nota sobre OVHcloud

OVHcloud usa un usuario intermedio `debian` en lugar de root directo. Esto genera inconsistencias graves con PM2, NVM y permisos de npm — en enero 2026 se verificaron instancias del mismo proveedor donde una funcionaba y otra recién levantada no.

**Usa OVHcloud solo para LAMP/LEMP básico (WordPress, PHP puro).** Para Node, React, PM2 o Quarkus: Vultr u otro proveedor que dé root real sin abstracción intermedia.

---

## 📋 Premisas

1. Estás en **Vultr** con Debian 13 limpio. Si usas otro proveedor, necesitas cliente SSH (PuTTY u otro).
2. Tienes acceso como **`root`**.
3. Ya tienes un **dominio apuntando a la IP del VPS**.
4. Reemplaza `tu-dominio.com` en **todos** los scripts con tu dominio real, incluyendo extensión.

> Abre un bloc de notas y anota: IP, password root, contraseña MariaDB, usuario/password Gitea. Los necesitarás varias veces.

---

# FASE 1: LEMP Base

## 1. Actualización del Sistema

```bash
apt update && apt upgrade -y
apt install -y ufw curl git vim htop sudo build-essential
```

---

## 2. Firewall (UFW)

```bash
ufw default deny incoming
ufw default allow outgoing

ufw allow ssh
ufw allow http
ufw allow https

ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp

ufw enable
```

---

## 3. Base de Datos (MariaDB)

### Instalación

```bash
apt install -y mariadb-server
```

### Seguridad inicial

```bash
mysql_secure_installation
```

> Si falla (modo Debian puro, no Ubuntu), ve al **Anexo 1** al final del documento.

### Crear bases de datos y usuario de aplicación

```bash
mariadb -u root -p
```

```sql
-- Seis bases de datos de trabajo
CREATE DATABASE IF NOT EXISTS db_app_principal;
CREATE DATABASE IF NOT EXISTS db_reserva_1;
CREATE DATABASE IF NOT EXISTS db_reserva_2;
CREATE DATABASE IF NOT EXISTS db_reserva_3;
CREATE DATABASE IF NOT EXISTS db_reserva_4;
CREATE DATABASE IF NOT EXISTS db_reserva_5;

-- Usuario dedicado (nunca uses root desde la app)
-- Sustituye 'adminsql' y 'TuClaveSegura' por lo que prefieras
CREATE USER 'adminsql'@'localhost' IDENTIFIED BY 'TuClaveSegura';

-- Permisos por base de datos
GRANT ALL PRIVILEGES ON db_app_principal.* TO 'adminsql'@'localhost';
GRANT ALL PRIVILEGES ON db_reserva_1.*     TO 'adminsql'@'localhost';
GRANT ALL PRIVILEGES ON db_reserva_2.*     TO 'adminsql'@'localhost';
GRANT ALL PRIVILEGES ON db_reserva_3.*     TO 'adminsql'@'localhost';
GRANT ALL PRIVILEGES ON db_reserva_4.*     TO 'adminsql'@'localhost';
GRANT ALL PRIVILEGES ON db_reserva_5.*     TO 'adminsql'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

> En total manejas tres contraseñas distintas: root del sistema, root de MariaDB y el usuario `adminsql`. Anótalas todas.

### Ajuste de memoria (para servidores de 4 GB con Quarkus conviviendo)

```bash
nano /etc/mysql/mariadb.conf.d/99-ajustes-memoria.cnf
```

```ini
[mysqld]
innodb_buffer_pool_size = 512M
max_connections = 200
sql_mode = ""
```

```bash
systemctl restart mariadb

# Verificar que aplicó (debe dar 536870912)
mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
```

> Si el valor no cambia hay otro archivo sobreescribiendo la configuración. Ver **Anexo 2**.

---

## 4. PHP 8.4

```bash
apt install -y php8.4 php8.4-fpm php8.4-mysql php8.4-curl php8.4-bcmath \
  php8.4-xml php8.4-mbstring php8.4-zip php8.4-gd php8.4-intl
```

Por qué las extensiones esenciales:

- **php-curl** — peticiones externas, APIs de pago, verificación de licencias
- **php-bcmath** — aritmética de precisión, indispensable en facturación electrónica
- **php-xml** — estructuras XML, CFDI, RSS
- **php-mbstring** — cadenas multibyte, caracteres especiales en español

### Ajuste opcional de php.ini

```bash
nano /etc/php/8.4/fpm/php.ini
```

Busca y ajusta:

```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
```

```bash
systemctl restart php8.4-fpm
systemctl enable php8.4-fpm
```

---

## 5. Nginx

```bash
apt install -y nginx
```

### Crear directorios del sitio

```bash
mkdir -p /var/www/tu-dominio.com/public_html
chown -R www-data:www-data /var/www/tu-dominio.com
chmod -R 755 /var/www/tu-dominio.com
```

> **Recomendación:** Pon un `<h1>Hola!</h1>` en `public_html/index.html` **antes** de continuar. Verifica que lo ves en el navegador ahora, no en dos horas cuando hayas avanzado más.

### Archivo de configuración del sitio

```bash
nano /etc/nginx/sites-available/tu-dominio.com
```

```nginx
server {
    listen 80;
    server_name tu-dominio.com www.tu-dominio.com;
    root /var/www/tu-dominio.com/public_html;
    index index.php index.html index.htm;

    access_log /var/log/nginx/tu-dominio.access.log;
    error_log  /var/log/nginx/tu-dominio.error.log;

    # Permalinks de WordPress y rutas dinámicas
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # Procesamiento PHP 8.4 vía FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Denegar .htaccess (seguridad, aunque Nginx no los usa)
    location ~ /\.ht {
        deny all;
    }

    # Caché de archivos estáticos
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires max;
        log_not_found off;
    }
}
```

### Activar el sitio

```bash
# Enlace simbólico para activar
ln -s /etc/nginx/sites-available/tu-dominio.com /etc/nginx/sites-enabled/

# Desactivar el sitio default para evitar conflictos
rm /etc/nginx/sites-enabled/default

# Verificar sintaxis (busca "syntax is ok")
nginx -t

# Si todo está OK, recargar
systemctl reload nginx
```

### Verificar el puente PHP-Nginx

```bash
nano /var/www/tu-dominio.com/public_html/test.php
```

Escribe `<?php echo "<h1>PHP funciona</h1>"; ?>`, guarda y ábrelo en el navegador. Elimina el archivo después.

---

## 6. Certbot (SSL)

> El dominio debe apuntar a la IP del VPS **antes** de ejecutar esto.

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d tu-dominio.com -d www.tu-dominio.com

# Verificar renovación automática
certbot renew --dry-run
```

---

# FASE 2: Node + PM2 + React + Gitea

## 7. Node.js 22 LTS

No uses los repositorios estándar de Debian — traen versiones antiguas. Usa NodeSource:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs

# Verificar
node -v   # v22.x
npm -v    # 10.x
```

---

## 8. PM2

PM2 es el gestor de procesos que hace "inmortal" cualquier app de Node: la reinicia automáticamente si falla, persiste entre reinicios y da monitoreo en tiempo real. Es el punto medio ideal entre control y complejidad — ni Docker pesado ni proceso suelto sin supervisión.

```bash
npm install pm2 -g
pm2 startup
```

> Normalmente este comando te da una línea larga que empieza con `sudo env PATH=...` que debes copiar y pegar. En Debian limpio a veces lo configura solo. Para forzarlo:

```bash
pm2 startup systemd
pm2 save

# Verificar
systemctl status pm2-root   # debe decir active (running)
```

### Si PM2 dice "comando no encontrado"

```bash
ln -s /usr/local/bin/pm2 /usr/bin/pm2
hash -r
```

> El servicio se llama `pm2-root` porque se instaló como root. Con otro usuario se llamaría `pm2-nombreusuario`.

> **Monitoreo en tiempo real:** `pm2 monit` abre un panel de RAM y CPU por proceso. Vital cuando conviven MariaDB, Gitea y Quarkus.

---

## 9. React con Vite

### ¿Por qué Vite y no Create React App?

Create React App está deprecado. Vite es el estándar actual: más rápido, más ligero, mejor soporte.

### Advertencia de seguridad previa

Este manual cubre React en modo **estático** — archivos compilados servidos directamente por Nginx, sin React Server Components. Los CVE de diciembre 2025 (CVE-2025-55182 al CVE-2025-67779) **no aplican en este modo**, ya que requieren un servidor Node activo procesando peticiones RSC. Si en algún momento migras a Next.js con SSR o cualquier framework que use RSC, revisa el estado de seguridad de tu versión antes de desplegar.

### Diferencia clave Nginx vs Apache para React

En **Apache** el subdirectorio `/app` se configura con `RewriteRule` dentro de un bloque `<Directory>`.  
En **Nginx** se usa `alias` con barras explícitas. **Las barras en Nginx importan:** `/app` no es lo mismo que `/app/`. Si pones la barra en el `location`, el `alias` también debe terminar en barra, o verás un 404.

### Crear el proyecto

```bash
mkdir -p /home/git/proyectos
cd /home/git/proyectos

npm create vite@latest mi-app-react -- --template react
cd mi-app-react
npm install
```

### Configurar Vite para subdirectorio `/app`

```bash
nano vite.config.js
```

```javascript
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: '/app/',  // La clave para convivir con WordPress u otro sitio en la raíz
})
```

> Si obtienes error de token expirado con `@vitejs/react-swc`:

```bash
rm -f ~/.npmrc
npm cache clean --force
npm install @vitejs/plugin-react --save-dev
```

### Compilar y desplegar

```bash
npm run build

mkdir -p /var/www/tu-dominio.com/public_html/app
cp -r dist/* /var/www/tu-dominio.com/public_html/app/
chown -R www-data:www-data /var/www/tu-dominio.com/public_html/app/
chmod -R 755 /var/www/tu-dominio.com/public_html/app/
```

### Permisos de directorio para Nginx

```bash
chmod o+x /var/www
chmod o+x /var/www/tu-dominio.com
chmod o+x /var/www/tu-dominio.com/public_html
chmod o+x /var/www/tu-dominio.com/public_html/app
```

### Configurar Nginx para servir React en `/app`

Edita el archivo del sitio y añade dentro del bloque `server`:

```bash
nano /etc/nginx/sites-available/tu-dominio.com
```

```nginx
# Bloque para la SPA de React en /app
# OJO: la barra al final de alias es obligatoria en Nginx
location /app/ {
    alias /var/www/tu-dominio.com/public_html/app/;
    index index.html;
    try_files $uri $uri/ /app/index.html;
}
```

```bash
nginx -t && systemctl reload nginx
```

### Ciclo de actualización (cada vez que haces cambios)

```bash
cd /home/git/proyectos/mi-app-react
npm run build

rm -rf /var/www/tu-dominio.com/public_html/app/*
cp -r dist/* /var/www/tu-dominio.com/public_html/app/
chown -R www-data:www-data /var/www/tu-dominio.com/public_html/app/
chmod -R 755 /var/www/tu-dominio.com/public_html/app/
```

---

## 10. Gitea (Control de Versiones Propio)

### Gitea vs Gitea Actions

**Gitea** es el servidor de repositorios Git: interfaz web, gestión de usuarios, pull requests, issues, wikis. Equivalente a tener tu propio GitHub privado. Es un binario de Go, sin dependencias externas.

**Gitea Actions** es el sistema de CI/CD integrado, equivalente a GitHub Actions. Permite ejecutar flujos automatizados al hacer push. **Requiere un runner separado** (`act_runner`) que se instala y configura aparte. Este manual instala solo Gitea base — Actions queda como expansión futura.

### Crear usuario del sistema

```bash
adduser \
  --system \
  --shell /bin/bash \
  --group \
  --disabled-password \
  --home /home/git \
  git

id git  # debe mostrar uid con valor tipo 10x
```

### Crear directorios y permisos

```bash
mkdir -p /var/lib/gitea/{custom,data,log}
chown -R git:git /var/lib/gitea/
chmod -R 750 /var/lib/gitea/

mkdir -p /etc/gitea
chown root:git /etc/gitea
chmod 770 /etc/gitea
chown -R git:git /etc/gitea
chown -R git:git /var/lib/gitea
chmod -R 770 /etc/gitea
```

### Descargar el binario

Verifica la versión más reciente en https://dl.gitea.com/gitea/

```bash
wget -O /usr/local/bin/gitea \
  https://dl.gitea.com/gitea/1.22.3/gitea-1.22.3-linux-amd64

chmod +x /usr/local/bin/gitea
gitea --version
```

### Abrir puerto temporalmente para la instalación web

```bash
ufw allow 3000/tcp
```

> Este puerto se cerrará al final, una vez configurado el proxy Nginx.

### Crear servicio systemd

```bash
nano /etc/systemd/system/gitea.service
```

```ini
[Unit]
Description=Gitea (Soberanía de Código)
After=network.target
After=mariadb.service

[Service]
RestartSec=2s
Type=simple
User=git
Group=git
WorkingDirectory=/var/lib/gitea/
ExecStart=/usr/local/bin/gitea web --config /etc/gitea/app.ini
Restart=always
Environment=USER=git HOME=/home/git GITEA_WORK_DIR=/var/lib/gitea

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable gitea
systemctl start gitea
```

### Corregir permisos del directorio home

```bash
chown -R git:git /home/git/
chmod 755 /home/git/
systemctl restart gitea
```

### Verificar que Gitea responde

```bash
curl -I http://127.0.0.1:3000
```

### Instalación web (primera vez)

Entra a `http://tu-ip:3000` en el navegador. Configura:

- **Base de datos:** SQLite3 (suficiente para uso personal o equipo pequeño; si prefieres MariaDB, usa `db_reserva_1`)
- **URL base:** `http://tu-ip:3000` por ahora — se cambia después
- **Cuenta administrador:** anótala en papel — Gitea no tiene recuperación de contraseña por correo por defecto. Usa un nombre como `gitea2`.

### Deshabilitar registro público

```bash
nano /etc/gitea/app.ini
```

Busca o crea la sección `[service]`:

```ini
[service]
DISABLE_REGISTRATION              = true
SHOW_REGISTRATION_BUTTON          = false
ALLOW_ONLY_EXTERNAL_REGISTRATION  = false
```

```bash
systemctl restart gitea
```

Verifica en ventana de incógnito que `http://tu-ip:3000/user/sign_up` no permite registro.

---

# FASE 3: Gitea con SSL + Quarkus

## 11. Gitea: Proxy Nginx y SSL en subdominio

### Paso previo: crear el registro DNS

En tu panel de DNS (Vultr, Porkbun, u otro) crea:

```
Tipo:   A
Nombre: git
Valor:  [IP de tu VPS]
TTL:    300
```

Espera ~10 minutos a que propague. Verifica con `ping git.tu-dominio.com`.

### Archivo de configuración Nginx para Gitea

```bash
nano /etc/nginx/sites-available/git.tu-dominio.com
```

```nginx
server {
    listen 80;
    server_name git.tu-dominio.com;

    access_log /var/log/nginx/gitea_access.log;
    error_log  /var/log/nginx/gitea_error.log;

    location / {
        proxy_pass http://localhost:3000;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Sin esto, commits de más de 1 MB fallan con error 413
        # 256M es suficiente en la práctica; 512M no sobra
        client_max_body_size 512M;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/git.tu-dominio.com /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### SSL para el subdominio de Gitea

```bash
certbot --nginx -d git.tu-dominio.com

# Verificar dry-run
certbot certonly --dry-run -d git.tu-dominio.com
```

Certbot modificará el archivo de Nginx automáticamente e inyectará las líneas SSL. Verifica que `https://git.tu-dominio.com` abre con candado verde.

### Actualizar la identidad de Gitea en app.ini

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

### Cerrar el puerto 3000 (ya no se necesita)

```bash
ufw delete allow 3000/tcp
ufw status  # solo deben quedar 22, 80 y 443
```

Verifica en Gitea que los enlaces de clonar ya muestren `https://git.tu-dominio.com` y no la IP.

### Permisos finales del app.ini

```bash
chmod 750 /etc/gitea
chmod 640 /etc/gitea/app.ini
```

---

## 12. Quarkus vía SDKMAN

### ¿Por qué SDKMAN y no `apt install`?

`apt install java` instala la versión que decida Debian, que puede no ser la óptima para Quarkus. SDKMAN permite instalar y cambiar versiones de Java, Quarkus CLI, Maven y Gradle sin tocar las variables de entorno manualmente ni contaminar el sistema.

### ¿Quarkus JVM vs GraalVM Native Image?

**Quarkus en modo JVM:** Corre sobre la JVM estándar. Arranque en segundos. Lo que instalamos aquí.

**Quarkus con GraalVM Native Image:** Compila a ejecutable nativo sin JVM en tiempo de ejecución. Arranque en milisegundos, RAM drasticamente menor (una app que usa 200 MB en JVM puede usar 30 MB en nativo). El costo: compilación tarda varios minutos y requiere mínimo 4 GB de RAM disponible durante el proceso.

Para un servidor de 4 GB con MariaDB, Nginx y Gitea conviviendo, el modo JVM es el punto de partida correcto. La compilación nativa queda como optimización futura.

### Por qué Quarkus sobre Node/Express para el backend

Quarkus es robusto, independiente del servidor web (es agnóstico a Nginx o Apache) y consume mucho menos RAM que Spring Boot. Para un endpoint de monitoreo o una API REST simple, la diferencia en arranque y consumo de memoria frente a un proceso Node/Express con Express es visible desde el primer `pm2 monit`.

### 1. Instalar SDKMAN

```bash
apt update && apt install zip unzip curl -y

curl -s "https://get.sdkman.io" | bash
source "$HOME/.sdkman/bin/sdkman-init.sh"

sdk version   # confirma instalación
sdk help
```

### 2. Instalar Java 21 (Temurin — Eclipse Foundation)

```bash
sdk install java 21.0.2-tem

# Verificar
java -version   # debe mostrar OpenJDK Runtime Environment Temurin-21

# Si estás en una máquina con instalación previa y no toma el cambio:
sdk use java 21.0.2-tem
# Sal de SSH y vuelve a entrar, luego verifica con java -version
```

### 3. Instalar Quarkus CLI

```bash
sdk install quarkus
quarkus --version
```

### 4. Crear el proyecto monitor

El proyecto vivirá en la carpeta de proyectos de Git que ya creamos:

```bash
cd /home/git/proyectos

quarkus create app monitor-quarkus-instance --extension=resteasy-jackson
cd monitor-quarkus-instance
```

Verás `SUCCESS` en verde. Si no: no sigas.

### 5. Crear el recurso de monitoreo

> **Nota de seguridad:** No uses `/stats` o `/health` como endpoint — los scanners automáticos los buscan primero. Usa algo que solo tú conozcas, como tus iniciales o un token. En este ejemplo: `/statsaoa`.

```bash
nano src/main/java/org/acme/MonitorResource.java
```

```java
package org.acme;

import jakarta.ws.rs.GET;
import jakarta.ws.rs.Path;
import jakarta.ws.rs.Produces;
import jakarta.ws.rs.core.MediaType;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.HashMap;
import java.util.Map;

@Path("/statsaoa")
public class MonitorResource {

    @GET
    @Produces(MediaType.APPLICATION_JSON)
    public Map<String, String> getStats() {
        Map<String, String> stats = new HashMap<>();

        stats.put("hora", LocalDateTime.now()
            .format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")));

        stats.put("memoria", executeCommand(
            "free -m | awk 'NR==2{printf \"%.2f%%\", $3*100/$2 }'"));

        stats.put("disco", executeCommand(
            "df -h / | awk 'NR==2{print $5}'"));

        return stats;
    }

    private String executeCommand(String command) {
        try {
            Process process = Runtime.getRuntime()
                .exec(new String[]{"/bin/sh", "-c", command});
            BufferedReader reader = new BufferedReader(
                new InputStreamReader(process.getInputStream()));
            return reader.readLine();
        } catch (Exception e) {
            return "Error: " + e.getMessage();
        }
    }
}
```

> **Atención con las comillas:** WordPress y algunos editores convierten comillas rectas `"` en curvas `"`. Si copias de un blog, revisa que el código Java tenga comillas rectas antes de compilar.

### 6. Configurar puerto y CORS

```bash
nano src/main/resources/application.properties
```

```properties
quarkus.http.port=8080
quarkus.http.host=127.0.0.1

# CORS: solo tu dominio puede consumir la API
quarkus.http.cors=true
quarkus.http.cors.origins=https://tu-dominio.com
```

### 7. Dar permisos al wrapper y probar en modo dev

```bash
chmod +x mvnw
./mvnw quarkus:dev
```

En otra terminal (o desde tu máquina local vía SSH):

```bash
ssh root@tu-ip-vultr
curl http://localhost:8080/statsaoa
```

Respuesta esperada:

```json
{"memoria":"43.26%","hora":"2026-01-02 20:27:56","disco":"17%"}
```

Si ves ese JSON: el backend está funcionando.

### 8. Exponer Quarkus a través de Nginx

Edita el archivo SSL del dominio principal que Certbot generó:

```bash
nano /etc/nginx/sites-available/tu-dominio.com
```

Añade dentro del bloque `server` (junto al bloque de React):

```nginx
# Proxy para la API de Quarkus
# La barra después del 8080 es crítica en Nginx
location /api/ {
    proxy_pass http://localhost:8080/;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

```bash
nginx -t && systemctl reload nginx
```

Prueba desde el navegador: `https://tu-dominio.com/api/statsaoa`

### 9. Dejar Quarkus siempre activo con systemd

```bash
nano /etc/systemd/system/monitor-quarkus.service
```

```ini
[Unit]
Description=Monitor Quarkus Instance
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/home/git/proyectos/monitor-quarkus-instance
ExecStart=/home/git/proyectos/monitor-quarkus-instance/mvnw quarkus:dev -Dquarkus.http.host=0.0.0.0
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
systemctl daemon-reload
systemctl enable monitor-quarkus
systemctl start monitor-quarkus
```

Ahora el endpoint sobrevive reinicios del VPS y cierres de terminal.

---

## 13. Widget React que consume la API de Quarkus

Este componente puede incluirse en tu proyecto React como punto de partida para cualquier dashboard de monitoreo:

```javascript
// src/components/MonitorServidor.jsx
import React, { useState, useEffect } from 'react';

const MonitorServidor = () => {
  const [stats, setStats] = useState(null);

  useEffect(() => {
    const fetchStats = async () => {
      try {
        const response = await fetch('https://tu-dominio.com/api/statsaoa');
        const data = await response.json();
        setStats(data);
      } catch (err) {
        console.error('Instancia no responde', err);
      }
    };

    fetchStats();
    const timer = setInterval(fetchStats, 30000); // Actualiza cada 30 segundos
    return () => clearInterval(timer);
  }, []);

  if (!stats) return <div>Conectando con la instancia...</div>;

  return (
    <div style={{
      border: '1px solid #333',
      padding: '15px',
      borderRadius: '5px',
      backgroundColor: '#000',
      color: '#0f0',
      fontFamily: 'monospace'
    }}>
      <h4>MONITOR DE INSTANCIA</h4>
      <p>RAM:   {stats.memoria}</p>
      <p>DISCO: {stats.disco}</p>
      <small>Sync: {stats.hora}</small>
    </div>
  );
};

export default MonitorServidor;
```

---

## ✅ Checklist Final

```bash
# Nginx
systemctl status nginx                        # active (running)
nginx -t                                      # syntax is ok

# PHP
php -v                                        # PHP 8.4.x

# MariaDB
systemctl status mariadb                      # active (running)

# Node
node -v                                       # v22.x

# PM2
systemctl status pm2-root                     # active (running)
pm2 list                                      # procesos activos

# React compilado
ls /var/www/tu-dominio.com/public_html/app/   # index.html + assets/

# React accesible
curl -I https://tu-dominio.com/app/           # 200 OK

# Gitea
systemctl status gitea                        # active (running)
grep DISABLE_REGISTRATION /etc/gitea/app.ini  # true
grep ROOT_URL /etc/gitea/app.ini              # https://git.tu-dominio.com/

# Puertos abiertos (solo 22, 80, 443)
ufw status

# Quarkus
systemctl status monitor-quarkus              # active (running)
curl http://localhost:8080/statsaoa           # JSON con memoria, disco, hora
curl -I https://tu-dominio.com/api/statsaoa   # 200 OK a través de Nginx
```

---

## 📝 Notas Finales

- Sustituye `tu-dominio.com` y `git.tu-dominio.com` en **todos** los pasos.
- Nunca expongas credenciales en repositorios públicos. Usa `.env` excluido del control de versiones.
- El puerto 3000 (Gitea) y el 8080 (Quarkus) **nunca deben abrirse en UFW** — solo Nginx los consume internamente.
- Documenta qué versión de cada tecnología instalaste y en qué fecha. Los sistemas se degradan y esa información es oro para diagnosticar meses después.
- **Sobre microservicios:** Quarkus facilita crear endpoints independientes. Resiste la tentación de multiplicarlos sin control. Con cinco o seis programadores con ideas propias de arquitectura, treinta microservicios son treinta vectores de ataque y treinta procesos consumiendo RAM. Un `/statsaoa` es una cosa; treinta microservicios sin gobernanza, otra. La simplicidad tiene valor de supervivencia.
- **Sobre npm y auditorías:** En proyectos React con dependencias transitivas es común ver cientos de paquetes. En entornos que requieren auditoría formal (IMSS, SAT, salud, financiero), este volumen hace que la revisión sea prácticamente inviable sin herramientas especializadas. Considera esto antes de elegir React para esos contextos.

---

## Anexo 1: MariaDB modo seguro manual en Debian 13

Si `mysql_secure_installation` falla en Debian puro:

```bash
mariadb
```

```sql
-- Cambiar contraseña de root
SET PASSWORD FOR 'root'@'localhost' = PASSWORD('TuClaveSegura');

-- Eliminar usuarios anónimos
DELETE FROM mysql.user WHERE User='';

-- Bloquear acceso remoto de root
DELETE FROM mysql.user WHERE User='root'
  AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Borrar base de datos de prueba
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

FLUSH PRIVILEGES;
EXIT;
```

---

## Anexo 2: Forzar configuración de MariaDB cuando hay múltiples archivos

MariaDB en Debian carga archivos en orden alfabético/numérico. Si tu cambio no aplica, otro archivo lo sobreescribe. Solución: crear un archivo con número alto que se cargue al final:

```bash
nano /etc/mysql/mariadb.conf.d/99-ajustes-memoria.cnf
```

```ini
[mysqld]
innodb_buffer_pool_size = 512M
```

```bash
systemctl restart mariadb
mysql -u root -p -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size';"
# Debe mostrar: 536870912
```

---

## Anexo 3: Limpieza total — regresar a LEMP puro

Útil si OVHcloud bloqueó PM2/Node o si quieres resetear el servidor a solo PHP+Nginx:

```bash
# 1. Detener y desinstalar PM2
pm2 kill || true
npm uninstall -g pm2
rm -rf ~/.pm2 /root/.pm2 /home/debian/.pm2

# 2. Eliminar Node, NVM y el proyecto React
rm -rf /home/git/proyectos
rm -rf ~/.nvm ~/.npm ~/.bower
sed -i '/nvm/d' ~/.bashrc
sed -i '/NVM_DIR/d' ~/.bashrc
source ~/.bashrc

# 3. Limpiar directorio de producción en Nginx
rm -rf /var/www/tu-dominio.com/public_html/app
chown -R www-data:www-data /var/www/tu-dominio.com
chmod -R 755 /var/www/tu-dominio.com

# 4. Limpiar la sección de React del VirtualHost Nginx
nano /etc/nginx/sites-available/tu-dominio.com
# (Eliminar el bloque location /app/ y el bloque location /api/)
nginx -t && systemctl restart nginx

# 5. Detener Quarkus
systemctl stop monitor-quarkus
systemctl disable monitor-quarkus
rm /etc/systemd/system/monitor-quarkus.service
systemctl daemon-reload
```

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
