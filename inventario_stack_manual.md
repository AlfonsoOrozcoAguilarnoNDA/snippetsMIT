# Manual: inventario del stack de la aplicación (`inventario_stack.py`)

| | |
|---|---|
| **Versión** | 1.0 |
| **Fecha** | 21 de noviembre de 2026 |
| **Dirigido a** | Equipo de Infraestructura |
| **Devolver la salida a** | [NOMBRE / CORREO] |
| **Script** | `inventario_stack.py` (versión 2.0) |

---

## 1. Objetivo y caso de uso

Los servidores donde corre la aplicación X (cinco o más) tienen configuraciones distintas, y hoy no hay una forma fiable de saber qué hay instalado y corriendo en cada uno: si la aplicación se sirve con Apache o Nginx, si usa gunicorn, qué versión de Python y qué librerías tiene cada entorno.

Este script se ejecuta **en cada servidor**, solo lee información y genera un reporte de texto. Ese reporte se devuelve a la persona indicada arriba, quien compara los servidores entre sí.

**Lo que se busca es diagnosticar, no corregir.** Primero se necesita saber qué hay, con un procedimiento repetible y fiable. Las decisiones sobre qué cambiar vienen después.

## 2. Qué hace y qué no hace

**Hace**

- Detecta servicios y procesos relevantes: Apache, Nginx, PHP-FPM, gunicorn, uwsgi, supervisor y bases de datos.
- Lista los puertos en escucha.
- Registra versiones, módulos y los archivos de configuración de Apache/Nginx (con su hash y solo las directivas relevantes).
- Localiza el proyecto y sus entornos virtuales (venv) a partir de los procesos en ejecución.
- Lista los paquetes instalados en cada entorno (`pip freeze`).
- Detecta dependencias externas del código (bases de datos, APIs, correo, etc.).
- Sugiere un `requirements.txt` cruzando lo que el código importa con lo instalado.

**No hace**

- **No modifica nada:** no instala, no reinicia, no cambia configuración.
- **No guarda contraseñas ni claves.** De los archivos `.env` solo lista los *nombres* de las variables. En URLs, comandos y configuraciones, los valores sospechosos se reemplazan por `***` o `[REDACTADO]`.
- **No envía nada por red.**
- Lo único que escribe es **un archivo de texto** con el reporte.

> Cada sección se ejecuta por separado: si una falla, se anota el error y el script continúa con la siguiente. Un reporte incompleto sigue siendo útil.

## 3. Cómo interpretar lo que se encuentre

**Que dos servidores sean distintos no significa, por sí solo, que uno esté mal.** Es una señal de que no hay uniformidad, y eso es justo lo que se quiere medir. Por ejemplo:

- El servidor de pruebas puede estar más actualizado que producción, pero **producción puede tener correcciones urgentes que nunca llegaron a pruebas**. Sobrescribir uno con otro sin revisarlo puede perder trabajo.
- Una librería con otra versión puede ser un descuido, una actualización de seguridad o una dependencia que alguien instaló a mano para resolver un problema.
- Diferencias esperadas: hostname, IP, fecha, PIDs y rutas propias de cada servidor.

El reporte entrega datos. La decisión de cuál servidor es la referencia se toma **después de revisar las diferencias**, no antes.

## 4. Sobre `requirements.txt`, `pipreqs`, `poetry` y similares

**Tener un `requirements.txt` es una buena práctica**, y probablemente el proyecto no lo tiene o no puedes confiar en el. Es un objetivo razonable a mediano plazo, pero **no es el objetivo de esta etapa**.

**Por qué no se introducen herramientas nuevas ahora**

- **`pipreqs`** genera la lista de dependencias a partir de los `import` del código. Es útil, pero *adivina*: puede equivocarse cuando el nombre del import no coincide con el del paquete, con imports condicionales y con versiones, y no incluye las dependencias indirectas. Lo que realmente corre en producción es lo que está instalado en el entorno, no lo que el código parece pedir.
- **`poetry`, `pipenv` y `pip-tools`** gestionan dependencias con archivos de bloqueo (lock). Son herramientas muy buenas cuando el proyecto ya tiene un proceso de despliegue ordenado, pero implican cambiar la forma de instalar y desplegar en cada servidor. Hacerlo ahora añadiría cambios a un entorno que todavía no se entiende.

**La fuente de verdad en esta etapa** es el `pip freeze` de cada entorno que aparece en el reporte: es lo que *realmente está instalado y corriendo*.

La sección "REQUIREMENTS.TXT RECONSTRUIDO" del reporte es solo una **pista** (cruza imports con paquetes instalados, solo dependencias directas). No sustituye al `pip freeze` ni es un `requirements.txt` listo para usar.

**Un orden razonable para después**

1. Comparar los reportes de todos los servidores.
2. Decidir cuál es la referencia (normalmente producción, incorporando las correcciones de los demás).
3. Poner esa versión del código en control de versiones (git).
4. Generar el `requirements.txt` con `pip freeze` desde el venv de la referencia y **probarlo en un entorno limpio**.
5. Definir un único procedimiento de despliegue.
6. Solo entonces, evaluar si herramientas como `poetry` aportan algo al equipo.

## 5. Requisitos

- Servidor Linux con **Python 3.5 o superior** (`python3`). No requiere instalar nada más.
- **Usuario `root` o `sudo` (recomendado).** Sin root el script funciona, pero verá menos: no accede a los procesos de otros usuarios, no ve qué proceso escucha en cada puerto y puede no leer algunas configuraciones. El reporte avisa cuando eso ocurre.
- Espacio: unos pocos KB para el reporte.

## 6. Procedimiento (repetir en cada servidor)

1. **Copiar** `inventario_stack.py` al servidor, a una carpeta **que no sea la del sitio web** (por ejemplo `/root` o `/tmp`).

2. **Ejecutar** desde esa carpeta, con `sudo` si es posible:

   ```bash
   sudo python3 inventario_stack.py
   ```

3. **Revisar el mensaje final**, que indica dónde quedó el reporte:

   ```text
   Reporte generado correctamente: /root/inventario_stack_srv-prod_20261121_101500.txt
   ```

4. **Enviar el archivo `.txt`** a la persona indicada. No conviene renombrarlo: el nombre incluye el servidor y la fecha.

5. **Limpiar** (ver sección 9).

### Opciones

| Opción | Para qué sirve |
|---|---|
| `--proyecto /ruta/del/crm` | Indicar la ruta del proyecto si el script no la detecta solo. Se puede repetir. |
| `--salida /carpeta` | Elegir dónde se escribe el reporte. |
| `--stdout` | Imprimir en pantalla en lugar de crear un archivo (útil si no hay permisos de escritura). |
| `--sin-ejecutar` | No ejecutar el `python`/`pip` de los entornos de la aplicación; solo leer archivos. Los paquetes se leen de los directorios `dist-info`. |

> **Importante:** no ejecutar el script desde la carpeta del sitio web. Si el reporte quedara dentro de una carpeta accesible por web, podría descargarse por URL. Por defecto el archivo se crea con permisos `600` (solo lo lee quien lo ejecutó).

## 7. Qué contiene el reporte

| Sección | Contenido |
|---|---|
| **ENCABEZADO** | Hostname, IPs, fecha, usuario que ejecutó, sistema operativo, versión de Debian y de Python. |
| **SERVICIOS (systemd)** | Estado (activo/habilitado) de apache2, nginx, php-fpm, gunicorn, uwsgi, supervisor, bases de datos, etc. |
| **PROCESOS RELEVANTES** | Procesos de la aplicación y de los servicios, con usuario, directorio de trabajo y línea de comando (secretos ocultos). |
| **PUERTOS EN ESCUCHA** | Salida de `ss -tulpn` (o `netstat`). |
| **SERVIDORES WEB, PHP Y CONFIGURACIÓN** | Versiones, módulos de Apache y PHP, archivos de configuración con hash y directivas relevantes, y paquetes `dpkg` relacionados. |
| **UNIDADES SYSTEMD Y SUPERVISOR** | Cómo se arranca la aplicación (`ExecStart`, `WorkingDirectory`, usuario) y los nombres de sus variables de entorno. |
| **PROYECTO(S) DETECTADO(S)** | Archivos clave con hash, nombres de variables `.env`, dependencias externas, librerías importadas y URLs (sin credenciales). |
| **ENTORNOS PYTHON Y PAQUETES** | Python del sistema y de cada venv, con su `pip freeze`. |
| **REQUIREMENTS.TXT RECONSTRUIDO** | Pista de dependencias directas (ver sección 4). |
| **RESUMEN** | Conteos y secciones que fallaron. |

## 8. Qué devolver

- El archivo **`.txt`** completo, uno por servidor.
- Si el script falló parcialmente, enviar igualmente el reporte: las secciones con error quedan marcadas.
- Si no se pudo ejecutar, avisar con el mensaje de error y la versión de `python3` del servidor (`python3 --version`).

## 9. Limpieza

Una vez enviado el reporte, borrar del servidor el script y el reporte:

```bash
rm -f inventario_stack.py inventario_stack_*.txt
```

Ajustar la ruta si se ejecutó desde otra carpeta o con `--salida`.

El reporte contiene información sensible desde el punto de vista de seguridad (versiones, rutas, usuarios, puertos, nombres de variables, URLs internas), aunque no incluya contraseñas. **No debe quedarse en el servidor ni circular por canales abiertos.**

> **Nota sobre scripts de inventario anteriores.** Si en algún servidor existe otro script de inventario previo o su salida (por ejemplo `dependencias_crm.txt`), conviene localizarlo y retirarlo: puede incluir URLs con credenciales o líneas de comando completas. Si estuviera dentro de una carpeta accesible por web, revisar los logs de acceso y valorar rotar las credenciales que aparezcan.

## 10. Problemas frecuentes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| El reporte dice "sin root faltará información" | Se ejecutó sin `sudo` | Repetir con `sudo python3 inventario_stack.py` |
| "No se detectó ningún proyecto" | No hay proceso de la aplicación en marcha o está en una ruta no estándar | Usar `--proyecto /ruta/del/crm` |
| Error de sintaxis al arrancar | `python3` es anterior a 3.5, o se usó `python` (Python 2) | Ejecutar con `python3` y confirmar con `python3 --version` |
| "No fue posible obtener los puertos" | No están instalados `ss` ni `netstat` | Anotarlo; el resto del reporte sigue siendo válido |
| "No hubo dónde escribir el reporte" | Sin permisos de escritura en la carpeta | Usar `--salida /tmp` o `--stdout` |
| Una sección marcada con `[ERROR en esta sección...]` | Fallo puntual de esa sección | Enviar el reporte igual, con el mensaje de error visible |

## 11. Apéndice: cómo se comparan los reportes (para quien los recibe)

1. Guardar cada reporte con el nombre del servidor.
2. **Comparar los paquetes de cada entorno** (la sección más importante para las dependencias):

   ```bash
   diff <(sed -n '/ENTORNOS PYTHON Y PAQUETES/,/REQUIREMENTS.TXT RECONSTRUIDO/p' srv-pruebas.txt) \
        <(sed -n '/ENTORNOS PYTHON Y PAQUETES/,/REQUIREMENTS.TXT RECONSTRUIDO/p' srv-prod.txt)
   ```

3. **Comparar la configuración del servidor web.** El hash de cada archivo permite ver de un vistazo cuáles difieren sin volcar la configuración completa. Después se revisan las directivas de los que difieren.
4. **Ignorar el ruido esperado:** hostname, IPs, fecha, PIDs y tiempos.
5. **Combinar con los otros reportes** (inventario del código y esquema de la base de datos) antes de decidir cuál servidor es la referencia: una diferencia aislada dice poco, pero un patrón (por ejemplo, un servidor que difiere en código, paquetes y base de datos a la vez) cuenta una historia clara.
