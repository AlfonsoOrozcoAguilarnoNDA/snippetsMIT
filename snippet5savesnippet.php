<?php
/*
 * Autor: Alfonso Orozco Aguilar
 * Fecha: 2026-03-20
 * Objetivo: Guardar código generado con LLM con validación por dirección IP y enlace de ejecución.
 * Filename: snippet_manager.php
 * https://vibecodingmexico.com/snippets-5-guardar-snippets/
 * Licencia: MIT
 */
// Set headers to prevent caching on the client side and proxies
header("Expires: Mon, 26 Jul 1990 05:00:00 GMT"); // Date in the past
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); // HTTP/1.0
// --- CONFIGURACIÓN ---
$ip_autorizada = '127.0.0.1'; // <-- Cambia esto por tu IP real
$ip_autorizada = '201.103.232.198'; // <-- Cambia esto por tu IP real

$cliente_ip = $_SERVER['REMOTE_ADDR'];
$motor_ia = "Gemini 3 Flash";

// 1. VALIDACIÓN DE SEGURIDAD POR IP
if ($cliente_ip !== $ip_autorizada) {
    die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>
            <h1>403 - Acceso Denegado</h1>
            <p>La dirección IP <strong>$cliente_ip</strong> no está autorizada.</p>
         </div>");
}

// 2. LÓGICA DE GUARDADO
$mensaje = "";
$error_permisos = !is_writable('.');

if (isset($_POST['confirmar_guardado'])) {
    $nombre_archivo = trim($_POST['filename']); 
    $comentario = substr(strip_tags($_POST['objetivo']), 0, 250);
    $licencia = $_POST['licencia'];
    $codigo = $_POST['codigo'];
    $fecha = date('Y-m-d H:i:s');

    // Construcción limpia: Aseguramos espacio entre <?php y el comentario
    $cabecera = "<?php" . PHP_EOL;
    $cabecera .= "/*" . PHP_EOL;
    $cabecera .= " * Autor: Alfonso Orozco Aguilar" . PHP_EOL;
    $cabecera .= " * Fecha: $fecha" . PHP_EOL;
    $cabecera .= " * Objetivo: $comentario" . PHP_EOL;
    $cabecera .= " * Filename: $nombre_archivo" . PHP_EOL;
    $cabecera .= " * Licencia: $licencia" . PHP_EOL;
    $cabecera .= " */" . PHP_EOL;
    $cabecera .= "?>" . PHP_EOL . PHP_EOL;

    if (file_put_contents($nombre_archivo, $cabecera . $codigo) !== false) {
        $link_ejecucion = "";
        
        // Detectar extensión de forma infalible
        $info = pathinfo($nombre_archivo);
        $extension = isset($info['extension']) ? strtolower($info['extension']) : '';

        if ($extension === 'php') {
            // @1@: Generación del botón de apertura
            $link_ejecucion = "<a href='./$nombre_archivo' target='_blank'><i class='fas fa-external-link-alt'></i> Abrir script</a>";
        }
        
        // @2@: Mensaje de confirmación
        $mensaje = "<div class='alert alert-success mt-3 shadow-sm d-flex align-items-center justify-content-between'>
                        <span><i class='fas fa-check-circle'></i> Archivo <strong>$nombre_archivo</strong> guardado en el servidor.</span>
                        $link_ejecucion
                    </div>";
    } else {
        $mensaje = "<div class='alert alert-danger mt-3 shadow-sm'><i class='fas fa-times-circle'></i> Error de escritura. Revisa permisos.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snippet Manager | Vibecoding</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { padding-top: 75px; padding-bottom: 80px; background-color: #f4f7f6; }
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,.05); }
        textarea.code-area { font-family: 'Consolas', monospace; font-size: 0.85rem; }
        .footer { position: fixed; bottom: 0; width: 100%; height: 60px; line-height: 60px; background-color: #ffffff; border-top: 1px solid #dee2e6; z-index: 1030; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand text-primary" href="#"><i class="fas fa-terminal"></i> Vibe<strong>Snippets</strong></a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item"><a class="nav-link" href="https://www.google.com" target="_blank"><i class="fab fa-google"></i> Google</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown"><i class="fas fa-star"></i> Principales</a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#">GPT-4o</a>
                        <a class="dropdown-item" href="#">Claude 3.5</a>
                        <a class="dropdown-item" href="#">Gemini 1.5 Pro</a>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown"><i class="fas fa-layer-group"></i> Secundarias</a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="#">Llama 3.1</a>
                        <a class="dropdown-item" href="#">Phi-3.5</a>
                        <a class="dropdown-item" href="#">DeepSeek</a>
                    </div>
                </li>
            </ul>
            <span class="navbar-text badge badge-light p-2">
                <i class="fas fa-robot text-primary"></i> Motor: <strong><?php echo $motor_ia; ?></strong>
            </span>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <?php if ($error_permisos): ?>
        <div class="alert alert-danger"><strong>Error:</strong> No hay permisos de escritura en la carpeta.</div>
    <?php endif; ?>

    <?php echo $mensaje; ?>

    <form id="snippetForm" method="POST">
        <div class="row">
            <div class="col-lg-9">
                <div class="card p-3 shadow-sm border-0">
                    <textarea name="codigo" class="form-control code-area" rows="18" required placeholder="// Pega el código aquí..."></textarea>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card p-3 shadow-sm border-0">
                    <div class="form-group">
                        <label class="small font-weight-bold">Filename:</label>
                        <input type="text" name="filename" id="filename" class="form-control form-control-sm" required placeholder="script.php">
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Objetivo:</label>
                        <textarea name="objetivo" class="form-control form-control-sm" maxlength="250" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="small font-weight-bold">Licencia:</label>
                        <select name="licencia" class="form-control form-control-sm">
                            <option value="MIT" selected>MIT</option>
                            <option value="LGPL 2.1">LGPL 2.1</option>
                            <option value="BSD 3">BSD 3</option>
                            <option value="GPL 3.0">GPL 3.0</option>
                        </select>
                    </div>
                    <button type="button" id="btnCheck" class="btn btn-primary btn-block shadow-sm">
                        <i class="fas fa-save"></i> Guardar y Firmar
                    </button>
                </div>
            </div>
        </div>
        <input type="hidden" name="confirmar_guardado" value="1">
    </form>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-white py-2">
                <h5 class="modal-title small">Confirmar Sobrescritura</h5>
            </div>
            <div class="modal-body text-center">
                ¿El archivo ya existe, deseas reemplazarlo?
            </div>
            <div class="modal-footer justify-content-center py-2">
                <button type="button" class="btn btn-sm btn-light border" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btnExecuteSave" class="btn btn-sm btn-danger px-4">Sobrescribir</button>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center small">
        <span class="text-muted">PHP: <strong><?php echo phpversion(); ?></strong></span>
        <span class="text-muted text-uppercase">
            IP Cliente: <span class="badge badge-info"><?php echo $cliente_ip; ?></span> | 
            IP Servidor: <span class="badge badge-secondary"><?php echo $_SERVER['SERVER_ADDR'] ?? 'Local'; ?></span>
        </span>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('#btnCheck').click(function() {
        let filename = $('#filename').val();
        if(!filename) { alert("Nombre de archivo requerido."); return; }
        let existentes = <?php echo json_encode(array_values(array_diff(scandir('.'), array('..', '.')))); ?>;
        if (existentes.includes(filename)) {
            $('#confirmModal').modal('show');
        } else {
            $('#snippetForm').submit();
        }
    });
    $('#btnExecuteSave').click(function() { $('#snippetForm').submit(); });
});
</script>

</body>
</html>
