![Logo de Vibe Coding México](logo.jpg)
# ✂️ Snippets MIT

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892bf.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

**Snippets MIT** es una colección de código funcional generado como parte de la 
sección **Snippets** de [vibecodingmexico.com](https://vibecodingmexico.com).

Cada archivo en este repositorio corresponde a un post distinto. No es un sistema 
único ni un proyecto continuo — es una caja de herramientas que crece con el sitio.

> ⚠️ Si buscas el repositorio **SnippetsLGPL**, ese es un repositorio separado 
> con licencia distinta. Este repositorio es exclusivamente MIT.

---

## ¿Qué es un Snippet en este contexto?

Un snippet es código que nació de una necesidad real, resuelto rápido, 
evaluado en la práctica y publicado tal cual — sin pretender ser un sistema completo.

Puede ser:
- Un script que resolví para algo mío y que tardó más de 20 minutos
- Un experimento rápido con uno o varios LLMs sin evaluación intensiva
- Una prueba de concepto que no amerita repositorio propio

Cada archivo incluye en su encabezado: el post de origen, el modelo que lo generó 
y la fecha. El código se publica bajo licencia MIT — úsalo libremente, 
pero si te explota es asunto tuyo.

---

## 📂 Archivos Disponibles

| Archivo | Post de Origen | Generado por | Fecha |
|---|---|---|---|
| `imagen_dia_mistral.php` | Snippet #001: El Experimento de Autorreferencia | Mistral 2026 | 2026-03 | Con bugs
| `imagen_dia_claude.php` | Snippet #001: El Experimento de Autorreferencia | Claude | 2026-03 | Con bugs

*(La tabla crece con cada nuevo post)*

---
# 📖 ¿Qué manual necesito?

**Repositorio:** [snippetsMIT](https://github.com/AlfonsoOrozcoAguilarnoNDA/snippetsMIT)  
**Autor:** Alfonso Orozco Aguilar — [vibecodingmexico.com](https://vibecodingmexico.com)

Todos los manuales asumen **Debian 13 (Trixie)** en **Vultr VPS** con acceso root real.  
Si usas otro proveedor, lee la nota de OVHcloud en cada manual antes de empezar.

---

## Resumen rápido

| Manual | Servidor Web | PHP | Node/React | Gitea | Quarkus | Para quién |
|--------|-------------|-----|-----------|-------|---------|------------|
| `instalar_LAMP_python3_debian13.md` | Apache | ✅ 8.4 | ❌ | ❌ | ❌ | El punto de partida. PHP + Python + Flask. |
| `instalar_LAMP_react_node_quarkus_gitea_debian13.md` | Apache | ✅ 8.4 | ✅ Node 22 + React | ✅ | ✅ | LAMP completo con stack moderno. |
| `instalar_LEMP_debian13.md` | **Nginx** | ✅ 8.4 | ✅ Node 22 + React | ✅ | ✅ | Lo mismo pero con Nginx en lugar de Apache. |
| `instalar_GITEA_debian13.md` | Apache (existente) | — | ❌ | ✅ v1 | ❌ | Solo Gitea. Versión inicial. |
| `instalar_GITEA_debian13v2.md` | Apache (existente) | — | ❌ | ✅ v2 | ❌ | Solo Gitea. **Versión recomendada.** |

---

## El orden lógico de instalación

```
1. instalar_LAMP_python3_debian13.md        ← Empieza aquí siempre
2. instalar_GITEA_debian13v2.md             ← Agrega control de versiones
3. instalar_LAMP_react_node_quarkus_...md   ← Si necesitas Node/React/Quarkus
```

> El manual LEMP es una alternativa al paso 3, no un paso adicional.  
> **No instales Apache y Nginx en el mismo servidor** — compiten por los puertos 80 y 443.

---

## ¿Apache (LAMP) o Nginx (LEMP)?

La diferencia central es el servidor web. El resto del stack es prácticamente idéntico.

**Usa Apache si:**
- Es tu primera vez configurando un servidor
- Usas `.htaccess` (WordPress, Laravel, CodeIgniter)
- Prefieres `VirtualHost` y `RewriteRule` — más documentación en español disponible
- Quieres el stack recomendado por el autor de este repositorio

**Usa Nginx si:**
- Ya tienes experiencia con servidores
- Necesitas mayor rendimiento bajo carga alta
- Estás familiarizado con bloques `server`, `location` y `alias`
- Sabes que **las barras en Nginx importan**: `/app` no es lo mismo que `/app/`

> El autor prefiere Apache para trabajo real con Compliance (facturación electrónica,  
> sistemas de salud, gobierno) por menor número de dependencias y mayor documentación  
> en español para el mercado LATAM.

---

## Diferencia entre instalar_GITEA v1 y v2

Usa siempre **v2**. Los cambios respecto a v1 son correcciones de problemas reales:

| Punto | v1 | v2 |
|-------|----|----|
| Puerto 3000 | Nunca se abre | Se abre para la instalación y se cierra después |
| Sección `[server]` en app.ini | No incluida | **Incluida** — sin esto los links de clonar apuntan a la IP |
| Registro desactivado | Solo 1 línea | 3 líneas — más robusto |
| Límite de tamaño de commits | No configurado | `LimitRequestBody` en Apache — evita error 413 |
| Gitea vs Gitea Actions | No explicado | Explicado al inicio — evita confusión frecuente |

---

## Nota sobre OVHcloud

OVHcloud usa un usuario intermedio `debian` en lugar de root directo.  
Esto genera problemas graves con **PM2, NVM y permisos de npm**.

- ✅ LAMP/LEMP básico con PHP: funciona en OVHcloud
- ✅ Gitea: funciona en OVHcloud  
- ⚠️ Node, React, PM2, Quarkus: usa **Vultr u otro proveedor con root real**

---

## Tecnologías y por qué estas y no otras

**PHP 8.4 en lugar de Node para backend:**  
PHP está preinstalado en casi todos los servidores. Sin dependencias externas auditables.  
Indispensable en entornos regulados (facturación electrónica, salud, gobierno).

**Python + Flask para servicios específicos:**  
Para análisis de datos, dashboards o APIs donde Python tiene ventaja real.  
Siempre en entorno virtual — nunca instalar paquetes pip en el sistema global.

**GO:**  
Ejecutables y para muchos mas simple de instalar que python.  
Ventaja ydesventaja, pero rápido.

**Gitea en lugar de GitHub/GitLab:**  
Control de versiones propio en tu servidor. Un binario de Go sin dependencias.  
Si los modelos públicos desaparecen o te banean, tu código sigue siendo tuyo.

**Quarkus en lugar de Spring Boot:**  
Menor consumo de RAM. Más rápido en arranque. Mejor para VPS de recursos limitados.  
Spring Boot en un servidor de 1 GB RAM con MariaDB y Nginx conviviendo es un problema.

**React en modo estático solamente:**  
Los CVE de diciembre 2025 (CVE-2025-55182 al CVE-2025-67779) afectan React Server Components.  
En modo estático — archivos compilados servidos por Apache/Nginx — no aplican.  
Para proyectos regulados considera Vue.js, Astro o PHP puro.

---

## 🛠️ Especificaciones Generales

* **Lenguaje principal:** PHP 8.x procedural
* **Frontend:** Bootstrap 4.6.x vía jsDelivr cuando aplica
* **Base de datos:** mysqli cuando aplica — la conexión va en `config.php` externo
* **Arquitectura:** Archivos independientes, sin dependencias entre snippets
* **Ambiente:** Optimizado para cPanel y hospedajes compartidos en LATAM

---

## ⚙️ Uso General

Cada archivo es autónomo. Lee el encabezado del archivo antes de ejecutarlo — 
ahí están los requerimientos específicos de ese snippet.

Para los que usan base de datos:
1. Ejecuta el `CREATE TABLE` incluido en el encabezado o en el post de origen
2. Crea tu `config.php` con la variable `$link` de conexión mysqli
3. Ajusta las variables de configuración marcadas como `'TU_CLAVE_AQUI'`

---

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
