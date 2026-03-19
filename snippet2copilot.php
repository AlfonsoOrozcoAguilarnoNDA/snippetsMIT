<?php
// https://vibecodingmexico.com/snippet-2-visor-imagenes/
/**
 * MIT License
 * 
 * Copyright (c) 2026 Alfonso Orozco Aguilar
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Demo Mosaico PHP</title>
  <!-- Bootstrap 4.6.x desde jsDelivr -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body {
      padding-top: 70px;
      padding-bottom: 70px;
    }
    footer {
      height: 50px;
      line-height: 50px;
    }
    .img-thumb {
      cursor: pointer;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>

<!-- Barra de navegación fija -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <a class="navbar-brand" href="#">Modelo: Microsoft Copilot</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNav">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item">
        <a class="nav-link" href="https://www.google.com" target="_blank"><i class="fab fa-google"></i> Google</a>
      </li>
      <li class="nav-item">
        <button class="btn btn-danger nav-link" style="color:white;"><i class="fas fa-sign-out-alt"></i> Salir</button>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="opcionesDropdown" role="button" data-toggle="dropdown">
          Opciones
        </a>
        <div class="dropdown-menu">
          <a class="dropdown-item" href="#">Opción A</a>
          <a class="dropdown-item" href="#">Opción B</a>
          <a class="dropdown-item" href="#">Opción C</a>
        </div>
      </li>
    </ul>
  </div>
</nav>

<div class="container">
  <div class="row">
    <?php
    // Listar imágenes de la carpeta actual
    $imagenes = glob("*.{jpg,jpeg,png,gif}", GLOB_BRACE);
    foreach ($imagenes as $img) {
        echo '<div class="col-md-3">';
        echo '<img src="'.$img.'" class="img-fluid img-thumb" data-toggle="modal" data-target="#modalImagen" data-img="'.$img.'">';
        echo '</div>';
    }
    ?>
  </div>
</div>

<!-- Modal para ampliar imagen -->
<div class="modal fade" id="modalImagen" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body text-center">
        <img id="imagenModal" src="" class="img-fluid">
      </div>
    </div>
  </div>
</div>

<!-- Footer fijo -->
<footer class="bg-dark text-white text-center fixed-bottom">
  <span>© 2026 Demo rápido bajo licencia MIT</span>
</footer>

<!-- Scripts Bootstrap y jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  $('#modalImagen').on('show.bs.modal', function (event) {
    var img = $(event.relatedTarget).data('img');
    $('#imagenModal').attr('src', img);
  });
</script>

</body>
</html>
