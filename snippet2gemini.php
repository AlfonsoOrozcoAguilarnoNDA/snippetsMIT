<?php
/**
 * Proyecto: Visor de Imágenes "Vibe-QuickView"
 * Licencia: MIT
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
 * https://vibecodingmexico.com/snippet-2-visor-imagenes/
 */

// Identificación del modelo
$ai_model = "Gemini 1.5 Flash";

// Escaneo de imágenes en el directorio actual
$directory = ".";
$images = glob($directory . "/*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>Laboratorio - Visor de Imágenes</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        body { padding-top: 70px; padding-bottom: 70px; background-color: #f8f9fa; }
        .img-thumbnail-custom {
            width: 100%;
            height: 200px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .img-thumbnail-custom:hover { transform: scale(1.03); }
        .navbar-custom { background: linear-gradient(90deg, #1e3c72 0%, #2a5298 100%); }
        .footer { background: #343a40; color: white; padding: 10px 0; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="fas fa-shield-alt"></i> Lab: <?php echo $ai_model; ?></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="https://google.com" target="_blank"><i class="fab fa-google"></i> Google</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dropA" data-toggle="dropdown">Opciones</a>
                    <div class="dropdown-menu shadow">
                        <a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Opción A</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-tools"></i> Opción B</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-database"></i> Opción C</a>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link btn btn-danger btn-sm text-white ml-lg-2" href="#"><i class="fas fa-sign-out-alt"></i> Salir</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row">
        <div class="col-12 mb-4">
            <h3 class="text-muted border-bottom pb-2">Galería de Laboratorio</h3>
        </div>
    </div>

    <div class="row">
        <?php if(empty($images)): ?>
            <div class="col-12 text-center">
                <div class="alert alert-info">No se encontraron imágenes en el directorio actual.</div>
            </div>
        <?php else: ?>
            <?php foreach($images as $image): ?>
                <div class="col-6 col-md-3 mb-4">
                    <div class="card shadow-sm">
                        <img src="<?php echo $image; ?>" 
                             class="img-thumbnail-custom card-img-top" 
                             alt="Imagen" 
                             data-toggle="modal" 
                             data-target="#viewModal" 
                             onclick="showImage('<?php echo $image; ?>')">
                        <div class="card-footer p-1 text-center">
                            <small class="text-truncate d-block"><?php echo basename($image); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0">
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <img src="" id="modalImg" class="img-fluid rounded-bottom">
            </div>
        </div>
    </div>
</div>

<footer class="footer fixed-bottom">
    <div class="container text-center">
        <small>© 2026 - Licencia MIT - <i class="fas fa-code"></i> PHP 8.x Procedural</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>

<script>
    function showImage(src) {
        document.getElementById('modalImg').src = src;
    }
</script>

</body>
</html>
