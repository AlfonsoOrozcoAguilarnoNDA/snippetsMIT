<?php
/**
 * SISTEMA: SNIPPET ideentificador / Bloqueador de Ips en base a archivo raw apache
 * https://vibecodingmexico.com/snippets-lector-de-raw-logs/
 * FECHA: 29 de abril de 2026
 * LICENCIA: MIT
 * COAUTORÍA: Gemini 3 Flash (v.2026-04) & Alfonso Orozco Aguilar
 * DESCRIPCIÓN: Refactorización quirúrgica de cadenas en scripts locales con validación SHA.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// ===============================================
// CONFIGURACIÓN DE SEGURIDAD Y BASE DE DATOS
// ===============================================

// Variable global para la conexión a la base de datos
global $link;

// -----------------------------------------------
// 1. Configuración de Acceso por IP (Lista Blanca)
// -----------------------------------------------

// Lista de direcciones IP autorizadas para modificar los datos.
define('AUTHORIZED_IPS', serialize([
    '127.0.0.1',  // IP local para pruebas
    '201.103.232.198' // 'TU.DIRECCION.IP.PUBLICA', // REEMPLAZAR con tu IP real.
]));

/**
 * Verifica si la IP del usuario actual está autorizada.
 * Si no lo está, termina la ejecución y muestra un mensaje de error.
 * @return void
 */
function check_authorization() {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $allowed_ips = unserialize(AUTHORIZED_IPS);

    if (!in_array($client_ip, $allowed_ips)) {
        header('HTTP/1.0 403 Forbidden');
		    if (!in_array($client_ip, $allowed_ips)) {
        header('HTTP/1.0 403 Forbidden');
        die('<div style="padding: 20px; border: 1px solid #dc3545; background-color: #f8d7da; color: #721c24;"><h4>Acceso Denegado</h4><p>Tu dirección IP (' . htmlspecialchars($client_ip) . ') no está autorizada para acceder.</p></div>');
    
        die('<div class="alert alert-danger" role="alert">
                <h4 class="alert-heading"><i class="fas fa-lock"></i> Acceso Denegado</h4>
                <p>Tu dirección IP (' . htmlspecialchars($client_ip) . ') no está autorizada para acceder a esta aplicación.</p>
             </div>');
    }
}
}

// -----------------------------------------------
// 2. Configuración de Conexión a la Base de Datos
// -----------------------------------------------

// **IMPORTANTE: REEMPLAZAR CON TUS CREDENCIALES REALES**
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'dfsdsfdsdsdfs');
define('DB_PASS', 'gdgdgdfg');
define('DB_NAME', 'dfgsdffgfgd'); */

define( 'DB_NAME', 'serverdeaplicaci_viveWP' );

/** Database username */
define( 'DB_USER', 'serverdeaplicaci_viveWP' );

/** Database password */
define( 'DB_PASS', '2jQ9d4T4YYde' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/**
 * Intenta establecer la conexión a la base de datos MySQL y la guarda en la variable global $link.
 * @global mysqli $link Objeto de conexión a la base de datos.
 * @return void
 */
function db_connect() {
    global $link;

    $link = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($link->connect_error) {
        die("Error de Conexión a la Base de Datos: " . $link->connect_error);
    }

    // Configurar la codificación UTF8 MB4
    if (!$link->set_charset("utf8mb4")) {
        error_log("Error al cargar utf8mb4: " . $link->error);
    }
}

// Conectar la base de datos inmediatamente al incluir el archivo
db_connect();

?>
