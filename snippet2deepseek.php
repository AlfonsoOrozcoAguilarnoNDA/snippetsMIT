<?php
/**
 * Proyecto: Visor de Imágenes "Vibe-QuickView"
 * Licencia: MIT
 * Alfonso Orozco Aguilar
 * DeepSeek Coder v1.0
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

// Escanear el directorio actual en busca de imágenes
$imagenes = glob("*.{jpg,jpeg,png,gif,bmp,webp}", GLOB_BRACE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galería de Imágenes</title>
    
    <!-- Bootstrap 4.6.x desde jsDelivr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        body {
            padding-top: 76px;
            padding-bottom: 76px;
        }
        
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            line-height: 60px;
        }
        
        .card-img-top {
            height: 200px;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .card-img-top:hover {
            transform: scale(1.05);
        }
        
        .modal-img {
            max-height: 80vh;
            max-width: 100%;
            object-fit: contain;
        }
        
        .gallery-item {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <!-- Barra de navegación superior fija -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-images mr-2"></i>
                DeepSeek Coder v1.0
            </a>
            
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="https://www.google.com" target="_blank">
                            <i class="fab fa-google mr-1"></i>Google
                        </a>
                    </li>
                    
                    <!-- Dropdown de prueba -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                            <i class="fas fa-bars mr-1"></i>Opciones
                        </a>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#"><i class="fas fa-option mr-2"></i>Opción A</a>
                            <a class="dropdown-item" href="#"><i class="fas fa-option mr-2"></i>Opción B</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#"><i class="fas fa-option mr-2"></i>Opción C</a>
                        </div>
                    </li>
                </ul>
                
                <!-- Botón de salir -->
                <button class="btn btn-outline-light" onclick="console.log('Botón salir clickeado')">
                    <i class="fas fa-sign-out-alt mr-1"></i>Salir
                </button>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container">
        <div class="row">
            <?php if (empty($imagenes)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        No se encontraron imágenes en la carpeta actual.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($imagenes as $index => $imagen): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 gallery-item">
                        <div class="card h-100 shadow-sm">
                            <img src="<?php echo htmlspecialchars($imagen); ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($imagen); ?>"
                                 onclick="abrirModal('<?php echo htmlspecialchars($imagen); ?>', '<?php echo htmlspecialchars(addslashes($imagen)); ?>')">
                            <div class="card-body text-center p-2">
                                <small class="text-muted">
                                    <?php echo htmlspecialchars(substr($imagen, 0, 20)) . (strlen($imagen) > 20 ? '...' : ''); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer fijo -->
    <footer class="footer fixed-bottom text-center">
        <div class="container">
            <span class="text-muted">
                <i class="fas fa-copyright mr-1"></i>
                <?php echo date('Y'); ?> - Galería de Imágenes - 
                <a href="#licencia" data-toggle="modal" data-target="#licenciaModal" class="text-muted">
                    Licencia MIT
                </a>
            </span>
        </div>
    </footer>

    <!-- Modal para ver imágenes ampliadas -->
    <div class="modal fade" id="imagenModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitulo"></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="imagenModalSrc" class="modal-img img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Licencia MIT -->
    <div class="modal fade" id="licenciaModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Licencia MIT</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <pre style="white-space: pre-wrap; font-size: 0.9em;">
Copyright (c) <?php echo date('Y'); ?> DeepSeek Coder

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
                    </pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts necesarios -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function abrirModal(src, titulo) {
        $('#imagenModalSrc').attr('src', src);
        $('#modalTitulo').text(titulo);
        $('#imagenModal').modal('show');
    }
    
    // Prevenir que los enlaces del dropdown recarguen la página
    $(document).ready(function() {
        $('.dropdown-item').click(function(e) {
            e.preventDefault();
            console.log('Opción seleccionada:', $(this).text().trim());
        });
    });
    </script>
</body>
</html>
