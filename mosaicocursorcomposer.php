<?php
/**
 * Koala — Mosaicos de Auditoría Dinámicos (Paso 2)
 *
 * Generado con Composer 2.5 (Cursor AI Agent)
 * Experimento de https://vibecodingmexico.com/cursor-composer-probando-mosaico/
 * Licencia: MIT
 * 16 julio 2026
 *
 * @license MIT
 * @copyright Alfonso Orozco Aguilar
 */

$x = 72;

$excepciones = ['index.php', 'config.php'];

/**
 * Genera una rejilla de mosaicos para auditar archivos PHP de un directorio.
 *
 * @param string $directorio Ruta relativa o absoluta del directorio a inspeccionar.
 * @return string HTML de la rejilla Bootstrap.
 */
function muestra_mosaicos_php($directorio)
{
    global $x, $excepciones;

    $colores = ['primary', 'secondary', 'success', 'warning', 'danger'];
    $indice_color = 0;

    $ruta_real = realpath($directorio);
    if ($ruta_real === false || !is_dir($ruta_real)) {
        return '<div class="alert alert-danger mb-0"><i class="fas fa-times-circle mr-1"></i>Directorio no válido: '
            . htmlspecialchars((string) $directorio, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    $nombre_dir = basename($ruta_real);
    if ($nombre_dir === '' || $nombre_dir === '.' || $nombre_dir === DIRECTORY_SEPARATOR) {
        $nombre_dir = 'Directorio actual';
    }

    $icono_dir = ($directorio === '..' || $directorio === '../')
        ? 'fas fa-level-up-alt'
        : 'fas fa-folder-open';

    $html = '<div class="row no-gutters mosaico-grid">';

    $html .= '<div class="col-6 col-md-3 col-lg-2 px-1 mb-2">';
    $html .= '<div class="mosaico bg-white text-dark border">';
    $html .= '<div class="mosaico-cuerpo">';
    $html .= '<i class="' . $icono_dir . ' mosaico-icono" aria-hidden="true"></i>';
    $html .= '</div>';
    $html .= '<div class="mosaico-pie">' . htmlspecialchars($nombre_dir, ENT_QUOTES, 'UTF-8') . '</div>';
    $html .= '</div></div>';

    $patron = $ruta_real . DIRECTORY_SEPARATOR . '*.php';
    $archivos = glob($patron);
    if ($archivos === false) {
        $archivos = [];
    }

    sort($archivos, SORT_STRING | SORT_FLAG_CASE);

    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);
        $contenido = file($archivo);
        $lineas = is_array($contenido) ? count($contenido) : 0;

        $es_excepcion = in_array(strtolower($nombre), array_map('strtolower', $excepciones), true);

        if ($es_excepcion) {
            $clase_bg = 'bg-dark text-white';
            $icono = 'fas fa-database';
        } else {
            $color = $colores[$indice_color % count($colores)];
            $clase_bg = 'bg-' . $color . ' text-white';
            $icono = 'fab fa-php';
            $indice_color++;
        }

        $modificado_reciente = false;
        $mtime = filemtime($archivo);
        if ($mtime !== false) {
            $horas_desde_mod = (time() - $mtime) / 3600;
            if ($horas_desde_mod <= $x) {
                $modificado_reciente = true;
            }
        }

        if ($directorio === '.' || $directorio === './') {
            $href = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        } else {
            $href = htmlspecialchars(rtrim(str_replace('\\', '/', $directorio), '/') . '/' . $nombre, ENT_QUOTES, 'UTF-8');
        }

        $html .= '<div class="col-6 col-md-3 col-lg-2 px-1 mb-2">';
        $html .= '<a href="' . $href . '" target="_blank" rel="noopener noreferrer" class="mosaico-link d-block text-decoration-none">';
        $html .= '<div class="mosaico ' . $clase_bg . ' position-relative">';

        if ($modificado_reciente) {
            $html .= '<span class="badge badge-warning badge-alerta" title="Modificado en las últimas '
                . (int) $x . ' horas">';
            $html .= '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i>';
            $html .= '</span>';
        }

        $html .= '<div class="mosaico-cuerpo">';
        $html .= '<i class="' . $icono . ' mosaico-icono" aria-hidden="true"></i>';
        $html .= '</div>';
        $html .= '<div class="mosaico-pie d-flex justify-content-between align-items-center">';
        $html .= '<span class="mosaico-nombre text-truncate">' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '<span class="badge badge-light badge-lineas ml-1">' . (int) $lineas . '</span>';
        $html .= '</div>';
        $html .= '</div></a></div>';
    }

    $html .= '</div>';

    return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koala — Mosaicos de Auditoría</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
          integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css"
          integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0sEbZsboRHgIBf4CZ5Y722zc" crossorigin="anonymous">

    <style>
        :root {
            --koala-nav-h: 3.25rem;
            --koala-footer-h: 2.75rem;
            --koala-mosaico-h: 8.5rem;
        }

        html, body {
            height: 100%;
        }

        body {
            padding-top: var(--koala-nav-h);
            padding-bottom: var(--koala-footer-h);
            background-color: #f1f3f5;
            color: #212529;
        }

        .koala-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: var(--koala-nav-h);
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.08);
        }

        .koala-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: var(--koala-footer-h);
            background-color: #fff;
            border-top: 1px solid #dee2e6;
            font-size: 0.78rem;
            color: #6c757d;
        }

        .koala-main {
            min-height: calc(100vh - var(--koala-nav-h) - var(--koala-footer-h));
        }

        .seccion-titulo {
            font-size: 0.95rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
        }

        .mosaico {
            height: var(--koala-mosaico-h);
            border-radius: 0.35rem;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .mosaico-link:hover .mosaico {
            transform: translateY(-2px);
            box-shadow: 0 0.35rem 0.75rem rgba(0, 0, 0, 0.18);
        }

        .mosaico-cuerpo {
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mosaico-icono {
            font-size: 2rem;
            line-height: 1;
        }

        .mosaico-pie {
            flex: 0 0 auto;
            padding: 0.4rem 0.55rem;
            font-size: 0.72rem;
            background-color: rgba(0, 0, 0, 0.12);
        }

        .bg-white .mosaico-pie {
            background-color: #f8f9fa;
            color: #212529;
        }

        .mosaico-nombre {
            max-width: calc(100% - 2.5rem);
        }

        .badge-lineas {
            font-size: 0.65rem;
            font-weight: 600;
        }

        .badge-alerta {
            position: absolute;
            top: 0.35rem;
            right: 0.35rem;
            font-size: 0.65rem;
            z-index: 2;
        }

        .leyenda-aviso {
            font-size: 0.8rem;
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 0.25rem;
            padding: 0.5rem 0.75rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark koala-navbar navbar-expand">
    <a class="navbar-brand mb-0 h1" href="#">
        <i class="fas fa-paw mr-2" aria-hidden="true"></i>Koala
    </a>
    <span class="navbar-text d-none d-md-inline text-light-50 small ml-auto">
        Auditoría dinámica &middot; Composer 2.5 (Cursor AI) &middot; MIT
    </span>
</nav>

<main class="container-fluid koala-main py-3 px-2 px-md-3">
    <div class="leyenda-aviso">
        <i class="fas fa-info-circle mr-1" aria-hidden="true"></i>
        Los mosaicos con <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i></span>
        indican archivos modificados en las últimas <?php echo (int) $x; ?> horas.
    </div>

    <section class="mb-4">
        <h2 class="seccion-titulo">
            <i class="fas fa-folder mr-1 text-muted" aria-hidden="true"></i>Directorio actual
        </h2>
        <?php echo muestra_mosaicos_php('.'); ?>
    </section>

    <section>
        <h2 class="seccion-titulo">
            <i class="fas fa-level-up-alt mr-1 text-muted" aria-hidden="true"></i>Directorio padre
        </h2>
        <?php echo muestra_mosaicos_php('..'); ?>
    </section>
</main>

<footer class="koala-footer d-flex align-items-center justify-content-between px-3">
    <span>&copy; <?php echo date('Y'); ?> Koala</span>
    <span class="text-center flex-grow-1 d-none d-sm-inline">
        Generado con <strong>Composer 2.5</strong> (Cursor AI) &mdash; Licencia MIT
    </span>
    <span class="text-muted">PHP <?php echo PHP_VERSION; ?></span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>
</html>
