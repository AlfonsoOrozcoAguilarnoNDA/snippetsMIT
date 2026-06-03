<?php
/**
 * VetHome — Control de salud para tus mascotas
 * 
 * Licencia: MIT
 * Generado por Kimi K2.6 v2.6 el 2026-06-03
 * 
 * @license MIT
 * @author Generado por Kimi K2.6 v2.6 el 2026-06-03
 */
/**
 * Proyecto: Control de salud mascotas
 * Licencia: MIT
 * * Alfonso Orozco Aguilar
 * * Copyright (c) 2026 
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
require_once 'config.php';

// ============================================================
// CREAR TABLAS SI NO EXISTEN
// ============================================================

$link->query("
CREATE TABLE IF NOT EXISTS animales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    especie VARCHAR(50) NOT NULL,
    raza VARCHAR(100),
    fecha_nacimiento VARCHAR(20),
    fecha_tipo ENUM('exacta','estimada') DEFAULT 'estimada',
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$link->query("
CREATE TABLE IF NOT EXISTS visitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    tipo_visita VARCHAR(80) NOT NULL,
    fecha_visita VARCHAR(20) NOT NULL,
    costo DECIMAL(10,2),
    veterinario VARCHAR(150),
    comentarios TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// ============================================================
// CONFIGURACIÓN
// ============================================================

$self = $_SERVER['PHP_SELF'];
$action = $_GET['action'] ?? 'animales';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$especies = ['Perro', 'Gato', 'Ave', 'Conejo', 'Tortuga', 'Pez', 'Hámster', 'Otro'];
$tipos_visita = ['Vacuna Rabia', 'Vacuna Múltiple', 'Desparasitación Interna', 'Desparasitación Externa', 'Consulta General', 'Cirugía', 'Urgencia', 'Control de Peso', 'Análisis de Laboratorio', 'Otra'];

// ============================================================
// PROCESAR FORMULARIOS (POST)
// ============================================================

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- GUARDAR ANIMAL ---
    if (isset($_POST['guardar_animal'])) {
        $nombre = trim($link->real_escape_string($_POST['nombre'] ?? ''));
        $especie = trim($link->real_escape_string($_POST['especie'] ?? ''));
        $raza = trim($link->real_escape_string($_POST['raza'] ?? ''));
        $fecha_nacimiento = trim($link->real_escape_string($_POST['fecha_nacimiento'] ?? ''));
        $fecha_tipo = trim($link->real_escape_string($_POST['fecha_tipo'] ?? 'estimada'));
        $notas = trim($link->real_escape_string($_POST['notas'] ?? ''));
        
        if (empty($nombre) || empty($especie)) {
            $msg = 'El nombre y la especie son obligatorios.';
            $msg_type = 'danger';
        } else {
            if (!empty($_POST['animal_id'])) {
                $aid = (int)$_POST['animal_id'];
                $stmt = $link->prepare("UPDATE animales SET nombre=?, especie=?, raza=?, fecha_nacimiento=?, fecha_tipo=?, notas=? WHERE id=?");
                $stmt->bind_param('ssssssi', $nombre, $especie, $raza, $fecha_nacimiento, $fecha_tipo, $notas, $aid);
                $stmt->execute();
                $msg = 'Animal actualizado correctamente.';
            } else {
                $stmt = $link->prepare("INSERT INTO animales (nombre, especie, raza, fecha_nacimiento, fecha_tipo, notas) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('ssssss', $nombre, $especie, $raza, $fecha_nacimiento, $fecha_tipo, $notas);
                $stmt->execute();
                $msg = 'Animal registrado correctamente.';
            }
            $msg_type = 'success';
        }
    }
    
    // --- ELIMINAR ANIMAL ---
    if (isset($_POST['eliminar_animal'])) {
        $aid = (int)$_POST['animal_id'];
        $link->query("DELETE FROM animales WHERE id = $aid");
        $msg = 'Animal eliminado correctamente.';
        $msg_type = 'success';
    }
    
    // --- GUARDAR VISITA ---
    if (isset($_POST['guardar_visita'])) {
        $animal_id = (int)$_POST['animal_id'];
        $tipo_visita = trim($link->real_escape_string($_POST['tipo_visita'] ?? ''));
        $fecha_visita = trim($link->real_escape_string($_POST['fecha_visita'] ?? ''));
        $costo = !empty($_POST['costo']) ? (float)$_POST['costo'] : null;
        $veterinario = trim($link->real_escape_string($_POST['veterinario'] ?? ''));
        $comentarios = trim($link->real_escape_string($_POST['comentarios'] ?? ''));
        
        if (empty($animal_id) || empty($tipo_visita) || empty($fecha_visita)) {
            $msg = 'El animal, tipo de visita y fecha son obligatorios.';
            $msg_type = 'danger';
        } else {
            if (!empty($_POST['visita_id'])) {
                $vid = (int)$_POST['visita_id'];
                $stmt = $link->prepare("UPDATE visitas SET animal_id=?, tipo_visita=?, fecha_visita=?, costo=?, veterinario=?, comentarios=? WHERE id=?");
                $stmt->bind_param('issdssi', $animal_id, $tipo_visita, $fecha_visita, $costo, $veterinario, $comentarios, $vid);
                $stmt->execute();
                $msg = 'Visita actualizada correctamente.';
            } else {
                $stmt = $link->prepare("INSERT INTO visitas (animal_id, tipo_visita, fecha_visita, costo, veterinario, comentarios) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('issdss', $animal_id, $tipo_visita, $fecha_visita, $costo, $veterinario, $comentarios);
                $stmt->execute();
                $msg = 'Visita registrada correctamente.';
            }
            $msg_type = 'success';
        }
    }
    
    // --- ELIMINAR VISITA ---
    if (isset($_POST['eliminar_visita'])) {
        $vid = (int)$_POST['visita_id'];
        $link->query("DELETE FROM visitas WHERE id = $vid");
        $msg = 'Visita eliminada correctamente.';
        $msg_type = 'success';
    }
}

// ============================================================
// OBTENER DATOS
// ============================================================

$animales = [];
$res = $link->query("SELECT * FROM animales ORDER BY nombre");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $animales[] = $row;
    }
}

$visitas = [];
$res = $link->query("
    SELECT v.*, a.nombre as animal_nombre, a.especie 
    FROM visitas v 
    JOIN animales a ON v.animal_id = a.id 
    ORDER BY v.fecha_visita DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $visitas[] = $row;
    }
}

$animal_edit = null;
$visita_edit = null;

if ($action === 'editar_animal' && $id > 0) {
    $res = $link->query("SELECT * FROM animales WHERE id = $id");
    if ($res) $animal_edit = $res->fetch_assoc();
}

if ($action === 'editar_visita' && $id > 0) {
    $res = $link->query("SELECT * FROM visitas WHERE id = $id");
    if ($res) $visita_edit = $res->fetch_assoc();
}

// ============================================================
// HTML
// ============================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetHome — Control de salud para tus mascotas</title>
    
    <!-- Bootstrap 4.6.x -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">
    
    <style>
        body {
            background-color: #EBEBEB;
            padding-top: 70px;
            padding-bottom: 60px;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
        }
        .nav-link {
            font-weight: 500;
        }
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-radius: 8px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
            border-radius: 8px 8px 0 0 !important;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #555;
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .badge-especie {
            font-size: 0.85rem;
            padding: 0.4em 0.8em;
        }
        .footer-fixed {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #343a40;
            color: #fff;
            padding: 12px 0;
            font-size: 0.85rem;
            z-index: 1030;
        }
        .footer-fixed a {
            color: #adb5bd;
        }
        .footer-fixed a:hover {
            color: #fff;
            text-decoration: none;
        }
        .ia-badge {
            font-size: 0.75rem;
            color: #adb5bd;
        }
        .sticky-top-custom {
            position: sticky;
            top: 0;
            z-index: 1020;
        }
        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.15);
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #888;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top-custom">
        <div class="container">
            <a class="navbar-brand" href="<?= $self ?>">
                <i class="fas fa-paw mr-2"></i>VetHome
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item <?= $action === 'animales' || $action === 'editar_animal' || $action === 'nuevo_animal' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= $self ?>?action=animales"><i class="fas fa-dog mr-1"></i> Mis Animales</a>
                    </li>
                    <li class="nav-item <?= $action === 'visitas' || $action === 'editar_visita' || $action === 'nueva_visita' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= $self ?>?action=visitas"><i class="fas fa-stethoscope mr-1"></i> Visitas</a>
                    </li>
                    <li class="nav-item <?= $action === 'acerca' ? 'active' : '' ?>">
                        <a class="nav-link" href="<?= $self ?>?action=acerca"><i class="fas fa-info-circle mr-1"></i> Acerca de</a>
                    </li>
                </ul>
                <span class="navbar-text ia-badge">
                    <i class="fas fa-robot mr-1"></i>Generado por Kimi K2.6 v2.6 el 2026-06-03
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-5">

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                <i class="fas <?= $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-2"></i>
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php
        // ============================================================
        // SECCIÓN: MIS ANIMALES
        // ============================================================
        if ($action === 'animales' || $action === 'editar_animal' || $action === 'nuevo_animal'):
        ?>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas <?= $animal_edit ? 'fa-edit' : 'fa-plus-circle' ?> mr-2"></i>
                            <?= $animal_edit ? 'Editar Animal' : 'Nuevo Animal' ?>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="<?= $self ?>?action=animales">
                                <?php if ($animal_edit): ?>
                                    <input type="hidden" name="animal_id" value="<?= $animal_edit['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-tag mr-1 text-muted"></i> Nombre <span class="text-danger">*</span></label>
                                    <input type="text" name="nombre" class="form-control" required 
                                           value="<?= $animal_edit ? htmlspecialchars($animal_edit['nombre']) : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-paw mr-1 text-muted"></i> Especie <span class="text-danger">*</span></label>
                                    <select name="especie" class="form-control" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($especies as $esp): ?>
                                            <option value="<?= $esp ?>" <?= ($animal_edit && $animal_edit['especie'] === $esp) ? 'selected' : '' ?>>
                                                <?= $esp ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-dna mr-1 text-muted"></i> Raza</label>
                                    <input type="text" name="raza" class="form-control" 
                                           value="<?= $animal_edit ? htmlspecialchars($animal_edit['raza']) : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-birthday-cake mr-1 text-muted"></i> Fecha de Nacimiento / Edad</label>
                                    <input type="text" name="fecha_nacimiento" class="form-control" 
                                           placeholder="Ej: 2020-05-15 o 'adoptado 2024 aprox 1 año'"
                                           value="<?= $animal_edit ? htmlspecialchars($animal_edit['fecha_nacimiento']) : '' ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-question-circle mr-1 text-muted"></i> Tipo de Fecha</label>
                                    <select name="fecha_tipo" class="form-control">
                                        <option value="estimada" <?= ($animal_edit && $animal_edit['fecha_tipo'] === 'estimada') ? 'selected' : '' ?>>Estimada</option>
                                        <option value="exacta" <?= ($animal_edit && $animal_edit['fecha_tipo'] === 'exacta') ? 'selected' : '' ?>>Exacta</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-sticky-note mr-1 text-muted"></i> Notas</label>
                                    <textarea name="notas" class="form-control" rows="3"><?= $animal_edit ? htmlspecialchars($animal_edit['notas']) : '' ?></textarea>
                                </div>
                                
                                <button type="submit" name="guardar_animal" class="btn btn-primary btn-block">
                                    <i class="fas fa-save mr-2"></i><?= $animal_edit ? 'Actualizar' : 'Guardar' ?> Animal
                                </button>
                                
                                <?php if ($animal_edit): ?>
                                    <a href="<?= $self ?>?action=animales" class="btn btn-outline-secondary btn-block mt-2">
                                        <i class="fas fa-times mr-2"></i>Cancelar
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list mr-2"></i>Mis Animales</span>
                            <span class="badge badge-primary badge-pill"><?= count($animales) ?> registrados</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($animales)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-paw"></i>
                                    <h5>No hay animales registrados</h5>
                                    <p>Usa el formulario de la izquierda para agregar tu primera mascota.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Especie</th>
                                                <th>Raza</th>
                                                <th>Fecha/Edad</th>
                                                <th>Notas</th>
                                                <th class="text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($animales as $a): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($a['nombre']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info badge-especie">
                                                            <i class="fas fa-paw mr-1"></i><?= $a['especie'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($a['raza'] ?: '—') ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($a['fecha_nacimiento'] ?: '—') ?>
                                                        <small class="d-block text-muted"><?= $a['fecha_tipo'] ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($a['notas']): ?>
                                                            <small class="text-muted" title="<?= htmlspecialchars($a['notas']) ?>">
                                                                <?= htmlspecialchars(substr($a['notas'], 0, 50)) ?><?= strlen($a['notas']) > 50 ? '...' : '' ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-right">
                                                        <a href="<?= $self ?>?action=editar_animal&id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" action="<?= $self ?>?action=animales" class="d-inline" onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars($a['nombre']) ?>? Se eliminarán también todas sus visitas.');">
                                                            <input type="hidden" name="animal_id" value="<?= $a['id'] ?>">
                                                            <button type="submit" name="eliminar_animal" class="btn btn-sm btn-outline-danger btn-action" title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        // ============================================================
        // SECCIÓN: VISITAS
        // ============================================================
        elseif ($action === 'visitas' || $action === 'editar_visita' || $action === 'nueva_visita'):
        ?>

            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas <?= $visita_edit ? 'fa-edit' : 'fa-plus-circle' ?> mr-2"></i>
                            <?= $visita_edit ? 'Editar Visita' : 'Nueva Visita' ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($animales)): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Primero debes registrar al menos un animal.
                                </div>
                                <a href="<?= $self ?>?action=animales" class="btn btn-primary btn-block">
                                    <i class="fas fa-arrow-right mr-2"></i>Ir a Animales
                                </a>
                            <?php else: ?>
                                <form method="POST" action="<?= $self ?>?action=visitas">
                                    <?php if ($visita_edit): ?>
                                        <input type="hidden" name="visita_id" value="<?= $visita_edit['id'] ?>">
                                    <?php endif; ?>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-dog mr-1 text-muted"></i> Animal <span class="text-danger">*</span></label>
                                        <select name="animal_id" class="form-control" required>
                                            <option value="">Seleccionar animal...</option>
                                            <?php foreach ($animales as $a): ?>
                                                <option value="<?= $a['id'] ?>" <?= ($visita_edit && $visita_edit['animal_id'] == $a['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($a['nombre']) ?> (<?= $a['especie'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-clipboard-list mr-1 text-muted"></i> Tipo de Visita <span class="text-danger">*</span></label>
                                        <select name="tipo_visita" class="form-control" required>
                                            <option value="">Seleccionar...</option>
                                            <?php foreach ($tipos_visita as $tv): ?>
                                                <option value="<?= $tv ?>" <?= ($visita_edit && $visita_edit['tipo_visita'] === $tv) ? 'selected' : '' ?>>
                                                    <?= $tv ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-alt mr-1 text-muted"></i> Fecha de Visita <span class="text-danger">*</span></label>
                                        <input type="text" name="fecha_visita" class="form-control" required
                                               placeholder="Ej: 2024-03-15 o 'marzo 2024'"
                                               value="<?= $visita_edit ? htmlspecialchars($visita_edit['fecha_visita']) : '' ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-dollar-sign mr-1 text-muted"></i> Costo</label>
                                        <input type="number" name="costo" class="form-control" step="0.01" min="0"
                                               value="<?= $visita_edit ? $visita_edit['costo'] : '' ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-user-md mr-1 text-muted"></i> Veterinario</label>
                                        <input type="text" name="veterinario" class="form-control"
                                               value="<?= $visita_edit ? htmlspecialchars($visita_edit['veterinario']) : '' ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-comment mr-1 text-muted"></i> Comentarios</label>
                                        <textarea name="comentarios" class="form-control" rows="3"><?= $visita_edit ? htmlspecialchars($visita_edit['comentarios']) : '' ?></textarea>
                                    </div>
                                    
                                    <button type="submit" name="guardar_visita" class="btn btn-primary btn-block">
                                        <i class="fas fa-save mr-2"></i><?= $visita_edit ? 'Actualizar' : 'Guardar' ?> Visita
                                    </button>
                                    
                                    <?php if ($visita_edit): ?>
                                        <a href="<?= $self ?>?action=visitas" class="btn btn-outline-secondary btn-block mt-2">
                                            <i class="fas fa-times mr-2"></i>Cancelar
                                        </a>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list mr-2"></i>Historial de Visitas</span>
                            <span class="badge badge-primary badge-pill"><?= count($visitas) ?> registradas</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($visitas)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-stethoscope"></i>
                                    <h5>No hay visitas registradas</h5>
                                    <p>Usa el formulario de la izquierda para registrar una nueva visita veterinaria.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Animal</th>
                                                <th>Tipo</th>
                                                <th>Costo</th>
                                                <th>Veterinario</th>
                                                <th class="text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($visitas as $v): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($v['fecha_visita']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info badge-especie">
                                                            <i class="fas fa-paw mr-1"></i><?= htmlspecialchars($v['animal_nombre']) ?>
                                                        </span>
                                                        <small class="d-block text-muted"><?= $v['especie'] ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-secondary">
                                                            <?= $v['tipo_visita'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($v['costo']): ?>
                                                            <strong>$<?= number_format($v['costo'], 2) ?></strong>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($v['veterinario'] ?: '—') ?>
                                                    </td>
                                                    <td class="text-right">
                                                        <a href="<?= $self ?>?action=editar_visita&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" action="<?= $self ?>?action=visitas" class="d-inline" onsubmit="return confirm('¿Eliminar esta visita?');">
                                                            <input type="hidden" name="visita_id" value="<?= $v['id'] ?>">
                                                            <button type="submit" name="eliminar_visita" class="btn btn-sm btn-outline-danger btn-action" title="Eliminar">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        // ============================================================
        // SECCIÓN: ACERCA DE
        // ============================================================
        elseif ($action === 'acerca'):
        ?>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header text-center">
                            <i class="fas fa-info-circle mr-2"></i>Acerca de VetHome
                        </div>
                        <div class="card-body text-center">
                            <i class="fas fa-paw fa-4x text-primary mb-4"></i>
                            <h3>VetHome</h3>
                            <p class="lead text-muted">Control de salud para tus mascotas</p>
                            
                            <hr class="my-4">
                            
                            <div class="row text-left mt-4">
                                <div class="col-md-6">
                                    <h5><i class="fas fa-check-circle text-success mr-2"></i>Características</h5>
                                    <ul class="list-unstyled text-muted">
                                        <li><i class="fas fa-angle-right mr-2"></i>Registro de animales con datos flexibles</li>
                                        <li><i class="fas fa-angle-right mr-2"></i>Historial de visitas veterinarias</li>
                                        <li><i class="fas fa-angle-right mr-2"></i>Control de costos y tratamientos</li>
                                        <li><i class="fas fa-angle-right mr-2"></i>Interfaz responsive con Bootstrap 4</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5><i class="fas fa-code text-primary mr-2"></i>Tecnología</h5>
                                    <ul class="list-unstyled text-muted">
                                        <li><i class="fas fa-angle-right mr-2"></i>PHP 8.x procedural</li>
                                        <li><i class="fas fa-angle-right mr-2"></i>MySQLi nativo</li>
                                        <li><i class="fas fa-angle-right mr-2"></i>Bootstrap 4.6.x</li>
                                        <li><i class="fas fa-angle-right mr-2"></i>Font Awesome 5.15.4</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="alert alert-light border">
                                <p class="mb-1"><strong>Licencia:</strong> MIT</p>
                                <p class="mb-0 text-muted">Software libre. Puedes usar, modificar y distribuir este código.</p>
                            </div>
                            
                            <p class="text-muted mt-3">
                                <small>
                                    <i class="fas fa-robot mr-1"></i>
                                    Generado por Kimi K2.6 v2.6 el 2026-06-03
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- FOOTER FIJO -->
    <footer class="footer-fixed">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-left">
                    <i class="fas fa-paw mr-2"></i>
                    <strong>VetHome</strong> — Control de salud para tus mascotas
                </div>
                <div class="col-md-6 text-center text-md-right">
                    <span class="mr-3">Licencia MIT</span>
                    <span class="ia-badge">
                        <i class="fas fa-robot mr-1"></i>Generado por Kimi K2.6 v2.6 el 2026-06-03
                    </span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>
</html>
