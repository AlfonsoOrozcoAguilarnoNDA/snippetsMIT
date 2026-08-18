<?php
/**
Autor: Alfonso Orozco Aguilar, como coautor Gemini no se identifica version en Chat
Objetivo: Ver loggeado desde wordpress,. en una pagina, el estado actual de memoria, disco y load
          de un servidor. Algunas operaciones pueden estar bloqueadas en servers especificos
          Si no estas logueado no ves nada.
Fecha: 17 - Agosto 2026          
**/
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
<?php
/**
 * Monitor básico de servidor para WordPress
 */

// Cargar el entorno de WordPress para usar sus funciones de autenticación
if ( file_exists( __DIR__ . '/wp-load.php' ) ) {
    require_once __DIR__ . '/wp-load.php';
} else {
    die( 'Error: No se encontró wp-load.php. Asegúrate de colocar este archivo en la raíz de WordPress.' );
}

// Restringir el acceso estrictamente a usuarios administradores
if ( ! is_user_logged_in() || ! current_user_can( 'administrator' ) ) {
    wp_die( 'Acceso denegado: Se requieren permisos de administrador.' );
}

// -------------------------------------------------------------
// CONFIGURACIÓN DE ZONA HORARIA Y HORA ACTUAL
// -------------------------------------------------------------
date_default_timezone_set( 'America/Mexico_City' );
$hora_mexico = date( 'Y-m-d H:i:s T' );

// -------------------------------------------------------------
// PROMEDIO DE CARGA DE CPU (LOAD AVERAGE)
// -------------------------------------------------------------
$load_avg = array( 'N/A', 'N/A', 'N/A' );
if ( function_exists( 'sys_getloadavg' ) ) {
    $get_load = sys_getloadavg();
    if ( is_array( $get_load ) && count( $get_load ) >= 3 ) {
        $load_avg = array(
            round( $get_load[0], 2 ),
            round( $get_load[1], 2 ),
            round( $get_load[2], 2 ),
        );
    }
}

// -------------------------------------------------------------
// METRICAS DE RUTA Y DISCO
// -------------------------------------------------------------
$ruta_absoluta = __FILE__;

$disco_total = disk_total_space( __DIR__ );
$disco_libre = disk_free_space( __DIR__ );
$disco_usado = $disco_total - $disco_libre;
$disco_porcentaje_usado = ( $disco_total > 0 ) ? round( ( $disco_usado / $disco_total ) * 100, 2 ) : 0;
$disco_porcentaje_libre = ( $disco_total > 0 ) ? round( ( $disco_libre / $disco_total ) * 100, 2 ) : 0;

// -------------------------------------------------------------
// METRICAS DE MEMORIA RAM (Debian 13 via /proc/meminfo)
// -------------------------------------------------------------
$ram_total = 0;
$ram_libre = 0;
$ram_disponible = 0;

if ( is_readable( '/proc/meminfo' ) ) {
    $meminfo = file_get_contents( '/proc/meminfo' );
    if ( preg_match( '/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
        $ram_total = $matches[1] * 1024;
    }
    if ( preg_match( '/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
        $ram_disponible = $matches[1] * 1024;
    }
}

// Si MemAvailable no está disponible, calculamos la libre directa
if ( $ram_disponible === 0 && is_readable( '/proc/meminfo' ) ) {
    if ( preg_match( '/MemFree:\s+(\d+)\s+kB/', $meminfo, $matches ) ) {
        $ram_disponible = $matches[1] * 1024;
    }
}

$ram_usada = $ram_total - $ram_disponible;
$ram_porcentaje_libre = ( $ram_total > 0 ) ? round( ( $ram_disponible / $ram_total ) * 100, 2 ) : 0;
$ram_porcentaje_usado = ( $ram_total > 0 ) ? round( ( $ram_usada / $ram_total ) * 100, 2 ) : 0;

// Función auxiliar para dar formato a bytes
function formatear_bytes( $bytes, $precision = 2 ) {
    $unidades = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    $bytes = max( $bytes, 0 );
    $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
    $pow = min( $pow, count( $unidades ) - 1 );
    $bytes /= pow( 1024, $pow );
    return round( $bytes, $precision ) . ' ' . $unidades[$pow];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado del Servidor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="bg-light py-4">

<div class="container">

    <!-- RUTA ABSOLUTA -->
    <div class="card mb-4 border-info shadow-sm">
        <div class="card-body bg-white text-dark">
            <h6 class="text-muted mb-1"><i class="fas fa-folder-open mr-2"></i>Ruta Absoluta del Archivo:</h6>
            <code class="h5 text-primary text-break"><?php echo htmlspecialchars( $ruta_absoluta ); ?></code>
        </div>
    </div>

    <!-- HORA LOCAL Y CPU LOAD AVERAGE -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card shadow-sm border-left-primary h-100 py-2">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">
                        <i class="far fa-clock mr-1"></i> Hora Servidor (México)
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-dark"><?php echo $hora_mexico; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-left-success h-100 py-2">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">
                        <i class="fas fa-microchip mr-1"></i> CPU Load Average (1m, 5m, 15m)
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-dark">
                        <span class="badge badge-secondary p-2"><?php echo $load_avg[0]; ?></span>
                        <span class="badge badge-secondary p-2"><?php echo $load_avg[1]; ?></span>
                        <span class="badge badge-secondary p-2"><?php echo $load_avg[2]; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        <!-- MEMORIA RAM -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white font-weight-bold">
                    <i class="fas fa-memory mr-2"></i>Memoria RAM
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Total:</strong> <?php echo formatear_bytes( $ram_total ); ?></p>
                    <p class="mb-1"><strong>Disponible:</strong> <?php echo formatear_bytes( $ram_disponible ); ?> (<?php echo $ram_porcentaje_libre; ?>%)</p>
                    <p class="mb-3"><strong>En uso:</strong> <?php echo formatear_bytes( $ram_usada ); ?> (<?php echo $ram_porcentaje_usado; ?>%)</p>

                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: <?php echo $ram_porcentaje_usado; ?>%;" 
                             aria-valuenow="<?php echo $ram_porcentaje_usado; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo $ram_porcentaje_usado; ?>% Usado
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DISCO DURO -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-dark text-white font-weight-bold">
                    <i class="fas fa-hdd mr-2"></i>Almacenamiento (Disco)
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Capacidad Total:</strong> <?php echo formatear_bytes( $disco_total ); ?></p>
                    <p class="mb-1"><strong>Espacio Libre:</strong> <?php echo formatear_bytes( $disco_libre ); ?> (<?php echo $disco_porcentaje_libre; ?>%)</p>
                    <p class="mb-3"><strong>Espacio Usado:</strong> <?php echo formatear_bytes( $disco_usado ); ?> (<?php echo $disco_porcentaje_usado; ?>%)</p>

                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-warning text-dark" role="progressbar" 
                             style="width: <?php echo $disco_porcentaje_usado; ?>%;" 
                             aria-valuenow="<?php echo $disco_porcentaje_usado; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo $disco_porcentaje_usado; ?>% Usado
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
