<?php
/**
 * WordPress Index Generator
 * Genera un índice cronológico de lectura organizado por categorías.
 * * @author    Alfonso Orozco Aguilar
 *   @coauthor  Gemini, modificado a mano.
 * @date      20 de marzo de 2026
 * @license   MIT License
 * * Copyright (c) 2026 Alfonso Orozco Aguilar
 * * Se concede permiso, de forma gratuita, a cualquier persona que obtenga una copia
 * de este software y los archivos de documentación asociados...
 * Uso :
 * 1 genere una pagina con titulo indice en su blog
 * 2 Copie este archivo como wp-index-generator.php en el mismo nivel de su wordpress.
 */

// 1. Cargar el entorno de WordPress
require_once('wp-load.php');

/* Activa esto para que solo el admin pueda hacerlo, yo lo dejo abierto
if ( !is_user_logged_in() || !current_user_can('manage_options') ) {
    header('HTTP/1.1 403 Forbidden');
    die('Acceso denegado: Se requiere sesión de administrador activa.');
}
*/

$csh=0; // contador
// Seguridad: Solo administradores o ejecución por CLI (Debian/Server)
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    if (php_sapi_name() !== 'cli') {
        die('Acceso denegado: Se requieren privilegios de administrador.');
    }
}

global $wpdb;

/** * LÓGICA DE EXTRACCIÓN Y FORMATEO
 */
$categories = get_categories(array('orderby' => 'name', 'order' => 'DESC'));
$html_output = '<div class="wp-reading-index py-3">';

foreach ($categories as $cat) {
    $posts = get_posts(array(
        'category'       => $cat->term_id,
        'orderby'        => 'date',
        'order'          => 'ASC', // Orden de lectura: Antiguo -> Reciente
        'posts_per_page' => -1
    ));

    if ($posts) {
        $html_output .= "
        <div class='card mb-4 border-0 shadow-sm'>
            <div class='card-header bg-white border-bottom'>
                <h4 class='m-0 h5 text-primary'><i class='fas fa-bookmark mr-2'></i> " . esc_html($cat->name) . "</h4>
            </div>
            <div class='list-group list-group-flush'><ol>";
        
        foreach ($posts as $post) {
            $csh ++;
            $link = get_permalink($post->ID);
            $date = get_the_date('d M, Y', $post->ID);
            $html_output .= "<li><span class='badge badge-light badge-pill text-muted font-weight-normal'>{$date}</span>
                <a href='{$link}' class='list-group-item list-group-item-action d-flex justify-content-between align-items-center'>
                    <span><i class='far fa-file-alt mr-4 text-muted'></i>&nbsp;&nbsp;" . get_the_title($post->ID) . "</span></a>";
        }

        $html_output .= '</ol>
        </div></div><h6>';
    }
}
$html_output .= "$csh Entradas en total".'
        <hr>
                
                <h3><i class="fas fa-external-link-alt mr-2">&nbsp;&nbsp;&nbsp;<a href="../wp-index-generator.php" target="_blank">
    </i> Regenerar Índice en vivo
</a></h3>
        
        </div>';

/**
 * ACTUALIZACIÓN DE LA PÁGINA "INDICE"
 */
$target_title = 'Indice';
$page_id = $wpdb->get_var($wpdb->prepare(
    "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'page' AND post_status = 'publish' LIMIT 1",
    $target_title
));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WP Index Generator | Console</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.2/css/all.min.css">
    <style>
        body { background: #f0f2f5; color: #333; }
        .console-card { max-width: 700px; margin: 50px auto; border-radius: 15px; overflow: hidden; }
        .author-credit { font-size: 0.85rem; letter-spacing: 0.5px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card console-card shadow-lg border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span class="font-weight-bold"><i class="fas fa-terminal mr-2"></i>WP_INDEX_GEN v1.0</span>
            <span class="badge badge-success">20 Marzo 2026</span>
        </div>
        
        <div class="card-body bg-white p-5 text-center">
            <?php if ($page_id): 
                wp_update_post(['ID' => $page_id, 'post_content' => $html_output]); 
            ?>
                <i class="fas fa-sync-alt fa-3x text-success mb-4 fa-spin" style="animation-duration: 3s;"></i>
                <h2 class="h4">Página Actualizada con Éxito</h2>
                <p class="text-muted small">El índice cronológico ha sido inyectado en la página "Indice" (ID: <?php echo $page_id; ?>).</p>
                <hr>
                <a href="<?php echo get_permalink($page_id); ?>" class="btn btn-primary btn-lg shadow-sm" target="_blank">
                    <i class="fas fa-external-link-alt mr-2"></i>Ver el Índice en vivo
                </a>
            <?php else: ?>
                <i class="fas fa-exclamation-circle fa-3x text-danger mb-4"></i>
                <h2 class="h4 text-danger">Página no encontrada</h2>
                <p>No existe una página con el título exacto <strong>"Indice"</strong>.</p>
                <p class="small text-muted font-italic">Crea una página nueva llamada "Indice" y vuelve a ejecutar este script.</p>
            <?php endif; ?>
        </div>

        <div class="card-footer bg-light p-3">
            <div class="row align-items-center">
                <div class="col-sm-6 text-muted author-credit">
                    Desarrollado por: <strong>Alfonso Orozco Aguilar</strong>
                </div>
                <div class="col-sm-6 text-right text-muted small">
                    Licencia MIT | PHP 8.x Compatible
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
