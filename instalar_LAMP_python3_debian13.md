![Logo de Vibe Coding México](logo.jpg)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892bf.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
# 🛠️ Manual de Instalación: La Maravilla de 5 USD

**Stack:** LAMP (Debian 13) + SSL Certbot + PHP 8.4 + Python Flask  
**Plataforma base:** Vultr VPS (o similares)

Este manual detalla la configuración de un entorno de servidor profesional, estable y económico, optimizado para evitar la complejidad innecesaria de los hiperescaladores.

---

## ⚠️ Notas Importantes de Distribución

1. **Debian 13 (Trixie):** Usamos esta distribución por ser probada, antigua y confiable. **No usamos Ubuntu** para evitar el sistema de *Snaps* y dependencias de terceros que comprometan la estabilidad.
2. **Manejadores de Paquetes:** Si decides usar distribuciones basadas en Red Hat (como Rocky Linux o AlmaLinux), recuerda que el comando `apt` cambia por `dnf` y las rutas de configuración de Apache pueden variar.
3. **Privilegios:** Se asume el uso de `sudo` para ejecutar comandos con privilegios de superusuario.
4. **Vigencia:** Escribimos esto el 21 de marzo 2026, con el tiempo puede estar desfasado.

---

## 1. Actualización del Sistema

Actualizamos los repositorios y el sistema base antes de instalar cualquier paquete.

```bash
sudo apt update && sudo apt upgrade -y
```

---

## 2. Instalación del Stack LAMP

Instalamos Apache, PHP 8.4 y MariaDB en un solo paso.

```bash
sudo apt install apache2 php8.4 php8.4-mysql mariadb-server -y
```

### Habilitar módulos de Apache

```bash
sudo a2enmod rewrite
sudo a2enmod ssl
sudo systemctl restart apache2
```

---

## 3. Firewall (UFW)

Instalamos y configuramos UFW con una política de denegación por defecto. Ejecuta cada regla por separado para detectar errores fácilmente.

```bash
sudo apt install ufw -y

# Política base
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Reglas esenciales
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https

# Reglas explícitas por puerto (por si las dudas)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Activar el firewall
sudo ufw enable
```

---

## 4. Base de Datos (MariaDB)

### Instalación y seguridad inicial

```bash
sudo mysql_secure_installation
```

### Crear usuario de aplicación

> **Buena práctica:** Nunca uses `root` desde tu aplicación. Crea un usuario dedicado con permisos limitados.

Entra a la consola de MariaDB:

```bash
sudo mysql -u root -p
```

Crea el usuario y asígnale permisos sobre las bases de datos de tu proyecto:

```sql
-- Crear usuario
CREATE USER 'usuario_app'@'localhost' IDENTIFIED BY 'TuContraseñaSegura123!';

-- Crear bases de datos
CREATE DATABASE bd_proyecto_1;
CREATE DATABASE bd_proyecto_2;
CREATE DATABASE bd_proyecto_3;
CREATE DATABASE bd_proyecto_4;
CREATE DATABASE bd_proyecto_5;

-- Otorgar permisos (repite para cada BD que necesite)
GRANT ALL PRIVILEGES ON bd_proyecto_1.* TO 'usuario_app'@'localhost';
GRANT ALL PRIVILEGES ON bd_proyecto_2.* TO 'usuario_app'@'localhost';

-- Aplicar cambios
FLUSH PRIVILEGES;
EXIT;
```

### Desactivar el Modo Estricto (SQL Mode)

Para asegurar compatibilidad con diversos desarrollos, editamos la configuración:

```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

Busca la sección `[mysqld]` . Si no existe creala y añade:

```ini
sql_mode = ""
```

Reinicia el servicio para aplicar cambios:

```bash
sudo systemctl restart mariadb
```

---

## 5. Configurar PHP (FPM y Extensiones)

### Instalar PHP-FPM y todas las extensiones recomendadas

Instalamos `php8.4-fpm` junto con el conjunto completo de extensiones en un solo comando:

```bash
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-curl php8.4-bcmath php8.4-xml \
  php8.4-mbstring php8.4-zip php8.4-gd php8.4-intl
```

Las tres extensiones que **siempre** debes incluir:

- **`php-curl`** — Indispensable para que el servidor pueda hacer peticiones externas, como verificar licencias o conectar con APIs de pago.
- **`php-bcmath`** — Maneja aritmética de precisión arbitraria. Sin esto, algunos cálculos financieros pueden dar errores de redondeo. Indispensable en factura electrónica.
- **`php-xml`** — Necesario para cualquier sistema que use estructuras de datos XML o RSS.

> Es posible que necesites instalar algún módulo extra según tu proyecto, pero estas son las más comunes. Puedes instalar módulos adicionales en cualquier momento con `sudo apt install php8.4-<modulo> -y`.

### Activar y habilitar PHP-FPM

> **Nota:** En algunos proveedores como OVHcloud, `php8.4-fpm` puede no estar disponible o comportarse distinto. Verifica tu versión activa con `php -v` antes de continuar.

```bash
sudo systemctl restart php8.4-fpm
sudo systemctl enable php8.4-fpm
```

---

## 6. Configuración del VirtualHost

### Crear el directorio del proyecto

```bash
sudo mkdir -p /var/www/tu-dominio.com/public_html/app
```

> **Recomendación:** Pon algo en `index.html` de `public_html` y algo en el de `app` **antes** de continuar. Algo tan simple como `<h1>Hola!</h1>` sirve. Verifica que ves lo correcto **ahora** y no en dos horas cuando hayas avanzado más.

### Crear el archivo de configuración

```bash
sudo nano /etc/apache2/sites-available/tu-dominio.com.conf
```

Pega la siguiente configuración usando tu propia dirección de correo en `ServerAdmin`:

```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    ServerAlias www.tu-dominio.com
    ServerAdmin admin@tu-dominio.com

    DocumentRoot /var/www/tu-dominio.com/public_html

    <Directory /var/www/tu-dominio.com/public_html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Bloque específico para la app React
    # Asegura que Apache busque primero los archivos físicos
    <Directory /var/www/tu-dominio.com/public_html/app>
        RewriteEngine On
        RewriteBase /app/
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /app/index.html [L]
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/tu-dominio-error.log
    CustomLog ${APACHE_LOG_DIR}/tu-dominio-access.log combined
</VirtualHost>
```

### Activar el sitio y recargar Apache

Ejecuta los comandos **uno por uno** para detectar errores en cada paso:

```bash
sudo a2ensite tu-dominio.com.conf
sudo systemctl reload apache2
sudo apache2ctl configtest
```

---

## 7. Certificados SSL con Certbot

> **Prerequisito:** Tu dominio debe apuntar a la IP de tu VPS **antes** de ejecutar Certbot. Ejecuta cada comando por separado, no en lote.

```bash
sudo apt install certbot python3-certbot-apache -y
```

> **Nota:** En algunos casos puede ser necesario quitar el parámetro `--apache` y usar el modo standalone. Prueba primero con `--apache`.

```bash
# Obtener el certificado
sudo certbot --apache -d tu-dominio.com -d www.tu-dominio.com
```

### Verificar la renovación automática

```bash
# Verificar que el timer está activo
sudo systemctl status certbot.timer

# Ver próxima ejecución programada
sudo systemctl list-timers | grep certbot

# Probar renovación en seco (sin aplicar cambios)
sudo certbot renew --dry-run
```

---

## 8. Python 3.x y Flask (Entorno Aislado)

En Debian 13 es fundamental usar entornos virtuales para no interferir con las librerías del sistema.

```bash
sudo apt install python3 python3-pip python3-venv -y

# Crear directorio y entorno virtual
mkdir ~/miproyecto && cd ~/miproyecto
python3 -m venv venv
source venv/bin/activate

# Instalación de Flask
pip install flask
```

---

## 9. Respaldos: Comprimir Directorios (el WinZip de Linux)

En Linux la herramienta estándar para comprimir y empaquetar es `tar` combinada con `gzip`. No necesitas instalar nada extra ya que viene incluida en Debian.

### Comprimir un directorio (crear respaldo)

```bash
# Sintaxis general
tar -czvf nombre-del-respaldo.tar.gz /ruta/al/directorio/

# Ejemplo: respaldar el directorio de tu sitio web
tar -czvf respaldo-sitio-$(date +%Y-%m-%d).tar.gz /var/www/tu-dominio.com/
```

Las banderas que usamos:

- **`-c`** — Crear un archivo nuevo
- **`-z`** — Comprimir con gzip (equivalente a `.zip`)
- **`-v`** — Verbose: muestra los archivos mientras se procesan
- **`-f`** — Especifica el nombre del archivo de salida

> El truco `$(date +%Y-%m-%d)` añade la fecha automáticamente al nombre, por ejemplo: `respaldo-sitio-2025-07-10.tar.gz`. Muy útil para no sobreescribir respaldos anteriores.

### Descomprimir un respaldo

```bash
# Extraer en el directorio actual
tar -xzvf respaldo-sitio-2025-07-10.tar.gz

# Extraer en un directorio específico
tar -xzvf respaldo-sitio-2025-07-10.tar.gz -C /ruta/destino/
```

### Respaldar también la base de datos

Un respaldo completo incluye archivos **y** base de datos. Para MariaDB:

```bash
# Exportar una base de datos a un archivo SQL
mysqldump -u root -p bd_proyecto_1 > respaldo-bd-$(date +%Y-%m-%d).sql

# Comprimir el SQL resultante
gzip respaldo-bd-2025-07-10.sql
```

### Ver el contenido de un `.tar.gz` sin extraerlo

```bash
tar -tzvf respaldo-sitio-2025-07-10.tar.gz
```

> **Tip:** Si prefieres una interfaz visual similar a WinZip, puedes instalar `zip` y `unzip` con `sudo apt install zip unzip -y`, aunque `tar.gz` es el estándar en servidores Linux y el más eficiente para respaldos.

---

## Notas Finales

- Sustituye `tu-dominio.com` y `www.tu-dominio.com` por tu dominio real en **todos** los pasos.
- Sustituye `usuario_app` y `TuContraseñaSegura123!` por credenciales propias y seguras.
- Nunca expongas credenciales de base de datos en repositorios públicos. Usa variables de entorno o archivos `.env` excluidos del control de versiones.

## 🧪 Notas del Autor

Este repositorio es parte de los experimentos documentados en 
**[vibecodingmexico.com](https://vibecodingmexico.com)**.

El código aquí publicado fue generado con asistencia de LLMs — 
se documenta cuál modelo generó qué y en qué fecha, porque eso 
es parte del experimento. Los modelos cambian. Los resultados de hoy 
no garantizan los resultados de mañana. Por eso se fecha todo.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991.

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

El código es tuyo para usar, copiar, modificar y distribuir. 
La única condición es mantener el aviso de copyright en las copias sustanciales.

Para proyectos con requerimientos de licencia distintos, 
revisa el repositorio **SnippetsLGPL**.

---

## ✍️ Acerca del Autor
* **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
* **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)
