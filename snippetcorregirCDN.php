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

$directorioBase = __DIR__; 
$extensionBuscada = 'php';
$patrones = [
    'maxcdn.bootstrapcdn.com',
  	'stackpath.bootstrapcdn.com',
    'cdnjs.cloudflare.com',
    'code.jquery.com'
];

$resultados = [];

// Ejecución por default (sin esperar POST)
$directory = new RecursiveDirectoryIterator($directorioBase);
$iterator = new RecursiveIteratorIterator($directory);

foreach ($iterator as $archivo) {
    if ($archivo->isFile() && $archivo->getExtension() === $extensionBuscada) {
        // Evitar que el propio script se audite a sí mismo si se llama igual
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
            $tieneIntegrity = (strpos($contenido, 'integrity') !== false);
            $tieneLicencia = (strpos($contenido, 'Licencia') !== false);
            
            $resultados[] = [
                'archivo' => $rutaCompleta,
                'dominios' => implode(', ', array_unique($dominiosDetectados)),
                'integrity' => $tieneIntegrity,
                'licencia' => $tieneLicencia
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor de Código - Gemini 3 Flash</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { padding-top: 70px; padding-bottom: 80px; }
        .footer { position: fixed; bottom: 0; width: 100%; height: 60px; line-height: 60px; background-color: #222; color: #aaa; border-top: 2px solid #444; }
        .table-sm td, .table-sm th { font-size: 0.82rem; vertical-align: middle; }
        .text-monospace { word-break: break-all; color: #555; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark shadow">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <strong>Gemini 3 Flash</strong> <span class="badge badge-warning ml-2">Scanner Activo</span>
        </a>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark">Reporte de Auditoría: <?php echo count($resultados); ?> archivos detectados</h5>
            <small class="text-muted">Directorio: <?php echo $directorioBase; ?></small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-striped mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width: 50px;">#</th>
                            <th>Ruta del Archivo</th>
                            <th>Dominios (CDN)</th>
                            <th class="text-center">Atributo Integrity</th>
                            <th class="text-center">Licencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resultados)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No se encontraron archivos con los patrones definidos.</td></tr>
                        <?php else: ?>
                            <?php foreach ($resultados as $index => $res): ?>
                                <tr>
                                    <td class="text-center font-weight-bold text-secondary"><?php echo $index + 1; ?></td>
                                    <td class="text-monospace"><?php echo htmlspecialchars($res['archivo']); ?></td>
                                    <td><span class="text-danger font-weight-bold small"><?php echo $res['dominios']; ?></span></td>
                                    <td class="text-center">
                                        <?php if ($res['integrity']): ?>
                                            <span class="badge badge-danger px-3">CON INTEGRITY</span>
                                        <?php else: ?>
                                            <span class="badge badge-success px-3">NO TIENE</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($res['licencia']): ?>
                                            <span class="badge badge-success px-3">SI</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger px-3">NO</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="py-5"></div>
</div>

<footer class="footer mt-auto bg-dark text-white text-center">
    <div class="container d-flex justify-content-between">
        <span><small>Auditoría Automática de Sistemas</small></span>
        <span><small>Estatus: <strong>Finalizado</strong></small></span>
        <span><small>2026</small></span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
