![Logo de Vibe Coding México](instalargitea.jpg)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892bf.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
# 🐙 Instalar Gitea en Debian 13 (Vultr)

**Stack:** Gitea + Git + Apache VirtualHost + SSL Certbot  
**Subdominio:** `git.tu-dominio.com`  
**Plataforma base:** Vultr VPS — mismo servidor del manual LAMP

> Este manual es complemento de [Instalar LAMP y Python en Debian 13](./instalar_LAMP_python3_debian13.md).  
> Se asume que Apache, UFW y Certbot ya están instalados y funcionando.

---

## ⚠️ Antes de empezar — Anota esto

Abre tu bloc de notas (el mismo donde guardaste la IP y el password del servidor) y reserva espacio para:

```
Gitea Admin User:     _______________
Gitea Admin Password: _______________   ← ANÓTALA AHORA, no hay recuperación fácil
Gitea Admin Email:    _______________
URL de Gitea:         https://git.tu-dominio.com
```

> **Advertencia crítica:** Gitea no tiene recuperación de contraseña por correo por defecto.  
> Si pierdes la contraseña del administrador, tendrás que resetearla por línea de comandos.  
> **Anótala en papel si hace falta.**

---
Hay una versión 2, te sugiero que uses esa. Alfinal de este archivo vienen los cambios quese hicieron contra versión 2
---

## 1. Apuntar el Subdominio

Antes de cualquier instalación, entra a tu panel de DNS (Vultr, Porkbun, o donde tengas el dominio) y crea un registro:

```
Tipo:  A
Nombre: git
Valor: [IP de tu VPS]
TTL:   300 (o el mínimo disponible)
```

Espera unos minutos a que propague antes de continuar.  
Puedes verificar con:

```bash
ping git.tu-dominio.com
```

---

## 2. Instalar Git

```bash
sudo apt update
sudo apt install git -y

# Verificar instalación
git --version
```

---

## 3. Crear Usuario del Sistema para Gitea

Gitea necesita un usuario dedicado del sistema. **No lo ejecutes como root.**

```bash
sudo adduser \
  --system \
  --shell /bin/bash \
  --gecos 'Gitea' \
  --group \
  --disabled-password \
  --home /home/gitea \
  gitea
```

---

## 4. Crear Directorios de Gitea

```bash
sudo mkdir -p /var/lib/gitea/{custom,data,log}
sudo chown -R gitea:gitea /var/lib/gitea/
sudo chmod -R 750 /var/lib/gitea/

sudo mkdir /etc/gitea
sudo chown root:gitea /etc/gitea
sudo chmod 770 /etc/gitea
```

---

## 5. Descargar Gitea

Descarga el binario oficial. Verifica la versión más reciente en  
https://dl.gitea.com/gitea/

```bash
# Descargar binario (ajusta la versión si hay una más nueva)
sudo wget -O /usr/local/bin/gitea \
  https://dl.gitea.com/gitea/1.22.3/gitea-1.22.3-linux-amd64

# Dar permisos de ejecución
sudo chmod +x /usr/local/bin/gitea

# Verificar
gitea --version
```

---

## 6. Crear el Servicio systemd

```bash
sudo nano /etc/systemd/system/gitea.service
```

Pega el siguiente contenido:

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

Activa e inicia el servicio:

```bash
sudo systemctl daemon-reload
sudo systemctl enable gitea
sudo systemctl start gitea

# Verificar que está corriendo
sudo systemctl status gitea
```

---

## 7. Configurar Apache como Proxy Inverso

Gitea corre en el puerto 3000 internamente.  
Apache lo expone al mundo en el puerto 80/443.

```bash
# Habilitar módulo proxy
sudo a2enmod proxy
sudo a2enmod proxy_http

# Crear configuración del subdominio
sudo nano /etc/apache2/sites-available/git.tu-dominio.com.conf
```

Pega la siguiente configuración:

```apache
<VirtualHost *:80>
    ServerName git.tu-dominio.com
    ServerAdmin admin@tu-dominio.com

    ProxyPreserveHost On
    ProxyRequests Off
    ProxyPass / http://localhost:3000/
    ProxyPassReverse / http://localhost:3000/

    ErrorLog ${APACHE_LOG_DIR}/gitea-error.log
    CustomLog ${APACHE_LOG_DIR}/gitea-access.log combined
</VirtualHost>
```

Activa el sitio:

```bash
sudo a2ensite git.tu-dominio.com.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

---

## 8. Certificado SSL con Certbot

```bash
sudo certbot --apache -d git.tu-dominio.com
```

> Certbot modificará automáticamente tu VirtualHost para redirigir HTTP a HTTPS.  
> Verifica que funcione entrando a `https://git.tu-dominio.com` en el navegador.

---

## 9. Instalación Web de Gitea

Abre `https://git.tu-dominio.com` en tu navegador.  
Te va a aparecer el asistente de instalación. Configura así:

**Base de Datos:**
```
Tipo:     SQLite3  ← más simple para un VPS de 5 USD
Ruta:     /var/lib/gitea/data/gitea.db
```

> Si prefieres MariaDB, usa una de las bases de datos que ya creaste en el manual LAMP.  
> Para un uso personal o de equipo pequeño, SQLite3 es suficiente y más sencillo.

**Configuración General:**
```
Título del sitio:  Tu nombre o el de tu proyecto
URL base:          https://git.tu-dominio.com/
```

**Cuenta Administrador** — este es el momento crítico:
```
Usuario:    [el que elegiste]
Contraseña: [ANÓTALA AHORA en tu bloc de notas]
Email:      [tu correo]
```

Da clic en **Instalar Gitea** y espera.

---

## 10. Desactivar Registro de Nuevos Usuarios

> **Importante:** Por defecto Gitea permite que cualquiera se registre.  
> Si tu Gitea es personal o de equipo cerrado, desactívalo inmediatamente.

Edita el archivo de configuración:

```bash
sudo nano /etc/gitea/app.ini
```

Busca la sección `[service]` y añade o modifica:

```ini
[service]
DISABLE_REGISTRATION = true
```

Si la sección no existe, agrégala completa.  
Reinicia Gitea para aplicar:

```bash
sudo systemctl restart gitea
```

> **Verificación:** Abre una ventana de incógnito y entra a  
> `https://git.tu-dominio.com/user/sign_up`  
> Debe mostrar error o redirigir. Si aún permite registro, revisa que guardaste el archivo.

---

## 11. Permisos Finales del Archivo de Configuración

Una vez completada la instalación web, restringe los permisos:

```bash
sudo chmod 750 /etc/gitea
sudo chmod 640 /etc/gitea/app.ini
```

---

## 12. Verificar Firewall

Gitea corre internamente en el puerto 3000.  
**No abras el puerto 3000 en UFW** — el tráfico debe pasar por Apache.

```bash
# Verificar que el puerto 3000 NO está abierto al exterior
sudo ufw status

# Solo deben estar abiertos 22, 80 y 443
```

---

## 13. Comandos Útiles de Mantenimiento

```bash
# Ver logs en tiempo real
sudo journalctl -f -u gitea

# Reiniciar después de cambios en app.ini
sudo systemctl restart gitea

# Ver estado del servicio
sudo systemctl status gitea

# Backup manual de la base de datos SQLite
sudo -u gitea cp /var/lib/gitea/data/gitea.db \
  /home/tu-usuario/respaldo-gitea-$(date +%Y-%m-%d).db
```


# Inician Cambios quese hicieron para crear la versión 2
# Guía de instalación y configuración de Gitea  
**Versión:** gitea-1.22.3  
**Fecha de redacción:** 21/03/2026  

---

## Gitea vs Gitea Actions
Es importante distinguir que **Gitea Actions** requiere un componente separado: `act_runner`.  
- **Gitea**: el servidor principal de repositorios.  
- **act_runner**: ejecutor independiente para flujos de trabajo (CI/CD).  
No confundirlos evita errores de despliegue y configuración.

---

## Configuración de [server] en `app.ini`
Este bloque suele ser el más omitido y problemático, ya que define la identidad pública del servicio:

```ini
[server]
DOMAIN           = git.midominio.com
ROOT_URL         = https://git.midominio.com/
SSH_DOMAIN       = git.midominio.com
SSH_PORT         = 22

# Terminan Cambios quese hicieron para crear la versión 2
---

## Notas Finales

- Sustituye `git.tu-dominio.com` por tu subdominio real en **todos** los pasos.
- La contraseña del administrador va en tu bloc de notas **antes** de instalar.
- `DISABLE_REGISTRATION = true` es obligatorio si tu Gitea no es público.
- El puerto 3000 nunca debe abrirse en UFW — solo Apache lo usa internamente.
- Para agregar usuarios manualmente: panel de administración → **Usuarios** → **Crear cuenta**.

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

El código es tuyo para usar, copiar, modificar y distribuir. 
La única condición es mantener el aviso de copyright en las copias sustanciales.

Para proyectos con requerimientos de licencia distintos, 
revisa el repositorio **SnippetsLGPL**.

---

## ✍️ Acerca del Autor
Este repositorio es parte de los experimentos documentados en 
**[vibecodingmexico.com](https://vibecodingmexico.com)**.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991.

* **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
* **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)

*Complemento del manual [Instalar LAMP y Python en Debian 13](./instalar_LAMP_python3_debian13.md)*  
*Publicado en [vibecodingmexico.com](https://vibecodingmexico.com) • Licencia MIT*
