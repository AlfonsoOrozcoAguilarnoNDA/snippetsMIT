<?php
/**
 * Script PHP para gestión de Arcivos Recursivos
 * Versión: 1.01
 * Modelo: Kimi 2.5 y Gemini (2 columnas nuevas) Claude Sonnet 4.6 Integró los dos
 * Licencia: MIT
 * Razonamiento : https://vibecodingmexico.com/recursivo/
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
/**
 * Analizador de Archivos PHP Recursivo - Fusión v2
 * Base: Kimi K2.5 | Columnas extra: Gemini 3 Flash
 * Columnas: #, Archivo, Líneas, UTF-8, BOM, Licencia, Recursivo, Font Awesome, Integrity (SRI)
 */

// ── Funciones originales (Script 1) ────────────────────────────────────────

function tieneBOM($archivo) {
    $contenido = file_get_contents($archivo, false, null, 0, 3);
    return ($contenido === "\xEF\xBB\xBF");
}

function esUTF8($archivo) {
    $contenido = file_get_contents($archivo);
    return mb_check_encoding($contenido, 'UTF-8');
}

function contarLineasCodigo($archivo) {
    $lineas = file($archivo);
    $contador = 0;
    foreach ($lineas as $linea) {
        $lineaTrim = trim($linea);
        if (!empty($lineaTrim) && !preg_match('/^(\/\/|#|\/\*|\*)/', $lineaTrim)) {
            $contador++;
        }
    }
    return $contador;
}

function mencionaLicencia($archivo) {
    $contenido = file_get_contents($archivo);
    return (stripos($contenido, 'licencia') !== false ||
            stripos($contenido, 'license') !== false);
}

function esRecursivo($archivo, $nombreArchivo) {
    $contenido = file_get_contents($archivo);
    $nombreBase = basename($nombreArchivo);
    return (strpos($contenido, $nombreBase) !== false);
}

function obtenerArchivosPHP($directorio, &$resultados = [], $basePath = '') {
    $archivos = scandir($directorio);
    foreach ($archivos as $archivo) {
        if ($archivo === '.' || $archivo === '..') continue;
        $rutaCompleta = $directorio . DIRECTORY_SEPARATOR . $archivo;
        $rutaRelativa = $basePath ? $basePath . '/' . $archivo : $archivo;
        if (is_dir($rutaCompleta)) {
            obtenerArchivosPHP($rutaCompleta, $resultados, $rutaRelativa);
        } elseif (pathinfo($archivo, PATHINFO_EXTENSION) === 'php') {
            $resultados[] = [
                'ruta'         => $rutaRelativa,
                'ruta_completa'=> $rutaCompleta,
                'nombre'       => $archivo
            ];
        }
    }
    return $resultados;
}

// ── Funciones nuevas (Script 2) ─────────────────────────────────────────────

function detectarFontAwesome($rutaCompleta) {
    $contenido = file_get_contents($rutaCompleta);
    $tieneLib  = (stripos($contenido, 'font-awesome') !== false || stripos($contenido, 'fontawesome') !== false);
    $usaIconos = (bool) preg_match('/class=["\']fa[srb]? fa-/', $contenido);

    if ($tieneLib && $usaIconos)  return 'ok';
    if ($tieneLib && !$usaIconos) return 'innecesario';
    if (!$tieneLib && $usaIconos) return 'roto';
    return 'na';
}

function detectarIntegrity($rutaCompleta) {
    $contenido = file_get_contents($rutaCompleta);
    return (strpos($contenido, 'integrity=') !== false);
}

// ── Análisis principal ──────────────────────────────────────────────────────

$archivosPHP = obtenerArchivosPHP('.');
$analisis    = [];

foreach ($archivosPHP as $index => $archivo) {
    $analisis[] = [
        'numero'    => $index + 1,
        'ruta'      => $archivo['ruta'],
        'nombre'    => $archivo['nombre'],
        'lineas'    => contarLineasCodigo($archivo['ruta_completa']),
        'utf8'      => esUTF8($archivo['ruta_completa']),
        'bom'       => tieneBOM($archivo['ruta_completa']),
        'licencia'  => mencionaLicencia($archivo['ruta_completa']),
        'recursivo' => esRecursivo($archivo['ruta_completa'], $archivo['nombre']),
        'fa'        => detectarFontAwesome($archivo['ruta_completa']),
        'integrity' => detectarIntegrity($archivo['ruta_completa']),
    ];
}

$totalArchivos = count($analisis);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Analizador PHP - Kimi K2.5</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">

    <style>
        body {
            padding-top: 70px;
            padding-bottom: 60px;
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .badge-custom {
            font-size: 0.9em;
            padding: 8px 12px;
        }
        .footer-fixed {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 50px;
            background-color: #343a40;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1030;
        }
        .icon-check   { color: #28a745; }
        .icon-cross   { color: #dc3545; }
        .icon-warning { color: #ffc107; }
        .recursivo-alert {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        tr:hover { background-color: #f5f5f5; }
        .file-path {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #495057;
        }
    </style>
</head>
<body>

    <!-- Navbar Fijo -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-robot mr-2"></i>Kimi K2.5
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item active">
                        <a class="nav-link" href="#">
                            <i class="fas fa-search mr-1"></i>Analizador PHP
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light btn-sm" href="#" onclick="window.close(); return false;">
                            <i class="fas fa-sign-out-alt mr-1"></i>Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-center mb-3">
                    <i class="fas fa-folder-open text-primary mr-2"></i>
                    Análisis de Archivos PHP
                </h2>
                <p class="text-muted text-center">
                    Directorio actual: <code><?php echo getcwd(); ?></code> |
                    Total archivos analizados: <span class="badge badge-primary"><?php echo $totalArchivos; ?></span>
                </p>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Archivo</th>
                            <th style="width:100px;">Líneas</th>
                            <th style="width:80px;" class="text-center">UTF-8</th>
                            <th style="width:80px;" class="text-center">BOM</th>
                            <th style="width:100px;" class="text-center">Licencia</th>
                            <th style="width:120px;" class="text-center">Recursivo</th>
                            <th style="width:130px;" class="text-center">Font Awesome</th>
                            <th style="width:130px;" class="text-center">Integrity (SRI)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analisis as $archivo): ?>
                        <tr>
                            <td class="font-weight-bold"><?php echo $archivo['numero']; ?></td>

                            <!-- Archivo + alerta recursivo -->
                            <td>
                                <div class="file-path">
                                    <i class="fab fa-php text-primary mr-2"></i>
                                    <?php echo htmlspecialchars($archivo['ruta']); ?>
                                </div>
                                <?php if ($archivo['recursivo']): ?>
                                <div class="recursivo-alert mt-2">
                                    <i class="fas fa-exclamation-triangle icon-warning mr-1"></i>
                                    <strong>¡Atención!</strong> Este archivo contiene una referencia a sí mismo
                                    (<code><?php echo htmlspecialchars($archivo['nombre']); ?></code>)
                                </div>
                                <?php endif; ?>
                            </td>

                            <!-- Líneas -->
                            <td>
                                <span class="badge badge-info badge-custom">
                                    <?php echo $archivo['lineas']; ?> líneas
                                </span>
                            </td>

                            <!-- UTF-8 -->
                            <td class="text-center">
                                <?php if ($archivo['utf8']): ?>
                                    <i class="fas fa-check-circle icon-check fa-lg" title="UTF-8 válido"></i>
                                    <span class="d-block small text-success">Sí</span>
                                <?php else: ?>
                                    <i class="fas fa-times-circle icon-cross fa-lg" title="No es UTF-8"></i>
                                    <span class="d-block small text-danger">No</span>
                                <?php endif; ?>
                            </td>

                            <!-- BOM -->
                            <td class="text-center">
                                <?php if ($archivo['bom']): ?>
                                    <i class="fas fa-bomb text-warning fa-lg" title="Tiene BOM"></i>
                                    <span class="d-block small text-warning">Sí</span>
                                <?php else: ?>
                                    <i class="fas fa-check-circle icon-check fa-lg" title="Sin BOM"></i>
                                    <span class="d-block small text-success">No</span>
                                <?php endif; ?>
                            </td>

                            <!-- Licencia -->
                            <td class="text-center">
                                <?php if ($archivo['licencia']): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-balance-scale mr-1"></i>Sí
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No</span>
                                <?php endif; ?>
                            </td>

                            <!-- Recursivo -->
                            <td class="text-center">
                                <?php if ($archivo['recursivo']): ?>
                                    <span class="badge badge-warning badge-custom">
                                        <i class="fas fa-sync-alt mr-1"></i>Sí
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-light badge-custom text-muted">
                                        <i class="fas fa-minus mr-1"></i>No
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Font Awesome (nuevo) -->
                            <td class="text-center">
                                <?php
                                switch ($archivo['fa']) {
                                    case 'ok':
                                        echo '<span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>OK</span>';
                                        break;
                                    case 'innecesario':
                                        echo '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Innecesario</span>';
                                        break;
                                    case 'roto':
                                        echo '<span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Roto</span>';
                                        break;
                                    default:
                                        echo '<span class="badge badge-light border text-muted">N/A</span>';
                                }
                                ?>
                            </td>

                            <!-- Integrity SRI (nuevo) -->
                            <td class="text-center">
                                <?php if ($archivo['integrity']): ?>
                                    <span class="text-info font-weight-bold">
                                        <i class="fas fa-shield-alt mr-1"></i>Detectado
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="fas fa-minus mr-1"></i>Limpio
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($analisis)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No se encontraron archivos PHP en este directorio.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Resumen de cards -->
        <?php if (!empty($analisis)): ?>
        <div class="row mb-5">
            <div class="col-md-2">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h5 class="card-title text-success"><i class="fas fa-check-circle"></i> UTF-8 Válido</h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, fn($a) => $a['utf8'])); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><i class="fas fa-bomb"></i> Con BOM</h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, fn($a) => $a['bom'])); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h5 class="card-title text-info"><i class="fas fa-balance-scale"></i> Con Licencia</h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, fn($a) => $a['licencia'])); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><i class="fas fa-sync-alt"></i> Recursivos</h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, fn($a) => $a['recursivo'])); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h5 class="card-title text-success"><i class="fas fa-flag"></i> FA OK</h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, fn($a) => $a['fa'] === 'ok')); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h5 class="card-title text-info"><i class="fas fa-shield-alt"></i> Con SRI</h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, fn($a) => $a['integrity'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer Fijo -->
    <footer class="footer-fixed">
        <div class="container text-center">
            <span class="mr-3">
                <i class="fab fa-php mr-2"></i>
                Versión de PHP: <strong><?php echo phpversion(); ?></strong>
            </span>
            <span class="text-muted">|</span>
            <span class="ml-3 text-muted">
                <i class="fas fa-code mr-1"></i>
                Generado por Kimi K2.5
            </span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFV38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
    <script>
        $(function () { $('[data-toggle="tooltip"]').tooltip(); });
    </script>
</body>
</html>
