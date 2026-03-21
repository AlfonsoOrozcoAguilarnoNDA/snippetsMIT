![Logo de Vibe Coding México](instalargitea.jpg)

[![Gitea Version](https://img.shields.io/badge/gitea-1.22.3-609926.svg)](https://gitea.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

# 🐙 Instalar Gitea en Debian 13 (Vultr)

**Stack:** Gitea + Git + Apache VirtualHost + SSL Certbot  
**Subdominio:** `git.tu-dominio.com`  
**Plataforma base:** Vultr VPS — mismo servidor del manual LAMP  
**Fecha de redacción:** Enero 2026

> Este manual es complemento de [Instalar LAMP y Python en Debian 13](./instalar_LAMP_python3_debian13.md).  
> Se asume que Apache, UFW y Certbot ya están instalados y funcionando.

---

## Gitea vs Gitea Actions — diferencia importante

**Gitea** es el servidor de repositorios Git: interfaz web, gestión de usuarios, organizaciones, pull requests, issues y wikis. Es el equivalente a tener tu propio GitHub privado en tu servidor. Está escrito en Go, es un solo binario sin dependencias externas y consume muy poca RAM.

**Gitea Actions** es el sistema de CI/CD integrado, equivalente a GitHub Actions. Permite ejecutar flujos automatizados (pruebas, builds, deploys) al hacer push a un repositorio. **Gitea Actions requiere un runner separado** — un proceso adicional llamado `act_runner` que se instala y configura independientemente del binario principal de Gitea. No se activa solo al instalar Gitea.

Este manual instala **solo Gitea base**. Gitea Actions queda como expansión futura cuando el flujo de trabajo lo justifique.

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

## 1. Apuntar el Subdominio

Antes de cualquier instalación, entra a tu panel de DNS (Vultr, Porkbun, o donde tengas el dominio) y crea un registro:

```
Tipo:   A
Nombre: git
Valor:  [IP de tu VPS]
TTL:    300 (o el mínimo disponible)
```

Espera unos minutos a que propague antes de continuar. Puedes verificar con:

```bash
ping git.tu-dominio.com
```

No prossigas hasta que responda con la IP correcta.

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

Descarga el binario oficial. Verifica la versión más reciente en https://dl.gitea.com/gitea/

```bash
sudo wget -O /usr/local/bin/gitea \
  https://dl.gitea.com/gitea/1.22.3/gitea-1.22.3-linux-amd64

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

Gitea corre en el puerto 3000 internamente. Apache lo expone al mundo en el puerto 80/443.

```bash
# Habilitar módulos proxy
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

    # Sin esto, commits de más de 1 MB fallan con error 413 Request Entity Too Large
    # 256M es suficiente en la práctica; 512M no sobra para repos con binarios
    LimitRequestBody 536870912

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

Abre temporalmente el puerto 3000 para poder completar la instalación web antes de cerrar el acceso directo:

```bash
sudo ufw allow 3000/tcp
```

Obtén el certificado SSL:

```bash
sudo certbot --apache -d git.tu-dominio.com
```

> Certbot modificará automáticamente tu VirtualHost para redirigir HTTP a HTTPS.  
> Verifica que funcione entrando a `https://git.tu-dominio.com` en el navegador.

---

## 9. Instalación Web de Gitea

Abre `https://git.tu-dominio.com` en tu navegador. Te aparecerá el asistente de instalación. Configura así:

**Base de Datos:**

```
Tipo:  SQLite3  ← más simple para un VPS de 5 USD
Ruta:  /var/lib/gitea/data/gitea.db
```

> Si prefieres MariaDB, usa una de las bases de datos que ya creaste en el manual LAMP.  
> Para uso personal o equipo pequeño, SQLite3 es suficiente y más sencillo.

**Configuración General:**

```
Título del sitio:  Tu nombre o el de tu proyecto
URL base:          https://git.tu-dominio.com/
```

**Cuenta Administrador** — este es el momento crítico:

```
Usuario:    [el que elegiste — sugerido: gitea2]
Contraseña: [ANÓTALA AHORA en tu bloc de notas]
Email:      [tu correo]
```

Da clic en **Instalar Gitea** y espera. Puede tardar un par de minutos.

---

## 10. Configurar la Identidad de Gitea (app.ini)

Este paso es el que más se omite y el que más problemas causa después. Sin él, Gitea sigue apuntando a la IP del servidor en lugar del dominio, los enlaces de clonar son incorrectos y SSH da errores.

```bash
sudo nano /etc/gitea/app.ini
```

Busca o crea la sección `[server]` y configura:

```ini
[server]
DOMAIN      = git.tu-dominio.com
HTTP_PORT   = 3000
ROOT_URL    = https://git.tu-dominio.com/
DISABLE_SSH = false
SSH_DOMAIN  = git.tu-dominio.com
SSH_PORT    = 22
```

Reinicia Gitea para aplicar los cambios:

```bash
sudo systemctl restart gitea
```

Verifica en la interfaz web que los enlaces de clonar ya muestren `https://git.tu-dominio.com` y no la IP del servidor.

---

## 11. Desactivar Registro de Nuevos Usuarios

Por defecto Gitea permite que cualquiera se registre. Si tu instancia es personal o de equipo cerrado, desactívalo inmediatamente.

En el mismo `app.ini`, busca o crea la sección `[service]`:

```ini
[service]
DISABLE_REGISTRATION             = true
SHOW_REGISTRATION_BUTTON         = false
ALLOW_ONLY_EXTERNAL_REGISTRATION = false
```

Las tres líneas trabajan juntas:

- `DISABLE_REGISTRATION = true` — nadie puede crear cuentas aunque conozcan el link directo.
- `SHOW_REGISTRATION_BUTTON = false` — desaparece el botón de la interfaz.
- `ALLOW_ONLY_EXTERNAL_REGISTRATION = false` — bloquea también el registro vía OAuth externo.

```bash
sudo systemctl restart gitea
```

**Verificación:** Abre una ventana de incógnito y entra a `https://git.tu-dominio.com/user/sign_up`. Debe mostrar error o redirigir al login. Si aún permite registro, revisa que guardaste el archivo correctamente.

---

## 12. Cerrar el Puerto 3000

Una vez que Gitea está operando a través de Apache con SSL, el puerto 3000 no debe estar accesible desde el exterior. El tráfico debe pasar exclusivamente por Apache en los puertos 80/443.

```bash
# Cerrar el puerto que abrimos para la instalación
sudo ufw delete allow 3000/tcp

# Verificar el estado — solo deben aparecer 22, 80 y 443
sudo ufw status
```

> Si en algún momento necesitas acceder directamente a Gitea para diagnóstico, puedes abrirlo temporalmente y cerrarlo de nuevo al terminar.

---

## 13. Permisos Finales del Archivo de Configuración

Una vez completada toda la configuración, restringe los permisos del `app.ini`:

```bash
sudo chmod 750 /etc/gitea
sudo chmod 640 /etc/gitea/app.ini
```

---

## 14. Comandos Útiles de Mantenimiento

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

# Agregar usuarios manualmente (si el registro está desactivado)
# Panel de administración → Usuarios → Crear cuenta
```

---

## Notas Finales

- Sustituye `git.tu-dominio.com` por tu subdominio real en **todos** los pasos.
- La contraseña del administrador va en tu bloc de notas **antes** de instalar.
- Las tres líneas de `[service]` son obligatorias si tu Gitea no es público — no basta con una sola.
- El puerto 3000 nunca debe quedar abierto en UFW en producción — solo Apache lo usa internamente.
- Configura siempre la sección `[server]` del `app.ini` con el dominio real — sin esto los enlaces de clonar quedan apuntando a la IP y SSH no funciona correctamente.
- Este manual cubre **Gitea base**. Para CI/CD automatizado necesitas instalar `act_runner` por separado — eso es Gitea Actions y requiere su propio manual.

---

## ⚖️ Licencia

Este repositorio se distribuye bajo licencia **MIT**.

La única condición es mantener el aviso de copyright en las copias sustanciales.

Para proyectos con requerimientos de licencia distintos,
revisa el repositorio **SnippetsLGPL**.

---

## ✍️ Acerca del Autor
Este repositorio es parte de los experimentos documentados en
**[vibecodingmexico.com](https://vibecodingmexico.com)**.

Mi nombre es **Alfonso Orozco Aguilar**, mexicano, programador desde 1991.


- **Sitio Web:** [vibecodingmexico.com](https://vibecodingmexico.com)
- **Facebook:** [Perfil de Alfonso Orozco Aguilar](https://www.facebook.com/alfonso.orozcoaguilar)

*Complemento del manual [Instalar LAMP y Python en Debian 13](./instalar_LAMP_python3_debian13.md)*  
*Publicado en [vibecodingmexico.com](https://vibecodingmexico.com) • Licencia MIT*
