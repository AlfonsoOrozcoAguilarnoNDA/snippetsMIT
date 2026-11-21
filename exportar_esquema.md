# Manual: exportar el esquema de la base de datos elegida. Usamos `crm` como ejemplo.

| | |
|---|---|
| **Versión** | 1.0 |
| **Fecha** | 21 de noviembre de 2026 |
| **Dirigido a** | Equipo de Infraestructura |
| **Devolver la salida a** | [NOMBRE / CORREO] |
| **Script** | `exportar_esquema_crm.sh` |

---

## 1. Objetivo y caso de uso

Los servidores del sistema CRM (cinco o más) tienen configuraciones distintas y no se sabe con certeza en qué se diferencian. Este manual describe cómo obtener, **en cada servidor**, la **estructura** de la base de datos `crm` (tablas, columnas, índices, vistas, procedimientos, triggers y eventos) junto con la **versión del motor** (MySQL o MariaDB).

El único fin es **comparar la estructura entre servidores**. La salida de cada servidor se devuelve a la persona indicada arriba, quien hace la comparación.

## 2. Qué hace y qué no hace

**Hace**

- Detecta si el servidor corre **MySQL** o **MariaDB** y registra la versión exacta.
- Registra charset/collation del servidor y de la base `crm`, motor de tablas por defecto y `sql_mode`.
- Lista las tablas, vistas, rutinas, triggers y eventos de `crm`.
- Exporta el esquema con `mysqldump --no-data` (solo estructura).
- Empaqueta todo en un `.tar.gz` para enviarlo.

**No hace**

- **No exporta datos** de la aplicación (clientes, usuarios, contraseñas, etc.).
- **No modifica nada**: no ejecuta `INSERT`, `UPDATE`, `DELETE`, `ALTER` ni `DROP`; no cambia configuración ni reinicia servicios. Solo consultas de lectura.
- **No guarda la contraseña**: se pide al ejecutar, se usa en un archivo temporal con permisos 600 y se borra al terminar.
- Lo único que escribe es una carpeta nueva y su `.tar.gz` (en el directorio actual, o en `/tmp` si no hay permiso de escritura).

> Es seguro ejecutarlo en producción: el volcado `--no-data` solo lee metadatos y no bloquea tablas.

## 3. Requisitos

- Servidor Linux con `bash`.
- Cliente de base de datos instalado: `mysql`/`mysqldump` o `mariadb`/`mariadb-dump`. El script detecta cuál hay.
- Una de estas dos formas de acceso:
  - **Recomendada:** ejecutar con `sudo` y dejar el usuario en blanco (entra como `root` por socket, sin contraseña, en instalaciones MariaDB/MySQL estándar de Debian).
  - Un usuario de base de datos con permisos de lectura sobre `crm`, `SHOW VIEW`, `TRIGGER` y `EVENT`, y acceso a las rutinas almacenadas.
- Espacio mínimo: unos pocos MB.

Si faltan permisos para rutinas o eventos, el script **reintenta con menos objetos** y deja anotado en el encabezado del `.sql` qué modo funcionó. No se detiene.

## 4. Procedimiento (repetir en cada servidor)

1. **Copiar** `exportar_esquema_crm.sh` al servidor, en cualquier carpeta donde se pueda escribir (por ejemplo `/tmp` o el home).

2. **Dar permiso de ejecución:**

   ```bash
   chmod +x exportar_esquema_crm.sh
   ```

3. **Ejecutar.** Opción recomendada:

   ```bash
   sudo ./exportar_esquema_crm.sh
   ```

   Cuando pregunte `Usuario de MySQL/MariaDB`, presionar **Enter** (usa el usuario del sistema por socket).
   Si no se tiene `sudo` o `root`, escribir el usuario de base de datos y su contraseña cuando la pida (no se ve al teclear).

4. **Revisar el resumen** que imprime al final. Ejemplo:

   ```text
   ==============================================================
    Motor detectado : MariaDB 10.11.6-MariaDB-0+deb12u1
    Modo de volcado : completo (tablas, vistas, rutinas, triggers, eventos)
    Carpeta         : /tmp/esquema_crm_srv-prod_20261121_101500
    Archivo a enviar: /tmp/esquema_crm_srv-prod_20261121_101500.tar.gz
    Enviar a        : [NOMBRE / CORREO]
   ==============================================================
   ```

5. **Enviar el archivo `.tar.gz`** a la persona indicada. Conviene no renombrarlo: el nombre incluye el servidor y la fecha.

6. **Limpiar** (ver sección 7).

## 5. Qué se genera

| Archivo | Contenido |
|---|---|
| `info_servidor.txt` | Hostname, IPs, SO, **motor (MySQL/MariaDB) y versión**, charset/collation, `sql_mode`, y lista de tablas, rutinas, triggers y eventos. |
| `esquema_crm_<servidor>_raw.sql` | Esquema tal como lo entregó `mysqldump`. |
| `esquema_crm_<servidor>_normalizado.sql` | Igual, pero sin `AUTO_INCREMENT=N` ni `DEFINER`, que cambian entre servidores sin que la estructura sea distinta. **Es el que se usa para comparar.** |
| `sha256.txt` | Huellas de los archivos anteriores. |
| `LEEME.txt` | Explicación del contenido de la carpeta. |
| `errores.txt` | Mensajes de error o aviso. Si está vacío, todo salió bien. |

Los archivos `.sql` empiezan con un encabezado que explica que contienen **solo estructura, sin datos**, para qué se generaron y a quién enviarlos. Así, si alguien los encuentra después en el servidor, sabe qué son y que pueden borrarse.

## 6. Qué devolver

- El archivo **`.tar.gz`** completo, uno por servidor.
- Si el script no pudo crear el `.tar.gz`, enviar la carpeta completa.
- Si algo falló, enviar de todos modos lo generado, incluido `errores.txt`.

## 7. Limpieza

Una vez enviada la salida, borrar del servidor:

```bash
rm -rf esquema_crm_*          # carpeta(s) generada(s) y sus .tar.gz
rm -f exportar_esquema_crm.sh # el script
```

Ajustar la ruta si el script se ejecutó desde otra carpeta o desde `/tmp`.

## 8. Alternativa manual (sin el script)

Si por alguna razón no se puede usar el script, estos dos comandos dan lo esencial. Reemplazar `USUARIO` (omitir `-u USUARIO -p` si se entra como `root` por socket con `sudo`).

**a) Versión y motor** (en la salida, `MariaDB` aparece en la versión si es MariaDB):

```bash
mysql -u USUARIO -p -e "SELECT VERSION(), @@version_comment, @@character_set_server, @@collation_server;"
```

**b) Esquema de `crm`, solo estructura:**

```bash
mysqldump -u USUARIO -p --no-data --routines --triggers --events \
  --skip-comments --skip-dump-date --skip-add-drop-table --skip-lock-tables \
  crm > esquema_crm_$(hostname).sql
```

En MariaDB 10.5 o posterior se pueden usar `mariadb` y `mariadb-dump` en lugar de `mysql` y `mysqldump`.
Enviar ambos resultados (la salida del paso a) copiada en un `.txt` y el archivo `.sql`).

## 9. Problemas frecuentes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| `no se encontró el cliente mysql/mariadb` | Falta el paquete cliente | Instalar `mariadb-client` o `mysql-client` |
| `no se pudo conectar al servidor` | Usuario o clave incorrectos, o el servicio no corre | Probar con `sudo` y Enter en el usuario, o revisar `errores.txt` |
| `la base de datos 'crm' no existe` | La base tiene otro nombre en ese servidor | Avisar a quien recibe la salida con el nombre real |
| `Modo de volcado` distinto de "completo" | El usuario no tiene permiso para rutinas o eventos | Repetir con `sudo` y usuario en blanco, o con un usuario administrador |
| `errores.txt` con "Access denied" | Permisos insuficientes | Igual que el caso anterior |
| No se puede escribir en la carpeta actual | Permisos de la carpeta | El script usa `/tmp` automáticamente |

## 10. Apéndice: cómo se comparan los resultados (para quien recibe los archivos)

1. Extraer cada `.tar.gz` en una carpeta por servidor.
2. **Comparación rápida:** si los archivos `*_normalizado.sql` de dos servidores tienen el mismo `sha256`, la estructura es idéntica (siempre que el "Modo de volcado" sea el mismo):

   ```bash
   sha256sum */esquema_crm_*_normalizado.sql
   ```

3. **Comparación detallada:** el encabezado de cada `.sql` contiene el nombre del script y el modo de exportación, no el servidor ni la fecha, así que `diff` solo marca diferencias reales de estructura:

   ```bash
   diff -u srv-pruebas/esquema_crm_*_normalizado.sql srv-prod/esquema_crm_*_normalizado.sql
   ```

4. Comparar también los `info_servidor.txt`: motor y versión (MySQL frente a MariaDB), charset/collation y `sql_mode` suelen ser la causa de diferencias entre servidores.
