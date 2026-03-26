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
    '127.0.0.1', 
    '::1',       
	'201.103.224.166', // la tuya
    '192.168.1.1', 
];

$ipVisitante = $_SERVER['REMOTE_ADDR'];
$accesoConcedido = in_array($ipVisitante, $ipsAutorizadas);

// --- 2. VERIFICACIÓN DE DEPENDENCIAS ---
$mbstring_instalada = extension_loaded('mbstring');

$directorioBase = __DIR__; 
$extensionBuscada = 'php';

// Listado de patrones actualizado v1.7
$patrones = [
    'maxcdn.bootstrapcdn.com',
    'stackpath.bootstrapcdn.com',
    'cdnjs.cloudflare.com',
    '4.6.3', '4.5.2', '4.5.3',
    '5.15.1', '5.15.2', '5.15.3',
    '3.2.1', '3.5.1', '3.6.0', '3.7.0',
    '6.0.0', '6.5.0', '6.5.2',
    'slim', 'popper',
    'sha256-XOqroi11tYnBDfnSiK9n7No+34id9mN9F5nOTpL0skw=',
    'code.jquery.com'
];

$resultados = [];

if ($accesoConcedido && $mbstring_instalada) {
    $directory = new RecursiveDirectoryIterator($directorioBase);
    $iterator = new RecursiveIteratorIterator($directory);

    foreach ($iterator as $archivo) {
        if ($archivo->isFile() && $archivo->getExtension() === $extensionBuscada) {
            if ($archivo->getFilename() === basename(__FILE__)) continue;

            $rutaCompleta = $archivo->getPathname();
            // Generamos una ruta relativa para el enlace target_blank
            $rutaRelativa = str_replace($directorioBase . DIRECTORY_SEPARATOR, '', $rutaCompleta);
            
            $contenido = file_get_contents($rutaCompleta);
            
            $matchPatron = false;
            $detectados = [];
            
            foreach ($patrones as $patron) {
                if (strpos($contenido, $patron) !== false) {
                    $matchPatron = true;
                    $detectados[] = $patron;
                }
            }

            $tieneBOM = (substr($contenido, 0, 3) === pack("CCC", 0xef, 0xbb, 0xbf));

            if ($matchPatron || $tieneBOM) {
                $lineas = @count(file($rutaCompleta)) ?: 0;
                $encoding = mb_detect_encoding($contenido, 'UTF-8', true) ?: 'Desconocido';
                
                $resultados[] = [
                    'archivo' => $rutaCompleta,
                    'enlace'  => $rutaRelativa,
                    'dominios' => implode(', ', array_unique($detectados)),
                    'integrity' => (strpos($contenido, 'integrity') !== false),
                    'licencia' => (strpos($contenido, 'Licencia') !== false),
                    'lineas' => $lineas,
                    'bom' => $tieneBOM,
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
    <title>Auditor Centinela v1.7</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; padding-bottom: 80px; }
        .footer { position: fixed; bottom: 0; width: 100%; height: 60px; line-height: 60px; background-color: #1a1a1a; color: #888; border-top: 2px solid #333; }
        .table-sm td, .table-sm th { font-size: 0.78rem; vertical-align: middle; }
        .badge-fixed { width: 95px; display: inline-block; text-align: center; }
        .row-bom { background-color: rgba(255, 0, 0, 0.04); }
        .link-archivo { color: #0056b3; text-decoration: underline; font-weight: 500; }
        .link-archivo:hover { color: #003366; background: #e9ecef; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark shadow">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <strong>Gemini 3 Flash</strong> <span class="badge badge-warning ml-2 text-dark">v1.7 PRO</span>
        </a>
        <span class="navbar-text small text-success">IP: <?php echo $ipVisitante; ?></span>
    </div>
</nav>

<div class="container-fluid mt-4">
    
    <?php if (!$accesoConcedido): ?>
        <div class="jumbotron shadow border-danger bg-white text-center mt-5">
            <h1 class="display-4 text-danger font-weight-bold">Acceso Denegado</h1>
            <p class="lead">IP <strong><?php echo $ipVisitante; ?></strong> no autorizada.</p>
        </div>

    <?php elseif (!$mbstring_instalada): ?>
        <div class="alert alert-danger shadow">Error: Falta extensión mbstring.</div>

    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-dark">Auditoría de Integridad: <strong><?php echo count($resultados); ?></strong> archivos encontrados</h5>
                <span class="small text-muted font-italic">Patrones: CDNs, Versiones y Detección de BOM activa</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-dark text-center">
                            <tr>
                                <th>#</th>
                                <th class="text-left">Ruta del Archivo (Click para abrir)</th>
                                <th>Líneas</th>
                                <th>Encoding / BOM</th>
                                <th>Patrones / Versiones</th>
                                <th>Integrity</th>
                                <th>Licencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resultados)): ?>
                                <tr><td colspan="7" class="text-center py-5">No se detectaron archivos con los criterios de búsqueda.</td></tr>
                            <?php else: ?>
                                <?php foreach ($resultados as $index => $res): ?>
                                    <tr class="<?php echo $res['bom'] ? 'row-bom' : ''; ?>">
                                        <td class="text-center font-weight-bold text-secondary"><?php echo $index + 1; ?></td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($res['enlace']); ?>" target="_blank" class="text-monospace small link-archivo">
                                                <?php echo htmlspecialchars($res['archivo']); ?>
                                            </a>
                                        </td>
                                        <td class="text-center"><?php echo number_format($res['lineas']); ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-info mb-1"><?php echo $res['encoding']; ?></span><br>
                                            <?php if ($res['bom']): ?>
                                                <span class="badge badge-danger badge-fixed">CON BOM</span>
                                            <?php else: ?>
                                                <span class="badge badge-light border text-muted badge-fixed small">Sin BOM</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-danger font-weight-bold">
                                            <?php echo $res['dominios'] ?: '<span class="text-muted small">Causa: Solo BOM</span>'; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-<?php echo $res['integrity'] ? 'danger' : 'success'; ?> badge-fixed py-1">
                                                <?php echo $res['integrity'] ? 'CON INTG' : 'LIMPIO'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-<?php echo $res['licencia'] ? 'success' : 'danger'; ?> badge-fixed py-1">
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

<footer class="footer mt-auto bg-dark text-white text-center shadow-lg">
    <div class="container d-flex justify-content-between small">
        <span>Vibe Coding México | Centinela v1.7</span>
        <span class="text-success">PROCESO DE AUDITORÍA FINALIZADO</span>
        <span>2026</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
