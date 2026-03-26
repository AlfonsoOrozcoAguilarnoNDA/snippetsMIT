<?php
/**
 * Proyecto: Visor de CDN Por corregir
 * Licencia: MIT
 * Fecha   : 26/mar/2026
 * Coautor : Gemini Web 1.5 Flash
 * * Alfonso Orozco Aguilar
 * * Copyright (c) 2026 Gemini 1.5 Flash
 * * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * https://vibecodingmexico.com/snippet-7-corregircdn/
 */
/**
 * Auditoría de Librerías y Atributos - Ejecución Automática
 * PHP 8.x Procedural + Bootstrap 4.6
 */
// --- 1. CONTROL DE ACCESO POR IP ---
$ipsAutorizadas = [
    '127.0.0.1', // Localhost IPv4
    '::1',       // Localhost IPv6
    '192.168.1.1', // Ejemplo IP local
    // Agrega aquí tu IP pública si lo corres en un servidor remoto
];

$ipVisitante = $_SERVER['REMOTE_ADDR'];
$accesoConcedido = in_array($ipVisitante, $ipsAutorizadas);

// --- 2. VERIFICACIÓN DE DEPENDENCIAS ---
$mbstring_instalada = extension_loaded('mbstring');

$directorioBase = __DIR__; 
$extensionBuscada = 'php';
$patrones = [
    'maxcdn.bootstrapcdn.com',
    'stackpath.bootstrapcdn.com',
    'cdnjs.cloudflare.com',
    '4.6.3',
    '4.5.2',
    '5.15.3',
    'code.jquery.com'
];

$resultados = [];

// Solo ejecutamos si hay acceso y mbstring está presente
if ($accesoConcedido && $mbstring_instalada) {
    $directory = new RecursiveDirectoryIterator($directorioBase);
    $iterator = new RecursiveIteratorIterator($directory);

    foreach ($iterator as $archivo) {
        if ($archivo->isFile() && $archivo->getExtension() === $extensionBuscada) {
            if ($archivo->getFilename() === basename(__FILE__)) continue;

            $rutaCompleta = $archivo->getPathname();
            $contenido = file_get_contents($rutaCompleta);
            
            $encontrado = false;
            $dominiosDetectados = [];
            
            foreach ($patrones as $patron) {
                if (strpos($contenido, $patron) !== false) {
                    $encontrado = true;
                    $dominiosDetectados[] = $patron;
                }
            }

            if ($encontrado) {
                $lineas = count(file($rutaCompleta));
                $bom = (substr($contenido, 0, 3) === pack("CCC", 0xef, 0xbb, 0xbf));
                $encoding = mb_detect_encoding($contenido, 'UTF-8', true) ?: 'Otro/Desconocido';
                
                $resultados[] = [
                    'archivo' => $rutaCompleta,
                    'dominios' => implode(', ', array_unique($dominiosDetectados)),
                    'integrity' => (strpos($contenido, 'integrity') !== false),
                    'licencia' => (strpos($contenido, 'Licencia') !== false),
                    'lineas' => $lineas,
                    'bom' => $bom,
                    'encoding' => $encoding
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor de Código - Acceso Restringido</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; padding-bottom: 80px; }
        .footer { position: fixed; bottom: 0; width: 100%; height: 60px; line-height: 60px; background-color: #1a1a1a; color: #888; border-top: 2px solid #333; }
        .table-sm td, .table-sm th { font-size: 0.78rem; vertical-align: middle; }
        .badge-fixed { width: 90px; display: inline-block; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark shadow">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <strong>Gemini 3 Flash</strong> <span class="badge badge-warning ml-2">Scanner v1.5</span>
        </a>
        <?php if ($accesoConcedido): ?>
            <span class="navbar-text small text-success font-weight-bold">● IP AUTORIZADA: <?php echo $ipVisitante; ?></span>
        <?php endif; ?>
    </div>
</nav>

<div class="container-fluid mt-4">
    
    <?php if (!$accesoConcedido): ?>
        <div class="jumbotron shadow border-danger bg-white text-center mt-5">
            <h1 class="display-4 text-danger font-weight-bold">Acceso Denegado</h1>
            <p class="lead">Lo sentimos, esta herramienta de auditoría es de uso privado.</p>
            <hr class="my-4">
            <div class="alert alert-warning d-inline-block px-5">
                La dirección IP <strong><?php echo $ipVisitante; ?></strong> no está autorizada.
            </div>
            <p class="mt-4 small text-muted">Por seguridad, este intento de acceso ha sido registrado.</p>
        </div>

    <?php elseif (!$mbstring_instalada): ?>
        <div class="alert alert-danger shadow">
            <h4 class="alert-heading font-weight-bold">¡Falta mbstring!</h4>
            <p>Habilita la extensión <code>mbstring</code> en tu configuración de PHP para continuar.</p>
        </div>

    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-dark font-weight-bold text-uppercase">Auditoría: <?php echo count($resultados); ?> Archivos</h5>
                <small class="text-muted">Directorio: <code><?php echo $directorioBase; ?></code></small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0">
                        <thead class="thead-dark text-center">
                            <tr>
                                <th>#</th>
                                <th class="text-left">Ruta del Archivo</th>
                                <th>Líneas</th>
                                <th>Encoding / BOM</th>
                                <th>CDN/Patrones</th>
                                <th>Integrity</th>
                                <th>Licencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resultados)): ?>
                                <tr><td colspan="7" class="text-center py-5">No se encontraron patrones para corregir.</td></tr>
                            <?php else: ?>
                                <?php foreach ($resultados as $index => $res): ?>
                                    <tr>
                                        <td class="text-center font-weight-bold text-secondary"><?php echo $index + 1; ?></td>
                                        <td class="text-monospace small"><?php echo htmlspecialchars($res['archivo']); ?></td>
                                        <td class="text-center"><?php echo number_format($res['lineas']); ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-info"><?php echo $res['encoding']; ?></span>
                                            <?php if ($res['bom']): ?>
                                                <span class="badge badge-danger">CON BOM</span>
                                            <?php else: ?>
                                                <span class="badge badge-light border text-muted small">Sin BOM</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-danger font-weight-bold"><?php echo $res['dominios']; ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-<?php echo $res['integrity'] ? 'danger' : 'success'; ?> badge-fixed p-2">
                                                <?php echo $res['integrity'] ? 'CON INTG' : 'LIMPIO'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-<?php echo $res['licencia'] ? 'success' : 'danger'; ?> badge-fixed p-2">
                                                <?php echo $res['licencia'] ? 'SI' : 'NO'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="py-5"></div>
</div>

<footer class="footer mt-auto bg-dark text-white text-center">
    <div class="container d-flex justify-content-between">
        <span><small>Auditoría de Sistemas 2026</small></span>
        <span><small>Estatus: <strong><?php echo ($accesoConcedido && $mbstring_instalada) ? 'ACTIVO' : 'RESTRINGIDO'; ?></strong></small></span>
        <span><small>Marzo 2026</small></span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
