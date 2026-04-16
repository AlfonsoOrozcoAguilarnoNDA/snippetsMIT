<?php
/**
 * Script PHP para gestión de Arcivos Recursivos
 * Versión: 1.01
 * Modelo: Gemma4b modelo MOE google/gemma-4-26b-a4b
 * Licencia: MIT
 * Razonamiento : https://vibecodingmexico.com/recursivo/
 *
 * Copyright (c) 2026 Alfonso Orozco Aguilar 16 abril 2026
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
?>
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Pantalla Bootstrap 4.6</title>
    
    <!-- Bootstrap 4.6.2 CSS via jsDelivr -->
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>
    
    <!-- Font Awesome 5.15.4 via jsDelivr -->
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css'>

    <style>
        /* Ajuste para que el contenido no quede oculto bajo el header fijo y el footer fijo */
        body {
            padding-top: 120px; /* Espacio para las dos navbars */
            padding-bottom: 60px; /* Espacio para el footer */
            min-height: 100vh;
        }

        /* Estilo para asegurar que la segunda navbar se posicione debajo de la primera */
        .navbar-second {
            margin-top: 56px; /* Altura aproximada de la primera navbar */
            border-bottom: 1px solid #dee2e6;
        }

        .fixed-header-container {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1030;
        }

        .footer-fixed {
            position: fixed;
            bottom: 0;
            width: 100%;
            z-index: 1030;
        }
    </style>
</head>
<body>

    <!-- Header Fijo que contiene las dos barras de navegación -->
    <header class='fixed-header-container'>
        <!-- Primera Barra de Navegación (Principal) -->
        <nav class='navbar navbar-expand-lg navbar-dark bg-dark'>
            <div class='container-fluid'>
                <a class='navbar-brand' href='#'><i class='fas fa-rocket'></i> App Principal</a>
                <button class='navbar-toggler' type='button' data-toggle='collapse' data-target='#nav1'>
                    <span class='navbar-toggler-icon'></span>
                </button>
                <div class='collapse navbar-collapse' id='nav1'>
                    <ul class='navbar-nav mr-auto'>
                        <li class='nav-item'>
                            <a class='nav-link' href='https://www.google.com' target='_blank'><i class='fab fa-google'></i> Google</a>
                        </li>
                        <li class='nav-item dropdown'>
                            <a class='nav-link dropdown-toggle' href='#' id='dropdown1' role='button' data-toggle='dropdown'>
                                Opciones 1
                            </a>
                            <div class='dropdown-menu'>
                                <a class='dropdown-item' href='#'>Acción A</a>
                                <a class='dropdown-item' href='#'>Acción B</a>
                                <a class='dropdown-item' href='#'>Acción C</a>
                            </div>
                        </li>
                    </ul>
                    <span class='navbar-text mr-3'>
                        <button class='btn btn-outline-danger btn-sm'><i class='fas fa-sign-out-alt'></i> Salir</button>
                    </span>
                </div>
            </div>
        </nav>

        <!-- Segunda Barra de Navegación (Secundaria) -->
        <nav class='navbar navbar-expand-lg navbar-light bg-light navbar-second'>
            <div class='container-fluid'>
                <button class='navbar-toggler' type='button' data-toggle='collapse' data-target='#nav2'>
                    <span class='navbar-toggler-icon'></span>
                </button>
                <div class='collapse navbar-collapse' id='nav2'>
                    <ul class='navbar-nav mr-auto'>
                        <li class='nav-item'>
                            <a class='nav-link' href='https://www.google.com' target='_blank'><i class='fab fa-google'></i> Google</a>
                        </li>
                        <li class='nav-item dropdown'>
                            <a class='nav-link dropdown-toggle' href='#' id='dropdown2' role='button' data-toggle='dropdown'>
                                Opciones 2
                            </a>
                            <div class='dropdown-menu'>
                                <a class='dropdown-item' href='#'>Sub-opción 1</a>
                                <a class='dropdown-item' href='#'>Sub-opción 2</a>
                                <a class='dropdown-item' href='#'>Sub-opción 3</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Contenido Principal -->
    <main class='container mt-4'>
        <div class='jumbotron shadow-sm'>
            <h1 class='display-4'>¡Bienvenido!</h1>
            <p class='lead'>Esta es una pantalla diseñada con Bootstrap 4.6.x y Font Awesome 5.15.4.</p>
            <hr class='my-4'>
            <p>El contenido principal se encuentra dentro de un Jumbotron. Las barras de navegación superiores son fijas y el footer también lo es.</p>
            <p class='text-muted small'>
                <!-- Información solicitada -->
                Modelo: GPT-4o <br>
                Fecha actual: <span id='fecha-actual'></span>
            </</strong>>
            <a class='btn btn-primary btn-lg' href='#' role='button'>Botón de Acción</a>
        </div>

        <!-- Bloque extra para probar el scroll -->
        <div class='card mt-4'>
            <div class='card-body'>
                <h5 class='card-title'>Información Adicional</h5>
                <p class='card-text'>Desliza hacia abajo para comprobar que el footer permanece fijo al final de la pantalla.</p>
            </div>
        </div>
        <div style='height: 1000px;'></div> <!-- Espaciador para permitir scroll -->
    </main>

    <!-- Footer Fijo -->
    <footer class='footer-fixed bg-dark text-white text-center py-3'>
        <div class='container'>
            <span>&copy; 2024 - Todos los derechos reservados.</span>
        </div>
    </footer>

    <!-- Scripts: jQuery, Popper.js y Bootstrap JS via jsDelivr -->
    <script src='https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js'></script>

    <script>
        // Script para mostrar la fecha de hoy dinámicamente
        document.getElementById('fecha-actual').innerText = new Date().toLocaleDateString();
    </script>
</body>
</html>
