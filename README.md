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
| `imagen_dia_mistral.php` | Snippet #001: El Experimento de Autorreferencia | Mistral 2026 | 2026-03 |

*(La tabla crece con cada nuevo post)*

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
