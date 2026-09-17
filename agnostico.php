<?php
/**
 * SISTEMA: SNIPPET 
 * https://vibecodingmexico.com/mudanza-inversa/
 * FECHA: 17 de junio de 2026
 * LICENCIA: MIT
 * COAUTORÍA: Gemini 3.5 Flash (v.2026-07) & Alfonso Orozco Aguilar
 * DESCRIPCIÓN: Mostrar en el entorno de Wordpress cuantos registros tiene una base de wordpress en posts y otros datos para comparación manual.
 */
// 1. Cargar el entorno de WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/wp-load.php');

// 2. Control de acceso: Verificar Administrador
if (!is_user_logged_in() || !current_user_can('administrator')) {
    header('HTTP/1.0 403 Forbidden');
    echo "Acceso denegado. Debes iniciar sesión como Administrador de WordPress en este dominio.";
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'posts';

// A. Conteo total de registros en la tabla wp_posts (incluye todo)
$total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

// B. Último registro modificado (excluyendo auto-drafts y revisiones)
$query_modified = "SELECT ID, post_title, post_type, post_status, post_modified 
                   FROM {$table} 
                   WHERE post_status NOT IN ('auto-draft', 'trash') 
                   AND post_type NOT IN ('revision')
                   ORDER BY post_modified DESC 
                   LIMIT 1";

$last_modified = $wpdb->get_row($query_modified);

$last_id = $last_modified ? $last_modified->ID : 'N/A';
$last_title = $last_modified ? $last_modified->post_title : 'N/A';
$last_type = $last_modified ? $last_modified->post_type : 'N/A';
$last_status = $last_modified ? $last_modified->post_status : 'N/A';
$last_date = $last_modified ? $last_modified->post_modified : 'N/A';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación de Sitio</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f0f2f5; padding: 30px; margin: 0; }
        .card { background: #fff; padding: 30px; border-radius: 12px; max-width: 650px; margin: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        h2 { margin-top: 0; color: #1a1a1a; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
        .info-box { background: #e7f3fe; border-left: 4px solid #0073aa; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; line-height: 1.5; color: #1e3a8a; }
        p { margin: 14px 0; font-size: 15px; line-height: 1.5; }
        strong { color: #2c3e50; }
        .code-box { background: #272822; color: #f8f8f2; padding: 6px 10px; border-radius: 6px; font-family: monospace; display: inline-block; font-size: 14px; word-break: break-all; }
        .footer { margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; font-size: 12px; color: #777; text-align: right; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Reporte de Dominio y Verificación</h2>

        <div class="info-box">
            <strong>Objetivo:</strong> Comparar la base de datos entre servidores (cPanel vs. Debian).<br>
            <strong>Sugerencia:</strong> Compara el <strong>total de registros</strong> y la <strong>última fecha de modificación</strong> en pantallas lado a lado.
        </div>

        <p><strong>1. Ruta absoluta real:</strong><br><span class="code-box"><?php echo __DIR__; ?></span></p>
        <p><strong>2. Dominio (HTTP_HOST):</strong><br><span class="code-box"><?php echo $_SERVER['HTTP_HOST']; ?></span></p>
        <p><strong>3. Prefijo de BD detectado:</strong><br><span class="code-box"><?php echo $wpdb->prefix; ?></span></p>
        <p><strong>4. Total de filas en <?php echo $table; ?>:</strong><br><span class="code-box"><?php echo number_format($total_posts); ?> registros</span></p>
        <p><strong>5. Último contenido modificado:</strong><br>
            <span class="code-box">
                ID #<?php echo $last_id; ?> — "<?php echo htmlspecialchars($last_title); ?>"<br>
                Tipo: <?php echo $last_type; ?> | Estado: <?php echo $last_status; ?><br>
                Última modificación: <?php echo $last_date; ?>
            </span>
        </p>

        <div class="footer">
            Generado por <strong>Gemini (Versión 3 / 3.5)</strong>[cite: 1]
        </div>
    </div>
</body>
</html>
