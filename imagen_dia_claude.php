<?php
/**
 * Script PHP para gestión de imágenes generadas por IA
 * Versión: 3.1
 * Modelo: Mistral AI (versión 2026)
 * Coautor: Claude Sonnet 4.6 (correcciones: filtro engine, validación 2MB, <?= → <?php)
 * Licencia: MIT
 *
 * Copyright (c) 2026 Alfonso Orozco Aguilar
 *
 * Se otorga permiso, de forma gratuita, a cualquier persona que obtenga una copia
 * de este software y los archivos de documentación asociados (el "Software"), para
 * tratar en el Software sin restricción, incluyendo sin limitación los derechos
 * de usar, copiar, modificar, fusionar, publicar, distribuir, sublicenciar, y/o
 * vender copias del Software, y para permitir a las personas a las que se les
 * proporcione el Software a hacerlo, sujeto a las siguientes condiciones:
 *
 * El aviso de copyright anterior y este aviso de permiso se incluirán en todas
 * las copias o partes sustanciales del Software.
 *
 * EL SOFTWARE SE PROPORCIONA "TAL CUAL", SIN GARANTÍA DE NINGÚN TIPO, EXPRESA O
 * IMPLÍCITA, INCLUYENDO PERO NO LIMITADO A LAS GARANTÍAS DE COMERCIABILIDAD,
 * IDONEIDAD PARA UN PROPÓSITO PARTICULAR Y NO INFRACCIÓN. EN NINGÚN CASO LOS
 * AUTORES O TITULARES DEL COPYRIGHT SERÁN RESPONSABLES DE NINGUNA RECLAMACIÓN,
 * DAÑOS U OTRAS RESPONSABILIDADES, YA SEA EN UNA ACCIÓN DE CONTRATO, AGRAVIO O
 * CUALQUIER OTRO MOTIVO, DERIVADAS DE, FUERA DE O EN CONEXIÓN CON EL SOFTWARE
 * O EL USO U OTROS TRATOS EN EL SOFTWARE.
 */

/*
// https://vibecodingmexico.com/snippet-1-ensayo-humano-vs-imagen/
-- Tabla para almacenar imágenes generadas por IA
CREATE TABLE imagenes_ia (
  id int(11) NOT NULL AUTO_INCREMENT,
  fecha date NOT NULL,
  engine varchar(20) NOT NULL,
  imagen_blob longblob NOT NULL,
  consecutivoengine int(11) NOT NULL,
  peso_bytes int(11) NOT NULL,
  checksum_sha256 char(64) NOT NULL,
  comentario text,
  nombre_archivo varchar(255) DEFAULT NULL,
  horadesubida datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fecha_engine (fecha,engine),
  KEY idx_engine (engine),
  KEY idx_consecutivo (consecutivoengine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

// Incluir configuración de la base de datos
include 'config.php';

// Configuración de seguridad
$ip_autorizada = '192.168.1.100'; // Cambiar por la IP autorizada
$master_password = 'TU_CLAVE_AQUI'; // Cambiar por la clave maestra

// Tamaño máximo de imagen: 2MB
define('MAX_IMAGEN_BYTES', 2 * 1024 * 1024);

// Verificar IP autorizada
if ($_SERVER['REMOTE_ADDR'] !== $ip_autorizada) {
    die("<div class='alert alert-danger'>Acceso no autorizado. IP no permitida.</div>");
}

// Función para validar contraseña
function validarPassword($input_password) {
    global $master_password;
    return hash_equals($master_password, $input_password);
}

// Función para generar hash SHA256 de un archivo
function generarChecksum($archivo_tmp) {
    return hash_file('sha256', $archivo_tmp);
}

// Función para obtener el consecutivo por engine
function obtenerConsecutivo($link, $engine) {
    $query = "SELECT MAX(consecutivoengine) AS max FROM imagenes_ia WHERE engine = ?";
    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "s", $engine);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return ($row['max'] ?? 0) + 1;
}

// Función para validar unicidad de Fecha + Engine
function validarUnicidad($link, $fecha, $engine) {
    $query = "SELECT COUNT(*) AS count FROM imagenes_ia WHERE fecha = ? AND engine = ?";
    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "ss", $fecha, $engine);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] == 0;
}

// Procesar formulario de alta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alta'])) {
    if (!validarPassword($_POST['password'])) {
        die("<div class='alert alert-danger'>Contraseña incorrecta. Acceso denegado.</div>");
    }

    $fecha = $_POST['fecha'];
    $engine = $_POST['engine'];
    $comentario = trim($_POST['comentario']);
    $archivo = $_FILES['imagen'];

    // Validar unicidad
    if (!validarUnicidad($link, $fecha, $engine)) {
        die("<div class='alert alert-danger'>Ya existe un registro para esta fecha y motor.</div>");
    }

    // Procesar archivo
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        die("<div class='alert alert-danger'>Error al subir el archivo.</div>");
    }

    // Validar tamaño máximo 2MB (corrección Claude Sonnet 4.6)
    if ($archivo['size'] > MAX_IMAGEN_BYTES) {
        die("<div class='alert alert-danger'>La imagen excede el límite de 2MB. Tamaño recibido: " . round($archivo['size'] / 1024 / 1024, 2) . "MB</div>");
    }

    $nombre_archivo = basename($archivo['name']);
    $archivo_tmp = $archivo['tmp_name'];
    $peso_bytes = $archivo['size'];
    $checksum = generarChecksum($archivo_tmp);
    $imagen_blob = file_get_contents($archivo_tmp);
    $consecutivo = obtenerConsecutivo($link, $engine);

    // Insertar en BD con send_long_data para LONGBLOB (corrección Claude Sonnet 4.6)
    $query = "INSERT INTO imagenes_ia (fecha, engine, imagen_blob, consecutivoengine, peso_bytes, checksum_sha256, comentario, nombre_archivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($link, $query);
    $null = NULL;
    mysqli_stmt_bind_param($stmt, "ssbissss", $fecha, $engine, $null, $consecutivo, $peso_bytes, $checksum, $comentario, $nombre_archivo);
    mysqli_stmt_send_long_data($stmt, 2, $imagen_blob);
    $resultado = mysqli_stmt_execute($stmt);

    if ($resultado) {
        echo "<div class='alert alert-success'>Registro guardado con éxito. Consecutivo: " . htmlspecialchars($consecutivo) . "</div>";
    } else {
        echo "<div class='alert alert-danger'>Error al guardar: " . mysqli_error($link) . "</div>";
    }
}

// Procesar borrado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar'])) {
    if (!validarPassword($_POST['password_borrar'])) {
        die("<div class='alert alert-danger'>Contraseña incorrecta. Borrado denegado.</div>");
    }

    $id = (int)$_POST['id'];
    $query = "DELETE FROM imagenes_ia WHERE id = ?";
    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    $resultado = mysqli_stmt_execute($stmt);

    if ($resultado) {
        echo "<div class='alert alert-success'>Registro eliminado con éxito.</div>";
    } else {
        echo "<div class='alert alert-danger'>Error al eliminar: " . mysqli_error($link) . "</div>";
    }
}

// Consulta de registros con filtros (corrección Claude Sonnet 4.6: filtro vacío = todos)
$engine_filtro = isset($_GET['engine']) && $_GET['engine'] !== '' ? $_GET['engine'] : '%';
$texto_filtro  = isset($_GET['texto'])  && $_GET['texto']  !== '' ? '%' . $_GET['texto'] . '%' : '%';

$query = "SELECT * FROM imagenes_ia WHERE engine LIKE ? AND comentario LIKE ? ORDER BY consecutivoengine DESC";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "ss", $engine_filtro, $texto_filtro);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// Variable para mostrar el valor seleccionado en el filtro del HTML
$engine_get = isset($_GET['engine']) ? $_GET['engine'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Imágenes IA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(to right, #4a6fa5, #166088);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-custom {
            background: linear-gradient(to right, #4a6fa5, #166088);
            color: white;
            border: none;
        }
        .btn-custom:hover {
            background: linear-gradient(to right, #166088, #4a6fa5);
        }
        .img-thumbnail {
            max-height: 150px;
            border: 2px solid #dee2e6;
        }
        .filtros {
            background: rgba(255, 255, 255, 0.8);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h2 class="text-center mb-4">Gestión de Imágenes Generadas por IA</h2>

        <!-- Filtros -->
        <div class="filtros">
            <form method="GET" class="form-inline">
                <div class="form-group mr-3">
                    <label for="engine_filtro" class="mr-2">Motor:</label>
                    <select name="engine" id="engine_filtro" class="form-control">
                        <option value="">Todos</option>
                        <option value="Grok" <?php if ($engine_get === 'Grok') echo 'selected'; ?>>Grok</option>
                        <option value="Gemini" <?php if ($engine_get === 'Gemini') echo 'selected'; ?>>Gemini</option>
                        <option value="Leonardo" <?php if ($engine_get === 'Leonardo') echo 'selected'; ?>>Leonardo</option>
						<option value="Venice.Ai" <?php if ($engine_get === 'Venice.Ai') echo 'selected'; ?>>Venice.Ai</option>
						<option value="Runway" <?php if ($engine_get === 'Runway') echo 'selected'; ?>>Runway</option>
                        <option value="https://www.craiyon.com/" <?php if ($engine_get === 'https://www.craiyon.com/') echo 'selected'; ?>>https://www.craiyon.com/</option>
                    </select>
                </div>
                <div class="form-group mr-3">
                    <label for="texto" class="mr-2">Buscar:</label>
                    <input type="text" name="texto" id="texto" class="form-control" placeholder="Comentarios..." value="<?php echo htmlspecialchars($_GET['texto'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-custom">Filtrar</button>
            </form>
        </div>

        <!-- Formulario de Alta -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Agregar Nueva Imagen</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="fecha">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="engine">Motor de IA</label>
                        <select name="engine" id="engine" class="form-control" required>
                            <option value="">Seleccionar...</option>
                            <option value="Grok">Grok</option>
                            <option value="Gemini">Gemini</option>
                            <option value="Leonardo">Leonardo</option>                            
							<option value="Venice.Ai">Venice.Ai</option>                            
							<option value="Runway">Runway</option>
							<option value="https://www.craiyon.com/">craiyon.com</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="imagen">Imagen (máximo 2MB)</label>
                        <input type="file" name="imagen" id="imagen" class="form-control-file" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label for="comentario">Comentarios</label>
                        <textarea name="comentario" id="comentario" class="form-control" rows="3" placeholder="Descripción de la pose, detalles técnicos, etc."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña Maestra</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" name="alta" class="btn btn-custom">Guardar Imagen</button>
                </form>
            </div>
        </div>

        <!-- Galería de Imágenes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Galería de Imágenes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>ID</th>
                                <th>Motor</th>
                                <th>Fecha</th>
                                <th>Consecutivo</th>
                                <th>Imagen</th>
                                <th>Comentarios</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($resultado)): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['engine']); ?></td>
                                <td><?php echo $row['fecha']; ?></td>
                                <td><?php echo $row['consecutivoengine']; ?></td>
                                <td>
                                    <img src="data:image/jpeg;base64,<?php echo base64_encode($row['imagen_blob']); ?>" alt="Imagen IA" class="img-thumbnail">
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($row['nombre_archivo']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['comentario']); ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este registro?')">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <div class="form-group">
                                            <input type="password" name="password_borrar" class="form-control form-control-sm" placeholder="Contraseña" required>
                                        </div>
                                        <button type="submit" name="borrar" class="btn btn-danger btn-sm">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
