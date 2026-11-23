# Solicitud Técnica y Guía de Extracción: Diagnóstico de Procedimientos Almacenados (MySQL / MariaDB)

> **⚠️ AVISO DE ALCANCE Y RESPONSABILIDAD**  
> Este documento **no constituye un plan de trabajo formal, cronograma ni compromiso de entrega**. Define únicamente los **pasos mínimos previos indispensables** de diagnóstico técnico.  
> **Caso de uso:** Verificar y comparar el estado estructural de las bases de datos y sus rutinas (*Stored Procedures*) entre distintos servidores productivos. Debido a políticas de segregación de funciones y seguridad de la información, **estos comandos deben ser ejecutados exclusivamente por un tercero autorizado (equipo de Infraestructura / DBA)** sin otorgar acceso directo a producción al equipo de auditoría/desarrollo.

---

## 1. Garantías de Seguridad e Impacto en Producción

Para tranquilidad del equipo de Infraestructura y cumplimiento en entornos financieros:
* **Operaciones 100% de solo lectura:** Los comandos utilizan `mysqldump` con `--no-data` y consultas a `information_schema`. No modifican, insertan ni eliminan registros ni estructuras.
* **Sin bloqueo de tablas:** El parámetro `--single-transaction` evita bloqueos (*locks*) sobre tablas transaccionales (InnoDB), garantizando que la operación de los *call centers* no se vea afectada.
* **Cero exposición de datos sensibles (PII / PCI):** Al omitir datos (`--no-data`), los archivos resultantes contienen **únicamente definiciones de esquema y código SQL de rutinas**, sin registros de clientes, llamadas ni información financiera.
* **Compatibilidad MySQL / MariaDB:** Los comandos y consultas de este documento son totalmente compatibles tanto con **MySQL** como con **MariaDB** (incluyendo el binario `mysqldump` o su alias `mariadb-dump`).

---

## 2. Pasos de Extracción (A ejecutar por Infraestructura en cada servidor)

Por favor, ejecutar los siguientes pasos en cada servidor involucrado, sustituyendo `<usuario>`, `<nombre_bd>` e `<identificador_servidor>` según corresponda:

### Paso 1: Exportación de estructura y rutinas (sin datos)
Genera un archivo `.sql` limpio con la definición de tablas, vistas, funciones y procedimientos almacenados:

```bash
mysqldump -u <usuario> -p --single-transaction --no-data --routines --triggers --events --skip-comments <nombre_bd> > esquema_<identificador_servidor>.sql
```

*(Nota: En versiones recientes de MariaDB puede utilizarse `mariadb-dump` con exactamente los mismos parámetros).*

### Paso 2: Exportación de inventario y fechas de modificación
Genera un reporte tabular (`.tsv` o `.csv`) con los nombres, fechas de creación y última modificación de cada rutina para identificar rápidamente qué servidor tiene versiones más recientes o desactualizadas:

```bash
mysql -u <usuario> -p -e "
SELECT 
    ROUTINE_SCHEMA AS base_datos,
    ROUTINE_NAME AS procedimiento,
    ROUTINE_TYPE AS tipo,
    CREATED AS fecha_creacion,
    LAST_ALTERED AS ultima_modificacion,
    DEFINER AS usuario_creador
FROM information_schema.ROUTINES 
WHERE ROUTINE_SCHEMA = '<nombre_bd>' 
ORDER BY LAST_ALTERED DESC;
" > inventario_rutinas_<identificador_servidor>.tsv
```

---

## 3. Protocolo de Entrega Segura

1. **Verificación rápida:** Confirmar que los archivos generados no contienen sentencias `INSERT INTO` (garantizando ausencia total de datos).
2. **Empaquetado:** Comprimir los archivos generados por servidor en un único archivo (ej. `diagnostico_esquemas_bd.zip`).
3. **Canal de entrega:** Compartir el paquete comprimido exclusivamente a través del canal interno seguro aprobado por la empresa. **No subir estos archivos a repositorios de código.**

---

## 4. Pasos Posteriores (A ejecutar por el equipo de Auditoría / Desarrollo)

Una vez recibidos los archivos de todos los servidores, el equipo técnico realizará de forma aislada (fuera de producción):

1. **Comparación entre servidores (`diff`):**
   ```bash
   diff -u esquema_servidor_1.sql esquema_servidor_2.sql > diferencias_servidores.patch
   ```
2. **Mapeo de uso en código fuente (Python):**
   ```bash
   grep -i "CREATE.*PROCEDURE" esquema_servidor_1.sql | sed -E 's/.*PROCEDURE `([^`]+)`.*/\1/' > lista_sps.txt

   while read sp; do
       echo "=== Buscando: $sp ==="
       grep -rnw '/ruta/al/codigo/python' -e "$sp" || echo "NO SE USA EN PYTHON"
   done < lista_sps.txt
   ```

## 5. Consideraciones adicionales

5.1 mysqldump --single-transaction con --no-data
El --single-transaction es útil para volcar datos sin bloquear, pero cuando se usa --no-data, el volcado es de metadatos y no hay transacción de datos que proteger. No es un error, pero es innecesario y puede confundir a un DBA que se pregunte por qué está ahí. Puedes dejarlo (no hace daño) o eliminarlo y explicar que --no-data ya garantiza que no hay lectura de datos. Yo lo dejaría, pero con una nota: "--single-transaction es redundante con --no-data, pero se incluye por consistencia con el estándar de extracción segura". O simplemente quitarlo para no dar pie a preguntas.

5.2 --skip-comments
Esto elimina los comentarios del dump, incluyendo la información de versión del servidor y la fecha del volcado. Para un diff entre servidores, esos comentarios son ruido, así que está bien. Pero ten en cuenta que también elimina la línea -- Dump completed, que es útil para verificar que el volcado terminó bien. No es crítico, pero si quieres verificar integridad del archivo, podrías omitir --skip-comments y filtrar después, o dejar una nota de que la verificación de integridad se hace por tamaño o por grep -c "CREATE TABLE".

5.3. DEFINER en el inventario de rutinas
El SELECT incluye DEFINER AS usuario_creador. Eso es útil, pero ten en cuenta que el DEFINER puede contener el nombre de usuario y el host ('usuario'@'host'), lo cual es información que en algunos entornos se considera sensible. No es PII de clientes, pero es información de cuentas. Si el equipo de infraestructura lo considera sensible, pueden omitirlo o enmascararlo. No es un error incluirlo, pero conviene mencionarlo.

5.4. La comparación con diff
El diff -u entre dos dumps de mysqldump va a producir muchas diferencias triviales (fechas, versiones, orden de tablas) que no son relevantes. Para que el diff sea útil, conviene normalizar los archivos antes: eliminar líneas de comentario, ordenar las tablas, etc. Una forma sencilla es usar mysqldump --skip-comments --skip-extended-insert --no-data y luego sort sobre las líneas de CREATE TABLE. Pero eso ya es análisis posterior, no parte del script de extracción. Puedes dejar una nota en la sección 4: "Para que el diff sea significativo, se recomienda normalizar los archivos (eliminar comentarios, ordenar por nombre de objeto) antes de comparar".

5.5. El grep de la sección 4
bash

grep -i "CREATE.*PROCEDURE" esquema_servidor_1.sql | sed -E 's/.*PROCEDURE `([^`]+)`.*/\1/' > lista_sps.txt

Esto funciona si el mysqldump genera CREATE PROCEDURE \nombre`en una sola línea. En algunos casos, elCREATEy el nombre pueden estar en líneas distintas, o elDEFINER` puede aparecer antes. Es un detalle menor, pero para robustez podrías usar algo como:
bash

grep -iE "CREATE.*PROCEDURE" esquema_servidor_1.sql | grep -oE '`[^`]+`' | head -1 | tr -d '`'

O simplemente dejar una nota de que el script asume el formato estándar de mysqldump.

5.6. El while read sp con grep -rnw
bash

while read sp; do
    echo "=== Buscando: $sp ==="
    grep -rnw '/ruta/al/codigo/python' -e "$sp" || echo "NO SE USA EN PYTHON"
done < lista_sps.txt

Esto es correcto, pero ten en cuenta que grep -w busca palabras completas, y los nombres de procedimientos pueden contener caracteres que no son "palabra" para grep (como _). En la mayoría de los casos funciona, pero si un procedimiento se llama sp_create_carga, grep -w lo encontrará. Si se llama sp-create-carga, no. No es un error, pero conviene saberlo.

---

## Licencia (MIT License)

Copyright (c) 2026

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
