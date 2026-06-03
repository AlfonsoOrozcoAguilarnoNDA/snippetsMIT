<?php
/**
 * Proyecto: Control de visitas de mascotas
 * Licencia: MIT
 * * Alfonso Orozco Aguilar
 * * Copyright (c) 2026 Gemini 3.5 Flash segun sitio web 
 * * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * https://vibecodingmexico.com/snippet-2-visor-imagenes/
 */
/*
Leer el articulo, no usar, es un trabajo incompleto. Gemini si esta degradado.
*/
// ============================================================================
// REQUERIMIENTO DE CONFIGURACIÓN Y CONEXIÓN
// ============================================================================
require_once 'config.php';

// Validar que la conexión $link exista y sea válida
if (!isset($link) || !($link instanceof mysqli)) {
    die("Error: La conexión de base de datos (\$link) no está disponible o no es un objeto MySQLi válido.");
}

// ============================================================================
// VERIFICACIÓN / CREACIÓN DE LA BASE DE DATOS (Estructura Solicitada)
// ============================================================================
$sql_tabla_animales = "
CREATE TABLE IF NOT EXISTS `animales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `especie` VARCHAR(50) NOT NULL,
    `raza` VARCHAR(100),
    `fecha_nacimiento` VARCHAR(20),
    `fecha_tipo` ENUM('exacta','estimada') DEFAULT 'estimada',
    `notas` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql_tabla_visitas = "
CREATE TABLE IF NOT EXISTS `visitas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `animal_id` INT NOT NULL,
    `tipo_visita` VARCHAR(80) NOT NULL,
    `fecha_visita` VARCHAR(20) NOT NULL,
    `costo` DECIMAL(10,2),
    `veterinario` VARCHAR(150),
    `comentarios` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`animal_id`) REFERENCES `animales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$link->query($sql_tabla_animales);
$link->query($sql_tabla_visitas);

// ============================================================================
// CONTROLADOR DE ACCIONES (LÓGICA DE PROCESAMIENTO)
// ============================================================================
$self = htmlspecialchars($_SERVER['PHP_SELF']);
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';

    // --- ACCIÓN: REGISTRAR ANIMAL ---
    if ($accion === 'registrar_animal') {
        $nombre = mysqli_real_escape_string($link, trim($_POST['nombre']));
        $especie = mysqli_real_escape_string($link, trim($_POST['especie']));
        $raza = mysqli_real_escape_string($link, trim($_POST['raza']));
        $fecha_nacimiento = mysqli_real_escape_string($link, trim($_POST['fecha_nacimiento']));
        $fecha_tipo = mysqli_real_escape_string($link, trim($_POST['fecha_tipo']));
        $notas = mysqli_real_escape_string($link, trim($_POST['notas']));

        if (!empty($nombre) && !empty($especie)) {
            $sql = "INSERT INTO animales (nombre, especie, raza, fecha_nacimiento, fecha_tipo, notas) 
                    VALUES ('$nombre', '$especie', '$raza', '$fecha_nacimiento', '$fecha_tipo', '$notas')";
            if ($link->query($sql)) {
                $msg = "Mascota registrada correctamente.";
                $msg_type = "success";
            } else {
                $msg = "Error al registrar la mascota: " . $link->error;
                $msg_type = "danger";
            }
        } else {
            $msg = "El nombre y la especie son campos obligatorios.";
            $msg_type = "warning";
        }
    }

    // --- ACCIÓN: REGISTRAR VISITA ---
    if ($accion === 'registrar_visita') {
        $animal_id = intval($_POST['animal_id']);
        $tipo_visita = mysqli_real_escape_string($link, trim($_POST['tipo_visita']));
        $fecha_visita = mysqli_real_escape_string($link, trim($_POST['fecha_visita']));
        $costo = !empty($_POST['costo']) ? floatval($_POST['costo']) : "NULL";
        $veterinario = mysqli_real_escape_string($link, trim($_POST['veterinario']));
        $comentarios = mysqli_real_escape_string($link, trim($_POST['comentarios']));

        if ($animal_id > 0 && !empty($tipo_visita) && !empty($fecha_visita)) {
            $sql = "INSERT INTO visitas (animal_id, tipo_visita, fecha_visita, costo, veterinario, comentarios) 
                    VALUES ($animal_id, '$tipo_visita', '$fecha_visita', $costo, '$veterinario', '$comentarios')";
            if ($link->query($sql)) {
                $msg = "Visita médica registrada correctamente.";
                $msg_type = "success";
            } else {
                $msg = "Error al registrar la visita: " . $link->error;
                $msg_type = "danger";
            }
        } else {
            $msg = "Seleccione una mascota, tipo de visita y fecha válida.";
            $msg_type = "warning";
        }
    }
}

// --- ACCIÓN: ELIMINAR REGISTROS (GET) ---
if (isset($_GET['eliminar_animal'])) {
    $id_del = intval($_GET['eliminar_animal']);
    if ($link->query("DELETE FROM animales WHERE id = $id_del")) {
        $msg = "Registro de la mascota y sus visitas eliminado.";
        $msg_type = "success";
    }
}

if (isset($_GET['eliminar_visita'])) {
    $id_del = intval($_GET['eliminar_visita']);
    if ($link->query("DELETE FROM visitas WHERE id = $id_del")) {
        $msg = "Registro de visita eliminado.";
        $msg_type = "success";
    }
}

// ============================================================================
// MANEJO DE VISTAS (NAVEGACIÓN)
// ============================================================================
$vista = isset($_GET['vista']) ? trim($_GET['vista']) : 'animales';

// Definición estricta de metadatos del modelo para Navbar/Footer
$metadata_ia = "Generado por Gemini 1.5 Pro el 3 de junio de 2026";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetHome — Control de Salud Animal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #EBEBEB; /* Gris neutro medio solicitado */
            padding-top: 75px; /* Evita solapamiento con Navbar */
            padding-bottom: 80px; /* Evita solapamiento con Footer */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .navbar-custom {
            background-color: #1e2d3b !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
        .footer-custom {
            background-color: #151f28;
            color: #b0bec5;
            font-size: 0.88rem;
            border-top: 2px solid #00bc8c;
        }
        .card {
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: none;
        }
        .card-header {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 1px solid #e9ecef;
        }
        .table-responsive {
            background: #ffffff;
            border-radius: 4px;
        }
        .badge-metadata {
            background-color: #2c3e50;
            color: #00bc8c;
            font-weight: bold;
            font-family: monospace;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-md navbar-dark navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand font-weight-bold" href="<?php echo $self; ?>?vista=animales">
                <i class="fas fa-paw text-warning mr-2"></i>VetHome
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarsExampleDefault">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item <?php echo ($vista === 'animales') ? 'active' : ''; ?>">
                        <a class="nav-link" href="<?php echo $self; ?>?vista=animales"><i class="fas fa-dog mr-1"></i> Mis Animales</a>
                    </li>
                    <li class="nav-item <?php echo ($vista === 'visitas') ? 'active' : ''; ?>">
                        <a class="nav-link" href="<?php echo $self; ?>?vista=visitas"><i class="fas fa-notes-medical mr-1"></i> Visitas</a>
                    </li>
                    <li class="nav-item <?php echo ($vista === 'acerca') ? 'active' : ''; ?>">
                        <a class="nav-link" href="<?php echo $self; ?>?vista=acerca"><i class="fas fa-info-circle mr-1"></i> Acerca de</a>
                    </li>
                </ul>
                <span class="navbar-text small d-none d-lg-inline">
                    <span class="badge badge-metadata p-2"><?php echo $metadata_ia; ?></span>
                </span>
            </div>
        </div>
    </nav>

    <main class="container">
        
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas <?php echo ($msg_type === 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php
        // ============================================================================
        // SECCIÓN: VISTA ANIMALES
        // ============================================================================
        if ($vista === 'animales'):
            // Obtener listado de animales
            $resultado_animales = $link->query("SELECT * FROM animales ORDER BY id DESC");
        ?>
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-plus mr-2 text-primary"></i>Alta de Mascota</div>
                        <div class="card-body">
                            <form action="<?php echo $self; ?>?vista=animales" method="POST">
                                <input type="hidden" name="accion" value="registrar_animal">
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Nombre *</label>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej. Rocko" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Especie *</label>
                                    <select name="especie" class="form-control" required>
                                        <option value="Perro">Perro</option>
                                        <option value="Gato">Gato</option>
                                        <option value="Ave">Ave</option>
                                        <option value="Conejo">Conejo</option>
                                        <option value="Tortuga">Tortuga</option>
                                        <option value="Pez">Pez</option>
                                        <option value="Hámster">Hámster</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Raza</label>
                                    <input type="text" name="raza" class="form-control" placeholder="Ej. Schnauzer, Mestizo">
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Fecha de Nacimiento</label>
                                    <input type="text" name="fecha_nacimiento" class="form-control" placeholder="2012 o 'adoptado en 2024'">
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Tipo de Fecha</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fecha_tipo" value="exacta" id="f_exacta">
                                        <label class="form-check-label" for="f_exacta">Exacta</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="fecha_tipo" value="estimada" id="f_estimada" checked>
                                        <label class="form-check-label" for="f_estimada">Estimada / Aproximada</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Notas de Salud / Observaciones</label>
                                    <textarea name="notas" class="form-control" rows="3" placeholder="Alergias, temperamento, señas particulares..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save mr-2"></i>Guardar Mascota</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-list mr-2 text-success"></i>Mascotas Registradas</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Especie / Raza</th>
                                            <th>Nacimiento</th>
                                            <th>Notas</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($resultado_animales && $resultado_animales->num_rows > 0): ?>
                                            <?php while ($row = $resultado_animales->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong><?php echo $row['id']; ?></strong></td>
                                                    <td><span class="text-dark font-weight-bold"><?php echo htmlspecialchars($row['nombre']); ?></span></td>
                                                    <td>
                                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($row['especie']); ?></span>
                                                        <small class="d-block text-muted"><?php echo htmlspecialchars($row['raza'] ?: 'No especificada'); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($row['fecha_nacimiento'] ?: 'No registrada'); ?>
                                                        <small class="d-block text-uppercase font-weight-bold text-muted" style="font-size:0.7rem;">
                                                            (<?php echo $row['fecha_tipo']; ?>)
                                                        </small>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo nl2br(htmlspecialchars($row['notas'] ?: 'Sin notas.')); ?></small></td>
                                                    <td class="text-center">
                                                        <a href="<?php echo $self; ?>?eliminar_animal=<?php echo $row['id']; ?>&vista=animales" 
                                                           class="btn btn-sm btn-outline-danger" 
                                                           onclick="return confirm('¿Está seguro de eliminar esta mascota? Se borrarán de forma permanente todas sus visitas médicas.');" 
                                                           title="Eliminar Mascota">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center p-4 text-muted">
                                                    <i class="fas fa-folder-open fa-2x d-block mb-2"></i>No hay mascotas dadas de alta en el sistema.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        // ============================================================================
        // SECCIÓN: VISTA VISITAS
        // ============================================================================
        elseif ($vista === 'visitas'):
            // Cargar Catálogo de Animales para el Select combo box
            $combo_animales = $link->query("SELECT id, nombre, especie FROM animales ORDER BY nombre ASC");
            
            // Query compleja para armar historial uniendo con datos del animal
            $sql_visitas = "
                SELECT v.*, a.nombre AS animal_nombre, a.especie AS animal_especie 
                FROM visitas v 
                INNER JOIN animales a ON v.animal_id = a.id 
                ORDER BY v.id DESC
            ";
            $resultado_visitas = $link->query($sql_visitas);
        ?>
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-file-medical mr-2 text-info"></i>Nueva Visita Médica</div>
                        <div class="card-body">
                            <form action="<?php echo $self; ?>?vista=visitas" method="POST">
                                <input type="hidden" name="accion" value="registrar_visita">
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Paciente (Mascota) *</label>
                                    <select name="animal_id" class="form-control" required>
                                        <option value="">-- Seleccionar Mascota --</option>
                                        <?php if ($combo_animales && $combo_animales->num_rows > 0): ?>
                                            <?php while ($an = $combo_animales->fetch_assoc()): ?>
                                                <option value="<?php echo $an['id']; ?>">
                                                    <?php echo htmlspecialchars($an['nombre']) . " (" . htmlspecialchars($an['especie']) . ")"; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Tipo de Visita *</label>
                                    <select name="tipo_visita" class="form-control" required>
                                        <option value="Consulta General">Consulta General</option>
                                        <option value="Vacuna Rabia">Vacuna Rabia</option>
                                        <option value="Vacuna Múltiple">Vacuna Múltiple</option>
                                        <option value="Desparasitación Interna">Desparasitación Interna</option>
                                        <option value="Desparasitación Externa">Desparasitación Externa</option>
                                        <option value="Cirugía">Cirugía</option>
                                        <option value="Urgencia">Urgencia</option>
                                        <option value="Control de Peso">Control de Peso</option>
                                        <option value="Análisis de Laboratorio">Análisis de Laboratorio</option>
                                        <option value="Otra">Otra</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Fecha de Visita *</label>
                                    <input type="text" name="fecha_visita" class="form-control" placeholder="Ej. 2026-06-03 o 'Hoy'" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Costo ($)</label>
                                    <input type="number" name="costo" step="0.01" min="0" class="form-control" placeholder="0.00">
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Médico Veterinario</label>
                                    <input type="text" name="veterinario" class="form-control" placeholder="Nombre del especialista">
                                </div>
                                
                                <div class="form-group">
                                    <label class="font-weight-bold">Comentarios / Diagnóstico</label>
                                    <textarea name="comentarios" class="form-control" rows="3" placeholder="Detalles de la consulta médica..."></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-info btn-block"><i class="fas fa-check mr-2"></i>Registrar Consulta</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header"><i class="fas fa-history mr-2 text-dark"></i>Historial de Eventos Médicos</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Paciente</th>
                                            <th>Detalles de Visita</th>
                                            <th>Fecha</th>
                                            <th>Costo</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($resultado_visitas && $resultado_visitas->num_rows > 0): ?>
                                            <?php while ($row = $resultado_visitas->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong><?php echo $row['id']; ?></strong></td>
                                                    <td>
                                                        <span class="text-dark font-weight-bold"><?php echo htmlspecialchars($row['animal_nombre']); ?></span>
                                                        <small class="d-block text-muted">(<?php echo htmlspecialchars($row['animal_especie']); ?>)</small>
                                                    </td>
                                                    <td>
                                                        <span class="text-info font-weight-bold"><?php echo htmlspecialchars($row['tipo_visita']); ?></span>
                                                        <small class="d-block text-dark mt-1"><strong>Vet:</strong> <?php echo htmlspecialchars($row['veterinario'] ?: 'No asignado'); ?></small>
                                                        <p class="mb-0 text-muted small mt-1" style="line-height: 1.2;"><?php echo nl2br(htmlspecialchars($row['comentarios'] ?: 'Sin observaciones.')); ?></p>
                                                    </td>
                                                    <td><span class="text-nowrap"><?php echo htmlspecialchars($row['fecha_visita']); ?></span></td>
                                                    <td>
                                                        <span class="text-success font-weight-bold">
                                                            <?php echo ($row['costo'] !== null) ? '$' . number_format($row['costo'], 2) : '—'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="<?php echo $self; ?>?eliminar_visita=<?php echo $row['id']; ?>&vista=visitas" 
                                                           class="btn btn-sm btn-outline-danger" 
                                                           onclick="return confirm('¿Eliminar de forma permanente el registro de esta visita?');" 
                                                           title="Eliminar Visita">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center p-4 text-muted">
                                                    <i class="fas fa-notes-medical fa-2x d-block mb-2"></i>No hay registros de consultas o visitas.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        // ============================================================================
        // SECCIÓN: VISTA ACERCA DE
        // ============================================================================
        elseif ($vista === 'acerca'):
        ?>
            <div class="card col-md-8 mx-auto p-4 shadow-sm">
                <div class="card-body text-center">
                    <h2 class="display-5 text-dark font-weight-bold"><i class="fas fa-clinic-medical text-primary mr-2"></i>Acerca de VetHome</h2>
                    <p class="lead text-secondary mt-3">
                        Una solución integrada en un solo módulo procedural para el control clínico de pequeñas especies y mascotas domésticas.
                    </p>
                    <hr class="my-4">
                    <div class="text-left bg-light p-4 rounded" style="border-left: 5px solid #00bc8c;">
                        <h5 class="font-weight-bold text-dark">Información del Sistema de Auditoría:</h5>
                        <ul class="list-unstyled mb-0 mt-2">
                            <li class="mb-2"><strong>Modelo Generador:</strong> Gemini 1.5 Pro</li>
                            <li class="mb-2"><strong>Versión de Producción:</strong> v1.5</li>
                            <li class="mb-2"><strong>Fecha de Emisión:</strong> 3 de junio de 2026</li>
                            <li class="mb-2"><strong>Arquitectura Base:</strong> PHP 8.x (Estructurado) + Extensión MySQLi Nativa</li>
                        </ul>
                    </div>
                    <p class="mt-4 text-muted small">
                        Este software se proporciona bajo los términos de la Licencia MIT. Libre para su uso, modificación y distribución.
                    </p>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <footer class="footer-custom fixed-bottom py-3">
        <div class="container text-center text-md-left">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-left">
                    <span class="font-weight-bold">VetHome</span> — Control de salud para tus mascotas
                    <span class="mx-2">|</span> 
                    <a href="https://opensource.org/licenses/MIT" target="_blank" class="text-warning text-decoration-none">Licencia MIT</a>
                </div>
                <div class="col-md-6 text-center text-md-right mt-2 mt-md-0">
                    <small class="text-muted" style="font-family: monospace; letter-spacing: 0.5px;">
                        <?php echo $metadata_ia; ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
