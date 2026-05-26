<?php
/**
 * LLM Bitácora de Campo
 * Registro diario de uso y evaluación de modelos LLM
 * 
 * @author    Alfonso Orozco Aguilar (VibeCodingMexico.com)
 * @coauthor  Kimi K2.6 (Moonshot AI)
 * @license   MIT
 * @version   1.0.0
 * @date      2026-05-26
 * 
 * Ubicación: /wp-content/themes/tu-tema/llm-bitacora.php
 * Acceso:    https://tusitio.com/wp-content/themes/tu-tema/llm-bitacora.php
 */
// se asdume un wordpress. ver mas informacion en :
// https://vibecodingmexico.com/snippet-evaluador-de-llm/
// ---------------------------------------------------------------------------
// CARGAR WORDPRESS
// ---------------------------------------------------------------------------
$theme_dir = dirname(__FILE__);
$wp_load_paths = [
    $theme_dir . '/../../../wp-load.php',
    $theme_dir . '/../../wp-load.php',
    $theme_dir . '/../wp-load.php',
    $theme_dir . '/wp-load.php',
  //  ABSPATH . 'wp-load.php',
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Error: No se pudo cargar WordPress. Verifica la ubicación del archivo.');
}

// ---------------------------------------------------------------------------
// CONFIGURACIÓN
// ---------------------------------------------------------------------------
$current_user = wp_get_current_user();
$is_logged_in = is_user_logged_in();
$usuario_actual = $is_logged_in ? $current_user->user_login : 'Visitante';

// Zona horaria de WordPress
$wp_timezone = wp_timezone_string();
$tz = new DateTimeZone($wp_timezone);
$now = new DateTime('now', $tz);

// Tabla
$wpdb = $GLOBALS['wpdb'];
$table_name = $wpdb->prefix . 'llm_bitacora';

// Categorías
$categorias = ['analisis', 'legal', 'ortografia', 'codigo', 'imagen', 'otro'];

// ---------------------------------------------------------------------------
// CREAR TABLA SI NO EXISTE (auto-install)
// ---------------------------------------------------------------------------
$charset_collate = $wpdb->get_charset_collate();
$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    fecha_hora datetime NOT NULL,
    llm_nombre varchar(50) NOT NULL,
    llm_version varchar(30) NOT NULL,
    categoria enum('analisis','legal','ortografia','codigo','imagen','otro') NOT NULL,
    calificacion tinyint(2) unsigned NOT NULL,
    thumb enum('up','down') NOT NULL,
    notas text DEFAULT NULL,
    usuario_wp varchar(60) NOT NULL,
    creado_en timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fecha (fecha_hora),
    KEY idx_llm (llm_nombre),
    KEY idx_categoria (categoria)
) {$charset_collate};";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

// ---------------------------------------------------------------------------
// PROCESAR ACCIONES (solo usuarios logueados)
// ---------------------------------------------------------------------------
$mensaje = '';
$tipo_mensaje = '';

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificar nonce
    if (!isset($_POST['llm_nonce']) || !wp_verify_nonce($_POST['llm_nonce'], 'llm_bitacora_action')) {
        $mensaje = 'Error de seguridad: nonce inválido.';
        $tipo_mensaje = 'danger';
    } else {

        $accion = isset($_POST['accion']) ? sanitize_text_field($_POST['accion']) : '';

        // GUARDAR / ACTUALIZAR
        if ($accion === 'guardar' || $accion === 'actualizar') {

            // Validar checkbox de confirmación
            if (!isset($_POST['confirmar']) || $_POST['confirmar'] !== '1') {
                $mensaje = 'Debes confirmar la acción marcando el checkbox.';
                $tipo_mensaje = 'warning';
            } else {

                $fecha_hora = sanitize_text_field($_POST['fecha_hora']);
                $llm_nombre = sanitize_text_field($_POST['llm_nombre']);
                $llm_version = sanitize_text_field($_POST['llm_version']);
                $categoria = sanitize_text_field($_POST['categoria']);
                $calificacion = intval($_POST['calificacion']);
                $thumb = sanitize_text_field($_POST['thumb']);
                $notas = sanitize_textarea_field($_POST['notas']);

                // Validaciones
                $errores = [];
                if (empty($fecha_hora)) $errores[] = 'Fecha/hora es obligatoria.';
                if (empty($llm_nombre)) $errores[] = 'Nombre del LLM es obligatorio.';
                if (empty($llm_version)) $errores[] = 'Versión del LLM es obligatoria.';
                if (!in_array($categoria, $categorias)) $errores[] = 'Categoría inválida.';
                if ($calificacion < 0 || $calificacion > 10) $errores[] = 'Calificación debe ser 0-10.';
                if (!in_array($thumb, ['up', 'down'])) $errores[] = 'Thumb inválido.';

                if (empty($errores)) {

                    $datos = [
                        'fecha_hora'   => $fecha_hora,
                        'llm_nombre'   => $llm_nombre,
                        'llm_version'  => $llm_version,
                        'categoria'    => $categoria,
                        'calificacion' => $calificacion,
                        'thumb'        => $thumb,
                        'notas'        => $notas,
                        'usuario_wp'   => $usuario_actual,
                    ];

                    if ($accion === 'actualizar' && !empty($_POST['id'])) {
                        $id = intval($_POST['id']);
                        $result = $wpdb->update($table_name, $datos, ['id' => $id], 
                            ['%s','%s','%s','%s','%d','%s','%s','%s'], ['%d']);
                        if ($result !== false) {
                            $mensaje = 'Entrada actualizada correctamente.';
                            $tipo_mensaje = 'success';
                        } else {
                            $mensaje = 'Error al actualizar.';
                            $tipo_mensaje = 'danger';
                        }
                    } else {
                        $result = $wpdb->insert($table_name, $datos, 
                            ['%s','%s','%s','%s','%d','%s','%s','%s']);
                        if ($result) {
                            $mensaje = 'Entrada guardada correctamente.';
                            $tipo_mensaje = 'success';
                        } else {
                            $mensaje = 'Error al guardar: ' . $wpdb->last_error;
                            $tipo_mensaje = 'danger';
                        }
                    }
                } else {
                    $mensaje = 'Errores: ' . implode(' ', $errores);
                    $tipo_mensaje = 'danger';
                }
            }
        }

        // ELIMINAR
        if ($accion === 'eliminar' && !empty($_POST['id'])) {
            $id = intval($_POST['id']);
            $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
            if ($result) {
                $mensaje = 'Entrada eliminada correctamente.';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar.';
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// ---------------------------------------------------------------------------
// OBTENER DATOS PARA EDICIÓN
// ---------------------------------------------------------------------------
$editar_id = isset($_GET['editar']) ? intval($_GET['editar']) : 0;
$entrada_editar = null;
if ($is_logged_in && $editar_id > 0) {
    $entrada_editar = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d", $editar_id
    ));
}

// ---------------------------------------------------------------------------
// CONSULTAS
// ---------------------------------------------------------------------------

// Últimos 30 días para la tabla principal
$fecha_30_dias = (clone $now)->modify('-30 days')->format('Y-m-d H:i:s');
$entradas_recientes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE fecha_hora >= %s ORDER BY fecha_hora DESC",
    $fecha_30_dias
));

// Evaluación general (universo completo)
$evaluacion_general = $wpdb->get_results(
    "SELECT 
        llm_nombre,
        llm_version,
        COUNT(*) as total_usos,
        ROUND(AVG(calificacion), 2) as promedio_calif,
        SUM(CASE WHEN thumb = 'up' THEN 1 ELSE 0 END) as thumbs_up,
        SUM(CASE WHEN thumb = 'down' THEN 1 ELSE 0 END) as thumbs_down,
        ROUND(SUM(CASE WHEN thumb = 'up' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as pct_up
    FROM {$table_name}
    GROUP BY llm_nombre, llm_version
    ORDER BY promedio_calif DESC, pct_up DESC"
);

// Conteos por categoría (últimos 30 días)
$conteo_categorias = $wpdb->get_results($wpdb->prepare(
    "SELECT categoria, COUNT(*) as total, ROUND(AVG(calificacion), 1) as promedio 
     FROM {$table_name} 
     WHERE fecha_hora >= %s 
     GROUP BY categoria 
     ORDER BY total DESC",
    $fecha_30_dias
));

// ---------------------------------------------------------------------------
// HTML
// ---------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LLM Bitácora de Campo — Kimi K2.6</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">

    <style>
        body { padding-top: 70px; padding-bottom: 60px; background: #f8f9fa; }
        .navbar-brand { font-weight: bold; }
        .panel-eval { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .thumb-up { color: #28a745; }
        .thumb-down { color: #dc3545; }
        .calif-badge { font-size: 1.1em; }
        .footer-fixed {
            position: fixed; bottom: 0; width: 100%; height: 50px;
            background: #343a40; color: #adb5bd; line-height: 50px; z-index: 1030;
        }
        .readonly-msg { background: #e9ecef; border-left: 4px solid #6c757d; }
        .form-required label::after { content: " *"; color: #dc3545; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-clipboard-list"></i> LLM Bitácora de Campo
            <span class="badge badge-info ml-2">Kimi K2.6</span>
        </a>
        <div class="navbar-text text-light">
            <?php if ($is_logged_in): ?>
                <i class="fas fa-user-check"></i> <?php echo esc_html($usuario_actual); ?> 
                <span class="badge badge-success">Admin</span>
            <?php else: ?>
                <i class="fas fa-user"></i> Visitante 
                <span class="badge badge-secondary">Solo lectura</span>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container-fluid">

    <!-- MENSAJES -->
    <?php if ($mensaje): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show">
                <?php echo esc_html($mensaje); ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- PANEL DE EVALUACIÓN GENERAL -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card panel-eval">
                <div class="card-header bg-dark text-white">
                    <i class="fas fa-chart-bar"></i> Evaluación General (Histórico Completo)
                </div>
                <div class="card-body">
                    <?php if (count($evaluacion_general) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>LLM</th>
                                        <th>Versión</th>
                                        <th>Usos</th>
                                        <th>Promedio</th>
                                        <th><i class="fas fa-thumbs-up thumb-up"></i></th>
                                        <th><i class="fas fa-thumbs-down thumb-down"></i></th>
                                        <th>% Up</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($evaluacion_general as $eval): 
                                        $pct_class = $eval->pct_up >= 70 ? 'badge-success' : ($eval->pct_up >= 40 ? 'badge-warning' : 'badge-danger');
                                    ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($eval->llm_nombre); ?></strong></td>
                                        <td><?php echo esc_html($eval->llm_version); ?></td>
                                        <td><span class="badge badge-secondary"><?php echo $eval->total_usos; ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $eval->promedio_calif >= 7 ? 'badge-success' : ($eval->promedio_calif >= 4 ? 'badge-warning' : 'badge-danger'); ?> calif-badge">
                                                <?php echo $eval->promedio_calif; ?>/10
                                            </span>
                                        </td>
                                        <td class="thumb-up"><strong><?php echo $eval->thumbs_up; ?></strong></td>
                                        <td class="thumb-down"><strong><?php echo $eval->thumbs_down; ?></strong></td>
                                        <td><span class="badge <?php echo $pct_class; ?>"><?php echo $eval->pct_up; ?>%</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-inbox"></i> No hay datos registrados aún.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO (solo logueados) -->
    <?php if ($is_logged_in): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-<?php echo $entrada_editar ? 'edit' : 'plus-circle'; ?>"></i> 
                    <?php echo $entrada_editar ? 'Editar Entrada #' . $entrada_editar->id : 'Nueva Entrada'; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php wp_nonce_field('llm_bitacora_action', 'llm_nonce'); ?>
                        <input type="hidden" name="accion" value="<?php echo $entrada_editar ? 'actualizar' : 'guardar'; ?>">
                        <?php if ($entrada_editar): ?>
                            <input type="hidden" name="id" value="<?php echo $entrada_editar->id; ?>">
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-3 form-group form-required">
                                <label>Fecha/Hora (CDMX)</label>
                                <input type="datetime-local" name="fecha_hora" class="form-control" required
                                    value="<?php echo $entrada_editar ? date('Y-m-d\TH:i', strtotime($entrada_editar->fecha_hora)) : $now->format('Y-m-d\TH:i'); ?>">
                            </div>
                            <div class="col-md-2 form-group form-required">
                                <label>LLM Nombre</label>
                                <input type="text" name="llm_nombre" class="form-control" required 
                                    placeholder="Ej: Kimi" maxlength="50"
                                    value="<?php echo $entrada_editar ? esc_attr($entrada_editar->llm_nombre) : ''; ?>">
                            </div>
                            <div class="col-md-2 form-group form-required">
                                <label>Versión</label>
                                <input type="text" name="llm_version" class="form-control" required 
                                    placeholder="Ej: K2.6" maxlength="30"
                                    value="<?php echo $entrada_editar ? esc_attr($entrada_editar->llm_version) : ''; ?>">
                            </div>
                            <div class="col-md-2 form-group form-required">
                                <label>Categoría</label>
                                <select name="categoria" class="form-control" required>
                                    <option value="">-- Selecciona --</option>
                                    <?php foreach ($categorias as $cat): 
                                        $selected = ($entrada_editar && $entrada_editar->categoria === $cat) ? 'selected' : '';
                                        $label = ucfirst($cat);
                                    ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1 form-group form-required">
                                <label>Calif (0-10)</label>
                                <input type="number" name="calificacion" class="form-control" required 
                                    min="0" max="10" step="1"
                                    value="<?php echo $entrada_editar ? esc_attr($entrada_editar->calificacion) : '5'; ?>">
                            </div>
                            <div class="col-md-2 form-group form-required">
                                <label>Thumb</label>
                                <select name="thumb" class="form-control" required>
                                    <option value="">-- --</option>
                                    <option value="up" <?php echo ($entrada_editar && $entrada_editar->thumb === 'up') ? 'selected' : ''; ?>>
                                        <i class="fas fa-thumbs-up"></i> 👍 Up
                                    </option>
                                    <option value="down" <?php echo ($entrada_editar && $entrada_editar->thumb === 'down') ? 'selected' : ''; ?>>
                                        <i class="fas fa-thumbs-down"></i> 👎 Down
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-10 form-group">
                                <label>Notas (opcional)</label>
                                <textarea name="notas" class="form-control" rows="2" maxlength="1000"
                                    placeholder="Observaciones breves..."><?php echo $entrada_editar ? esc_textarea($entrada_editar->notas) : ''; ?></textarea>
                            </div>
                            <div class="col-md-2 form-group d-flex align-items-end">
                                <div class="form-check mb-2">
                                    <input type="checkbox" name="confirmar" value="1" class="form-check-input" id="confirmar" required>
                                    <label class="form-check-label" for="confirmar">
                                        <strong>Confirmar envío</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-<?php echo $entrada_editar ? 'warning' : 'primary'; ?>">
                                    <i class="fas fa-<?php echo $entrada_editar ? 'save' : 'save'; ?>"></i> 
                                    <?php echo $entrada_editar ? 'Actualizar Entrada' : 'Guardar Entrada'; ?>
                                </button>
                                <?php if ($entrada_editar): ?>
                                    <a href="?" class="btn btn-secondary ml-2">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- MENSAJE PARA VISITANTES -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="readonly-msg p-3 rounded">
                <i class="fas fa-lock"></i> 
                <strong>Modo solo lectura:</strong> Inicia sesión en WordPress para agregar, editar o eliminar entradas.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TABLA DE ÚLTIMOS 30 DÍAS -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-history"></i> 
                    Registros de los Últimos 30 Días 
                    <span class="badge badge-light"><?php echo count($entradas_recientes); ?> entradas</span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($entradas_recientes) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha/Hora</th>
                                        <th>LLM</th>
                                        <th>Versión</th>
                                        <th>Categoría</th>
                                        <th>Calif</th>
                                        <th>Thumb</th>
                                        <th>Notas</th>
                                        <th>Usuario</th>
                                        <?php if ($is_logged_in): ?><th>Acciones</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entradas_recientes as $entry): 
                                        $fecha_formateada = (new DateTime($entry->fecha_hora, new DateTimeZone('UTC')))->setTimezone($tz)->format('d/m/Y H:i');
                                    ?>
                                    <tr>
                                        <td><span class="badge badge-dark"><?php echo $entry->id; ?></span></td>
                                        <td><?php echo esc_html($fecha_formateada); ?></td>
                                        <td><strong><?php echo esc_html($entry->llm_nombre); ?></strong></td>
                                        <td><?php echo esc_html($entry->llm_version); ?></td>
                                        <td><span class="badge badge-info"><?php echo ucfirst(esc_html($entry->categoria)); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $entry->calificacion >= 7 ? 'badge-success' : ($entry->calificacion >= 4 ? 'badge-warning' : 'badge-danger'); ?>">
                                                <?php echo $entry->calificacion; ?>/10
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($entry->thumb === 'up'): ?>
                                                <i class="fas fa-thumbs-up thumb-up fa-lg"></i>
                                            <?php else: ?>
                                                <i class="fas fa-thumbs-down thumb-down fa-lg"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo esc_html($entry->notas); ?></small></td>
                                        <td><small class="text-muted"><?php echo esc_html($entry->usuario_wp); ?></small></td>
                                        <?php if ($is_logged_in): ?>
                                        <td>
                                            <a href="?editar=<?php echo $entry->id; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="" style="display:inline;" 
                                                  onsubmit="return confirm('¿Eliminar entrada #<?php echo $entry->id; ?>?');">
                                                <?php wp_nonce_field('llm_bitacora_action', 'llm_nonce'); ?>
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $entry->id; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-inbox fa-2x"></i>
                            <p class="mt-2">No hay registros en los últimos 30 días.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CONTEO POR CATEGORÍA -->
    <?php if (count($conteo_categorias) > 0): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h6><i class="fas fa-tags"></i> Distribución por Categoría (30 días)</h6>
                    <div class="row">
                        <?php foreach ($conteo_categorias as $cat): ?>
                        <div class="col-md-2 text-center">
                            <div class="p-2 border rounded bg-white">
                                <strong><?php echo ucfirst(esc_html($cat->categoria)); ?></strong><br>
                                <span class="badge badge-primary"><?php echo $cat->total; ?></span>
                                <small class="text-muted">avg <?php echo $cat->promedio; ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- FOOTER -->
<footer class="footer-fixed">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 text-left">
                <small>
                    <i class="fas fa-code"></i> PHP <?php echo PHP_VERSION; ?> | 
                    <i class="fas fa-database"></i> MySQL <?php echo $wpdb->db_version(); ?> | 
                    <i class="fas fa-shield-alt"></i> MIT
                </small>
            </div>
            <div class="col-md-6 text-right">
                <small>
                    <i class="fas fa-robot"></i> Coautor: Kimi K2.6 (Moonshot AI) | 
                    <i class="fas fa-user"></i> Autor: VibeCodingMexico.com
                </small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
