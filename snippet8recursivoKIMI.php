<?php
/**
 * Script PHP para gestión de Arcivos Recursivos
 * Versión: 1
 * Modelo: Kimi 2.5 
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
 * Analizador de Archivos PHP Recursivo
 * Autor: Kimi K2.5
 * Versión: 1.0
 */

// Función para verificar si un archivo tiene BOM
function tieneBOM($archivo) {
    $contenido = file_get_contents($archivo, false, null, 0, 3);
    return ($contenido === "\xEF\xBB\xBF");
}

// Función para verificar si es UTF-8 válido
function esUTF8($archivo) {
    $contenido = file_get_contents($archivo);
    return mb_check_encoding($contenido, 'UTF-8');
}

// Función para contar líneas de código (excluyendo líneas vacías y comentarios simples)
function contarLineasCodigo($archivo) {
    $lineas = file($archivo);
    $contador = 0;
    
    foreach ($lineas as $linea) {
        $lineaTrim = trim($linea);
        // Excluir líneas vacías y comentarios simples de una línea
        if (!empty($lineaTrim) && !preg_match('/^(\/\/|#|\/\*|\*)/', $lineaTrim)) {
            $contador++;
        }
    }
    return $contador;
}

// Función para verificar si menciona "Licencia" (case insensitive)
function mencionaLicencia($archivo) {
    $contenido = file_get_contents($archivo);
    return (stripos($contenido, 'licencia') !== false || 
            stripos($contenido, 'license') !== false);
}

// Función para verificar llamada recursiva (el archivo se llama a sí mismo)
function esRecursivo($archivo, $nombreArchivo) {
    $contenido = file_get_contents($archivo);
    // Buscar el nombre del archivo en el contenido (excluyendo comentarios y strings podría ser más complejo)
    // Buscamos el nombre base del archivo
    $nombreBase = basename($nombreArchivo);
    return (strpos($contenido, $nombreBase) !== false);
}

// Función recursiva para obtener todos los archivos PHP
function obtenerArchivosPHP($directorio, &$resultados = [], $basePath = '') {
    $archivos = scandir($directorio);
    
    foreach ($archivos as $archivo) {
        if ($archivo === '.' || $archivo === '..') continue;
        
        $rutaCompleta = $directorio . DIRECTORY_SEPARATOR . $archivo;
        $rutaRelativa = $basePath ? $basePath . '/' . $archivo : $archivo;
        
        if (is_dir($rutaCompleta)) {
            // Recursivamente buscar en subdirectorios
            obtenerArchivosPHP($rutaCompleta, $resultados, $rutaRelativa);
        } elseif (pathinfo($archivo, PATHINFO_EXTENSION) === 'php') {
            $resultados[] = [
                'ruta' => $rutaRelativa,
                'ruta_completa' => $rutaCompleta,
                'nombre' => $archivo
            ];
        }
    }
    
    return $resultados;
}

// Obtener archivos PHP del directorio actual
$archivosPHP = obtenerArchivosPHP('.');

// Analizar cada archivo
$analisis = [];
foreach ($archivosPHP as $index => $archivo) {
    $info = [
        'numero' => $index + 1,
        'ruta' => $archivo['ruta'],
        'nombre' => $archivo['nombre'],
        'lineas' => contarLineasCodigo($archivo['ruta_completa']),
        'utf8' => esUTF8($archivo['ruta_completa']),
        'bom' => tieneBOM($archivo['ruta_completa']),
        'licencia' => mencionaLicencia($archivo['ruta_completa']),
        'recursivo' => esRecursivo($archivo['ruta_completa'], $archivo['nombre'])
    ];
    $analisis[] = $info;
}

// Total de archivos
$totalArchivos = count($analisis);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Analizador PHP - Kimi K2.5</title>
    
    <!-- Bootstrap 4.6.2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    
    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    
    <style>
        body {
            padding-top: 70px; /* Espacio para navbar fijo */
            padding-bottom: 60px; /* Espacio para footer fijo */
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
        
        .icon-check { color: #28a745; }
        .icon-cross { color: #dc3545; }
        .icon-warning { color: #ffc107; }
        
        .recursivo-alert {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
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
                            <i class="fas fa-search-code mr-1"></i>Analizador PHP
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
    <div class="container">
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
                            <th scope="col" style="width: 50px;">#</th>
                            <th scope="col">Archivo</th>
                            <th scope="col" style="width: 100px;">Líneas</th>
                            <th scope="col" style="width: 80px;">UTF-8</th>
                            <th scope="col" style="width: 80px;">BOM</th>
                            <th scope="col" style="width: 100px;">Licencia</th>
                            <th scope="col" style="width: 120px;">Recursivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analisis as $archivo): ?>
                        <tr>
                            <td class="font-weight-bold"><?php echo $archivo['numero']; ?></td>
                            <td>
                                <div class="file-path">
                                    <i class="fab fa-php text-primary mr-2"></i>
                                    <?php echo htmlspecialchars($archivo['ruta']); ?>
                                </div>
                                <?php if ($archivo['recursivo']): ?>
                                <div class="recursivo-alert mt-2">
                                    <i class="fas fa-exclamation-triangle icon-warning mr-1"></i>
                                    <strong>¡Atención!</strong> Este archivo contiene una referencia a sí mismo (<code><?php echo htmlspecialchars($archivo['nombre']); ?></code>)
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info badge-custom">
                                    <?php echo $archivo['lineas']; ?> líneas
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($archivo['utf8']): ?>
                                    <i class="fas fa-check-circle icon-check fa-lg" title="UTF-8 válido"></i>
                                    <span class="d-block small text-success">Sí</span>
                                <?php else: ?>
                                    <i class="fas fa-times-circle icon-cross fa-lg" title="No es UTF-8"></i>
                                    <span class="d-block small text-danger">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($archivo['bom']): ?>
                                    <i class="fas fa-bomb text-warning fa-lg" title="Tiene BOM"></i>
                                    <span class="d-block small text-warning">Sí</span>
                                <?php else: ?>
                                    <i class="fas fa-check-circle icon-check fa-lg" title="Sin BOM"></i>
                                    <span class="d-block small text-success">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($archivo['licencia']): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-balance-scale mr-1"></i>Sí
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">No</span>
                                <?php endif; ?>
                            </td>
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
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($analisis)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No se encontraron archivos PHP en este directorio.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Resumen -->
        <?php if (!empty($analisis)): ?>
        <div class="row mb-5">
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h5 class="card-title text-success">
                            <i class="fas fa-check-circle"></i> UTF-8 Válido
                        </h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, function($a) { return $a['utf8']; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <i class="fas fa-bomb"></i> Con BOM
                        </h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, function($a) { return $a['bom']; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <h5 class="card-title text-info">
                            <i class="fas fa-balance-scale"></i> Con Licencia
                        </h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, function($a) { return $a['licencia']; })); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <h5 class="card-title text-danger">
                            <i class="fas fa-sync-alt"></i> Recursivos
                        </h5>
                        <p class="card-text display-4">
                            <?php echo count(array_filter($analisis, function($a) { return $a['recursivo']; })); ?>
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

    <!-- Scripts necesarios para Bootstrap 4 -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFV38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
    
    <script>
        // Tooltip initialization
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>
