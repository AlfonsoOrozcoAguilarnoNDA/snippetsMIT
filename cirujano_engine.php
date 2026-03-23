<?php
/**
 * SISTEMA: SNIPPET 6 CIRUJANO ENGINE
 * https://vibecodingmexico.com/snippet-6-cirujano/
 * FECHA: 23 de marzo de 2026
 * LICENCIA: MIT
 * COAUTORÍA: Gemini 1.5 Flash (v.2026-03) & Alfonso Orozco Aguilar
 * DESCRIPCIÓN: Refactorización quirúrgica de cadenas en scripts locales con validación SHA.
 */

// 1. CONTROL DE CACHÉ ESTRICTO
header("Expires: Tue, 01 Jan 1980 10:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 2. CONFIGURACIÓN Y CONEXIÓN
include_once "config.php"; 
// Se asume $link activo desde config.php
mysqli_set_charset($link, "utf8mb4");

date_default_timezone_set("America/Mexico_City");

// 3. CREACIÓN DE TABLA SI NO EXISTE
$sql_table = "CREATE TABLE IF NOT EXISTS CIRUJANO (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cadena_original TEXT NOT NULL,
    cadena_nueva TEXT NOT NULL,
    activo VARCHAR(2) DEFAULT 'si',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
mysqli_query($link, $sql_table);

$mensaje_op = "";

// 4. PROCESAR ACCIONES (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // AGREGAR REGLA
    if (isset($_POST["action"]) && $_POST["action"] == "add_rule") {
        $orig = mysqli_real_escape_string($link, $_POST["orig"]);
        $new = mysqli_real_escape_string($link, $_POST["new"]);
        if (!empty($orig)) {
            $sql_ins = "INSERT INTO CIRUJANO (cadena_original, cadena_nueva, activo) VALUES (\"$orig\", \"$new\", 'si')";
            mysqli_query($link, $sql_ins);
        }
    }

    // ELIMINAR REGLA
    if (isset($_POST["action"]) && $_POST["action"] == "del_rule") {
        $id = (int)$_POST["id_rule"];
        mysqli_query($link, "DELETE FROM CIRUJANO WHERE id = $id");
    }

    // OPERACIÓN QUIRÚRGICA
    if (isset($_POST["action"]) && $_POST["action"] == "operar") {
        $reglas_activas = [];
        $res_reglas = mysqli_query($link, "SELECT * FROM CIRUJANO WHERE activo = 'si'");
        while ($r = mysqli_fetch_assoc($res_reglas)) { $reglas_activas[] = $r; }

        $archivos = scandir(__DIR__);
        foreach ($archivos as $arc) {
            $ruta = __DIR__ . "/" . $arc;
            if (pathinfo($arc, PATHINFO_EXTENSION) == "php" && $arc != basename(__FILE__)) {
                if (is_writable($ruta)) {
                    $cont_original = file_get_contents($ruta);
                    $nuevo_cont = $cont_original;
                    $hubo_cambio = false;

                    foreach ($reglas_activas as $reg) {
                        if (str_contains($nuevo_cont, $reg["cadena_original"])) {
                            $nuevo_cont = str_replace($reg["cadena_original"], $reg["cadena_nueva"], $nuevo_cont);
                            $hubo_cambio = true;
                        }
                    }

                    if ($hubo_cambio) {
                        $tmp_path = $ruta . ".tmp";
                        file_put_contents($tmp_path, $nuevo_cont);
                        
                        // Verificación SHA
                        if (sha1_file($tmp_path) !== sha1($cont_original)) {
                            unlink($ruta);
                            rename($tmp_path, $ruta);
                        } else {
                            unlink($tmp_path);
                        }
                    }
                }
            }
        }
        $mensaje_op = "Operación finalizada con éxito.";
    }
}

// 5. DIAGNÓSTICO DE ARCHIVOS
$reglas_check = [];
$res_c = mysqli_query($link, "SELECT * FROM CIRUJANO WHERE activo = 'si'");
while ($rc = mysqli_fetch_assoc($res_c)) { $reglas_check[] = $rc; }

$listado_archivos = [];
foreach (scandir(__DIR__) as $f) {
    if (pathinfo($f, PATHINFO_EXTENSION) == "php" && $f != basename(__FILE__)) {
        $full = __DIR__ . "/" . $f;
        $c_file = file_get_contents($full);
        $alert = false;
        foreach ($reglas_check as $rg) {
            if (str_contains($c_file, $rg["cadena_original"])) { $alert = true; break; }
        }
        $listado_archivos[] = [
            "nombre" => $f,
            "status" => $alert ? "danger" : "success",
            "writable" => is_writable($full)
        ];
    }
}

// Información de Versiones
$php_v = phpversion();
$db_v_res = mysqli_query($link, "SELECT VERSION() AS v");
$db_v_row = mysqli_fetch_assoc($db_v_res);
$db_v = $db_v_row["v"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIRUJANO - System Refactor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body { background-color: #0f0f0f; color: #dcdcdc; padding-top: 70px; padding-bottom: 70px; font-family: "Segoe UI", Tahoma, sans-serif; }
        .navbar { border-bottom: 2px solid #007bff; }
        .footer { background-color: #1a1a1a; border-top: 1px solid #333; height: 50px; line-height: 50px; }
        .card { background-color: #1a1a1a; border: 1px solid #333; margin-bottom: 20px; }
        .table { color: #dcdcdc; }
        .badge-danger { animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .btn-cirujano { background-color: #0056b3; border-color: #004a99; color: white; transition: 0.3s; }
        .btn-cirujano:hover { background-color: #007bff; color: white; transform: scale(1.02); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <a class="navbar-brand text-primary" href="#"><i class="fas fa-syringe"></i> CIRUJANO v2.0</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navMain">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="https://www.google.com" target="_blank text-white"><i class="fab fa-google"></i> Google</a>
            </li>
            <li class="nav-item dropdown text-white">
                <a class="nav-link dropdown-toggle" href="#" id="dropGen" data-toggle="dropdown">Opciones</a>
                <div class="dropdown-menu bg-dark text-white border-secondary">
                    <a class="dropdown-item text-white" href="#">Herramientas</a>
                    <a class="dropdown-item text-white" href="#">Reportes</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-white" href="#">Configuración</a>
                </div>
            </li>
        </ul>
        <span class="navbar-text text-info">
            Coautor: Gemini 1.5 Flash (2026-03)
        </span>
    </div>
</nav>

<div class="container-fluid">
    <?php if ($mensaje_op != "") { echo "<div class=\"alert alert-info\">$mensaje_op</div>"; } ?>

    <div class="row">
        <div class="col-md-7">
            <div class="card shadow">
                <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-microscope"></i> Diagnóstico Quirúrgico</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="operar">
                        <button type="submit" class="btn btn-cirujano">
                            <i class="fas fa-user-md"></i> OPERAR AHORA
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Script PHP</th>
                                <th>Estado</th>
                                <th>Permisos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listado_archivos as $arc_info): ?>
                            <tr>
                                <td><?php echo $arc_info["nombre"]; ?></td>
                                <td>
                                    <?php if ($arc_info["status"] == "danger"): ?>
                                        <span class="badge badge-danger"><i class="fas fa-radiation"></i> INCISIÓN REQUERIDA</span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><i class="fas fa-check-circle"></i> SALUDABLE</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $arc_info["writable"] ? "Escritura OK" : "SOLO LECTURA"; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card shadow border-info">
                <div class="card-header bg-dark text-info">
                    <h5 class="mb-0"><i class="fas fa-book-medical"></i> Diccionario de Cadenas</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_rule">
                        <div class="form-group">
                            <label>Cadena Original</label>
                            <input type="text" name="orig" class="form-control bg-dark text-white border-secondary" placeholder="Ej: cdn.old.com/bootstrap..." required>
                        </div>
                        <div class="form-group">
                            <label>Cadena Nueva</label>
                            <input type="text" name="new" class="form-control bg-dark text-white border-secondary" placeholder="Ej: https://cdn.jsdelivr.net/..." required>
                        </div>
                        <button type="submit" class="btn btn-block btn-info">Agregar Regla</button>
                    </form>

                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-dark border-secondary">
                            <thead><tr><th>Original</th><th>Nueva</th><th>Acción</th></tr></thead>
                            <tbody>
                                <?php
                                $rules = mysqli_query($link, "SELECT * FROM CIRUJANO ORDER BY id DESC");
                                while ($rw = mysqli_fetch_assoc($rules)):
                                ?>
                                <tr>
                                    <td class="small text-truncate" style="max-width: 100px;"><?php echo htmlspecialchars($rw["cadena_original"]); ?></td>
                                    <td class="small text-truncate" style="max-width: 100px;"><?php echo htmlspecialchars($rw["cadena_nueva"]); ?></td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="del_rule">
                                            <input type="hidden" name="id_rule" value="<?php echo $rw["id"]; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="footer fixed-bottom text-muted px-4 bg-dark">
    <div class="d-flex justify-content-between">
        <span>© 2026 - Licencia MIT | <strong>CIRUJANO</strong></span>
        <span>
            <i class="fab fa-php"></i> PHP: <?php echo $php_v; ?> | 
            <i class="fas fa-database"></i> MariaDB: <?php echo $db_v; ?>
        </span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
